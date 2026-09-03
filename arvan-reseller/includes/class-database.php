<?php
/**
 * Database abstraction for plugin tables.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Database {

	/** @var wpdb */
	private $wpdb;

	/** @var bool */
	private $transaction_active = false;

	/** @var array<string, string> */
	private $tables = array(
		'wallets'             => 'arvan_reseller_wallets',
		'wallet_transactions' => 'arvan_reseller_wallet_transactions',
		'transactions'        => 'arvan_reseller_wallet_transactions',
		'payments'            => 'arvan_reseller_payments',
		'orders'              => 'arvan_reseller_orders',
		'resources'           => 'arvan_reseller_resources',
		'usage_records'       => 'arvan_reseller_usage_records',
		'usage_logs'          => 'arvan_reseller_usage_records',
		'invoices'            => 'arvan_reseller_invoices',
		'settlements'         => 'arvan_reseller_settlements',
		'notifications'       => 'arvan_reseller_notifications',
		'audit_logs'          => 'arvan_reseller_audit_logs',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
	}

	/**
	 * Get a fully qualified allowlisted table name.
	 *
	 * @param string $table Logical table key.
	 * @return string
	 */
	public function get_table_name( $table ) {
		return isset( $this->tables[ $table ] ) ? $this->wpdb->prefix . $this->tables[ $table ] : '';
	}

	/**
	 * Start an InnoDB transaction.
	 *
	 * @phpstan-impure
	 * @return bool
	 */
	public function begin_transaction() {
		if ( $this->transaction_active ) {
			return true;
		}

		$result = $this->wpdb->query( 'START TRANSACTION' );

		if ( false === $result ) {
			return false;
		}

		$this->transaction_active = true;

		return true;
	}

	/**
	 * Commit the active transaction.
	 *
	 * @phpstan-impure
	 * @return bool
	 */
	public function commit() {
		if ( ! $this->transaction_active ) {
			return true;
		}

		$result = $this->wpdb->query( 'COMMIT' );

		if ( false === $result ) {
			$this->wpdb->query( 'ROLLBACK' );
			$this->transaction_active = false;

			return false;
		}

		$this->transaction_active = false;

		return true;
	}

	/**
	 * Roll back the active transaction.
	 *
	 * @phpstan-impure
	 * @return bool
	 */
	public function rollback() {
		if ( ! $this->transaction_active ) {
			return true;
		}

		$result                   = $this->wpdb->query( 'ROLLBACK' );
		$this->transaction_active = false;

		return false !== $result;
	}

	/**
	 * Report whether this service owns an open transaction.
	 *
	 * @return bool
	 */
	public function in_transaction() {
		return $this->transaction_active;
	}

	/**
	 * Insert a row.
	 *
	 * @param string $table Logical table key.
	 * @param array  $data Insert data.
	 * @param array  $format Optional data formats.
	 * @return int|false
	 */
	public function insert( $table, array $data, array $format = array() ) {
		$table_name = $this->get_table_name( $table );

		if ( '' === $table_name || ! $this->has_valid_status( $table, $data ) ) {
			return false;
		}

		$result = empty( $format ) ? $this->wpdb->insert( $table_name, $data ) : $this->wpdb->insert( $table_name, $data, $format );

		return false === $result ? false : (int) $this->wpdb->insert_id;
	}

	/**
	 * Update mutable rows. Immutable ledgers are never updateable here.
	 *
	 * @param string $table Logical table key.
	 * @param array  $data Update data.
	 * @param array  $where Where clauses.
	 * @param array  $format Optional data formats.
	 * @param array  $where_format Optional where formats.
	 * @return int|false
	 */
	public function update( $table, array $data, array $where, array $format = array(), array $where_format = array() ) {
		if ( in_array( $table, array( 'transactions', 'wallet_transactions' ), true ) ) {
			return false;
		}

		$table_name = $this->get_table_name( $table );

		if ( '' === $table_name || ! $this->has_valid_status( $table, $data ) ) {
			return false;
		}

		return $this->wpdb->update( $table_name, $data, $where, $format, $where_format );
	}

	/**
	 * Get a single row by equality conditions.
	 *
	 * @param string $table Logical table key.
	 * @param array  $where Where clauses.
	 * @return array|null
	 */
	public function get_row_by( $table, array $where ) {
		$table_name = $this->get_table_name( $table );

		if ( '' === $table_name || empty( $where ) ) {
			return null;
		}

		$args  = array();
		$query = "SELECT * FROM {$table_name}" . $this->build_where_clause( $where, $args ) . ' LIMIT 1';
		$row   = $this->wpdb->get_row( $this->wpdb->prepare( $query, $args ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get multiple rows by equality conditions.
	 *
	 * @param string $table Logical table key.
	 * @param array  $where Conditions.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 * @param string $order_by Allowlisted order column.
	 * @param string $order Sort direction.
	 * @return array
	 */
	public function get_results_by( $table, array $where = array(), $limit = 100, $offset = 0, $order_by = 'id', $order = 'DESC' ) {
		$table_name = $this->get_table_name( $table );

		if ( '' === $table_name ) {
			return array();
		}

		$allowed_order_by = array( 'id', 'customer_id', 'created_at', 'updated_at', 'calculated_at', 'last_synced_at', 'last_billed_at', 'usage_start', 'usage_end' );
		$order_by         = in_array( $order_by, $allowed_order_by, true ) ? $order_by : 'id';
		$order            = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$limit            = max( 1, absint( $limit ) );
		$offset           = max( 0, absint( $offset ) );
		$args             = array();
		$query            = "SELECT * FROM {$table_name}";

		if ( ! empty( $where ) ) {
			$query .= $this->build_where_clause( $where, $args );
		}

		$query .= " ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
		$args[] = $limit;
		$args[] = $offset;
		$rows   = $this->wpdb->get_results( $this->wpdb->prepare( $query, $args ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get a bounded page with an allowlisted multi-column search.
	 *
	 * @param string $table Logical table key.
	 * @param array  $where Equality conditions.
	 * @param string $search Search term.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 * @return array
	 */
	public function search_results( $table, array $where, $search, $limit = 100, $offset = 0 ) {
		$table_name = $this->get_table_name( $table );
		$columns    = array(
			'wallets'       => array( 'customer_id', 'currency', 'status' ),
			'payments'      => array( 'payment_reference', 'customer_id', 'status', 'provider' ),
			'orders'        => array( 'order_reference', 'customer_id', 'resource_id', 'status', 'failure_code', 'region' ),
			'resources'     => array( 'resource_id', 'customer_id', 'region', 'status', 'remote_status' ),
			'usage_records' => array( 'resource_id', 'customer_id', 'billing_reference', 'unit' ),
			'settlements'   => array( 'settlement_reference', 'status', 'currency', 'adapter' ),
			'audit_logs'    => array( 'event_type', 'object_type', 'object_id', 'request_id', 'actor_user_id', 'customer_id' ),
		);

		if ( '' === $table_name || ! isset( $columns[ $table ] ) || '' === trim( (string) $search ) ) {
			return $this->get_results_by( $table, $where, $limit, $offset );
		}

		$args   = array();
		$query  = "SELECT * FROM {$table_name}";
		$query .= empty( $where ) ? '' : $this->build_where_clause( $where, $args );
		$like   = '%' . $this->wpdb->esc_like( trim( (string) $search ) ) . '%';
		$parts  = array();
		foreach ( $columns[ $table ] as $column ) {
			$parts[] = "{$column} LIKE %s";
			$args[]  = $like;
		}
		$query .= ( empty( $where ) ? ' WHERE ' : ' AND ' ) . '(' . implode( ' OR ', $parts ) . ') ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = max( 1, absint( $limit ) );
		$args[] = max( 0, absint( $offset ) );
		$rows   = $this->wpdb->get_results( $this->wpdb->prepare( $query, $args ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create or retrieve a customer wallet without a check-then-insert race.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $currency ISO-style currency code.
	 * @return array|null
	 */
	public function ensure_wallet( $customer_id, $currency = 'IRR' ) {
		$customer_id = absint( $customer_id );
		$currency    = $this->normalize_currency( $currency );

		if ( $customer_id <= 0 ) {
			return null;
		}

		$table           = $this->get_table_name( 'wallets' );
		$now             = current_time( 'mysql', true );
		$settings        = get_option( 'arvan_reseller_settings', array() );
		$threshold_minor = Arvan_Reseller_Money::to_minor( isset( $settings['default_wallet_threshold'] ) ? (string) $settings['default_wallet_threshold'] : '0' );
		$threshold_minor = is_wp_error( $threshold_minor ) || $threshold_minor < 0 ? 0 : $threshold_minor;
		$sql             = $this->wpdb->prepare(
			"INSERT IGNORE INTO {$table} (customer_id, currency, balance_minor, threshold_minor, status, created_at, updated_at) VALUES (%d, %s, 0, %d, %s, %s, %s)",
			$customer_id,
			$currency,
			$threshold_minor,
			'active',
			$now,
			$now
		);

		if ( false === $this->wpdb->query( $sql ) ) {
			return null;
		}

		return $this->get_wallet_by_customer_id( $customer_id, $currency );
	}

	/**
	 * Fetch a wallet by owner and currency.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $currency Currency.
	 * @return array|null
	 */
	public function get_wallet_by_customer_id( $customer_id, $currency = 'IRR' ) {
		return $this->get_row_by(
			'wallets',
			array(
				'customer_id' => absint( $customer_id ),
				'currency'    => $this->normalize_currency( $currency ),
			)
		);
	}

	/**
	 * Lock and return a wallet row for the active transaction.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $currency Currency.
	 * @return array|null
	 */
	public function lock_wallet( $customer_id, $currency = 'IRR' ) {
		if ( ! $this->transaction_active || null === $this->ensure_wallet( $customer_id, $currency ) ) {
			return null;
		}

		$table = $this->get_table_name( 'wallets' );
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$table} WHERE customer_id = %d AND currency = %s LIMIT 1 FOR UPDATE",
			absint( $customer_id ),
			$this->normalize_currency( $currency )
		);
		$row   = $this->wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update a locked wallet using its previous value as an extra guard.
	 *
	 * @param int $wallet_id Wallet row ID.
	 * @param int $before_minor Expected current balance.
	 * @param int $after_minor New balance.
	 * @return bool
	 */
	public function update_locked_wallet_balance( $wallet_id, $before_minor, $after_minor ) {
		if ( ! $this->transaction_active || $after_minor < 0 ) {
			return false;
		}

		$table = $this->get_table_name( 'wallets' );
		$query = $this->wpdb->prepare(
			"UPDATE {$table} SET balance_minor = %d, low_balance_notified = CASE WHEN %d > threshold_minor THEN 0 ELSE low_balance_notified END, updated_at = %s WHERE id = %d AND balance_minor = %d",
			(int) $after_minor,
			(int) $after_minor,
			current_time( 'mysql', true ),
			absint( $wallet_id ),
			(int) $before_minor
		);

		return 1 === (int) $this->wpdb->query( $query );
	}

	/** @return bool */
	public function mark_wallet_low_balance_notified( $wallet_id ) {
		return false !== $this->update(
			'wallets',
			array(
				'low_balance_notified' => 1,
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $wallet_id ) ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Append an immutable ledger row.
	 *
	 * @param array $data Transaction data.
	 * @return int|false
	 */
	public function create_transaction( array $data ) {
		$defaults = array(
			'wallet_id'            => 0,
			'customer_id'          => 0,
			'transaction_type'     => '',
			'amount_minor'         => 0,
			'balance_before_minor' => 0,
			'balance_after_minor'  => 0,
			'currency'             => 'IRR',
			'reference_type'       => '',
			'reference_id'         => '',
			'idempotency_key'      => '',
			'description'          => '',
			'metadata'             => '',
			'created_at'           => current_time( 'mysql', true ),
		);
		$data     = wp_parse_args( $data, $defaults );

		return $this->insert(
			'wallet_transactions',
			$data,
			array( '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Find an immutable transaction by idempotency key.
	 *
	 * @param string $idempotency_key Key.
	 * @return array|null
	 */
	public function get_transaction_by_idempotency_key( $idempotency_key ) {
		return $this->get_row_by( 'wallet_transactions', array( 'idempotency_key' => (string) $idempotency_key ) );
	}

	/**
	 * Compatibility duplicate lookup by business reference.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $reference_type Reference type.
	 * @param string $reference_id Reference ID.
	 * @param string $transaction_type Type.
	 * @return bool
	 */
	public function transaction_exists( $customer_id, $reference_type, $reference_id, $transaction_type ) {
		$table = $this->get_table_name( 'wallet_transactions' );
		$query = $this->wpdb->prepare(
			"SELECT id FROM {$table} WHERE customer_id = %d AND reference_type = %s AND reference_id = %s AND transaction_type = %s LIMIT 1",
			absint( $customer_id ),
			(string) $reference_type,
			(string) $reference_id,
			(string) $transaction_type
		);

		return (bool) $this->wpdb->get_var( $query );
	}

	/**
	 * Get customer ledger history.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_transactions_by_customer_id( $customer_id, $limit = 50, $currency = '', $offset = 0 ) {
		$where = array( 'customer_id' => absint( $customer_id ) );
		if ( '' !== (string) $currency ) {
			$where['currency'] = $this->normalize_currency( $currency );
		}
		return $this->get_results_by( 'wallet_transactions', $where, $limit, $offset );
	}

	/**
	 * Derive a wallet balance from the append-only ledger.
	 *
	 * @param int $wallet_id Wallet ID.
	 * @return int|null
	 */
	public function get_ledger_balance_minor( $wallet_id ) {
		$table = $this->get_table_name( 'wallet_transactions' );
		$query = $this->wpdb->prepare(
			"SELECT COALESCE(SUM(CASE WHEN transaction_type = 'credit' THEN amount_minor WHEN transaction_type = 'debit' THEN -amount_minor ELSE 0 END), 0) FROM {$table} WHERE wallet_id = %d",
			absint( $wallet_id )
		);
		$value = $this->wpdb->get_var( $query );

		return null === $value ? null : (int) $value;
	}

	/**
	 * Create a dedicated payment row.
	 *
	 * @param array $data Payment data.
	 * @return int|false
	 */
	public function create_payment( array $data ) {
		$defaults = array(
			'customer_id'        => 0,
			'wallet_id'          => 0,
			'payment_reference'  => '',
			'idempotency_key'    => '',
			'amount_minor'       => 0,
			'currency'           => 'IRR',
			'status'             => 'pending',
			'provider'           => 'mock',
			'provider_reference' => '',
			'metadata'           => '',
			'created_at'         => current_time( 'mysql', true ),
			'updated_at'         => current_time( 'mysql', true ),
			'completed_at'       => null,
			'expires_at'         => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
		);

		return $this->insert( 'payments', wp_parse_args( $data, $defaults ) );
	}

	/** @return array|null */
	public function get_payment_by_reference( $reference ) {
		return $this->get_row_by( 'payments', array( 'payment_reference' => (string) $reference ) );
	}

	/** @return array|null */
	public function get_payment_by_idempotency_key( $key ) {
		return $this->get_row_by( 'payments', array( 'idempotency_key' => (string) $key ) );
	}

	/**
	 * Lock a payment row before a state transition.
	 *
	 * @return array|null
	 */
	public function lock_payment_by_reference( $reference ) {
		if ( ! $this->transaction_active ) {
			return null;
		}

		$table = $this->get_table_name( 'payments' );
		$row   = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE payment_reference = %s LIMIT 1 FOR UPDATE", (string) $reference ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Transition a locked payment only from its expected state.
	 *
	 * @return bool
	 */
	public function transition_payment_status( $payment_id, $from, $to, array $extra = array() ) {
		if ( ! $this->transaction_active || ! Arvan_Reseller_Status::is_valid( 'payments', $to ) ) {
			return false;
		}

		$table   = $this->get_table_name( 'payments' );
		$data    = array_merge(
			$extra,
			array(
				'status'     => $to,
				'updated_at' => current_time( 'mysql', true ),
			)
		);
		$sets    = array();
		$args    = array();
		$allowed = array( 'status', 'updated_at', 'completed_at', 'provider_reference', 'metadata' );

		foreach ( $data as $column => $value ) {
			if ( ! in_array( $column, $allowed, true ) ) {
				continue;
			}

			$sets[] = "{$column} = %s";
			$args[] = $value;
		}

		$args[] = absint( $payment_id );
		$args[] = (string) $from;
		$query  = "UPDATE {$table} SET " . implode( ', ', $sets ) . ' WHERE id = %d AND status = %s';

		return 1 === (int) $this->wpdb->query( $this->wpdb->prepare( $query, $args ) );
	}

	/** @return array */
	public function get_payments_by_customer_id( $customer_id, $limit = 50, $offset = 0 ) {
		return $this->get_results_by( 'payments', array( 'customer_id' => absint( $customer_id ) ), $limit, $offset );
	}

	/**
	 * Append a redacted audit event.
	 *
	 * @return int|false
	 */
	public function create_audit_log( $event_type, $object_type = '', $object_id = '', array $metadata = array(), $customer_id = 0, $actor_user_id = null ) {
		$actor_user_id = null === $actor_user_id && function_exists( 'get_current_user_id' ) ? get_current_user_id() : absint( $actor_user_id );

		return $this->insert(
			'audit_logs',
			array(
				'actor_user_id' => absint( $actor_user_id ),
				'customer_id'   => absint( $customer_id ),
				'event_type'    => sanitize_key( $event_type ),
				'object_type'   => sanitize_key( $object_type ),
				'object_id'     => sanitize_text_field( (string) $object_id ),
				'request_id'    => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : '',
				'metadata'      => wp_json_encode( Arvan_Reseller_Security::redact( $metadata ) ),
				'created_at'    => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Save or update a resource mapping.
	 *
	 * @param array $data Resource data.
	 * @return int|false
	 */
	public function save_resource( array $data ) {
		$existing = ! empty( $data['resource_id'] ) ? $this->get_resource_by_arvan_id( $data['resource_id'], isset( $data['product_type'] ) ? $data['product_type'] : '', isset( $data['region'] ) ? $data['region'] : '' ) : null;
		$defaults = array(
			'customer_id'    => 0,
			'order_id'       => null,
			'resource_id'    => '',
			'product_type'   => '',
			'region'         => '',
			'status'         => 'pending',
			'remote_status'  => 'unknown',
			'currency'       => 'IRR',
			'remote_payload' => '',
			'last_synced_at' => null,
			'last_billed_at' => null,
			'created_at'     => current_time( 'mysql', true ),
			'updated_at'     => current_time( 'mysql', true ),
		);
		$data     = wp_parse_args( $data, $defaults );

		if ( null !== $existing ) {
			$mutable = $data;
			unset( $mutable['created_at'], $mutable['resource_id'] );
			$result = $this->update( 'resources', $mutable, array( 'id' => (int) $existing['id'] ) );

			return false === $result ? false : (int) $existing['id'];
		}

		return $this->insert( 'resources', $data );
	}

	/**
	 * Find a remote resource in its product/region namespace.
	 *
	 * @param string $resource_id Resource ID.
	 * @param string $product_type Optional product type.
	 * @param string $region Optional region.
	 * @return array|null
	 */
	public function get_resource_by_arvan_id( $resource_id, $product_type = '', $region = '' ) {
		$where = array( 'resource_id' => (string) $resource_id );

		if ( '' !== (string) $product_type ) {
			$where['product_type'] = (string) $product_type;
		}

		if ( '' !== (string) $region ) {
			$where['region'] = (string) $region;
		}

		return $this->get_row_by( 'resources', $where );
	}

	/** @return array */
	public function get_resources_by_customer_id( $customer_id, $limit = 100, $offset = 0 ) {
		return $this->get_results_by( 'resources', array( 'customer_id' => absint( $customer_id ) ), $limit, $offset );
	}

	/** @return array */
	public function get_billable_resources( $after_id = 0, $limit = 50 ) {
		$table = $this->get_table_name( 'resources' );
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$table} WHERE id > %d AND status IN (%s, %s, %s) AND (next_retry_at IS NULL OR next_retry_at <= %s) ORDER BY id ASC LIMIT %d",
			absint( $after_id ),
			'active',
			'provisioned',
			'suspended',
			current_time( 'mysql', true ),
			max( 1, min( 500, absint( $limit ) ) )
		);
		$rows  = $this->wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create a usage/billing record using integer financial fields.
	 *
	 * @param array $data Usage data.
	 * @return int|false
	 */
	public function create_usage_log( array $data ) {
		$defaults = array(
			'customer_id'           => 0,
			'resource_record_id'    => 0,
			'resource_id'           => '',
			'usage_quantity_scaled' => 0,
			'quantity_scale'        => Arvan_Reseller_Money::scale(),
			'unit'                  => '',
			'usage_start'           => current_time( 'mysql', true ),
			'usage_end'             => current_time( 'mysql', true ),
			'base_cost_minor'       => 0,
			'reseller_share_minor'  => 0,
			'total_charge_minor'    => 0,
			'charged_minor'         => 0,
			'uncovered_minor'       => 0,
			'currency'              => 'IRR',
			'billing_reference'     => '',
			'api_payload'           => '',
			'calculated_at'         => current_time( 'mysql', true ),
		);
		$data     = wp_parse_args( $data, $defaults );

		return $this->insert(
			'usage_records',
			$data,
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/** @return bool */
	public function usage_log_exists( $billing_reference ) {
		return null !== $this->get_row_by( 'usage_records', array( 'billing_reference' => (string) $billing_reference ) );
	}

	/**
	 * Create an order with database-level idempotency.
	 *
	 * @param array $data Order data.
	 * @return int|false
	 */
	public function create_order( array $data ) {
		$customer_id = isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : 0;
		$reference   = ! empty( $data['order_reference'] ) ? (string) $data['order_reference'] : 'order_' . wp_generate_uuid4();
		$defaults    = array(
			'customer_id'        => $customer_id,
			'product_type'       => '',
			'status'             => 'pending',
			'resource_record_id' => null,
			'resource_id'        => '',
			'region'             => '',
			'order_reference'    => $reference,
			'idempotency_key'    => hash( 'sha256', 'order|' . $customer_id . '|' . $reference ),
			'recovery_required'  => 0,
			'failure_code'       => '',
			'details'            => '',
			'created_at'         => current_time( 'mysql', true ),
			'updated_at'         => current_time( 'mysql', true ),
		);

		return $this->insert( 'orders', wp_parse_args( $data, $defaults ) );
	}

	/** @return array|null */
	public function get_order_by_idempotency_key( $key ) {
		return $this->get_row_by( 'orders', array( 'idempotency_key' => (string) $key ) );
	}

	/** @return array|null */
	public function get_order_by_reference( $reference ) {
		return $this->get_row_by( 'orders', array( 'order_reference' => (string) $reference ) );
	}

	/** @return array|null */
	public function lock_order( $order_id ) {
		if ( ! $this->transaction_active ) {
			return null;
		}

		$table = $this->get_table_name( 'orders' );
		$row   = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1 FOR UPDATE", absint( $order_id ) ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/** @return bool */
	public function transition_order_status( $order_id, $from, $to, array $extra = array() ) {
		if ( ! Arvan_Reseller_Status::is_valid( 'orders', $to ) ) {
			return false;
		}

		$table   = $this->get_table_name( 'orders' );
		$allowed = array( 'resource_record_id', 'resource_id', 'region', 'details', 'recovery_required', 'failure_code' );
		$sets    = array( 'status = %s', 'updated_at = %s' );
		$args    = array( $to, current_time( 'mysql', true ) );

		foreach ( $extra as $column => $value ) {
			if ( ! in_array( $column, $allowed, true ) ) {
				continue; }
			$sets[] = in_array( $column, array( 'resource_record_id', 'recovery_required' ), true ) ? "{$column} = %d" : "{$column} = %s";
			$args[] = $value;
		}

		$args[] = absint( $order_id );
		$args[] = (string) $from;

		// The placeholder list and replacements are both assembled from the fixed allowlist above.
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return 1 === (int) $this->wpdb->query( $this->wpdb->prepare( "UPDATE {$table} SET " . implode( ', ', $sets ) . ' WHERE id = %d AND status = %s', $args ) );
	}

	/** @return array */
	public function get_orders_requiring_recovery( $limit = 50 ) {
		return $this->get_results_by( 'orders', array( 'recovery_required' => 1 ), $limit, 0, 'id', 'ASC' );
	}

	/** @return int|false */
	public function create_notification( array $data ) {
		$defaults = array(
			'customer_id'       => 0,
			'notification_type' => '',
			'event_key'         => '',
			'status'            => 'pending',
			'channel'           => 'email',
			'payload'           => '',
			'error_code'        => '',
			'read_at'           => null,
			'created_at'        => current_time( 'mysql', true ),
			'sent_at'           => null,
		);

		return $this->insert( 'notifications', wp_parse_args( $data, $defaults ) );
	}

	/** Record one idempotent in-app lifecycle notification. @return int|false */
	public function record_notification_event( $customer_id, $type, $reference, array $payload = array() ) {
		$settings = get_option( 'arvan_reseller_settings', array() );
		if ( isset( $settings['notification_enabled'] ) && empty( $settings['notification_enabled'] ) ) {
			return false;
		}
		$type    = sanitize_key( (string) $type );
		$allowed = array( 'payment_completed', 'payment_failed', 'provisioning_failed', 'suspension', 'termination' );
		if ( absint( $customer_id ) <= 0 || ! in_array( $type, $allowed, true ) || '' === (string) $reference ) {
			return false;
		}
		$event_key = hash( 'sha256', 'customer-event-v1|' . absint( $customer_id ) . '|' . $type . '|' . (string) $reference );
		$existing  = $this->get_notification_by_event_key( $event_key );
		if ( null !== $existing ) {
			return (int) $existing['id'];
		}
		return $this->create_notification(
			array(
				'customer_id'       => absint( $customer_id ),
				'notification_type' => $type,
				'event_key'         => $event_key,
				'status'            => 'sent',
				'channel'           => 'in_app',
				'payload'           => wp_json_encode( Arvan_Reseller_Security::redact( $payload ) ),
				'sent_at'           => current_time( 'mysql', true ),
			)
		);
	}

	/** @return array|null */
	public function get_notification_by_event_key( $event_key ) {
		return $this->get_row_by( 'notifications', array( 'event_key' => (string) $event_key ) );
	}

	/** @return array */
	public function get_notifications_by_customer_id( $customer_id, $limit = 50, $offset = 0 ) {
		return $this->get_results_by( 'notifications', array( 'customer_id' => absint( $customer_id ) ), $limit, $offset );
	}

	/** Mark an owned notification read and return the updated safe source row. */
	public function mark_notification_read( $notification_id, $customer_id ) {
		$where = array(
			'id'          => absint( $notification_id ),
			'customer_id' => absint( $customer_id ),
		);
		$row = $this->get_row_by( 'notifications', $where );
		if ( null === $row ) {
			return null;
		}
		if ( empty( $row['read_at'] ) && false === $this->update( 'notifications', array( 'read_at' => current_time( 'mysql', true ) ), $where, array( '%s' ), array( '%d', '%d' ) ) ) {
			return null;
		}
		return $this->get_row_by( 'notifications', $where );
	}

	/** @return int|false */
	public function create_settlement( array $data ) {
		$defaults = array(
			'settlement_reference'  => '',
			'period_start'          => current_time( 'mysql', true ),
			'period_end'            => current_time( 'mysql', true ),
			'base_cost_minor'       => 0,
			'customer_charge_minor' => 0,
			'reseller_share_minor'  => 0,
			'currency'              => 'IRR',
			'status'                => 'pending',
			'adapter'               => 'mock',
			'metadata'              => '',
			'created_at'            => current_time( 'mysql', true ),
			'updated_at'            => current_time( 'mysql', true ),
		);

		return $this->insert( 'settlements', wp_parse_args( $data, $defaults ) );
	}

	/** @return array|null */
	public function get_settlement_by_reference( $reference ) {
		return $this->get_row_by( 'settlements', array( 'settlement_reference' => (string) $reference ) );
	}

	/** @return array */
	public function get_settlements( $limit = 50, $offset = 0 ) {
		return $this->get_results_by( 'settlements', array(), $limit, $offset );
	}

	/** Aggregate an invoicing period by customer for one immutable currency. */
	public function aggregate_customer_usage_period( $start, $end, $currency = 'IRR' ) {
		$table = $this->get_table_name( 'usage_records' );
		$query = $this->wpdb->prepare(
			"SELECT customer_id, currency, COALESCE(SUM(base_cost_minor),0) base_cost_minor, COALESCE(SUM(reseller_share_minor),0) reseller_share_minor, COALESCE(SUM(total_charge_minor),0) total_minor, COALESCE(SUM(charged_minor),0) charged_minor, COALESCE(SUM(uncovered_minor),0) uncovered_minor, COUNT(*) usage_count FROM {$table} WHERE usage_start >= %s AND usage_end <= %s AND currency = %s GROUP BY customer_id, currency ORDER BY customer_id ASC",
			(string) $start,
			(string) $end,
			$this->normalize_currency( $currency )
		);
		$rows = $this->wpdb->get_results( $query, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Return every immutable currency represented by usage in a period. */
	public function get_usage_currencies_period( $start, $end ) {
		$table = $this->get_table_name( 'usage_records' );
		$query = $this->wpdb->prepare(
			"SELECT DISTINCT currency FROM {$table} WHERE usage_start >= %s AND usage_end <= %s ORDER BY currency ASC",
			(string) $start,
			(string) $end
		);
		$rows  = $this->wpdb->get_col( $query );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$currencies = array();
		foreach ( $rows as $currency ) {
			$normalized = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $currency ) );
			if ( 3 === strlen( $normalized ) ) {
				$currencies[] = $normalized;
			}
		}

		return array_values( array_unique( $currencies ) );
	}

	/** @return array|null */
	public function get_invoice_by_reference( $reference ) {
		return $this->get_row_by( 'invoices', array( 'invoice_reference' => (string) $reference ) );
	}

	/** @return int|false */
	public function create_invoice( array $data ) {
		$defaults = array(
			'customer_id'          => 0,
			'invoice_reference'    => '',
			'period_start'         => current_time( 'mysql', true ),
			'period_end'           => current_time( 'mysql', true ),
			'base_cost_minor'      => 0,
			'reseller_share_minor' => 0,
			'total_minor'          => 0,
			'currency'             => 'IRR',
			'status'               => 'draft',
			'metadata'             => '',
			'created_at'           => current_time( 'mysql', true ),
			'updated_at'           => current_time( 'mysql', true ),
		);
		return $this->insert( 'invoices', wp_parse_args( $data, $defaults ) );
	}

	/** @return array */
	public function aggregate_usage_period( $start, $end, $currency = 'IRR' ) {
		$table = $this->get_table_name( 'usage_records' );
		$query = $this->wpdb->prepare(
			"SELECT COALESCE(SUM(base_cost_minor),0) base_cost_minor, COALESCE(SUM(total_charge_minor),0) customer_charge_minor, COALESCE(SUM(reseller_share_minor),0) reseller_share_minor, COUNT(*) usage_count FROM {$table} WHERE usage_start >= %s AND usage_end <= %s AND currency = %s",
			(string) $start,
			(string) $end,
			$this->normalize_currency( $currency )
		);
		$row   = $this->wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? array_map( 'intval', $row ) : array(
			'base_cost_minor'       => 0,
			'customer_charge_minor' => 0,
			'reseller_share_minor'  => 0,
			'usage_count'           => 0,
		);
	}

	/**
	 * Detect a duplicate-key database failure without exposing it externally.
	 *
	 * @return bool
	 */
	public function is_duplicate_error() {
		return false !== stripos( (string) $this->wpdb->last_error, 'duplicate' );
	}

	/** @return string */
	public function get_last_error() {
		return (string) $this->wpdb->last_error;
	}

	/**
	 * Build a prepared equality WHERE clause.
	 *
	 * @param array $where Conditions.
	 * @param array $args Prepared args.
	 * @return string
	 */
	private function build_where_clause( array $where, array &$args ) {
		$clauses = array();

		foreach ( $where as $column => $value ) {
			$column = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $column );

			if ( '' === $column ) {
				continue;
			}

			if ( is_int( $value ) ) {
				$clauses[] = "{$column} = %d";
			} else {
				$clauses[] = "{$column} = %s";
			}

			$args[] = $value;
		}

		return empty( $clauses ) ? '' : ' WHERE ' . implode( ' AND ', $clauses );
	}

	/** @return string */
	private function normalize_currency( $currency ) {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $currency ) );

		return 3 === strlen( $currency ) ? $currency : 'IRR';
	}

	/**
	 * Validate a status field before persistence.
	 *
	 * @param string $table Logical table.
	 * @param array  $data Data to persist.
	 * @return bool
	 */
	private function has_valid_status( $table, array $data ) {
		if ( ! array_key_exists( 'status', $data ) ) {
			return true;
		}

		return Arvan_Reseller_Status::is_valid( $table, $data['status'] );
	}
}
