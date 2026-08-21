<?php
/**
 * Database migration runner.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run database migrations.
 *
 * @return void
 */
function arvan_reseller_run_migrations() {
	$current_version  = (string) get_option( 'arvan_reseller_db_version', '0.0.0' );
	$required_version = defined( 'ARVAN_RESELLER_DB_VERSION' ) ? ARVAN_RESELLER_DB_VERSION : ARVAN_RESELLER_VERSION;

	if ( version_compare( $current_version, $required_version, '>=' ) ) {
		return;
	}

	require_once ARVAN_RESELLER_PATH . 'database/schema.php';

	arvan_reseller_create_tables();

	update_option( 'arvan_reseller_db_version', $required_version, false );
}
