<?php
/** Reliable background jobs with atomic locks, pagination and health state. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Arvan_Reseller_Cron {
	private $billing;
	private $database;
	private $api;
	private $notifications;
	private $provisioning;
	private $settlement;
	private $lock_key = 'arvan_reseller_usage_sync_lock';
	public function __construct( Arvan_Reseller_Billing $billing, Arvan_Reseller_Database $database, Arvan_Reseller_API_Client $api, Arvan_Reseller_Notifications $notifications, Arvan_Reseller_Provisioning $provisioning, Arvan_Reseller_Settlement $settlement ) {
		$this->billing       = $billing;
		$this->database      = $database;
		$this->api           = $api;
		$this->notifications = $notifications;
		$this->provisioning  = $provisioning;
		$this->settlement    = $settlement; }
	public function register_hooks() {
		add_action( 'arvan_reseller_usage_sync', array( $this, 'run_hourly_usage_sync' ) );
		add_action( 'arvan_reseller_settlement', array( $this, 'run_settlement' ) );
		add_action( 'arvan_reseller_reconciliation', array( $this, 'run_reconciliation' ) );
		$this->maybe_schedule_events(); }

	/** @return array|WP_Error */
	public function run_hourly_usage_sync() {
		$token = $this->acquire_lock( $this->lock_key, 55 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $token ) ) {
			return $token; }
		$previous_health = get_option( 'arvan_reseller_cron_health', array() );
		$health          = array(
			'status'          => 'running',
			'started_at'      => current_time( 'mysql', true ),
			'last_success_at' => is_array( $previous_health ) ? (string) ( $previous_health['last_success_at'] ?? '' ) : '',
			'last_failure_at' => is_array( $previous_health ) ? (string) ( $previous_health['last_failure_at'] ?? '' ) : '',
			'processed'       => 0,
			'failed'          => 0,
			'cursor'          => 0,
		);
		update_option( 'arvan_reseller_cron_health', $health, false );
		$customers = array();
		$cursor    = 0;
		$limit     = max( 1, min( 200, (int) apply_filters( 'arvan_reseller_usage_sync_batch_limit', 50 ) ) );
		try {
			while ( true ) {
				$rows = $this->database->get_billable_resources( $cursor, $limit );
				if ( empty( $rows ) ) {
					break; }
				foreach ( $rows as $resource ) {
					$cursor                                      = (int) $resource['id'];
					$currency = $this->resource_currency( $resource );
					$customers[ (int) $resource['customer_id'] ][ $currency ] = true;
					$result                                      = $this->process_resource( $resource );
					if ( is_wp_error( $result ) ) {
						++$health['failed'];
						$this->record_failure( $resource, $result );
					} else {
						++$health['processed'];
						$this->record_success( $resource ); }
				}
				$health['cursor'] = $cursor;
				if ( count( $rows ) < $limit ) {
					break; }
			}
			foreach ( $customers as $customer_id => $currencies ) {
				foreach ( array_keys( $currencies ) as $currency ) {
					$this->notifications->maybe_send_low_balance( $customer_id, $currency );
					$this->enforce_balance_policy( $customer_id, $currency );
				}
			}
			$health['status'] = 0 === $health['failed'] ? 'healthy' : 'degraded';
		} catch ( Throwable $error ) {
			$health['status']     = 'failed';
			$health['error_code'] = 'unexpected_job_failure'; } finally {
			$health['finished_at'] = current_time( 'mysql', true );
			if ( 'healthy' === $health['status'] ) {
				$health['last_success_at'] = $health['finished_at']; }
			if ( in_array( $health['status'], array( 'degraded', 'failed' ), true ) ) {
				$health['last_failure_at'] = $health['finished_at']; }
			update_option( 'arvan_reseller_cron_health', $health, false );
			$this->release_lock( $this->lock_key, $token ); }
			return $health;
	}
	public function run_reconciliation() {
		$token = $this->acquire_lock( 'arvan_reseller_reconciliation_lock', 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $token ) ) {
			return $token;
		} try {
			return $this->provisioning->reconcile_pending_resources( 50 );
		} finally {
			$this->release_lock( 'arvan_reseller_reconciliation_lock', $token ); } }
	public function run_settlement() {
		$token = $this->acquire_lock( 'arvan_reseller_settlement_lock', 30 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $token ) ) {
			return $token;
		} try {
			return $this->settlement->settle_previous_day();
		} finally {
			$this->release_lock( 'arvan_reseller_settlement_lock', $token ); } }

	private function process_resource( array $resource ) {
		$remote = $this->api->get_server( (string) $resource['region'], (string) $resource['resource_id'] );
		if ( is_wp_error( $remote ) ) {
			if ( 'arvan_reseller_api_not_found' === $remote->get_error_code() ) {
				$this->database->update(
					'resources',
					array(
						'status'        => 'terminated',
						'remote_status' => 'not_found',
						'terminated_at' => current_time( 'mysql', true ),
						'updated_at'    => current_time( 'mysql', true ),
					),
					array(
						'id'          => (int) $resource['id'],
						'customer_id' => (int) $resource['customer_id'],
					)
				);
				$this->record_resource_event( $resource, 'termination', 'remote_not_found' );
				return array(
					'skipped' => true,
					'reason'  => 'remote_not_found',
				);
			}
			return $remote;
		}
		$remote_status = sanitize_key( (string) ( $remote['body']['data']['status'] ?? 'unknown' ) );
		$this->database->update(
			'resources',
			array(
				'remote_status'  => $remote_status,
				'last_synced_at' => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array(
				'id'          => (int) $resource['id'],
				'customer_id' => (int) $resource['customer_id'],
			)
		);
		if ( in_array( $remote_status, array( 'terminated', 'deleted' ), true ) ) {
			$this->database->update(
				'resources',
				array(
					'status'        => 'terminated',
					'terminated_at' => current_time( 'mysql', true ),
					'updated_at'    => current_time( 'mysql', true ),
				),
				array(
					'id'          => (int) $resource['id'],
					'customer_id' => (int) $resource['customer_id'],
				)
			);
			$this->record_resource_event( $resource, 'termination', 'remote_status' );
			return array(
				'skipped' => true,
				'reason'  => 'remote_terminated',
			);
		}
		if ( in_array( $remote_status, array( 'shutoff', 'powered_off' ), true ) && 'suspended' !== (string) $resource['status'] ) {
			$this->database->update(
				'resources',
				array(
					'status'       => 'suspended',
					'suspended_at' => current_time( 'mysql', true ),
					'updated_at'   => current_time( 'mysql', true ),
				),
				array(
					'id'          => (int) $resource['id'],
					'customer_id' => (int) $resource['customer_id'],
				)
			);
			$this->record_resource_event( $resource, 'suspension', 'remote_status' );
			$resource['status'] = 'suspended';
		}
		if ( 'suspended' === (string) $resource['status'] ) {
			return array(
				'skipped' => true,
				'reason'  => 'suspended',
			);
		}
		$window = $this->billing->build_usage_query_window( $resource );
		if ( strtotime( $window['start'] . ' UTC' ) >= strtotime( $window['end'] . ' UTC' ) ) {
			return array(
				'skipped' => true,
				'reason'  => 'no_complete_window',
			);
		}
		$response = $this->api->get_usage_for_resource( $resource, $window );
		if ( is_wp_error( $response ) ) {
			return $response;
		} $data = (array) ( $response['body']['data'] ?? array() );
		if ( ! isset( $data['usage_amount'], $data['unit_price'] ) ) {
			return new WP_Error( 'arvan_reseller_invalid_usage_payload', __( 'Usage response is incomplete.', 'arvan-reseller' ) );
		} $data['start'] = (string) ( $data['start'] ?? $window['start'] );
		$data['end']     = (string) ( $data['end'] ?? $window['end'] );
		$result          = $this->billing->process_resource_usage( $resource, $data );
		return is_wp_error( $result ) && 'arvan_reseller_duplicate_billing' === $result->get_error_code() ? array( 'idempotent' => true ) : $result; }
	private function record_failure( array $resource, WP_Error $error ) {
		$count      = min( 20, (int) ( $resource['sync_failure_count'] ?? 0 ) + 1 );
		$error_data = $error->get_error_data();
		$retryable  = ( is_array( $error_data ) && ! empty( $error_data['retryable'] ) ) || in_array( $error->get_error_code(), array( 'http_request_failed', 'arvan_reseller_api_transport_error', 'arvan_reseller_api_rate_limited', 'arvan_reseller_api_server_error' ), true );
		$delay      = $retryable ? min( DAY_IN_SECONDS, 60 * ( 2 ** min( 10, $count ) ) ) : DAY_IN_SECONDS;
		$this->database->update(
			'resources',
			array(
				'sync_failure_count' => $count,
				'next_retry_at'      => gmdate( 'Y-m-d H:i:s', time() + $delay ),
				'last_error_code'    => sanitize_key( $error->get_error_code() ),
				'last_synced_at'     => current_time( 'mysql', true ),
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $resource['id'] )
		);
		$this->database->create_audit_log(
			'resource_sync_failed',
			'resource',
			(string) $resource['id'],
			array(
				'error_code' => $error->get_error_code(),
				'retryable'  => $retryable,
			),
			(int) $resource['customer_id'],
			0
		); }
	private function record_success( array $resource ) {
		$this->database->update(
			'resources',
			array(
				'sync_failure_count' => 0,
				'next_retry_at'      => null,
				'last_error_code'    => '',
				'last_synced_at'     => current_time( 'mysql', true ),
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $resource['id'] )
		); }

	private function enforce_balance_policy( $customer_id, $currency ) {
		$wallet = $this->database->get_wallet_by_customer_id( $customer_id, $currency );
		if ( null === $wallet || (int) $wallet['balance_minor'] > 0 ) {
			return; }
		$settings = get_option( 'arvan_reseller_settings', array() );
		if ( 'disabled' === (string) ( $settings['suspend_policy'] ?? 'zero_balance' ) ) {
			return;
		}
		$zone = sanitize_text_field( (string) ( $settings['availability_zone'] ?? '' ) );
		$cursor = 0;
		$limit  = max( 1, min( 200, (int) apply_filters( 'arvan_reseller_balance_policy_batch_limit', 50 ) ) );
		while ( true ) {
			$resources = $this->database->get_resources_for_balance_policy( $customer_id, $cursor, $limit );
			if ( empty( $resources ) ) {
				break;
			}
			foreach ( $resources as $resource ) {
				$cursor = (int) $resource['id'];
				if ( $this->resource_currency( $resource ) !== $currency ) {
					continue;
				}
				if ( 'suspended' !== (string) $resource['status'] ) {
					$response = $this->api->power_off_server( (string) $resource['region'], (string) $resource['resource_id'], $zone );
					if ( is_wp_error( $response ) ) {
						$this->record_failure( $resource, $response );
						continue;
					} $now = current_time( 'mysql', true );
					$this->database->update(
						'resources',
						array(
							'status'        => 'suspended',
							'remote_status' => 'powered_off',
							'suspended_at'  => $now,
							'updated_at'    => $now,
						),
						array(
							'id'          => (int) $resource['id'],
							'customer_id' => absint( $customer_id ),
						)
					);
					$this->database->create_audit_log( 'resource_suspended_zero_balance', 'resource', (string) $resource['id'], array(), $customer_id, 0 );
					$this->record_resource_event( $resource, 'suspension', 'zero_balance' );
					$resource['status']       = 'suspended';
					$resource['suspended_at'] = $now; }
				$this->maybe_terminate( $resource, $settings, $zone );
			}
			if ( count( $resources ) < $limit ) {
				break;
			}
		}
	}
	private function maybe_terminate( array $resource, array $settings, $zone ) {
		$policy = sanitize_key( (string) ( $settings['termination_policy'] ?? 'disabled' ) );
		if ( 'disabled' === $policy || 'suspended' !== (string) $resource['status'] ) {
			return;
		} $hours      = 'immediate' === $policy ? 0 : max( 1, absint( $settings['termination_grace_hours'] ?? 72 ) );
		$suspended_at = strtotime( (string) ( $resource['suspended_at'] ?? '' ) . ' UTC' );
		if ( false === $suspended_at || time() < $suspended_at + HOUR_IN_SECONDS * $hours ) {
			return;
		} $response = $this->api->terminate_server( (string) $resource['region'], (string) $resource['resource_id'], $zone );
		if ( is_wp_error( $response ) ) {
			$this->record_failure( $resource, $response );
			return;
		} $this->database->update(
			'resources',
			array(
				'status'        => 'terminated',
				'remote_status' => 'terminated',
				'terminated_at' => current_time( 'mysql', true ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array(
				'id'          => (int) $resource['id'],
				'customer_id' => (int) $resource['customer_id'],
			)
		);
		$this->database->create_audit_log( 'resource_terminated_by_policy', 'resource', (string) $resource['id'], array( 'policy' => $policy ), (int) $resource['customer_id'], 0 );
		$this->record_resource_event( $resource, 'termination', 'policy_' . $policy ); }

	private function record_resource_event( array $resource, $type, $reason ) {
		$reference = '' !== (string) ( $resource['resource_id'] ?? '' ) ? (string) $resource['resource_id'] : (string) ( $resource['id'] ?? '' );
		$this->database->record_notification_event(
			(int) ( $resource['customer_id'] ?? 0 ),
			$type,
			$reference,
			array(
				'resource_id' => $reference,
				'reason'      => sanitize_key( (string) $reason ),
			)
		);
	}

	private function resource_currency( array $resource ) {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $resource['currency'] ?? '' ) ) );
		if ( 3 === strlen( $currency ) ) {
			return $currency;
		}
		$settings = get_option( 'arvan_reseller_settings', array() );
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $settings['currency'] ?? 'IRR' ) ) );
		return 3 === strlen( $currency ) ? $currency : 'IRR'; }

	private function acquire_lock( $key, $ttl ) {
		$token = wp_generate_uuid4();
		$value = array(
			'token'   => $token,
			'expires' => time() + absint( $ttl ),
		);
		if ( $this->insert_lock_option( $key, $value ) ) {
			return $token;
		} $old = get_option( $key, array() );
		if ( is_array( $old ) && (int) ( $old['expires'] ?? 0 ) < time() ) {
			if ( $this->delete_lock_if_matches( $key, $old ) && $this->insert_lock_option( $key, $value ) ) {
				return $token;
			}
		} return new WP_Error( 'arvan_reseller_cron_locked', __( 'This background job is already running.', 'arvan-reseller' ) ); }
	/** @phpstan-impure */
	private function insert_lock_option( $key, array $value ) {
		return add_option( $key, $value, '', false );
	}
	private function release_lock( $key, $token ) {
		$value = get_option( $key, array() );
		if ( is_array( $value ) && hash_equals( (string) ( $value['token'] ?? '' ), (string) $token ) ) {
			$this->delete_lock_if_matches( $key, $value ); } }
	private function delete_lock_if_matches( $key, array $value ) {
		global $wpdb;
		$deleted = $wpdb->delete(
			$wpdb->options,
			array(
				'option_name'  => (string) $key,
				'option_value' => maybe_serialize( $value ),
			),
			array( '%s', '%s' )
		);
		if ( 1 === (int) $deleted ) {
			wp_cache_delete( $key, 'options' );
			return true;
		}
		return false;
	}
	private function maybe_schedule_events() {
		if ( ! wp_next_scheduled( 'arvan_reseller_usage_sync' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'arvan_reseller_usage_sync' );
		} if ( ! wp_next_scheduled( 'arvan_reseller_reconciliation' ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'arvan_reseller_reconciliation' );
		} if ( ! wp_next_scheduled( 'arvan_reseller_settlement' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 00:15 UTC' ), 'daily', 'arvan_reseller_settlement' ); } }
}
