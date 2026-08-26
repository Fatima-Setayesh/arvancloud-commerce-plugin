<?php
/** Idempotent Cloud Server provisioning and recovery. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Arvan_Reseller_Provisioning {
	private $database;
	private $api;

	public function __construct( Arvan_Reseller_Database $database, Arvan_Reseller_API_Client $api ) {
		$this->database = $database;
		$this->api      = $api; }

	/** @return array|WP_Error */
	public function create_server_order( $customer_id, array $configuration, $client_key ) {
		$customer_id = absint( $customer_id );
		$client_key  = sanitize_text_field( (string) $client_key );
		$region      = sanitize_key( (string) ( $configuration['region'] ?? '' ) );
		$payload     = $this->normalize_configuration( $configuration );

		if ( $customer_id <= 0 || '' === $client_key || strlen( $client_key ) > 191 || is_wp_error( $payload ) || '' === $region ) {
			return is_wp_error( $payload ) ? $payload : new WP_Error( 'arvan_reseller_invalid_order', __( 'Cloud Server order is invalid.', 'arvan-reseller' ) );
		}

		$idempotency_key = hash( 'sha256', 'cloud-server-order-v1|' . $customer_id . '|' . $client_key );
		$existing        = $this->database->get_order_by_idempotency_key( $idempotency_key );
		if ( null !== $existing ) {
			return $this->serialize_order( $existing, true ); }

		$reference = 'ord_' . str_replace( '-', '', wp_generate_uuid4() );
		$order_id  = $this->database->create_order(
			array(
				'customer_id'     => $customer_id,
				'product_type'    => 'cloud_server',
				'status'          => 'pending',
				'region'          => $region,
				'order_reference' => $reference,
				'idempotency_key' => $idempotency_key,
				'details'         => wp_json_encode( array( 'configuration' => $payload ) ),
			)
		);
		if ( false === $order_id ) {
			$existing = $this->database->get_order_by_idempotency_key( $idempotency_key );
			return null !== $existing ? $this->serialize_order( $existing, true ) : new WP_Error( 'arvan_reseller_order_create_failed', __( 'Unable to create the local order.', 'arvan-reseller' ) );
		}

		$this->database->create_audit_log(
			'order_created',
			'order',
			(string) $order_id,
			array(
				'mode'   => $this->api->get_mode(),
				'region' => $region,
			),
			$customer_id
		);
		if ( ! $this->database->transition_order_status( $order_id, 'pending', 'provisioning' ) ) {
			return new WP_Error( 'arvan_reseller_order_transition_failed', __( 'Unable to begin provisioning.', 'arvan-reseller' ) ); }

		$hourly_price_minor = $this->resolve_hourly_price_minor( $region, $payload['flavorId'] );
		if ( is_wp_error( $hourly_price_minor ) ) {
			$this->fail_order( $order_id, 'provisioning', $hourly_price_minor );
			return $hourly_price_minor; }

		$response = $this->api->create_server( $region, $payload, $idempotency_key );
		if ( is_wp_error( $response ) ) {
			$this->fail_order( $order_id, 'provisioning', $response );
			return $response; }

		$server      = isset( $response['body']['data'] ) && is_array( $response['body']['data'] ) ? $response['body']['data'] : array();
		$resource_id = isset( $server['id'] ) && is_scalar( $server['id'] ) ? sanitize_text_field( (string) $server['id'] ) : '';
		if ( '' === $resource_id ) {
			$error = new WP_Error( 'arvan_reseller_missing_resource_id', __( 'Cloud Server response did not include an ID.', 'arvan-reseller' ) );
			$this->fail_order( $order_id, 'provisioning', $error );
			return $error; }

		if ( ! $this->database->begin_transaction() ) {
			return $this->mark_recovery_required( $order_id, $resource_id, $server, 'transaction_start_failed' ); }
		$locked = $this->database->lock_order( $order_id );
		if ( null === $locked ) {
			$this->database->rollback();
			return $this->mark_recovery_required( $order_id, $resource_id, $server, 'order_lock_failed' ); }

		$resource_record_id = $this->database->save_resource(
			array(
				'customer_id'        => $customer_id,
				'order_id'           => $order_id,
				'resource_id'        => $resource_id,
				'product_type'       => 'cloud_server',
				'region'             => $region,
				'status'             => 'provisioned',
				'remote_status'      => sanitize_key( (string) ( $server['status'] ?? 'unknown' ) ),
				'hourly_price_minor' => $hourly_price_minor,
				'currency'           => $this->currency(),
				'remote_payload'     => wp_json_encode( Arvan_Reseller_Security::redact( $server ) ),
				'last_synced_at'     => current_time( 'mysql', true ),
			)
		);
		if ( false === $resource_record_id || ! $this->database->transition_order_status(
			$order_id,
			'provisioning',
			'provisioned',
			array(
				'resource_record_id' => $resource_record_id,
				'resource_id'        => $resource_id,
				'details'            => wp_json_encode(
					array(
						'configuration' => $payload,
						'resource_id'   => $resource_id,
					)
				),
				'recovery_required'  => 0,
			)
		) ) {
			$this->database->rollback();
			return $this->mark_recovery_required( $order_id, $resource_id, $server, 'local_persistence_failed' );
		}

		$this->database->create_audit_log(
			'server_provisioned',
			'resource',
			(string) $resource_record_id,
			array(
				'order_id'    => $order_id,
				'resource_id' => $resource_id,
			),
			$customer_id
		);
		if ( ! $this->database->commit() ) {
			return $this->mark_recovery_required( $order_id, $resource_id, $server, 'commit_failed' ); }

		return array(
			'id'                 => (int) $order_id,
			'order_reference'    => $reference,
			'status'             => 'provisioned',
			'resource_id'        => $resource_id,
			'resource_record_id' => (int) $resource_record_id,
			'idempotent'         => false,
		);
	}

	/** @return array */
	public function reconcile_pending_resources( $limit = 50 ) {
		$results = array();
		foreach ( $this->database->get_orders_requiring_recovery( $limit ) as $order ) {
			$results[] = $this->reconcile_order( $order ); }
		return $results;
	}

	/** @return array|WP_Error */
	public function reconcile_order( array $order ) {
		$details     = json_decode( (string) $order['details'], true );
		$resource_id = is_array( $details ) ? (string) ( $details['resource_id'] ?? '' ) : '';
		if ( '' === $resource_id ) {
			return new WP_Error( 'arvan_reseller_recovery_missing_resource', __( 'Recovery record has no remote resource ID.', 'arvan-reseller' ) ); }
		$response = $this->api->get_server( (string) $order['region'], $resource_id );
		if ( is_wp_error( $response ) ) {
			return $response; }
		$server = (array) ( $response['body']['data'] ?? array() );
		$config = is_array( $details['configuration'] ?? null ) ? $details['configuration'] : array();
		$price  = $this->resolve_hourly_price_minor( (string) $order['region'], (string) ( $config['flavorId'] ?? '' ) );
		if ( is_wp_error( $price ) ) {
			return $price; }
		$resource_record_id = $this->database->save_resource(
			array(
				'customer_id'        => (int) $order['customer_id'],
				'order_id'           => (int) $order['id'],
				'resource_id'        => $resource_id,
				'product_type'       => 'cloud_server',
				'region'             => (string) $order['region'],
				'status'             => 'provisioned',
				'remote_status'      => sanitize_key( (string) ( $server['status'] ?? 'unknown' ) ),
				'hourly_price_minor' => $price,
				'currency'           => $this->currency(),
				'remote_payload'     => wp_json_encode( Arvan_Reseller_Security::redact( $server ) ),
			)
		);
		if ( false === $resource_record_id ) {
			return new WP_Error( 'arvan_reseller_recovery_persistence_failed', __( 'Resource recovery could not be persisted.', 'arvan-reseller' ) ); }
		$this->database->update(
			'orders',
			array(
				'status'             => 'provisioned',
				'resource_record_id' => $resource_record_id,
				'resource_id'        => $resource_id,
				'recovery_required'  => 0,
				'failure_code'       => '',
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $order['id'] )
		);
		$this->database->create_audit_log( 'resource_reconciled', 'resource', (string) $resource_record_id, array( 'order_id' => (int) $order['id'] ), (int) $order['customer_id'] );
		return array(
			'order_id'           => (int) $order['id'],
			'resource_record_id' => (int) $resource_record_id,
			'status'             => 'reconciled',
		);
	}

	/** @return array */
	public function serialize_order( array $order, $idempotent = false ) {
		return array(
			'id'              => (int) $order['id'],
			'customer_id'     => (int) $order['customer_id'],
			'order_reference' => (string) $order['order_reference'],
			'product_type'    => (string) $order['product_type'],
			'status'          => (string) $order['status'],
			'resource_id'     => (string) $order['resource_id'],
			'region'          => (string) $order['region'],
			'created_at'      => (string) $order['created_at'],
			'updated_at'      => (string) $order['updated_at'],
			'idempotent'      => (bool) $idempotent,
		); }

	private function normalize_configuration( array $c ) {
		$required = array( 'availabilityZone', 'flavorId', 'imageId', 'name', 'rootVolumeSizeGigaBytes' );
		$out      = array();
		foreach ( $required as $key ) {
			if ( ! isset( $c[ $key ] ) || '' === (string) $c[ $key ] ) {
				return new WP_Error( 'arvan_reseller_invalid_server_configuration', __( 'Cloud Server configuration is incomplete.', 'arvan-reseller' ) );
			} $out[ $key ] = 'rootVolumeSizeGigaBytes' === $key ? absint( $c[ $key ] ) : sanitize_text_field( (string) $c[ $key ] );
		} if ( $out['rootVolumeSizeGigaBytes'] < 1 || $out['rootVolumeSizeGigaBytes'] > 10000 || strlen( $out['name'] ) > 100 ) {
			return new WP_Error( 'arvan_reseller_invalid_server_configuration', __( 'Cloud Server configuration is invalid.', 'arvan-reseller' ) );
		} foreach ( array( 'enableBackup', 'enableFailOver', 'enableIpv4', 'enableIpv6' ) as $b ) {
			if ( isset( $c[ $b ] ) ) {
				$out[ $b ] = (bool) $c[ $b ];
			}
		} return $out; }

	private function resolve_hourly_price_minor( $region, $flavor_id ) {
		$response = $this->api->list_flavors( $region, array( 'perPage' => 100 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		} $rows = (array) ( $response['body']['data'] ?? array() );
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && (string) ( $row['id'] ?? '' ) === $flavor_id ) {
				$minor = Arvan_Reseller_Money::to_minor( (string) ( $row['pricePerHour'] ?? '' ) );
				return is_wp_error( $minor ) || $minor <= 0 ? new WP_Error( 'arvan_reseller_invalid_flavor_price', __( 'Flavor has no valid hourly price.', 'arvan-reseller' ) ) : $minor;
			}
		} return new WP_Error( 'arvan_reseller_flavor_not_found', __( 'Selected Cloud Server flavor was not found.', 'arvan-reseller' ) ); }

	private function currency() {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $settings['currency'] ?? 'IRR' ) ) );
		return 3 === strlen( $currency ) ? $currency : 'IRR'; }

	private function fail_order( $id, $from, WP_Error $error ) {
		$this->database->transition_order_status( $id, $from, 'failed', array( 'failure_code' => sanitize_key( $error->get_error_code() ) ) );
		$this->database->create_audit_log( 'provisioning_failed', 'order', (string) $id, array( 'error_code' => $error->get_error_code() ) ); }
	private function mark_recovery_required( $id, $resource_id, array $server, $code ) {
		$order                  = $this->database->get_row_by( 'orders', array( 'id' => (int) $id ) );
		$details                = is_array( $order ) ? json_decode( (string) $order['details'], true ) : array();
		$details                = is_array( $details ) ? $details : array();
		$details['resource_id'] = $resource_id;
		$this->database->update(
			'orders',
			array(
				'status'            => 'failed',
				'resource_id'       => $resource_id,
				'recovery_required' => 1,
				'failure_code'      => sanitize_key( $code ),
				'details'           => wp_json_encode( $details ),
				'updated_at'        => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		$this->database->create_audit_log(
			'provisioning_recovery_required',
			'order',
			(string) $id,
			array(
				'resource_id' => $resource_id,
				'reason'      => $code,
			)
		);
		return new WP_Error(
			'arvan_reseller_provisioning_recovery_required',
			__( 'Server was created remotely but local recovery is required.', 'arvan-reseller' ),
			array(
				'order_id'          => (int) $id,
				'recovery_required' => true,
			)
		); }
}
