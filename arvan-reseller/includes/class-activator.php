<?php
/**
 * Plugin activator.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once ARVAN_RESELLER_PATH . 'database/migrations.php';

		if ( ! arvan_reseller_run_migrations() ) {
			wp_die( esc_html__( 'Arvan Reseller could not complete its database migration. No background jobs were scheduled.', 'arvan-reseller' ) );
		}
		self::add_capabilities();
		self::create_default_options();
		self::schedule_events();

		flush_rewrite_rules();
	}

	/**
	 * Create plugin defaults.
	 *
	 * @return void
	 */
	private static function create_default_options() {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$defaults = array(
			'version'                  => ARVAN_RESELLER_VERSION,
			'mode'                     => 'mock',
			'region'                   => 'ir-thr-mock',
			'availability_zone'        => 'mock-zone-1',
			'api_timeout'              => 20,
			'product_type'             => 'cloud_server',
			'currency'                 => 'IRR',
			'reseller_share_percent'   => 0,
			'default_wallet_threshold' => '0.0000',
			'minimum_topup'            => '1.0000',
			'maximum_topup'            => '1000000000.0000',
			'termination_policy'       => 'disabled',
			'termination_grace_hours'  => 72,
			'suspend_policy'           => 'zero_balance',
			'notification_enabled'     => 1,
			'delete_data_on_uninstall' => 0,
		);

		update_option( 'arvan_reseller_settings', wp_parse_args( $settings, $defaults ), false );
	}

	/**
	 * Schedule recurring jobs.
	 *
	 * @return void
	 */
	private static function schedule_events() {
		if ( ! wp_next_scheduled( 'arvan_reseller_usage_sync' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'arvan_reseller_usage_sync' );
		}

		if ( ! wp_next_scheduled( 'arvan_reseller_reconciliation' ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'arvan_reseller_reconciliation' );
		}

		if ( ! wp_next_scheduled( 'arvan_reseller_settlement' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 00:15 UTC' ), 'daily', 'arvan_reseller_settlement' );
		}
	}

	/** Add the least-privilege plugin capability to administrators. */
	private static function add_capabilities() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'manage_arvan_reseller' ); }
	}
}
