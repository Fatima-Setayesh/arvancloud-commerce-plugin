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
	 * @param int|string $usage_amount Usage quantity (four-decimal precision).
	 * @param int|string $unit_price Unit price.
	 * @return string|WP_Error Fixed-decimal money string or error.
	 */
	public function calculate_usage_cost( $usage_amount, $unit_price ) {
		$quantity_scaled  = Arvan_Reseller_Money::to_minor( $usage_amount );
		$unit_price_minor = Arvan_Reseller_Money::to_minor( $unit_price );

		if ( is_wp_error( $quantity_scaled ) || is_wp_error( $unit_price_minor ) || $quantity_scaled < 0 || $unit_price_minor < 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_usage_amount', __( 'Usage and unit price must be non-negative decimal values.', 'arvan-reseller' ) );
		}

		$cost_minor = Arvan_Reseller_Money::multiply_scaled( $quantity_scaled, $unit_price_minor );

		return is_wp_error( $cost_minor ) ? $cost_minor : Arvan_Reseller_Money::format( $cost_minor );
	}

	/**
	 * Calculate reseller share amount.
	 *
	 * @param int|string $base_cost Cost before share.
	 * @param int|string $share_percent Share percent.
	 * @return string|WP_Error
	 */
	public function calculate_reseller_share_amount( $base_cost, $share_percent ) {
		$base_minor   = Arvan_Reseller_Money::to_minor( $base_cost );
		$basis_points = $this->normalize_reseller_share_basis_points( $share_percent );

		if ( is_wp_error( $base_minor ) || $base_minor < 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_base_cost', __( 'Base cost must be a non-negative decimal value.', 'arvan-reseller' ) );
		}

		$share_minor = Arvan_Reseller_Money::percentage( $base_minor, $basis_points );

		return is_wp_error( $share_minor ) ? $share_minor : Arvan_Reseller_Money::format( $share_minor );
	}

	/**
	 * Calculate total customer charge.
	 *
	 * @param int|string      $usage_amount Usage amount.
	 * @param int|string      $unit_price Unit price.
	 * @param int|string|null $share_percent Share percent.
	 * @return array|WP_Error
	 */
	public function calculate_customer_charge( $usage_amount, $unit_price, $share_percent = null ) {
		$quantity_scaled  = Arvan_Reseller_Money::to_minor( $usage_amount );
		$unit_price_minor = Arvan_Reseller_Money::to_minor( $unit_price );
		$basis_points     = null === $share_percent ? $this->get_reseller_share_basis_points() : $this->normalize_reseller_share_basis_points( $share_percent );

		if ( is_wp_error( $quantity_scaled ) || is_wp_error( $unit_price_minor ) || $quantity_scaled < 0 || $unit_price_minor < 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_billing_amount', __( 'Billing values must be non-negative plain decimals.', 'arvan-reseller' ) );
		}

		$base_cost_minor = Arvan_Reseller_Money::multiply_scaled( $quantity_scaled, $unit_price_minor );

		if ( is_wp_error( $base_cost_minor ) ) {
			return $base_cost_minor;
		}

		$reseller_share_minor = Arvan_Reseller_Money::percentage( $base_cost_minor, $basis_points );

		if ( is_wp_error( $reseller_share_minor ) || $reseller_share_minor > PHP_INT_MAX - $base_cost_minor ) {
			return is_wp_error( $reseller_share_minor ) ? $reseller_share_minor : new WP_Error( 'arvan_reseller_money_overflow', __( 'Billing amount exceeded the supported range.', 'arvan-reseller' ) );
		}

		$total_charge_minor = $base_cost_minor + $reseller_share_minor;

		return array(
			'usage_amount'          => Arvan_Reseller_Money::format( $quantity_scaled ),
			'unit_price'            => Arvan_Reseller_Money::format( $unit_price_minor ),
			'base_cost'             => Arvan_Reseller_Money::format( $base_cost_minor ),
			'reseller_share'        => Arvan_Reseller_Money::format( $reseller_share_minor ),
			'share_percent'         => $this->format_basis_points( $basis_points ),
			'total_charge'          => Arvan_Reseller_Money::format( $total_charge_minor ),
			'usage_quantity_scaled' => $quantity_scaled,
			'unit_price_minor'      => $unit_price_minor,
			'base_cost_minor'       => $base_cost_minor,
			'reseller_share_minor'  => $reseller_share_minor,
			'total_charge_minor'    => $total_charge_minor,
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
		if ( '' !== (string) $endpoint ) {
			return new WP_Error( 'arvan_reseller_endpoint_override_blocked', __( 'Endpoint overrides are not allowed.', 'arvan-reseller' ) );
		}

		if ( 'cloud_server' !== sanitize_key( $product_type ) ) {
			return new WP_Error( 'arvan_reseller_unsupported_product', __( 'Only the documented Cloud Server product is supported.', 'arvan-reseller' ) );
		}

		$settings          = get_option( 'arvan_reseller_settings', array() );
		$payload['region'] = isset( $payload['region'] ) ? $payload['region'] : (string) ( $settings['region'] ?? '' );
		$idempotency_key   = isset( $payload['idempotency_key'] ) ? (string) $payload['idempotency_key'] : hash( 'sha256', wp_json_encode( $payload ) );

		return ( new Arvan_Reseller_Provisioning( $this->database, $this->api_client, $this->wallet ) )->create_server_order( $customer_id, $payload, $idempotency_key );
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

		if ( '' !== (string) $endpoint ) {
			return new WP_Error( 'arvan_reseller_endpoint_override_blocked', __( 'Endpoint overrides are not allowed.', 'arvan-reseller' ) );
		}
		$settings = get_option( 'arvan_reseller_settings', array() );
		$response = $this->api_client->power_off_server( (string) $resource['region'], $resource_id, (string) ( $settings['availability_zone'] ?? '' ) );

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

		$resource = $this->database->get_resource_by_arvan_id( $resource_id );
		if ( 'terminated' === (string) $resource['status'] ) {
			return array(
				'skipped'     => true,
				'resource_id' => (string) $resource_id,
				'status'      => 'terminated',
			);
		}
		if ( '' !== (string) $endpoint ) {
			return new WP_Error( 'arvan_reseller_endpoint_override_blocked', __( 'Endpoint overrides are not allowed.', 'arvan-reseller' ) );
		}
		$settings = get_option( 'arvan_reseller_settings', array() );
		$response = $this->api_client->terminate_server( (string) $resource['region'], $resource_id, (string) ( $settings['availability_zone'] ?? '' ) );

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
	public function process_resource_usage( array $resource, array $usage_data ) {
		$customer_id = absint( $resource['customer_id'] );
		$resource_id = (string) $resource['resource_id'];
		$window      = $this->normalize_usage_window( $usage_data, $resource );

		if ( is_wp_error( $window ) ) {
			return $window;
		}

		if ( $this->database->usage_log_exists( $window['billing_reference'] ) ) {
			return new WP_Error( 'arvan_reseller_duplicate_billing', __( 'Duplicate billing attempt blocked.', 'arvan-reseller' ) );
		}

		$charge = $this->calculate_customer_charge( $window['usage_amount'], $window['unit_price'] );

		if ( is_wp_error( $charge ) ) {
			return $charge;
		}

		$owns_transaction = ! $this->database->in_transaction();

		if ( $owns_transaction && ! $this->database->begin_transaction() ) {
			return new WP_Error( 'arvan_reseller_transaction_start_failed', __( 'Unable to start the billing transaction.', 'arvan-reseller' ) );
		}

		$debit = array(
			'transaction_id'  => 0,
			'applied_minor'   => 0,
			'uncovered_minor' => 0,
		);

		if ( $charge['total_charge_minor'] > 0 ) {
			$currency = $this->resource_currency( $resource );
			$debit = $this->wallet->charge_up_to_available_minor(
				$customer_id,
				$charge['total_charge_minor'],
				'usage_billing',
				$window['billing_reference'],
				sprintf(
					/* translators: %s: resource ID. */
					__( 'Usage billing for resource %s.', 'arvan-reseller' ),
					$resource_id
				),
				$currency
			);

			if ( is_wp_error( $debit ) ) {
				if ( $owns_transaction ) {
					$this->database->rollback();
				}

				return $debit;
			}
		}

		$usage_log_id = $this->database->create_usage_log(
			array(
				'customer_id'           => $customer_id,
				'resource_record_id'    => absint( $resource['id'] ),
				'resource_id'           => $resource_id,
				'usage_quantity_scaled' => $charge['usage_quantity_scaled'],
				'quantity_scale'        => Arvan_Reseller_Money::scale(),
				'unit'                  => $window['unit'],
				'usage_start'           => $window['usage_start'],
				'usage_end'             => $window['usage_end'],
				'base_cost_minor'       => $charge['base_cost_minor'],
				'reseller_share_minor'  => $charge['reseller_share_minor'],
				'total_charge_minor'    => $charge['total_charge_minor'],
				'charged_minor'         => (int) $debit['applied_minor'],
				'uncovered_minor'       => (int) $debit['uncovered_minor'],
				'currency'              => $this->resource_currency( $resource ),
				'billing_reference'     => $window['billing_reference'],
				'api_payload'           => wp_json_encode( $usage_data ),
			)
		);

		if ( false === $usage_log_id ) {
			if ( $owns_transaction ) {
				$this->database->rollback();
			}

			return new WP_Error(
				$this->database->is_duplicate_error() ? 'arvan_reseller_duplicate_billing' : 'arvan_reseller_usage_log_failed',
				__( 'Failed to save the idempotent usage record.', 'arvan-reseller' )
			);
		}

		$cursor_updated = $this->database->update(
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

		if ( false === $cursor_updated ) {
			if ( $owns_transaction ) {
				$this->database->rollback();
			}

			return new WP_Error( 'arvan_reseller_billing_cursor_failed', __( 'Failed to advance the resource billing cursor.', 'arvan-reseller' ) );
		}

		if ( $owns_transaction && ! $this->database->commit() ) {
			return new WP_Error( 'arvan_reseller_transaction_commit_failed', __( 'Unable to commit the billing transaction.', 'arvan-reseller' ) );
		}

		return array(
			'usage_log_id'       => $usage_log_id,
			'transaction_id'     => (int) $debit['transaction_id'],
			'total_charge'       => $charge['total_charge'],
			'total_charge_minor' => $charge['total_charge_minor'],
			'charged_minor'      => (int) $debit['applied_minor'],
			'uncovered_minor'    => (int) $debit['uncovered_minor'],
			'reseller_share'     => $charge['reseller_share'],
			'billing_reference'  => $window['billing_reference'],
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
		$end_time    = gmdate( 'Y-m-d H:00:00' );
		$cursor      = ! empty( $resource['last_billed_at'] ) ? (string) $resource['last_billed_at'] : (string) ( $resource['created_at'] ?? '' );
		$start_stamp = strtotime( $cursor . ' UTC' );
		$start_time  = false === $start_stamp ? gmdate( 'Y-m-d H:00:00', strtotime( '-1 hour UTC' ) ) : gmdate( 'Y-m-d H:i:s', $start_stamp );

		return array(
			'start' => $start_time,
			'end'   => $end_time,
		);
	}

	/**
	 * Resolve the configured reseller share.
	 *
	 * @return string
	 */
	public function get_reseller_share_percent() {
		return $this->format_basis_points( $this->get_reseller_share_basis_points() );
	}

	/**
	 * Resolve configured share as integer basis points.
	 *
	 * @return int
	 */
	private function get_reseller_share_basis_points() {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$share    = isset( $settings['reseller_share_percent'] ) ? $settings['reseller_share_percent'] : '0';

		return $this->normalize_reseller_share_basis_points( $share );
	}

	/**
	 * Normalize and cap a share percentage to 0..2000 basis points.
	 *
	 * @param int|string $share_percent Share percentage.
	 * @return int
	 */
	private function normalize_reseller_share_basis_points( $share_percent ) {
		$scaled = Arvan_Reseller_Money::to_minor( $share_percent );

		if ( is_wp_error( $scaled ) || $scaled <= 0 ) {
			return 0;
		}

		$basis_points = intdiv( $scaled + 50, 100 );

		return min( 2000, $basis_points );
	}

	/**
	 * Format basis points as a two-decimal percentage string.
	 *
	 * @param int $basis_points Basis points.
	 * @return string
	 */
	private function format_basis_points( $basis_points ) {
		return intdiv( (int) $basis_points, 100 ) . '.' . str_pad( (string) ( (int) $basis_points % 100 ), 2, '0', STR_PAD_LEFT );
	}

	/**
	 * Normalize usage data into a consistent billing shape.
	 *
	 * @param array $usage_data Usage payload.
	 * @param array $resource Resource record.
	 * @return array|WP_Error
	 */
	private function normalize_usage_window( array $usage_data, array $resource ) {
		$resource_id  = (string) $resource['resource_id'];
		$start_stamp  = ! empty( $usage_data['start'] ) ? strtotime( (string) $usage_data['start'] . ' UTC' ) : strtotime( '-1 hour UTC' );
		$end_stamp    = ! empty( $usage_data['end'] ) ? strtotime( (string) $usage_data['end'] . ' UTC' ) : time();
		$usage_amount = isset( $usage_data['usage_amount'] ) ? (string) $usage_data['usage_amount'] : '0';
		$unit_price   = isset( $usage_data['unit_price'] ) ? (string) $usage_data['unit_price'] : '0';
		$unit         = isset( $usage_data['unit'] ) ? sanitize_key( $usage_data['unit'] ) : '';

		if ( false === $start_stamp || false === $end_stamp || $start_stamp >= $end_stamp || '' === $resource_id ) {
			return new WP_Error( 'arvan_reseller_invalid_usage_window', __( 'Usage window is invalid.', 'arvan-reseller' ) );
		}

		$usage_start = gmdate( 'Y-m-d H:i:s', $start_stamp );
		$usage_end   = gmdate( 'Y-m-d H:i:s', $end_stamp );
		$reference   = 'usage:' . hash( 'sha256', absint( $resource['id'] ) . '|' . $resource_id . '|' . $usage_start . '|' . $usage_end );

		return array(
			'usage_start'       => $usage_start,
			'usage_end'         => $usage_end,
			'usage_amount'      => $usage_amount,
			'unit_price'        => $unit_price,
			'unit'              => $unit,
			'billing_reference' => $reference,
		);
	}

	/**
	 * Resolve currency without accepting arbitrary financial units.
	 *
	 * @return string
	 */
	private function get_currency() {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$currency = isset( $settings['currency'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $settings['currency'] ) ) : 'IRR';

		return 3 === strlen( $currency ) ? $currency : 'IRR';
	}

	/** Resolve the immutable resource currency, with a legacy fallback. */
	private function resource_currency( array $resource ) {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $resource['currency'] ?? '' ) ) );
		return 3 === strlen( $currency ) ? $currency : $this->get_currency();
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
