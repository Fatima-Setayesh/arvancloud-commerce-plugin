<?php
/**
 * Database schema definitions.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create or update plugin tables.
 *
 * @return void
 */
function arvan_reseller_create_tables() {
	global $wpdb;

	$charset_collate   = $wpdb->get_charset_collate();
	$wallets_table     = $wpdb->prefix . 'arvan_reseller_wallets';
	$transactions_table = $wpdb->prefix . 'arvan_reseller_transactions';
	$resources_table   = $wpdb->prefix . 'arvan_reseller_resources';
	$usage_logs_table  = $wpdb->prefix . 'arvan_reseller_usage_logs';
	$orders_table      = $wpdb->prefix . 'arvan_reseller_orders';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$queries   = array();
	$queries[] = "CREATE TABLE {$wallets_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		balance decimal(18,4) NOT NULL DEFAULT 0.0000,
		threshold decimal(18,4) NOT NULL DEFAULT 0.0000,
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY customer_id (customer_id),
		KEY status (status)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$transactions_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		transaction_type varchar(30) NOT NULL,
		amount decimal(18,4) NOT NULL,
		balance_before decimal(18,4) NOT NULL DEFAULT 0.0000,
		balance_after decimal(18,4) NOT NULL DEFAULT 0.0000,
		reference_type varchar(50) NOT NULL DEFAULT '',
		reference_id varchar(191) NOT NULL DEFAULT '',
		description text NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY customer_id (customer_id),
		KEY transaction_type (transaction_type),
		KEY reference_lookup (reference_type, reference_id),
		KEY customer_created (customer_id, created_at)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$resources_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		resource_id varchar(191) NOT NULL,
		product_type varchar(50) NOT NULL,
		status varchar(30) NOT NULL DEFAULT 'pending',
		remote_payload longtext NULL,
		last_synced_at datetime NULL,
		last_billed_at datetime NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY resource_id (resource_id),
		KEY customer_id (customer_id),
		KEY product_type (product_type),
		KEY status (status),
		KEY customer_status (customer_id, status)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$usage_logs_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		resource_id varchar(191) NOT NULL,
		usage_amount decimal(18,4) NOT NULL,
		unit varchar(30) NOT NULL DEFAULT '',
		usage_start datetime NOT NULL,
		usage_end datetime NOT NULL,
		cost decimal(18,4) NOT NULL,
		reseller_share decimal(18,4) NOT NULL DEFAULT 0.0000,
		billing_reference varchar(191) NOT NULL DEFAULT '',
		api_payload longtext NULL,
		calculated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY billing_reference (billing_reference),
		KEY customer_id (customer_id),
		KEY resource_id (resource_id),
		KEY usage_window (resource_id, usage_start, usage_end),
		KEY calculated_at (calculated_at)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$orders_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		product_type varchar(50) NOT NULL,
		status varchar(30) NOT NULL DEFAULT 'pending',
		resource_id varchar(191) NOT NULL DEFAULT '',
		order_reference varchar(191) NOT NULL DEFAULT '',
		details longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY customer_id (customer_id),
		KEY status (status),
		KEY order_reference (order_reference),
		KEY resource_id (resource_id)
	) {$charset_collate};";

	foreach ( $queries as $query ) {
		dbDelta( $query );
	}
}
