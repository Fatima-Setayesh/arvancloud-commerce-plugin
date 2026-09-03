<?php
/**
 * Versioned, non-destructive database migrations.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run database migrations.
 *
 * The version option is only advanced after all required tables and critical
 * integer-money columns can be observed in the database.
 *
 * @return bool True when the required schema is available.
 */
function arvan_reseller_run_migrations() {
	$current_version  = (string) get_option( 'arvan_reseller_db_version', '0.0.0' );
	$required_version = defined( 'ARVAN_RESELLER_DB_VERSION' ) ? ARVAN_RESELLER_DB_VERSION : ARVAN_RESELLER_VERSION;
	arvan_reseller_migrate_notification_setting();

	if ( version_compare( $current_version, $required_version, '>=' ) ) {
		return true;
	}

	require_once ARVAN_RESELLER_PATH . 'database/schema.php';

	arvan_reseller_prepare_legacy_orders();
	arvan_reseller_create_tables();
	arvan_reseller_finalize_legacy_indexes();
	arvan_reseller_migrate_legacy_financial_data();

	if ( ! arvan_reseller_schema_is_current() ) {
		return false;
	}

	update_option( 'arvan_reseller_db_version', $required_version, false );

	return true;
}

/** Preserve the legacy email preference while making its scope explicit. */
function arvan_reseller_migrate_notification_setting() {
	$settings = get_option( 'arvan_reseller_settings', array() );
	if ( ! is_array( $settings ) || array_key_exists( 'email_notifications_enabled', $settings ) ) {
		return;
	}
	$settings['email_notifications_enabled'] = ! isset( $settings['notification_enabled'] ) || ! empty( $settings['notification_enabled'] ) ? 1 : 0;
	update_option( 'arvan_reseller_settings', $settings, false );
}

/**
 * Replace legacy uniqueness rules after their composite successors exist.
 *
 * @return void
 */
function arvan_reseller_finalize_legacy_indexes() {
	global $wpdb;

	$wallets   = $wpdb->prefix . 'arvan_reseller_wallets';
	$orders    = $wpdb->prefix . 'arvan_reseller_orders';
	$resources = $wpdb->prefix . 'arvan_reseller_resources';

	if ( arvan_reseller_index_exists( $wallets, 'customer_currency' ) && arvan_reseller_index_exists( $wallets, 'customer_id' ) ) {
		$wpdb->query( "ALTER TABLE {$wallets} DROP INDEX customer_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	if ( arvan_reseller_index_exists( $resources, 'product_resource_region' ) && arvan_reseller_index_exists( $resources, 'resource_id' ) ) {
		$wpdb->query( "ALTER TABLE {$resources} DROP INDEX resource_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	if ( arvan_reseller_index_exists( $orders, 'order_reference' ) && ! arvan_reseller_index_is_unique( $orders, 'order_reference' ) ) {
		$wpdb->query( "ALTER TABLE {$orders} DROP INDEX order_reference, ADD UNIQUE KEY order_reference (order_reference)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

/**
 * Make legacy order references safe before dbDelta adds unique indexes.
 *
 * Existing rows are retained. Only blank or duplicate references are given a
 * deterministic legacy reference, and each row receives an idempotency key.
 *
 * @return void
 */
function arvan_reseller_prepare_legacy_orders() {
	global $wpdb;

	$table = $wpdb->prefix . 'arvan_reseller_orders';

	if ( ! arvan_reseller_table_exists( $table ) ) {
		return;
	}

	if ( ! arvan_reseller_column_exists( $table, 'idempotency_key' ) ) {
		$wpdb->query( "ALTER TABLE {$table} ADD idempotency_key char(64) NULL AFTER order_reference" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	if ( ! arvan_reseller_column_exists( $table, 'resource_record_id' ) ) {
		$wpdb->query( "ALTER TABLE {$table} ADD resource_record_id bigint(20) unsigned NULL AFTER status" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	if ( ! arvan_reseller_column_exists( $table, 'region' ) ) {
		$wpdb->query( "ALTER TABLE {$table} ADD region varchar(100) NOT NULL DEFAULT '' AFTER resource_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	$wpdb->query(
		"UPDATE {$table} AS orders_table
		INNER JOIN (
			SELECT order_reference
			FROM {$table}
			WHERE order_reference <> ''
			GROUP BY order_reference
			HAVING COUNT(*) > 1
		) AS duplicates ON duplicates.order_reference = orders_table.order_reference
		SET orders_table.order_reference = CONCAT(LEFT(orders_table.order_reference, 70), '-legacy-', orders_table.id)"
	); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$wpdb->query( "UPDATE {$table} SET order_reference = CONCAT('legacy-order-', id) WHERE order_reference = ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "UPDATE {$table} SET idempotency_key = SHA2(CONCAT('legacy-order:', id, ':', order_reference), 256) WHERE idempotency_key IS NULL OR idempotency_key = ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Backfill authoritative integer amounts and copy immutable legacy records.
 *
 * Legacy tables/columns are deliberately not dropped. They become read-only
 * historical data after this one-way copy.
 *
 * @return void
 */
function arvan_reseller_migrate_legacy_financial_data() {
	global $wpdb;

	$scale              = defined( 'ARVAN_RESELLER_MONEY_SCALE' ) ? absint( ARVAN_RESELLER_MONEY_SCALE ) : 10000;
	$wallets_table      = $wpdb->prefix . 'arvan_reseller_wallets';
	$legacy_ledger      = $wpdb->prefix . 'arvan_reseller_transactions';
	$ledger_table       = $wpdb->prefix . 'arvan_reseller_wallet_transactions';
	$legacy_usage_table = $wpdb->prefix . 'arvan_reseller_usage_logs';
	$usage_table        = $wpdb->prefix . 'arvan_reseller_usage_records';
	$resources_table    = $wpdb->prefix . 'arvan_reseller_resources';

	if ( arvan_reseller_column_exists( $wallets_table, 'balance' ) ) {
		$wpdb->query( "UPDATE {$wallets_table} SET balance_minor = GREATEST(0, ROUND(balance * {$scale}))" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	if ( arvan_reseller_column_exists( $wallets_table, 'threshold' ) ) {
		$wpdb->query( "UPDATE {$wallets_table} SET threshold_minor = GREATEST(0, ROUND(threshold * {$scale}))" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	if ( arvan_reseller_table_exists( $legacy_ledger ) && arvan_reseller_table_exists( $ledger_table ) ) {
		$wpdb->query(
			"INSERT IGNORE INTO {$ledger_table}
				(wallet_id, customer_id, transaction_type, amount_minor, balance_before_minor, balance_after_minor, currency, reference_type, reference_id, idempotency_key, description, metadata, created_at)
			SELECT wallets.id, legacy_rows.customer_id, legacy_rows.transaction_type,
				GREATEST(0, ROUND(ABS(legacy_rows.amount) * {$scale})),
				GREATEST(0, ROUND(legacy_rows.balance_before * {$scale})),
				GREATEST(0, ROUND(legacy_rows.balance_after * {$scale})),
				wallets.currency,
				COALESCE(NULLIF(legacy_rows.reference_type, ''), 'legacy'),
				COALESCE(NULLIF(legacy_rows.reference_id, ''), CONCAT('legacy-', legacy_rows.id)),
				SHA2(CONCAT('legacy-wallet-transaction:', legacy_rows.id), 256),
				legacy_rows.description,
				JSON_OBJECT('legacy_transaction_id', legacy_rows.id),
				legacy_rows.created_at
			FROM {$legacy_ledger} AS legacy_rows
			INNER JOIN {$wallets_table} AS wallets ON wallets.customer_id = legacy_rows.customer_id
			ORDER BY legacy_rows.id ASC"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	if ( arvan_reseller_table_exists( $legacy_usage_table ) && arvan_reseller_table_exists( $usage_table ) ) {
		$wpdb->query(
			"INSERT IGNORE INTO {$usage_table}
				(customer_id, resource_record_id, resource_id, usage_quantity_scaled, quantity_scale, unit, usage_start, usage_end, base_cost_minor, reseller_share_minor, total_charge_minor, charged_minor, uncovered_minor, currency, billing_reference, api_payload, calculated_at)
			SELECT legacy_usage.customer_id, resources.id, legacy_usage.resource_id,
				GREATEST(0, ROUND(legacy_usage.usage_amount * {$scale})), {$scale}, legacy_usage.unit,
				legacy_usage.usage_start, legacy_usage.usage_end,
				GREATEST(0, ROUND((legacy_usage.cost - legacy_usage.reseller_share) * {$scale})),
				GREATEST(0, ROUND(legacy_usage.reseller_share * {$scale})),
				GREATEST(0, ROUND(legacy_usage.cost * {$scale})),
				GREATEST(0, ROUND(legacy_usage.cost * {$scale})), 0, 'IRR',
				COALESCE(NULLIF(legacy_usage.billing_reference, ''), CONCAT('legacy-usage-', legacy_usage.id)),
				legacy_usage.api_payload, legacy_usage.calculated_at
			FROM {$legacy_usage_table} AS legacy_usage
			INNER JOIN {$resources_table} AS resources ON resources.resource_id = legacy_usage.resource_id
			ORDER BY legacy_usage.id ASC"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

/**
 * Verify required tables and critical integer-money columns.
 *
 * @return bool
 */
function arvan_reseller_schema_is_current() {
	global $wpdb;

	$required = array(
		'wallets'             => array( 'balance_minor', 'threshold_minor', 'currency', 'low_balance_notified' ),
		'wallet_transactions' => array( 'amount_minor', 'idempotency_key', 'balance_after_minor' ),
		'payments'            => array( 'amount_minor', 'idempotency_key', 'customer_id', 'expires_at' ),
		'orders'              => array( 'idempotency_key', 'resource_record_id', 'customer_id', 'recovery_required' ),
		'resources'           => array( 'order_id', 'region', 'customer_id', 'hourly_price_minor', 'currency', 'next_retry_at', 'suspended_at' ),
		'usage_records'       => array( 'total_charge_minor', 'charged_minor', 'uncovered_minor' ),
		'invoices'            => array( 'total_minor', 'customer_id' ),
		'settlements'         => array( 'settlement_reference', 'base_cost_minor', 'adapter' ),
		'notifications'       => array( 'event_key', 'customer_id', 'read_at' ),
		'audit_logs'          => array( 'event_type', 'created_at' ),
	);

	foreach ( $required as $suffix => $columns ) {
		$table = $wpdb->prefix . 'arvan_reseller_' . $suffix;

		if ( ! arvan_reseller_table_exists( $table ) ) {
			return false;
		}

		foreach ( $columns as $column ) {
			if ( ! arvan_reseller_column_exists( $table, $column ) ) {
				return false;
			}
		}
	}

	$required_unique_indexes = array(
		'wallets'             => array( 'customer_currency' ),
		'wallet_transactions' => array( 'idempotency_key' ),
		'payments'            => array( 'payment_reference', 'idempotency_key' ),
		'orders'              => array( 'order_reference', 'idempotency_key' ),
		'resources'           => array( 'product_resource_region', 'order_id' ),
		'usage_records'       => array( 'billing_reference', 'resource_window' ),
		'invoices'            => array( 'invoice_reference', 'customer_period' ),
		'settlements'         => array( 'settlement_reference', 'settlement_period' ),
		'notifications'       => array( 'event_key' ),
	);

	foreach ( $required_unique_indexes as $suffix => $indexes ) {
		$table = $wpdb->prefix . 'arvan_reseller_' . $suffix;

		foreach ( $indexes as $index ) {
			if ( ! arvan_reseller_index_is_unique( $table, $index ) ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Check table existence.
 *
 * @param string $table Fully-qualified plugin table name.
 * @return bool
 */
function arvan_reseller_table_exists( $table ) {
	global $wpdb;

	return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
}

/**
 * Check column existence on a controlled plugin table.
 *
 * @param string $table Fully-qualified plugin table name.
 * @param string $column Column name.
 * @return bool
 */
function arvan_reseller_column_exists( $table, $column ) {
	global $wpdb;

	if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $column ) ) {
		return false;
	}

	return null !== $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Check an index on a controlled plugin table.
 *
 * @param string $table Table name.
 * @param string $index Index name.
 * @return bool
 */
function arvan_reseller_index_exists( $table, $index ) {
	global $wpdb;

	if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $index ) ) {
		return false;
	}

	return null !== $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $index ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Check whether a controlled index is unique.
 *
 * @param string $table Table name.
 * @param string $index Index name.
 * @return bool
 */
function arvan_reseller_index_is_unique( $table, $index ) {
	global $wpdb;

	if ( ! arvan_reseller_index_exists( $table, $index ) ) {
		return false;
	}

	$row = $wpdb->get_row( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $index ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return is_array( $row ) && isset( $row['Non_unique'] ) && 0 === (int) $row['Non_unique'];
}
