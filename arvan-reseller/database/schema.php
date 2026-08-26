<?php
/**
 * Database schema definitions.
 *
 * All timestamps are UTC. Persisted money values use integer minor units at
 * ARVAN_RESELLER_MONEY_SCALE (10,000 minor units per currency unit).
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create or update plugin tables with dbDelta-compatible statements.
 *
 * @return void
 */
function arvan_reseller_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix . 'arvan_reseller_';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$queries = array();

	$queries[] = "CREATE TABLE {$prefix}wallets (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		currency char(3) NOT NULL DEFAULT 'IRR',
		balance_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		threshold_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		low_balance_notified tinyint(1) unsigned NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY customer_currency (customer_id, currency),
		KEY status (status),
		KEY customer_status (customer_id, status)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}wallet_transactions (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		wallet_id bigint(20) unsigned NOT NULL,
		customer_id bigint(20) unsigned NOT NULL,
		transaction_type varchar(30) NOT NULL,
		amount_minor bigint(20) unsigned NOT NULL,
		balance_before_minor bigint(20) unsigned NOT NULL,
		balance_after_minor bigint(20) unsigned NOT NULL,
		currency char(3) NOT NULL DEFAULT 'IRR',
		reference_type varchar(50) NOT NULL,
		reference_id varchar(191) NOT NULL,
		idempotency_key char(64) NOT NULL,
		description text NULL,
		metadata longtext NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY idempotency_key (idempotency_key),
		KEY wallet_reference (wallet_id, reference_type, reference_id, transaction_type),
		KEY customer_created (customer_id, created_at),
		KEY wallet_created (wallet_id, created_at),
		KEY transaction_type (transaction_type)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}payments (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		wallet_id bigint(20) unsigned NOT NULL,
		payment_reference varchar(100) NOT NULL,
		idempotency_key char(64) NOT NULL,
		amount_minor bigint(20) unsigned NOT NULL,
		currency char(3) NOT NULL DEFAULT 'IRR',
		status varchar(20) NOT NULL DEFAULT 'pending',
		provider varchar(30) NOT NULL DEFAULT 'mock',
		provider_reference varchar(191) NOT NULL DEFAULT '',
		metadata longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		completed_at datetime NULL,
		expires_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY payment_reference (payment_reference),
		UNIQUE KEY idempotency_key (idempotency_key),
		KEY customer_status (customer_id, status),
		KEY wallet_id (wallet_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}orders (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		product_type varchar(50) NOT NULL,
		status varchar(30) NOT NULL DEFAULT 'pending',
		resource_record_id bigint(20) unsigned NULL,
		resource_id varchar(191) NOT NULL DEFAULT '',
		region varchar(100) NOT NULL DEFAULT '',
		order_reference varchar(100) NOT NULL,
		idempotency_key char(64) NOT NULL,
		recovery_required tinyint(1) unsigned NOT NULL DEFAULT 0,
		failure_code varchar(100) NOT NULL DEFAULT '',
		details longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY order_reference (order_reference),
		UNIQUE KEY idempotency_key (idempotency_key),
		KEY customer_status (customer_id, status),
		KEY resource_record_id (resource_record_id),
		KEY resource_lookup (product_type, region, resource_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}resources (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		order_id bigint(20) unsigned NULL,
		resource_id varchar(191) NOT NULL,
		product_type varchar(50) NOT NULL,
		region varchar(100) NOT NULL DEFAULT '',
		status varchar(30) NOT NULL DEFAULT 'pending',
		remote_status varchar(30) NOT NULL DEFAULT 'unknown',
		hourly_price_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'IRR',
		remote_payload longtext NULL,
		sync_failure_count int(10) unsigned NOT NULL DEFAULT 0,
		next_retry_at datetime NULL,
		last_error_code varchar(100) NOT NULL DEFAULT '',
		last_synced_at datetime NULL,
		last_billed_at datetime NULL,
		suspended_at datetime NULL,
		terminated_at datetime NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY product_resource_region (product_type, resource_id, region),
		UNIQUE KEY order_id (order_id),
		KEY customer_status (customer_id, status),
		KEY remote_status (remote_status),
		KEY last_billed_at (last_billed_at),
		KEY retry_queue (next_retry_at, status),
		KEY customer_suspended (customer_id, suspended_at)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}usage_records (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		resource_record_id bigint(20) unsigned NOT NULL,
		resource_id varchar(191) NOT NULL,
		usage_quantity_scaled bigint(20) unsigned NOT NULL DEFAULT 0,
		quantity_scale int(10) unsigned NOT NULL DEFAULT 10000,
		unit varchar(30) NOT NULL DEFAULT '',
		usage_start datetime NOT NULL,
		usage_end datetime NOT NULL,
		base_cost_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		reseller_share_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		total_charge_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		charged_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		uncovered_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'IRR',
		billing_reference varchar(191) NOT NULL,
		api_payload longtext NULL,
		calculated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY billing_reference (billing_reference),
		UNIQUE KEY resource_window (resource_record_id, usage_start, usage_end),
		KEY customer_calculated (customer_id, calculated_at),
		KEY resource_id (resource_id),
		KEY uncovered_minor (uncovered_minor)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}invoices (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		invoice_reference varchar(100) NOT NULL,
		period_start datetime NOT NULL,
		period_end datetime NOT NULL,
		base_cost_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		reseller_share_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		total_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'IRR',
		status varchar(20) NOT NULL DEFAULT 'draft',
		metadata longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY invoice_reference (invoice_reference),
		UNIQUE KEY customer_period (customer_id, period_start, period_end),
		KEY customer_status (customer_id, status)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}settlements (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		settlement_reference varchar(100) NOT NULL,
		period_start datetime NOT NULL,
		period_end datetime NOT NULL,
		base_cost_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		customer_charge_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		reseller_share_minor bigint(20) unsigned NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'IRR',
		status varchar(20) NOT NULL DEFAULT 'pending',
		adapter varchar(20) NOT NULL DEFAULT 'mock',
		metadata longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY settlement_reference (settlement_reference),
		UNIQUE KEY settlement_period (period_start, period_end, currency),
		KEY status (status)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}notifications (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		notification_type varchar(50) NOT NULL,
		event_key char(64) NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		channel varchar(20) NOT NULL DEFAULT 'email',
		payload longtext NULL,
		error_code varchar(100) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		sent_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY event_key (event_key),
		KEY customer_type (customer_id, notification_type),
		KEY status_created (status, created_at)
	) {$charset_collate};";

	$queries[] = "CREATE TABLE {$prefix}audit_logs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
		event_type varchar(100) NOT NULL,
		object_type varchar(50) NOT NULL DEFAULT '',
		object_id varchar(191) NOT NULL DEFAULT '',
		request_id varchar(100) NOT NULL DEFAULT '',
		metadata longtext NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY event_created (event_type, created_at),
		KEY customer_created (customer_id, created_at),
		KEY object_lookup (object_type, object_id),
		KEY request_id (request_id)
	) {$charset_collate};";

	foreach ( $queries as $query ) {
		dbDelta( $query );
	}
}
