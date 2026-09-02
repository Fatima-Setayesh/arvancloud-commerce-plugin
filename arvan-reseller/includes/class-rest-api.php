<?php
/** Versioned REST API with cookie nonce, capabilities, rate limits and ownership checks. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Arvan_Reseller_REST_API {
	private $database;
	private $wallet;
	private $payment;
	private $provisioning;
	private $billing;
	private $api;
	private $cron;
	private $settings;
	public function __construct( Arvan_Reseller_Database $database, Arvan_Reseller_Wallet $wallet, Arvan_Reseller_Payment $payment, Arvan_Reseller_Provisioning $provisioning, Arvan_Reseller_Billing $billing, Arvan_Reseller_API_Client $api, Arvan_Reseller_Cron $cron, Arvan_Reseller_Settings $settings ) {
		$this->database     = $database;
		$this->wallet       = $wallet;
		$this->payment      = $payment;
		$this->provisioning = $provisioning;
		$this->billing      = $billing;
		$this->api          = $api;
		$this->cron         = $cron;
		$this->settings     = $settings; }
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) ); }
	public function register_routes() {
		$n         = 'arvan-reseller/v1';
		$customer  = array( 'permission_callback' => array( $this, 'customer_permission' ) );
		$admin     = array( 'permission_callback' => array( $this, 'admin_permission' ) );
		$list_args = $this->list_args();
		register_rest_route(
			$n,
			'/wallet',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'wallet' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/wallet/transactions',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'transactions' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/payments',
			array(
				array_merge(
					$customer,
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'payments' ),
						'args'     => $list_args,
					)
				),
				array_merge(
					$customer,
					array(
						'methods'  => WP_REST_Server::CREATABLE,
						'callback' => array( $this, 'create_payment' ),
						'args'     => array(
							'amount' => array(
								'required'          => true,
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_text_field',
								'validate_callback' => array( $this, 'validate_positive_money' ),
							),
						),
					)
				),
			)
		);
		register_rest_route(
			$n,
			'/payments/(?P<reference>[A-Za-z0-9_\-]+)/confirm',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'confirm_payment' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/catalog/regions',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'regions' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/catalog/images',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'images' ),
					'args'     => $this->region_args(),
				)
			)
		);
		register_rest_route(
			$n,
			'/catalog/flavors',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'flavors' ),
					'args'     => $this->region_args(),
				)
			)
		);
		register_rest_route(
			$n,
			'/orders',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'create_order' ),
					'args'     => array(
						'region'                  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => array( $this, 'validate_region' ),
						),
						'availabilityZone'        => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'flavorId'                => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'imageId'                 => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'name'                    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'rootVolumeSizeGigaBytes' => array(
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 1,
							'maximum'           => 10000,
							'sanitize_callback' => 'absint',
						),
						'enableBackup'            => array( 'type' => 'boolean' ),
						'enableFailOver'          => array( 'type' => 'boolean' ),
						'enableIpv4'              => array( 'type' => 'boolean' ),
						'enableIpv6'              => array( 'type' => 'boolean' ),
					),
				)
			)
		);
		register_rest_route(
			$n,
			'/resources',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'resources' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/resources/(?P<id>\d+)',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'resource' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/settings',
			array(
				array_merge(
					$admin,
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'admin_settings' ),
					)
				),
				array_merge(
					$admin,
					array(
						'methods'  => WP_REST_Server::EDITABLE,
						'callback' => array( $this, 'update_settings' ),
						'args'     => $this->settings_args(),
					)
				),
			)
		);
		register_rest_route(
			$n,
			'/admin/connection-test',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'connection_test' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/cron/run',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'run_cron' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/health',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'health' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/payments',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'admin_payments' ),
					'args'     => array_merge(
						$list_args,
						array(
							'status'      => array(
								'type'              => 'string',
								'enum'              => Arvan_Reseller_Status::allowed_for( 'payments' ),
								'sanitize_callback' => 'sanitize_key',
							),
							'customer_id' => array(
								'type'              => 'integer',
								'minimum'           => 1,
								'sanitize_callback' => 'absint',
							),
						)
					),
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/payments/(?P<reference>[A-Za-z0-9_\-]+)/refund',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'refund_payment' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/settlements',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'settlements' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/catalog/estimate',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'estimate' ),
					'args'     => array(
						'region'      => array(
							'required'          => true,
							'type'              => 'string',
							'pattern'           => '^[a-z0-9][a-z0-9-]{1,39}$',
							'sanitize_callback' => 'sanitize_key',
						),
						'flavor_id'   => array(
							'required'          => true,
							'type'              => 'string',
							'minLength'         => 1,
							'maxLength'         => 191,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'usage_hours' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_positive_money' ),
						),
					),
				)
			)
		);
		register_rest_route(
			$n,
			'/orders',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'orders' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/usage',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'usage' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/invoices',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'invoices' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/notifications',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'notifications' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/notifications/(?P<id>\d+)/read',
			array_merge(
				$customer,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'read_notification' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/customers',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'admin_customers' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/wallets',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'admin_wallets' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/orders',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'admin_orders' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/resources',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'admin_resources' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/usage',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'admin_usage' ),
					'args'     => $list_args,
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/reconciliation/run',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'run_reconciliation' ),
				)
			)
		);
		register_rest_route(
			$n,
			'/admin/audit-logs',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'audit_logs' ),
					'args'     => $list_args,
				)
			)
		);
	}

	public function customer_permission() {
		if ( ! is_user_logged_in() || ! Arvan_Reseller_Security::verify_rest_nonce() ) {
			return new WP_Error( 'arvan_reseller_unauthorized', __( 'Authentication and a valid REST nonce are required.', 'arvan-reseller' ), array( 'status' => 401 ) );
		} if ( ! Arvan_Reseller_Rate_Limiter::allow( 'customer_rest', get_current_user_id(), 60 ) ) {
			return new WP_Error( 'arvan_reseller_rate_limited', __( 'Too many requests.', 'arvan-reseller' ), array( 'status' => 429 ) );
		} return true; }
	public function validate_positive_money( $value ) {
		$minor = Arvan_Reseller_Money::to_minor( is_string( $value ) || is_int( $value ) ? $value : '' );
		return ! is_wp_error( $minor ) && $minor > 0; }
	public function validate_api_key( $value ) {
		return '' === trim( (string) $value ) || '' !== Arvan_Reseller_Security::sanitize_api_key( $value ); }
	public function validate_region( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^[a-z0-9][a-z0-9-]{1,39}$/', $value ) && false === strpos( $value, '--' ); }
	public function admin_permission( $request ) {
		unset( $request );
		$base = $this->customer_permission();
		if ( true !== $base ) {
			return $base;
		} return Arvan_Reseller_Security::can_manage_plugin() ? true : new WP_Error( 'arvan_reseller_forbidden', __( 'Administrator capability is required.', 'arvan-reseller' ), array( 'status' => 403 ) ); }

	public function wallet() {
		$currency = $this->configured_currency();
		$row      = $this->database->get_wallet_by_customer_id( get_current_user_id(), $currency );
		if ( null === $row ) {
			$this->wallet->create_wallet( get_current_user_id(), $currency );
			$row = $this->database->get_wallet_by_customer_id( get_current_user_id(), $currency );
		} return array(
			'balance'   => Arvan_Reseller_Money::format( (int) $row['balance_minor'] ),
			'threshold' => Arvan_Reseller_Money::format( (int) $row['threshold_minor'] ),
			'currency'  => (string) $row['currency'],
			'status'    => (string) $row['status'],
		); }
	public function transactions( $request ) {
		return $this->collection( array_map( array( $this, 'safe_transaction' ), $this->wallet->get_balance_history( get_current_user_id(), $this->fetch_limit( $request ), $this->configured_currency(), $this->offset( $request ) ) ), $request ); }
	public function payments( $request ) {
		return $this->collection( $this->payment->list_customer_payments( get_current_user_id(), $this->fetch_limit( $request ), $this->offset( $request ) ), $request ); }
	public function create_payment( $request ) {
		if ( ! Arvan_Reseller_Rate_Limiter::allow( 'payment_create', get_current_user_id(), 5, 5 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'arvan_reseller_rate_limited', __( 'Too many payment requests.', 'arvan-reseller' ), array( 'status' => 429 ) ); }
		$p = $this->params( $request );
		return $this->payment->create_payment( get_current_user_id(), (string) ( $p['amount'] ?? '' ), $this->idempotency_key( $request, $p ) ); }
	public function confirm_payment( $request ) {
		if ( ! Arvan_Reseller_Rate_Limiter::allow( 'payment_confirm', get_current_user_id(), 10, 5 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'arvan_reseller_rate_limited', __( 'Too many payment confirmations.', 'arvan-reseller' ), array( 'status' => 429 ) ); }
		return $this->payment->confirm_payment( get_current_user_id(), sanitize_text_field( (string) $request['reference'] ) ); }
	public function regions() {
		return $this->safe_remote( $this->api->list_regions() ); }
	public function images( $request ) {
		return $this->safe_remote( $this->api->list_images( sanitize_key( (string) $request->get_param( 'region' ) ) ) ); }
	public function flavors( $request ) {
		return $this->safe_remote( $this->api->list_flavors( sanitize_key( (string) $request->get_param( 'region' ) ) ) ); }
	public function create_order( $request ) {
		if ( ! Arvan_Reseller_Rate_Limiter::allow( 'provisioning_create', get_current_user_id(), 5, 10 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'arvan_reseller_rate_limited', __( 'Too many provisioning requests.', 'arvan-reseller' ), array( 'status' => 429 ) ); }
		$p = $this->params( $request );
		return $this->provisioning->create_server_order( get_current_user_id(), $p, $this->idempotency_key( $request, $p ) ); }
	public function resources( $request ) {
		return $this->collection( array_map( array( $this, 'safe_resource' ), $this->database->get_resources_by_customer_id( get_current_user_id(), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function resource( $request ) {
		$row = $this->database->get_row_by(
			'resources',
			array(
				'id'          => absint( $request['id'] ),
				'customer_id' => get_current_user_id(),
			)
		);
		return null === $row ? new WP_Error( 'arvan_reseller_resource_not_found', __( 'Resource not found.', 'arvan-reseller' ), array( 'status' => 404 ) ) : $this->safe_resource( $row ); }

	public function admin_settings() {
		$value                       = $this->settings->get_settings();
		$key                         = Arvan_Reseller_Security::get_decrypted_option( 'arvan_reseller_api_key' );
		$value['api_key_configured'] = is_string( $key ) && '' !== $key;
		$value['cron_status']        = get_option( 'arvan_reseller_cron_health', array( 'status' => 'never_run' ) );
		unset( $value['api_key'] );
		return $value; }
	public function update_settings( $request ) {
		$p = $this->params( $request );
		if ( ! empty( $p['delete_api_key'] ) && ! empty( $p['api_key'] ) ) {
			return new WP_Error( 'arvan_reseller_conflicting_key_action', __( 'API key rotation and deletion cannot be requested together.', 'arvan-reseller' ), array( 'status' => 400 ) );
		}
		$key_rotated = ! empty( $p['api_key'] );
		$key_deleted = ! empty( $p['delete_api_key'] );
		if ( $key_rotated && ! Arvan_Reseller_Security::store_encrypted_option( 'arvan_reseller_api_key', $p['api_key'] ) ) {
			return new WP_Error( 'arvan_reseller_api_key_storage_failed', __( 'The Machine User API key could not be encrypted and was not stored.', 'arvan-reseller' ), array( 'status' => 503 ) );
		}
		if ( $key_deleted ) {
			Arvan_Reseller_Security::delete_encrypted_option( 'arvan_reseller_api_key' );
		}
		unset( $p['api_key'], $p['delete_api_key'] );
		$merged    = array_merge( $this->settings->get_settings(), $p );
		$sanitized = $this->settings->sanitize_settings( $merged );
		update_option( 'arvan_reseller_settings', $sanitized, false );
		$this->database->create_audit_log(
			'settings_updated',
			'settings',
			'global',
			array(
				'mode'            => $sanitized['mode'],
				'api_key_rotated' => $key_rotated,
				'api_key_deleted' => $key_deleted,
			)
		);
		return $this->admin_settings(); }
	public function connection_test() {
		if ( ! Arvan_Reseller_Rate_Limiter::allow( 'connection_test', get_current_user_id(), 5, 5 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'arvan_reseller_rate_limited', __( 'Too many connection tests.', 'arvan-reseller' ), array( 'status' => 429 ) ); }
		$settings = $this->settings->get_settings();
		$result   = $this->api->test_connection( (string) $settings['region'] );
		$this->database->create_audit_log(
			is_wp_error( $result ) ? 'connection_test_failed' : 'connection_test_succeeded',
			'settings',
			'global',
			array(
				'mode'       => $this->api->get_mode(),
				'error_code' => is_wp_error( $result ) ? $result->get_error_code() : '',
			)
		);
		return $this->safe_remote( $result ); }
	public function run_cron() {
		$this->database->create_audit_log( 'manual_cron_started', 'cron', 'usage' );
		return $this->cron->run_hourly_usage_sync(); }
	public function health() {
		return array(
			'mode'             => $this->api->get_mode(),
			'database_version' => get_option( 'arvan_reseller_db_version', '' ),
			'cron'             => get_option( 'arvan_reseller_cron_health', array( 'status' => 'never_run' ) ),
			'schedules'        => array(
				'usage'          => wp_next_scheduled( 'arvan_reseller_usage_sync' ),
				'reconciliation' => wp_next_scheduled( 'arvan_reseller_reconciliation' ),
				'settlement'     => wp_next_scheduled( 'arvan_reseller_settlement' ),
			),
		); }
	public function admin_payments( $request ) {
		return $this->collection( $this->payment->list_admin_payments( sanitize_key( (string) $request->get_param( 'status' ) ), absint( $request->get_param( 'customer_id' ) ), $this->fetch_limit( $request ), $this->offset( $request ) ), $request ); }
	public function refund_payment( $request ) {
		return $this->payment->refund_payment( sanitize_text_field( (string) $request['reference'] ) ); }
	public function settlements( $request ) {
		return $this->collection( array_map(
			function ( $r ) {
				unset( $r['metadata'] );
				return $r;
			},
			$this->database->get_settlements( $this->fetch_limit( $request ), $this->offset( $request ) )
		), $request ); }
	public function estimate( $request ) {
		$p           = $this->params( $request );
		$hours       = isset( $p['usage_hours'] ) ? (string) $p['usage_hours'] : '';
		$hours_minor = Arvan_Reseller_Money::to_minor( $hours );
		$flavor_id   = sanitize_text_field( (string) ( $p['flavor_id'] ?? '' ) );
		$region      = sanitize_key( (string) ( $p['region'] ?? '' ) );
		if ( is_wp_error( $hours_minor ) || $hours_minor <= 0 || $hours_minor > 8760 * Arvan_Reseller_Money::scale() || '' === $flavor_id ) {
			return new WP_Error( 'arvan_reseller_invalid_estimate', __( 'Estimate input is invalid.', 'arvan-reseller' ), array( 'status' => 400 ) );
		}
		$response = $this->api->list_flavors( $region, array( 'perPage' => 100 ) );
		if ( is_wp_error( $response ) ) {
			return $response; }
		foreach ( (array) ( $response['body']['data'] ?? array() ) as $flavor ) {
			if ( is_array( $flavor ) && (string) ( $flavor['id'] ?? '' ) === $flavor_id ) {
				$charge = $this->billing->calculate_customer_charge( $hours, (string) ( $flavor['pricePerHour'] ?? '' ) );
				return is_wp_error( $charge ) ? $charge : array(
					'flavor_id'      => $flavor_id,
					'usage_hours'    => $charge['usage_amount'],
					'base_cost'      => $charge['base_cost'],
					'reseller_share' => $charge['reseller_share'],
					'share_percent'  => $charge['share_percent'],
					'total_charge'   => $charge['total_charge'],
					'currency'       => (string) ( $this->settings->get_settings()['currency'] ?? 'IRR' ),
					'mode'           => $this->api->get_mode(),
				);
			}
		}
		return new WP_Error( 'arvan_reseller_flavor_not_found', __( 'Selected Cloud Server flavor was not found.', 'arvan-reseller' ), array( 'status' => 404 ) );
	}
	public function orders( $request ) {
		return $this->collection( array_map( array( $this->provisioning, 'serialize_order' ), $this->database->get_results_by( 'orders', array( 'customer_id' => get_current_user_id() ), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function usage( $request ) {
		return $this->collection( array_map( array( $this, 'safe_usage' ), $this->database->get_results_by( 'usage_records', array( 'customer_id' => get_current_user_id() ), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function invoices( $request ) {
		return $this->collection( array_map( array( $this, 'safe_invoice' ), $this->database->get_results_by( 'invoices', array( 'customer_id' => get_current_user_id() ), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function notifications( $request ) {
		return $this->collection( array_map( array( $this, 'safe_notification' ), $this->database->get_notifications_by_customer_id( get_current_user_id(), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function read_notification( $request ) {
		$row = $this->database->mark_notification_read( absint( $request['id'] ), get_current_user_id() );
		return null === $row ? new WP_Error( 'arvan_reseller_notification_not_found', __( 'Notification not found.', 'arvan-reseller' ), array( 'status' => 404 ) ) : $this->safe_notification( $row ); }
	public function admin_customers( $request ) {
		return $this->collection( array_map(
			function ( $user ) {
				return array(
					'id'            => (int) $user->ID,
					'display_name'  => (string) $user->display_name,
					'email'         => (string) $user->user_email,
					'registered_at' => (string) $user->user_registered,
				);
			},
			get_users(
				array(
					'number' => $this->fetch_limit( $request ),
					'offset' => $this->offset( $request ),
					'fields' => array( 'ID', 'display_name', 'user_email', 'user_registered' ),
				)
			)
		), $request ); }
	public function admin_wallets( $request ) {
		return $this->collection( array_map( array( $this, 'safe_wallet' ), $this->database->get_results_by( 'wallets', array(), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function admin_orders( $request ) {
		return $this->collection( array_map( array( $this->provisioning, 'serialize_order' ), $this->database->get_results_by( 'orders', array(), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function admin_resources( $request ) {
		return $this->collection( array_map( array( $this, 'safe_resource' ), $this->database->get_results_by( 'resources', array(), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function admin_usage( $request ) {
		return $this->collection( array_map( array( $this, 'safe_usage' ), $this->database->get_results_by( 'usage_records', array(), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }
	public function run_reconciliation() {
		$this->database->create_audit_log( 'manual_reconciliation_started', 'cron', 'reconciliation' );
		return $this->cron->run_reconciliation(); }
	public function audit_logs( $request ) {
		return $this->collection( array_map( array( $this, 'safe_audit' ), $this->database->get_results_by( 'audit_logs', array(), $this->fetch_limit( $request ), $this->offset( $request ) ) ), $request ); }

	private function list_args() {
		return array(
			'limit' => array(
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 100,
				'default'           => 50,
				'sanitize_callback' => 'absint',
			),
			'page'  => array(
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 1000000,
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
		); }
	private function region_args() {
		return array(
			'region' => array(
				'type'              => 'string',
				'pattern'           => '^[a-z0-9][a-z0-9-]{1,39}$',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => array( $this, 'validate_region' ),
			),
		); }
	private function settings_args() {
		$text     = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);
		$textarea = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
		);
		$money    = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);
		return array(
			'company_name'             => $text,
			'company_logo_url'         => array(
				'type'              => 'string',
				'format'            => 'uri',
				'sanitize_callback' => 'esc_url_raw',
			),
			'company_contact_info'     => $textarea,
			'company_about'            => $textarea,
			'logo_attachment_id'       => array(
				'type'              => 'integer',
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			),
			'mode'                     => array(
				'type'              => 'string',
				'enum'              => array( 'mock', 'live' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'region'                   => array_merge( $this->region_args()['region'], array( 'required' => false ) ),
			'availability_zone'        => $text,
			'api_key'                  => array(
				'type'              => 'string',
				'minLength'         => 0,
				'maxLength'         => 4096,
				'validate_callback' => array( $this, 'validate_api_key' ),
				'sanitize_callback' => array( 'Arvan_Reseller_Security', 'sanitize_api_key' ),
			),
			'delete_api_key'           => array( 'type' => 'boolean' ),
			'currency'                 => array(
				'type'              => 'string',
				'pattern'           => '^[A-Za-z]{3}$',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'suspend_policy'           => array(
				'type'              => 'string',
				'enum'              => array( 'zero_balance', 'disabled' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'termination_policy'       => array(
				'type'              => 'string',
				'enum'              => array( 'disabled', 'immediate', 'grace' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'termination_grace_hours'  => array(
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 8760,
				'sanitize_callback' => 'absint',
			),
			'notification_enabled'     => array( 'type' => 'boolean' ),
			'delete_data_on_uninstall' => array( 'type' => 'boolean' ),
			'reseller_share_percent'   => $money,
			'default_wallet_threshold' => $money,
			'minimum_topup'            => $money,
			'maximum_topup'            => $money,
		); }

	private function params( $request ) {
		$p = $request->get_json_params();
		return is_array( $p ) ? wp_unslash( $p ) : array(); }
	private function idempotency_key( $request, array $p ) {
		$key = (string) $request->get_header( 'Idempotency-Key' );
		return '' !== $key ? sanitize_text_field( $key ) : sanitize_text_field( (string) ( $p['idempotency_key'] ?? '' ) ); }
	private function limit( $request ) {
		$value = $request->get_param( 'limit' );
		return max( 1, min( 100, absint( $value ? $value : 50 ) ) ); }
	private function page( $request ) {
		$value = $request->get_param( 'page' );
		return max( 1, min( 1000000, absint( $value ? $value : 1 ) ) ); }
	private function offset( $request ) {
		return ( $this->page( $request ) - 1 ) * $this->limit( $request ); }
	private function fetch_limit( $request ) {
		return $this->limit( $request ) + 1; }
	private function collection( array $items, $request ) {
		$limit    = $this->limit( $request );
		$has_more = count( $items ) > $limit;
		if ( $has_more ) {
			array_pop( $items );
		}
		if ( ! class_exists( 'WP_REST_Response' ) ) {
			return $items;
		}
		$response = new WP_REST_Response( array_values( $items ), 200 );
		$response->header( 'X-Arvan-Page', (string) $this->page( $request ) );
		$response->header( 'X-Arvan-Per-Page', (string) $limit );
		$response->header( 'X-Arvan-Has-More', $has_more ? 'true' : 'false' );
		return $response;
	}
	private function safe_remote( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		} return array(
			'data' => (array) ( $result['body']['data'] ?? array() ),
			'mode' => $this->api->get_mode(),
		); }
	private function configured_currency() {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $this->settings->get_settings()['currency'] ?? 'IRR' ) ) );
		return 3 === strlen( $currency ) ? $currency : 'IRR'; }
	public function safe_transaction( array $r ) {
		return array(
			'id'             => (int) $r['id'],
			'customer_id'    => (int) $r['customer_id'],
			'type'           => (string) $r['transaction_type'],
			'amount'         => Arvan_Reseller_Money::format( (int) $r['amount_minor'] ),
			'currency'       => (string) $r['currency'],
			'reference_type' => (string) $r['reference_type'],
			'description'    => (string) $r['description'],
			'created_at'     => (string) $r['created_at'],
		); }
	public function safe_resource( array $r ) {
		$remote = json_decode( (string) ( $r['remote_payload'] ?? '' ), true );
		$remote = is_array( $remote ) ? $remote : array();
		$detail = $this->safe_resource_detail( $remote );
		return array(
			'id'             => (int) $r['id'],
			'customer_id'    => (int) $r['customer_id'],
			'order_id'       => isset( $r['order_id'] ) ? (int) $r['order_id'] : 0,
			'resource_id'    => (string) $r['resource_id'],
			'product_type'   => (string) $r['product_type'],
			'region'         => (string) $r['region'],
			'status'         => (string) $r['status'],
			'remote_status'  => (string) $r['remote_status'],
			'hourly_price'   => Arvan_Reseller_Money::format( (int) ( $r['hourly_price_minor'] ?? 0 ) ),
			'currency'       => (string) ( $r['currency'] ?? 'IRR' ),
			'name'           => $detail['name'],
			'availability_zone' => $detail['availability_zone'],
			'image'          => $detail['image'],
			'flavor'         => $detail['flavor'],
			'ip_addresses'   => $detail['ip_addresses'],
			'root_volume_size_gb' => $detail['root_volume_size_gb'],
			'last_synced_at' => (string) $r['last_synced_at'],
			'last_billed_at' => (string) $r['last_billed_at'],
			'suspended_at'   => (string) ( $r['suspended_at'] ?? '' ),
			'terminated_at'  => (string) ( $r['terminated_at'] ?? '' ),
			'created_at'     => (string) $r['created_at'],
			'updated_at'     => (string) ( $r['updated_at'] ?? '' ),
		); }
	private function safe_resource_detail( array $remote ) {
		$image  = $this->safe_named_object( $remote['image'] ?? array(), array( 'id', 'name', 'os', 'distribution' ) );
		$flavor = $this->safe_named_object( $remote['flavor'] ?? array(), array( 'id', 'name', 'cpuCount', 'memoryMegaBytes', 'diskGigaBytes' ) );
		$ips    = array();
		foreach ( array( 'ip', 'ipv4', 'ipv6', 'ipAddress', 'ipAddresses', 'publicIpAddress', 'privateIpAddress', 'addresses' ) as $key ) {
			if ( array_key_exists( $key, $remote ) ) {
				$this->collect_ip_addresses( $remote[ $key ], $ips );
			}
		}
		$root_volume = absint( $remote['rootVolumeSizeGigaBytes'] ?? $remote['rootVolumeSize'] ?? 0 );
		return array(
			'name'                => sanitize_text_field( (string) ( $remote['name'] ?? '' ) ),
			'availability_zone'   => sanitize_text_field( (string) ( $remote['availabilityZone'] ?? '' ) ),
			'image'               => $image,
			'flavor'              => $flavor,
			'ip_addresses'        => array_values( array_unique( $ips ) ),
			'root_volume_size_gb' => $root_volume,
		);
	}
	private function safe_named_object( $value, array $allowed ) {
		if ( is_scalar( $value ) ) {
			return array( 'id' => sanitize_text_field( (string) $value ) );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$output = array();
		foreach ( $allowed as $key ) {
			if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
				$output[ $key ] = sanitize_text_field( (string) $value[ $key ] );
			}
		}
		return $output;
	}
	private function collect_ip_addresses( $value, array &$ips ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$this->collect_ip_addresses( $item, $ips );
			}
			return;
		}
		if ( is_scalar( $value ) && false !== filter_var( (string) $value, FILTER_VALIDATE_IP ) ) {
			$ips[] = (string) $value;
		}
	}
	public function safe_wallet( array $r ) {
		return array(
			'id'          => (int) $r['id'],
			'customer_id' => (int) $r['customer_id'],
			'balance'     => Arvan_Reseller_Money::format( (int) $r['balance_minor'] ),
			'threshold'   => Arvan_Reseller_Money::format( (int) $r['threshold_minor'] ),
			'currency'    => (string) $r['currency'],
			'status'      => (string) $r['status'],
			'updated_at'  => (string) $r['updated_at'],
		); }
	public function safe_usage( array $r ) {
		return array(
			'id'             => (int) $r['id'],
			'customer_id'    => (int) $r['customer_id'],
			'resource_id'    => (string) $r['resource_id'],
			'usage_amount'   => Arvan_Reseller_Money::format( (int) $r['usage_quantity_scaled'] ),
			'unit'           => (string) $r['unit'],
			'usage_start'    => (string) $r['usage_start'],
			'usage_end'      => (string) $r['usage_end'],
			'base_cost'      => Arvan_Reseller_Money::format( (int) $r['base_cost_minor'] ),
			'reseller_share' => Arvan_Reseller_Money::format( (int) $r['reseller_share_minor'] ),
			'total_charge'   => Arvan_Reseller_Money::format( (int) $r['total_charge_minor'] ),
			'charged'        => Arvan_Reseller_Money::format( (int) $r['charged_minor'] ),
			'uncovered'      => Arvan_Reseller_Money::format( (int) $r['uncovered_minor'] ),
			'currency'       => (string) $r['currency'],
		); }
	public function safe_invoice( array $r ) {
		return array(
			'id'                => (int) $r['id'],
			'invoice_reference' => (string) $r['invoice_reference'],
			'period_start'      => (string) $r['period_start'],
			'period_end'        => (string) $r['period_end'],
			'base_cost'         => Arvan_Reseller_Money::format( (int) $r['base_cost_minor'] ),
			'reseller_share'    => Arvan_Reseller_Money::format( (int) $r['reseller_share_minor'] ),
			'total'             => Arvan_Reseller_Money::format( (int) $r['total_minor'] ),
			'currency'          => (string) $r['currency'],
			'status'            => (string) $r['status'],
		); }
	public function safe_notification( array $r ) {
		return array(
			'id'         => (int) $r['id'],
			'type'       => (string) $r['notification_type'],
			'status'     => (string) $r['status'],
			'channel'    => (string) $r['channel'],
			'error_code' => (string) $r['error_code'],
			'is_read'    => ! empty( $r['read_at'] ),
			'read_at'    => (string) ( $r['read_at'] ?? '' ),
			'created_at' => (string) $r['created_at'],
			'sent_at'    => (string) $r['sent_at'],
		); }
	public function safe_audit( array $r ) {
		return array(
			'id'            => (int) $r['id'],
			'actor_user_id' => (int) $r['actor_user_id'],
			'customer_id'   => (int) $r['customer_id'],
			'event_type'    => (string) $r['event_type'],
			'object_type'   => (string) $r['object_type'],
			'object_id'     => (string) $r['object_id'],
			'request_id'    => (string) $r['request_id'],
			'created_at'    => (string) $r['created_at'],
		); }
}
