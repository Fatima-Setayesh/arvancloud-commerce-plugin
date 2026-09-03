<?php
/** Idempotent Cloud Server provisioning and recovery. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Arvan_Reseller_Provisioning {
	/** @var Arvan_Reseller_Database */
	private $database;

	/** @var Arvan_Reseller_API_Client */
	private $api;

	/** @var Arvan_Reseller_Wallet */
	private $wallet;

	public function __construct( Arvan_Reseller_Database $database, Arvan_Reseller_API_Client $api, ?Arvan_Reseller_Wallet $wallet = null ) {
		$this->database = $database;
		$this->api      = $api;
		$this->wallet   = null === $wallet ? new Arvan_Reseller_Wallet( $database ) : $wallet; }

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
			if ( 'failed' === (string) ( $existing['status'] ?? '' ) && '' !== (string) ( $existing['failure_code'] ?? '' ) ) {
				return new WP_Error(
					sanitize_key( (string) $existing['failure_code'] ),
					__( 'The previous order attempt failed.', 'arvan-reseller' ),
					array(
						'status'     => 409,
						'idempotent' => true,
					)
				);
			}
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
		$quote = $this->quote_snapshot( $hourly_price_minor );
		$order_details = array(
			'configuration' => $payload,
			'quote'         => $quote,
			'billing_model' => 'first_24_hours_prepaid_then_hourly',
			'payment'       => array(
				'required_at_order' => true,
				'status'            => 'pending',
				'debit_timing'      => 'at_order_for_first_24_hours',
			),
		);
		if ( is_wp_error( $quote ) ) {
			$this->fail_order( $order_id, 'provisioning', $quote );
			return $quote;
		}
		$total_minor = Arvan_Reseller_Money::to_minor( (string) $quote['total_charge'] );
		if ( is_wp_error( $total_minor ) || ! $this->database->begin_transaction() ) {
			$error = is_wp_error( $total_minor ) ? $total_minor : new WP_Error( 'arvan_reseller_transaction_start_failed', __( 'Unable to reserve the prepaid order amount.', 'arvan-reseller' ) );
			$this->fail_order_payment( $order_id, $reference, $customer_id, $error );
			return $error;
		}
		$debit = $this->wallet->debit_minor( $customer_id, $total_minor, 'order_prepaid', $reference, __( 'First 24 hours of Cloud Server service', 'arvan-reseller' ), $this->currency() );
		if ( is_wp_error( $debit ) ) {
			$this->database->rollback();
			$this->fail_order_payment( $order_id, $reference, $customer_id, $debit );
			return $debit;
		}
		$prepaid_until            = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql', true ) . ' UTC' ) + DAY_IN_SECONDS );
		$order_details['payment'] = array(
			'required_at_order' => true,
			'status'            => 'charged_at_order',
			'debit_timing'      => 'at_order_for_first_24_hours',
			'charged_amount'     => (string) $quote['total_charge'],
			'currency'           => (string) $quote['currency'],
			'transaction_id'     => (int) ( $debit['transaction_id'] ?? 0 ),
			'prepaid_until'      => $prepaid_until,
		);
		if ( false === $this->database->update(
			'orders',
			array(
				'details'    => wp_json_encode( $order_details ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $order_id )
		) || ! $this->database->commit() ) {
			$this->database->rollback();
			$error = new WP_Error( 'arvan_reseller_order_payment_persistence_failed', __( 'Unable to persist the prepaid order charge.', 'arvan-reseller' ) );
			$this->fail_order_payment( $order_id, $reference, $customer_id, $error );
			return $error;
		}
		$this->database->create_audit_log( 'order_prepaid_debited', 'order', (string) $order_id, array( 'transaction_id' => (int) ( $debit['transaction_id'] ?? 0 ), 'currency' => (string) $quote['currency'] ), $customer_id );

		$response = $this->api->create_server( $region, $payload, $idempotency_key );
		if ( is_wp_error( $response ) ) {
			if ( $this->is_ambiguous_create_error( $response ) ) {
				return $this->mark_ambiguous_create( $order_id, $reference, $customer_id, $order_details, $response );
			}
			$compensation = $this->compensate_order_debit( $order_id, $reference, $customer_id, $total_minor, $order_details );
			if ( is_wp_error( $compensation ) ) {
				$this->fail_order( $order_id, 'provisioning', $compensation );
				return $compensation;
			}
			$this->fail_order( $order_id, 'provisioning', $response );
			return $response; }

		$server      = isset( $response['body']['data'] ) && is_array( $response['body']['data'] ) ? $response['body']['data'] : array();
		$resource_id = isset( $server['id'] ) && is_scalar( $server['id'] ) ? sanitize_text_field( (string) $server['id'] ) : '';
		if ( '' === $resource_id ) {
			$error = new WP_Error( 'arvan_reseller_missing_resource_id', __( 'Cloud Server response did not include an ID.', 'arvan-reseller' ) );
			$compensation = $this->compensate_order_debit( $order_id, $reference, $customer_id, $total_minor, $order_details );
			if ( is_wp_error( $compensation ) ) {
				$this->fail_order( $order_id, 'provisioning', $compensation );
				return $compensation;
			}
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
				'last_billed_at'     => $prepaid_until,
			)
		);
		if ( false === $resource_record_id || ! $this->database->transition_order_status(
			$order_id,
			'provisioning',
			'provisioned',
			array(
				'resource_record_id' => $resource_record_id,
				'resource_id'        => $resource_id,
				'details'            => wp_json_encode( array_merge( $order_details, array( 'resource_id' => $resource_id ) ) ),
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

		$completed = $this->database->get_row_by( 'orders', array( 'id' => (int) $order_id ) );
		return null !== $completed ? $this->serialize_order( $completed ) : array(
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
			return $this->move_recovery_to_manual_review( $order, is_array( $details ) ? $details : array() ); }
		$response = $this->api->get_server( (string) $order['region'], $resource_id );
		if ( is_wp_error( $response ) ) {
			return $response; }
		$server = (array) ( $response['body']['data'] ?? array() );
		$config = is_array( $details['configuration'] ?? null ) ? $details['configuration'] : array();
		$price  = Arvan_Reseller_Money::to_minor( (string) ( $details['quote']['unit_price'] ?? '' ) );
		if ( is_wp_error( $price ) || $price <= 0 ) {
			$price = $this->resolve_hourly_price_minor( (string) $order['region'], (string) ( $config['flavorId'] ?? '' ) );
		}
		if ( is_wp_error( $price ) ) {
			return $price; }
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $details['quote']['currency'] ?? '' ) ) );
		$currency = 3 === strlen( $currency ) ? $currency : $this->currency();
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
				'currency'           => $currency,
				'remote_payload'     => wp_json_encode( Arvan_Reseller_Security::redact( $server ) ),
				'last_billed_at'     => sanitize_text_field( (string) ( $details['payment']['prepaid_until'] ?? '' ) ),
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

	/** Stop automatic recovery when there is no safe remote lookup key. */
	private function move_recovery_to_manual_review( array $order, array $details ) {
		$failure_code             = 'manual_review_missing_resource_id';
		$details['manual_review'] = array(
			'required' => true,
			'reason'   => $failure_code,
		);
		$updated = $this->database->update(
			'orders',
			array(
				'status'            => 'failed',
				'recovery_required' => 0,
				'failure_code'      => $failure_code,
				'details'           => wp_json_encode( $details ),
				'updated_at'        => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $order['id'] )
		);
		if ( false === $updated ) {
			return new WP_Error( 'arvan_reseller_manual_review_persistence_failed', __( 'The recovery record could not be moved to manual review.', 'arvan-reseller' ) );
		}
		$this->database->create_audit_log(
			'order_recovery_manual_review',
			'order',
			(string) $order['id'],
			array( 'failure_code' => $failure_code ),
			(int) $order['customer_id']
		);
		$this->database->record_notification_event(
			(int) $order['customer_id'],
			'provisioning_failed',
			(string) $order['order_reference'],
			array(
				'failure_code' => $failure_code,
				'manual_review' => true,
			)
		);
		return array(
			'order_id'     => (int) $order['id'],
			'status'       => 'manual_review',
			'failure_code' => $failure_code,
		);
	}

	/** @return array */
	public function serialize_order( array $order, $idempotent = false ) {
		$details = json_decode( (string) ( $order['details'] ?? '' ), true );
		$details = is_array( $details ) ? $details : array();
		return array(
			'id'              => (int) $order['id'],
			'customer_id'     => (int) $order['customer_id'],
			'order_reference' => (string) $order['order_reference'],
			'product_type'    => (string) $order['product_type'],
			'status'          => (string) $order['status'],
			'resource_id'     => (string) $order['resource_id'],
			'resource_record_id' => isset( $order['resource_record_id'] ) ? (int) $order['resource_record_id'] : 0,
			'region'          => (string) $order['region'],
			'configuration'   => $this->safe_configuration( $details['configuration'] ?? array() ),
			'quote'           => $this->safe_quote( $details['quote'] ?? array() ),
			'billing_model'   => 'first_24_hours_prepaid_then_hourly',
			'payment'         => $this->safe_payment( $details['payment'] ?? array() ),
			'recovery_required' => ! empty( $order['recovery_required'] ),
			'failure_code'    => sanitize_key( (string) ( $order['failure_code'] ?? '' ) ),
			'created_at'      => (string) $order['created_at'],
			'updated_at'      => (string) $order['updated_at'],
			'idempotent'      => (bool) $idempotent,
		); }

	private function safe_configuration( $configuration ) {
		if ( ! is_array( $configuration ) ) {
			return array();
		}
		$output = array();
		foreach ( array( 'availabilityZone', 'flavorId', 'imageId', 'name' ) as $key ) {
			if ( isset( $configuration[ $key ] ) && is_scalar( $configuration[ $key ] ) ) {
				$output[ $key ] = sanitize_text_field( (string) $configuration[ $key ] );
			}
		}
		if ( isset( $configuration['rootVolumeSizeGigaBytes'] ) ) {
			$output['rootVolumeSizeGigaBytes'] = absint( $configuration['rootVolumeSizeGigaBytes'] );
		}
		foreach ( array( 'enableBackup', 'enableFailOver', 'enableIpv4', 'enableIpv6' ) as $key ) {
			if ( array_key_exists( $key, $configuration ) ) {
				$output[ $key ] = (bool) $configuration[ $key ];
			}
		}
		return $output;
	}

	private function safe_quote( $quote ) {
		if ( ! is_array( $quote ) ) {
			return array();
		}
		$output = array();
		foreach ( array( 'usage_hours', 'unit_price', 'base_cost', 'reseller_share', 'total_charge' ) as $key ) {
			if ( isset( $quote[ $key ] ) && preg_match( '/^\d+\.\d{4}$/', (string) $quote[ $key ] ) ) {
				$output[ $key ] = (string) $quote[ $key ];
			}
		}
		if ( isset( $quote['currency'] ) && preg_match( '/^[A-Z]{3}$/', (string) $quote['currency'] ) ) {
			$output['currency'] = (string) $quote['currency'];
		}
		if ( isset( $quote['generated_at'] ) && is_scalar( $quote['generated_at'] ) ) {
			$output['generated_at'] = sanitize_text_field( (string) $quote['generated_at'] );
		}
		return $output;
	}

	private function safe_payment( $payment ) {
		$payment = is_array( $payment ) ? $payment : array();
		$status  = sanitize_key( (string) ( $payment['status'] ?? 'pending' ) );
		$status  = in_array( $status, array( 'pending', 'charged_at_order', 'compensated_after_failure', 'held_for_reconciliation' ), true ) ? $status : 'pending';
		$output  = array(
			'required_at_order' => true,
			'status'            => $status,
			'debit_timing'      => 'at_order_for_first_24_hours',
		);
		foreach ( array( 'charged_amount', 'currency', 'prepaid_until' ) as $key ) {
			if ( isset( $payment[ $key ] ) && is_scalar( $payment[ $key ] ) ) {
				$output[ $key ] = sanitize_text_field( (string) $payment[ $key ] );
			}
		}
		foreach ( array( 'transaction_id', 'compensation_transaction_id' ) as $key ) {
			if ( isset( $payment[ $key ] ) ) {
				$output[ $key ] = absint( $payment[ $key ] );
			}
		}
		return $output;
	}

	private function quote_snapshot( $hourly_price_minor ) {
		$hours_scaled   = 24 * Arvan_Reseller_Money::scale();
		$base_minor     = Arvan_Reseller_Money::multiply_scaled( $hours_scaled, (int) $hourly_price_minor );
		$settings       = get_option( 'arvan_reseller_settings', array() );
		$share_scaled   = Arvan_Reseller_Money::to_minor( (string) ( $settings['reseller_share_percent'] ?? '0' ) );
		$basis_points   = is_wp_error( $share_scaled ) || $share_scaled <= 0 ? 0 : min( 2000, intdiv( $share_scaled + 50, 100 ) );
		$share_minor    = is_wp_error( $base_minor ) ? $base_minor : Arvan_Reseller_Money::percentage( $base_minor, $basis_points );
		if ( is_wp_error( $base_minor ) || is_wp_error( $share_minor ) || $share_minor > PHP_INT_MAX - $base_minor ) {
			return new WP_Error( 'arvan_reseller_invalid_order_quote', __( 'The order quote exceeded the supported range.', 'arvan-reseller' ) );
		}
		return array(
			'usage_hours'    => '24.0000',
			'unit_price'     => Arvan_Reseller_Money::format( (int) $hourly_price_minor ),
			'base_cost'      => Arvan_Reseller_Money::format( $base_minor ),
			'reseller_share' => Arvan_Reseller_Money::format( $share_minor ),
			'total_charge'   => Arvan_Reseller_Money::format( $base_minor + $share_minor ),
			'currency'       => $this->currency(),
			'generated_at'   => current_time( 'mysql', true ),
		);
	}

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
		$order = $this->database->get_row_by( 'orders', array( 'id' => (int) $id ) );
		$this->database->create_audit_log( 'provisioning_failed', 'order', (string) $id, array( 'error_code' => $error->get_error_code() ), is_array( $order ) ? (int) $order['customer_id'] : 0 );
		if ( is_array( $order ) ) {
			$this->database->record_notification_event( (int) $order['customer_id'], 'provisioning_failed', (string) $order['order_reference'], array( 'failure_code' => sanitize_key( $error->get_error_code() ) ) );
		} }
	private function fail_order_payment( $id, $reference, $customer_id, WP_Error $error ) {
		$this->database->transition_order_status( $id, 'provisioning', 'failed', array( 'failure_code' => sanitize_key( $error->get_error_code() ) ) );
		$this->database->create_audit_log( 'order_payment_failed', 'order', (string) $id, array( 'error_code' => $error->get_error_code() ), $customer_id );
		$this->database->record_notification_event( $customer_id, 'payment_failed', $reference, array( 'failure_code' => sanitize_key( $error->get_error_code() ) ) );
	}
	private function compensate_order_debit( $id, $reference, $customer_id, $amount_minor, array &$details ) {
		$currency = (string) ( $details['quote']['currency'] ?? $this->currency() );
		$credit   = $this->wallet->credit_minor( $customer_id, $amount_minor, 'order_compensation', $reference, __( 'Cloud Server order provisioning compensation', 'arvan-reseller' ), $currency );
		if ( is_wp_error( $credit ) ) {
			$details['payment']['status'] = 'held_for_reconciliation';
			$this->database->update( 'orders', array( 'details' => wp_json_encode( $details ), 'recovery_required' => 1, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ) );
			$this->database->create_audit_log( 'order_compensation_failed', 'order', (string) $id, array( 'error_code' => $credit->get_error_code() ), $customer_id );
			return new WP_Error( 'arvan_reseller_order_compensation_failed', __( 'The order charge requires manual financial reconciliation.', 'arvan-reseller' ), array( 'recovery_required' => true ) );
		}
		$details['payment']['status']                      = 'compensated_after_failure';
		$details['payment']['compensation_transaction_id'] = (int) ( $credit['transaction_id'] ?? 0 );
		$this->database->update( 'orders', array( 'details' => wp_json_encode( $details ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ) );
		$this->database->create_audit_log( 'order_prepaid_compensated', 'order', (string) $id, array( 'transaction_id' => (int) ( $credit['transaction_id'] ?? 0 ) ), $customer_id );
		return $credit;
	}
	private function is_ambiguous_create_error( WP_Error $error ) {
		$data = $error->get_error_data();
		return 'arvan_reseller_invalid_api_response' === $error->get_error_code() || 'arvan_reseller_api_transport_error' === $error->get_error_code() || ( is_array( $data ) && ! empty( $data['retryable'] ) );
	}
	private function mark_ambiguous_create( $id, $reference, $customer_id, array $details, WP_Error $error ) {
		$details['payment']['status'] = 'held_for_reconciliation';
		$persisted = $this->database->transition_order_status(
			$id,
			'provisioning',
			'failed',
			array(
				'recovery_required' => 1,
				'failure_code'      => 'ambiguous_remote_create',
				'details'           => wp_json_encode( $details ),
				'updated_at'        => current_time( 'mysql', true ),
			)
		);
		if ( ! $persisted ) {
			return new WP_Error( 'arvan_reseller_order_recovery_persistence_failed', __( 'The ambiguous order state could not be persisted safely.', 'arvan-reseller' ), array( 'order_id' => (int) $id, 'recovery_required' => true ) );
		}
		$this->database->create_audit_log( 'provisioning_create_ambiguous', 'order', (string) $id, array( 'error_code' => $error->get_error_code() ), $customer_id );
		$this->database->record_notification_event( $customer_id, 'provisioning_failed', $reference, array( 'failure_code' => 'ambiguous_remote_create', 'recovery_required' => true ) );
		return new WP_Error( 'arvan_reseller_provisioning_recovery_required', __( 'The remote create result is ambiguous and requires manual reconciliation.', 'arvan-reseller' ), array( 'order_id' => (int) $id, 'recovery_required' => true ) );
	}
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
		if ( is_array( $order ) ) {
			$this->database->record_notification_event( (int) $order['customer_id'], 'provisioning_failed', (string) $order['order_reference'], array( 'failure_code' => sanitize_key( $code ), 'recovery_required' => true ) );
		}
		return new WP_Error(
			'arvan_reseller_provisioning_recovery_required',
			__( 'Server was created remotely but local recovery is required.', 'arvan-reseller' ),
			array(
				'order_id'          => (int) $id,
				'recovery_required' => true,
			)
		); }
}
