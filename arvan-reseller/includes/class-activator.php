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

		arvan_reseller_run_migrations();
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
			'version'                => ARVAN_RESELLER_VERSION,
			'api_base_url'           => '',
			'api_timeout'            => 20,
			'reseller_share_percent' => 0,
			'usage_endpoint'         => '',
			'resource_create_endpoint' => '',
			'resource_get_endpoint'  => '',
			'resource_suspend_endpoint' => '',
			'resource_terminate_endpoint' => '',
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
	}
}
