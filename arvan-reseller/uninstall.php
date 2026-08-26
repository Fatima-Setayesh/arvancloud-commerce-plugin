<?php
/** Safe uninstall: financial/customer data is retained unless explicitly opted in. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; }

global $wpdb;

foreach ( array( 'arvan_reseller_usage_sync', 'arvan_reseller_reconciliation', 'arvan_reseller_settlement' ) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}
foreach ( array( 'arvan_reseller_usage_sync_lock', 'arvan_reseller_reconciliation_lock', 'arvan_reseller_settlement_lock' ) as $lock ) {
	delete_option( $lock );
}

$rate_pattern = $wpdb->esc_like( '_transient_arvan_reseller_rate_' ) . '%';
$rate_timeout = $wpdb->esc_like( '_transient_timeout_arvan_reseller_rate_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $rate_pattern, $rate_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Credentials, transient health state, and capabilities are never retained after uninstall.
delete_option( 'arvan_reseller_api_key' );
delete_option( 'arvan_reseller_cron_health' );
$administrator_role = get_role( 'administrator' );
if ( $administrator_role ) {
	$administrator_role->remove_cap( 'manage_arvan_reseller' );
}

$settings = get_option( 'arvan_reseller_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return; }

$tables = array( 'audit_logs', 'notifications', 'settlements', 'invoices', 'usage_records', 'resources', 'orders', 'payments', 'wallet_transactions', 'wallets' );
foreach ( $tables as $suffix ) {
	$table = $wpdb->prefix . 'arvan_reseller_' . $suffix;
	if ( preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
delete_option( 'arvan_reseller_settings' );
delete_option( 'arvan_reseller_db_version' );
delete_option( 'arvan_reseller_mock_resources' );
