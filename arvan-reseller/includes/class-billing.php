<?php
/**
 * Billing and resource management service.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Billing {

	/**
	 * Database service.
	 *
	 * @var Arvan_Reseller_Database
	 */
	private $database;

	/**
	 * Wallet service.
	 *
	 * @var Arvan_Reseller_Wallet
	 */
	private $wallet;

	/**
	 * API client.
	 *
	 * @var Arvan_Reseller_API_Client
	 */
	private $api_client;

	/**
	 * Constructor.
	 *
	 * @param Arvan_Reseller_Database   $database Database service.
	 * @param Arvan_Reseller_Wallet     $wallet Wallet service.
	 * @param Arvan_Reseller_API_Client $api_client API client.
	 */
	public function __construct( Arvan_Reseller_Database $database, Arvan_Reseller_Wallet $wallet, Arvan_Reseller_API_Client $api_client ) {
		$this->database   = $database;
		$this->wallet     = $wallet;
		$this->api_client = $api_client;
	}

	/**
	 * Calculate total usage cost.
	 *
	 * @param float $usage_amount Usage amount.
	 * @param float $unit_price Unit price.
	 * @return float
	 */
	public function calculate_usage_cost( $usage_amount, $unit_price ) {
		return round( max( 0, (float) $usage_amount ) * max( 0, (float) $unit_price ), 4 );
	}

	/**
	 * Calculate reseller share amount.
	 *
	 * @param float $base_cost Cost before share.
	 * @param float $share_percent Share percent.
	 * @return float
	 */
	public function calculate_reseller_share_amount( $base_cost, $share_percent ) {
		$share_percent = $this->normalize_reseller_share( $share_percent );

		return round( max( 0, (float) $base_cost ) * ( $share_percent / 100 ), 4 );
	}

	/**
	 * Calculate total customer charge.
	 *
	 * @param float $usage_amount Usage amount.
	 * @param float $unit_price Unit price.
	 * @param float $share_percent Share percent.
	 * @return array
	 */
	public function calculate_customer_charge( $usage_amount, $unit_price, $share_percent = null ) {
		$base_cost       = $this->calculate_usage_cost( $usage_amount, $unit_price );
		$share_percent   = null === $share_percent ? $this->get_reseller_share_percent() : $this->normalize_reseller_share( $share_percent );
		$reseller_share  = $this->calculate_reseller_share_amount( $base_cost, $share_percent );
		$total_charge    = round( $base_cost + $reseller_share, 4 );

		return array(
			'usage_amount'    => round( (float) $usage_amount, 4 ),
			'unit_price'      => round( (float) $unit_price, 4 ),
			'base_cost'       => $base_cost,
			'reseller_share'  => $reseller_share,
			'share_percent'   => $share_percent,
			'total_charge'    => $total_charge,
		);
	}

	/**
	 * Track a resource created for a customer.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $product_type Product type.
	 * @param string $resource_id Remote resource ID.
	 * @param string $status Resource status.
	 * @param array  $payload Raw API payload.
	 * @return int|WP_Error
	 */
	public function track_resource( $customer_id, $product_type, $resource_id, $status = 'pending', array $payload = array() ) {
		$customer_id = absint( $customer_id );

		if ( $customer_id <= 0 || '' === (string) $resource_id ) {
			return new WP_Error( 'arvan_reseller_invalid_resource', __( 'Invalid resource tracking data.', 'arvan-reseller' ) );
		}

		$resource_id = $this->database->save_resource(
			array(
				'customer_id'    => $customer_id,
				'resource_id'    => (string) $resource_id,
				'product_type'   => sanitize_key( $product_type ),
				'status'         => sanitize_key( $status ),
				'remote_payload' => ! empty( $payload ) ? wp_json_encode( $payload ) : '',
				'updated_at'     => current_time( 'mysql', true ),
			)
		);

		if ( false === $resource_id ) {
			return new WP_Error( 'arvan_reseller_resource_tracking_failed', __( 'Failed to save resource mapping.', 'arvan-reseller' ) );
		}

		return (int) $resource_id;
	}

	/**
	 * Validate resource ownership.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $resource_id Resource ID.
	 * @return bool
	 */
	public function customer_owns_resource( $customer_id, $resource_id ) {
		$resource = $this->database->get_resource_by_arvan_id( $resource_id );

		return null !== $resource && absint( $resource['customer_id'] ) === absint( $customer_id );
	}

	/**
	 * Create a resource remotely and track it locally.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $product_type Product type.
	 * @param array  $payload API payload.
	 * @param string $endpoint Optional endpoint override.
	 * @return array|WP_Error
	 */
	public function provision_resource( $customer_id, $product_type, array $payload, $endpoint = '' ) {
		if ( ! is_scalar( $customer_id ) || ! is_numeric( (string) $customer_id ) || (int) $customer_id <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_customer', __( 'Invalid customer ID for provisioning.', 'arvan-reseller' ) );
		}

		if ( ! $this->database instanceof Arvan_Reseller_Database || ! $this->api_client instanceof Arvan_Reseller_API_Client ) {
			return new WP_Error( 'arvan_reseller_missing_dependency', __( 'Provisioning service dependencies are not available.', 'arvan-reseller' ) );
		}

		try {
			$customer_id  = absint( $customer_id );
			$product_type = sanitize_key( $product_type );

			if ( '' === $product_type ) {
				return new WP_Error( 'arvan_reseller_invalid_product_type', __( 'Invalid product type for provisioning.', 'arvan-reseller' ) );
			}

			$order_reference = $this->build_provisioning_reference( $customer_id, $product_type, $payload );
			$existing_order  = $this->get_existing_provisioning_order( $customer_id, $order_reference );

			if ( null !== $existing_order ) {
				return new WP_Error(
					'arvan_reseller_duplicate_provisioning',
					__( 'Duplicate provisioning request blocked.', 'arvan-reseller' ),
					array(
						'order_id'        => isset( $existing_order['id'] ) ? (int) $existing_order['id'] : 0,
						'order_reference' => $order_reference,
						'resource_id'     => isset( $existing_order['resource_id'] ) ? (string) $existing_order['resource_id'] : '',
					)
				);
			}

			$response = $this->api_client->create_resource( $payload, $endpoint );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( ! is_array( $response ) || ! isset( $response['body'] ) || ! is_array( $response['body'] ) ) {
				return new WP_Error( 'arvan_reseller_invalid_api_response', __( 'Provisioning API response is invalid.', 'arvan-reseller' ) );
			}

			$resource_id = $this->extract_resource_id_from_response( $response['body'] );

			if ( '' === $resource_id ) {
				return new WP_Error( 'arvan_reseller_missing_resource_id', __( 'Remote resource ID was not returned by the API.', 'arvan-reseller' ) );
			}

			$tracked = $this->track_resource( $customer_id, $product_type, $resource_id, 'provisioned', $response['body'] );

			if ( is_wp_error( $tracked ) ) {
				return $tracked;
			}

			$order_id = $this->database->create_order(
				array(
					'customer_id'     => $customer_id,
					'product_type'    => $product_type,
					'status'          => 'provisioned',
					'resource_id'     => $resource_id,
					'order_reference' => $order_reference,
					'details'         => wp_json_encode( $response['body'] ),
				)
			);

			if ( false === $order_id ) {
				$this->update_resource_status( $resource_id, 'provisioned', $response['body'] );

				return new WP_Error(
					'arvan_reseller_order_creation_failed',
					__( 'Resource was tracked but order creation failed.', 'arvan-reseller' ),
					array(
						'customer_id'     => $customer_id,
						'product_type'    => $product_type,
						'resource_id'     => $resource_id,
						'order_reference' => $order_reference,
						'db_error'        => $this->database->get_last_error(),
					)
				);
			}

			return array(
				'resource_id' => $resource_id,
				'order_id'    => (int) $order_id,
				'response'    => $response,
			);
		} catch ( Throwable $exception ) {
			return new WP_Error(
				'arvan_reseller_provisioning_failed',
				__( 'Provisioning failed unexpectedly.', 'arvan-reseller' ),
				array(
					'message' => $exception->getMessage(),
				)
			);
		}
	}

	/**
	 * Suspend a tracked resource and persist its local status.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $resource_id Resource ID.
	 * @param string $endpoint Optional endpoint override.
	 * @return array|WP_Error
	 */
	public function suspend_resource( $customer_id, $resource_id, $endpoint = '' ) {
		if ( ! $this->customer_owns_resource( $customer_id, $resource_id ) ) {
			return new WP_Error( 'arvan_reseller_invalid_ownership', __( 'Customer does not own this resource.', 'arvan-reseller' ) );
		}

		$resource = $this->database->get_resource_by_arvan_id( $resource_id );

		if ( null !== $resource && 'suspended' === (string) $resource['status'] ) {
			return array(
				'skipped'     => true,
				'resource_id' => (string) $resource_id,
				'status'      => 'suspended',
			);
		}

		$response = $this->api_client->suspend_resource( $resource_id, $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->update_resource_status( $resource_id, 'suspended', $response['body'] );

		return $response;
	}

	/**
	 * Terminate a tracked resource and persist its local status.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $resource_id Resource ID.
	 * @param string $endpoint Optional endpoint override.
	 * @return array|WP_Error
	 */
	public function terminate_resource( $customer_id, $resource_id, $endpoint = '' ) {
		if ( ! $this->customer_owns_resource( $customer_id, $resource_id ) ) {
			return new WP_Error( 'arvan_reseller_invalid_ownership', __( 'Customer does not own this resource.', 'arvan-reseller' ) );
		}

		$response = $this->api_client->terminate_resource( $resource_id, $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->update_resource_status( $resource_id, 'terminated', $response['body'] );

		return $response;
	}

	/**
	 * Process billing for a single resource window.
	 *
	 * @param array $resource Resource record.
	 * @param array $usage_data Usage data.
	 * @return array|WP_Error
	 */
	public function process_resource_usage(array $resource, array $usage_data ) {
		$customer_id = absint( $resource['customer_id'] );
		$resource_id = (string) $resource['resource_id'];
		$window      = $this->normalize_usage_window( $usage_data, $resource );

		if ( $this->database->usage_log_exists( $window['billing_reference'] ) ) {
			return new WP_Error( 'arvan_reseller_duplicate_billing', __( 'Duplicate billing attempt blocked.', 'arvan-reseller' ) );
		}

		$charge = $this->calculate_customer_charge( $window['usage_amount'], $window['unit_price'] );
		$debit  = $this->wallet->decrease_balance(
			$customer_id,
			$charge['total_charge'],
			'usage_billing',
			$window['billing_reference'],
			sprintf(
				/* translators: %s: resource ID. */
				__( 'Usage billing for resource %s.', 'arvan-reseller' ),
				$resource_id
			)
		);

		if ( is_wp_error( $debit ) ) {
			return $debit;
		}

		$usage_log_id = $this->database->create_usage_log(
			array(
				'customer_id'       => $customer_id,
				'resource_id'       => $resource_id,
				'usage_amount'      => number_format( $window['usage_amount'], 4, '.', '' ),
				'unit'              => $window['unit'],
				'usage_start'       => $window['usage_start'],
				'usage_end'         => $window['usage_end'],
				'cost'              => number_format( $charge['total_charge'], 4, '.', '' ),
				'reseller_share'    => number_format( $charge['reseller_share'], 4, '.', '' ),
				'billing_reference' => $window['billing_reference'],
				'api_payload'       => wp_json_encode( $usage_data ),
			)
		);

		if ( false === $usage_log_id ) {
			return new WP_Error( 'arvan_reseller_usage_log_failed', __( 'Failed to save usage log.', 'arvan-reseller' ) );
		}

		$this->database->update(
			'resources',
			array(
				'last_billed_at' => $window['usage_end'],
				'last_synced_at' => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array(
				'id' => (int) $resource['id'],
			),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'usage_log_id'    => $usage_log_id,
			'transaction_id'  => $debit['transaction_id'],
			'total_charge'    => $charge['total_charge'],
			'reseller_share'  => $charge['reseller_share'],
			'billing_reference' => $window['billing_reference'],
		);
	}

	/**
	 * Get billable resources.
	 *
	 * @return array
	 */
	public function get_billable_resources() {
		return $this->database->get_billable_resources();
	}

	/**
	 * Build the query window for usage fetching.
	 *
	 * @param array $resource Resource record.
	 * @return array
	 */
	public function build_usage_query_window( array $resource ) {
		$end_time   = gmdate( 'Y-m-d H:00:00' );
		$start_time = ! empty( $resource['last_billed_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $resource['last_billed_at'] ) ) : gmdate( 'Y-m-d H:00:00', strtotime( '-1 hour' ) );

		return array(
			'start' => $start_time,
			'end'   => $end_time,
		);
	}

	/**
	 * Resolve the configured reseller share.
	 *
	 * @return float
	 */
	public function get_reseller_share_percent() {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$share    = isset( $settings['reseller_share_percent'] ) ? (float) $settings['reseller_share_percent'] : 0;

		return $this->normalize_reseller_share( $share );
	}

	/**
	 * Normalize and cap the reseller share value.
	 *
	 * @param float $share_percent Share percent.
	 * @return float
	 */
	private function normalize_reseller_share( $share_percent ) {
		return round( max( 0, min( 20, (float) $share_percent ) ), 2 );
	}

	/**
	 * Normalize usage data into a consistent billing shape.
	 *
	 * @param array $usage_data Usage payload.
	 * @param array $resource Resource record.
	 * @return array
	 */
	private function normalize_usage_window( array $usage_data, array $resource ) {
		$resource_id   = (string) $resource['resource_id'];
		$usage_start   = ! empty( $usage_data['start'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $usage_data['start'] ) ) : gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );
		$usage_end     = ! empty( $usage_data['end'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $usage_data['end'] ) ) : current_time( 'mysql', true );
		$usage_amount  = isset( $usage_data['usage_amount'] ) ? (float) $usage_data['usage_amount'] : 0.0;
		$unit_price    = isset( $usage_data['unit_price'] ) ? (float) $usage_data['unit_price'] : 0.0;
		$unit          = isset( $usage_data['unit'] ) ? sanitize_key( $usage_data['unit'] ) : '';
		$reference     = $resource_id . ':' . gmdate( 'YmdHis', strtotime( $usage_start ) ) . ':' . gmdate( 'YmdHis', strtotime( $usage_end ) );

		return array(
			'usage_start'       => $usage_start,
			'usage_end'         => $usage_end,
			'usage_amount'      => round( $usage_amount, 4 ),
			'unit_price'        => round( $unit_price, 4 ),
			'unit'              => $unit,
			'billing_reference' => $reference,
		);
	}

	/**
	 * Extract a resource ID from API payloads.
	 *
	 * @param array $body Response body.
	 * @return string
	 */
	private function extract_resource_id_from_response( array $body ) {
		$candidates = array(
			isset( $body['id'] ) ? $body['id'] : '',
			isset( $body['data']['id'] ) ? $body['data']['id'] : '',
			isset( $body['resource_id'] ) ? $body['resource_id'] : '',
		);

		foreach ( $candidates as $candidate ) {
			if ( is_scalar( $candidate ) ) {
				$resource_id = trim( (string) $candidate );

				if ( '' !== $resource_id ) {
					return $resource_id;
				}
			}
		}

		return '';
	}

	/**
	 * Build a stable provisioning reference for duplicate protection.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $product_type Product type.
	 * @param array  $payload Provisioning payload.
	 * @return string
	 */
	private function build_provisioning_reference( $customer_id, $product_type, array $payload ) {
		$normalized_payload = wp_json_encode( $payload );

		return 'provision:' . $customer_id . ':' . $product_type . ':' . md5( (string) $normalized_payload );
	}

	/**
	 * Find an existing provisioning order by reference.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $order_reference Provisioning reference.
	 * @return array|null
	 */
	private function get_existing_provisioning_order( $customer_id, $order_reference ) {
		$orders = $this->database->get_results_by(
			'orders',
			array(
				'customer_id'     => absint( $customer_id ),
				'order_reference' => (string) $order_reference,
			),
			1
		);

		return ! empty( $orders[0] ) && is_array( $orders[0] ) ? $orders[0] : null;
	}

	/**
	 * Persist a resource status change.
	 *
	 * @param string $resource_id Resource ID.
	 * @param string $status New status.
	 * @param array  $payload Optional payload.
	 * @return void
	 */
	private function update_resource_status( $resource_id, $status, array $payload = array() ) {
		$resource = $this->database->get_resource_by_arvan_id( $resource_id );

		if ( null === $resource ) {
			return;
		}

		$this->database->update(
			'resources',
			array(
				'status'         => sanitize_key( $status ),
				'remote_payload' => ! empty( $payload ) ? wp_json_encode( $payload ) : (string) $resource['remote_payload'],
				'updated_at'     => current_time( 'mysql', true ),
				'last_synced_at' => current_time( 'mysql', true ),
			),
			array(
				'id' => (int) $resource['id'],
			),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}
}
