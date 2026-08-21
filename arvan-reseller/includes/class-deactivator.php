<?php
/**
 * Plugin deactivator.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::clear_scheduled_tasks();

		flush_rewrite_rules();
	}

	/**
	 * Remove scheduled cron events.
	 *
	 * @return void
	 */
	private static function clear_scheduled_tasks() {
		wp_clear_scheduled_hook( 'arvan_reseller_usage_sync' );
		delete_transient( 'arvan_reseller_usage_sync_lock' );
	}
}
