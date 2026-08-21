<?php
/**
 * Cron coordination service.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Cron {

	/**
	 * Billing service.
	 *
	 * @var Arvan_Reseller_Billing
	 */
	private $billing;

	/**
	 * Database service.
	 *
	 * @var Arvan_Reseller_Database
	 */
	private $database;

	/**
	 * API client.
	 *
	 * @var Arvan_Reseller_API_Client
	 */
	private $api_client;

	/**
	 * Sync lock transient key.
	 *
	 * @var string
	 */
	private $lock_key = 'arvan_reseller_usage_sync_lock';

	/**
	 * Constructor.
	 *
	 * @param Arvan_Reseller_Billing    $billing Billing service.
	 * @param Arvan_Reseller_Database   $database Database service.
	 * @param Arvan_Reseller_API_Client $api_client API client.
	 */
	public function __construct( Arvan_Reseller_Billing $billing, Arvan_Reseller_Database $database, Arvan_Reseller_API_Client $api_client ) {
		$this->billing    = $billing;
		$this->database   = $database;
		$this->api_client = $api_client;
	}

	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'arvan_reseller_usage_sync', array( $this, 'run_hourly_usage_sync' ) );
		$this->maybe_schedule_event();
	}

	/**
	 * Execute the hourly usage sync job.
	 *
	 * @return void
	 */
	public function run_hourly_usage_sync() {
		if ( $this->is_locked() ) {
			return;
		}

		$this->acquire_lock();

		try {
			$resources = $this->billing->get_billable_resources();
			$resources = array_slice( $resources, 0, $this->get_batch_limit() );

			foreach ( $resources as $resource ) {
				$result = $this->process_resource( $resource );

				if ( is_wp_error( $result ) ) {
					$this->mark_resource_sync_failure( $resource, $result );
					continue;
				}

				$this->mark_resource_sync_success( $resource, $result );
				$this->handle_post_billing_balance_state( $resource );
			}
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Process hourly usage for a resource.
	 *
	 * @param array $resource Resource record.
	 * @return array|WP_Error
	 */
	private function process_resource( array $resource ) {
		$query_window = $this->billing->build_usage_query_window( $resource );
		$response     = $this->api_client->get_usage(
			(string) $resource['resource_id'],
			array(
				'start' => $query_window['start'],
				'end'   => $query_window['end'],
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->classify_error( $response );
		}

		$usage_data = $this->normalize_usage_response( $response['body'], $query_window );

		if ( is_wp_error( $usage_data ) ) {
			return $this->classify_error( $usage_data, 'permanent' );
		}

		$billing_result = $this->billing->process_resource_usage( $resource, $usage_data );

		if ( is_wp_error( $billing_result ) ) {
			return $this->classify_error( $billing_result, $this->is_temporary_billing_error( $billing_result ) ? 'temporary' : 'permanent' );
		}

		return array(
			'usage_query_window' => $query_window,
			'usage_data'         => $usage_data,
			'billing_result'     => $billing_result,
		);
	}

	/**
	 * Normalize usage payloads returned by the API.
	 *
	 * @param array $body API response body.
	 * @param array $query_window Requested window.
	 * @return array|WP_Error
	 */
	private function normalize_usage_response( array $body, array $query_window ) {
		$usage_amount = null;
		$unit_price   = null;
		$unit         = '';

		if ( isset( $body['usage_amount'], $body['unit_price'] ) ) {
			$usage_amount = $body['usage_amount'];
			$unit_price   = $body['unit_price'];
			$unit         = isset( $body['unit'] ) ? (string) $body['unit'] : '';
		} elseif ( isset( $body['data']['usage_amount'], $body['data']['unit_price'] ) ) {
			$usage_amount = $body['data']['usage_amount'];
			$unit_price   = $body['data']['unit_price'];
			$unit         = isset( $body['data']['unit'] ) ? (string) $body['data']['unit'] : '';
		}

		if ( null === $usage_amount || null === $unit_price ) {
			return new WP_Error( 'arvan_reseller_invalid_usage_payload', __( 'Usage payload is missing billing fields.', 'arvan-reseller' ) );
		}

		return array(
			'start'        => $query_window['start'],
			'end'          => $query_window['end'],
			'usage_amount' => (float) $usage_amount,
			'unit_price'   => (float) $unit_price,
			'unit'         => sanitize_key( $unit ),
		);
	}

	/**
	 * Mark a failed resource sync safely.
	 *
	 * @param array    $resource Resource record.
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	private function mark_resource_sync_failure( array $resource, WP_Error $error ) {
		$resource_payload = $this->decode_resource_payload( $resource );
		$failure_count    = isset( $resource_payload['cron_meta']['failure_count'] ) ? absint( $resource_payload['cron_meta']['failure_count'] ) + 1 : 1;
		$failure_time     = current_time( 'mysql', true );
		$messages         = $error->get_error_messages();

		$resource_payload['cron_meta'] = array(
			'last_status'       => 'failed',
			'failure_count'     => $failure_count,
			'last_failure_time' => $failure_time,
			'last_error_code'   => $error->get_error_code(),
			'last_error_message'=> isset( $messages[0] ) ? (string) $messages[0] : '',
			'last_error_type'   => $this->get_error_type( $error ),
			'retryable'         => 'temporary' === $this->get_error_type( $error ),
		);

		$this->database->update(
			'resources',
			array(
				'updated_at'     => $failure_time,
				'last_synced_at' => $failure_time,
				'remote_payload' => wp_json_encode( $resource_payload ),
			),
			array(
				'id' => (int) $resource['id'],
			),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Track successful sync state.
	 *
	 * @param array $resource Resource record.
	 * @param array $result Successful result.
	 * @return void
	 */
	private function mark_resource_sync_success( array $resource, array $result ) {
		$resource_payload = $this->decode_resource_payload( $resource );
		$success_time     = current_time( 'mysql', true );

		$resource_payload['cron_meta'] = array(
			'last_status'       => 'success',
			'failure_count'     => 0,
			'last_failure_time' => isset( $resource_payload['cron_meta']['last_failure_time'] ) ? $resource_payload['cron_meta']['last_failure_time'] : '',
			'last_error_code'   => '',
			'last_error_message'=> '',
			'last_error_type'   => '',
			'retryable'         => false,
			'last_success_time' => $success_time,
			'last_usage_start'  => isset( $result['usage_data']['start'] ) ? $result['usage_data']['start'] : '',
			'last_usage_end'    => isset( $result['usage_data']['end'] ) ? $result['usage_data']['end'] : '',
		);

		$this->database->update(
			'resources',
			array(
				'updated_at'     => $success_time,
				'last_synced_at' => $success_time,
				'remote_payload' => wp_json_encode( $resource_payload ),
			),
			array(
				'id' => (int) $resource['id'],
			),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Handle threshold and zero-balance actions after billing.
	 *
	 * @param array $resource Resource record.
	 * @return void
	 */
	private function handle_post_billing_balance_state( array $resource ) {
		$wallet = $this->database->get_wallet_by_customer_id( (int) $resource['customer_id'] );

		if ( null === $wallet ) {
			return;
		}

		$balance   = round( (float) $wallet['balance'], 4 );
		$threshold = round( (float) $wallet['threshold'], 4 );

		if ( $threshold > 0 && $balance <= $threshold ) {
			do_action( 'arvan_reseller_wallet_low_balance', $resource, $wallet );
		}

		if ( $balance > 0 ) {
			return;
		}

		if ( isset( $resource['status'] ) && 'suspended' === (string) $resource['status'] ) {
			return;
		}

		$response = $this->api_client->suspend_resource( (string) $resource['resource_id'] );

		if ( is_wp_error( $response ) ) {
			$this->mark_resource_sync_failure( $resource, $this->classify_error( $response ) );
			return;
		}

		$resource_payload = $this->decode_resource_payload( $resource );
		$resource_payload['cron_meta']['last_suspend_time'] = current_time( 'mysql', true );
		$resource_payload['cron_meta']['last_status']       = 'suspended_zero_balance';

		$this->database->update(
			'resources',
			array(
				'status'         => 'suspended',
				'updated_at'     => current_time( 'mysql', true ),
				'last_synced_at' => current_time( 'mysql', true ),
				'remote_payload' => wp_json_encode( $resource_payload ),
			),
			array(
				'id' => (int) $resource['id'],
			),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		do_action( 'arvan_reseller_resource_zero_balance', $resource, $wallet, $response );
		do_action( 'arvan_reseller_resource_terminate_policy', $resource, $wallet, $response );
	}

	/**
	 * Add retry classification to an error.
	 *
	 * @param WP_Error $error Error object.
	 * @param string   $type Error type.
	 * @return WP_Error
	 */
	private function classify_error( WP_Error $error, $type = '' ) {
		$type = $type ? $type : ( $this->is_temporary_api_error( $error ) ? 'temporary' : 'permanent' );

		$error->add_data(
			array(
				'type' => $type,
			),
			$error->get_error_code()
		);

		return $error;
	}

	/**
	 * Detect temporary API failures.
	 *
	 * @param WP_Error $error Error object.
	 * @return bool
	 */
	private function is_temporary_api_error( WP_Error $error ) {
		$data        = $error->get_error_data();
		$status_code = is_array( $data ) && isset( $data['status_code'] ) ? (int) $data['status_code'] : 0;
		$code        = $error->get_error_code();
		$temporary   = array(
			'http_request_failed',
			'arvan_reseller_api_error',
		);

		if ( in_array( $code, $temporary, true ) && ( 0 === $status_code || $status_code >= 500 || 429 === $status_code ) ) {
			return true;
		}

		return 'http_request_failed' === $code;
	}

	/**
	 * Detect temporary billing failures.
	 *
	 * @param WP_Error $error Error object.
	 * @return bool
	 */
	private function is_temporary_billing_error( WP_Error $error ) {
		return in_array(
			$error->get_error_code(),
			array(
				'arvan_reseller_wallet_update_failed',
				'arvan_reseller_usage_log_failed',
				'arvan_reseller_ledger_write_failed',
			),
			true
		);
	}

	/**
	 * Read error type metadata.
	 *
	 * @param WP_Error $error Error object.
	 * @return string
	 */
	private function get_error_type( WP_Error $error ) {
		$data = $error->get_error_data();

		return is_array( $data ) && ! empty( $data['type'] ) ? (string) $data['type'] : 'permanent';
	}

	/**
	 * Decode stored resource payload safely.
	 *
	 * @param array $resource Resource record.
	 * @return array
	 */
	private function decode_resource_payload( array $resource ) {
		$payload = isset( $resource['remote_payload'] ) ? json_decode( (string) $resource['remote_payload'], true ) : array();

		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * Determine whether the job lock is active.
	 *
	 * @return bool
	 */
	private function is_locked() {
		return (bool) get_transient( $this->lock_key );
	}

	/**
	 * Acquire the sync lock.
	 *
	 * @return void
	 */
	private function acquire_lock() {
		set_transient( $this->lock_key, 1, HOUR_IN_SECONDS );
	}

	/**
	 * Release the sync lock.
	 *
	 * @return void
	 */
	private function release_lock() {
		delete_transient( $this->lock_key );
	}

	/**
	 * Resolve the maximum resources per run.
	 *
	 * @return int
	 */
	private function get_batch_limit() {
		$limit = (int) apply_filters( 'arvan_reseller_usage_sync_batch_limit', 50 );

		return max( 1, $limit );
	}

	/**
	 * Ensure the hourly sync event is scheduled.
	 *
	 * @return void
	 */
	private function maybe_schedule_event() {
		if ( wp_next_scheduled( 'arvan_reseller_usage_sync' ) ) {
			return;
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'arvan_reseller_usage_sync' );
	}
}
