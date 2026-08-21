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

	/**
	 * WordPress database object.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Supported plugin tables.
	 *
	 * @var array<string, string>
	 */
	private $tables = array(
		'wallets'      => 'arvan_reseller_wallets',
		'transactions' => 'arvan_reseller_transactions',
		'resources'    => 'arvan_reseller_resources',
		'usage_logs'   => 'arvan_reseller_usage_logs',
		'orders'       => 'arvan_reseller_orders',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
	}

	/**
	 * Get a fully qualified table name.
	 *
	 * @param string $table Logical table key.
	 * @return string
	 */
	public function get_table_name( $table ) {
		if ( ! isset( $this->tables[ $table ] ) ) {
			return '';
		}

		return $this->wpdb->prefix . $this->tables[ $table ];
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

		if ( '' === $table_name ) {
			return false;
		}

		$result = empty( $format ) ? $this->wpdb->insert( $table_name, $data ) : $this->wpdb->insert( $table_name, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update rows in a table.
	 *
	 * @param string $table Logical table key.
	 * @param array  $data Update data.
	 * @param array  $where Where clauses.
	 * @param array  $format Optional data formats.
	 * @param array  $where_format Optional where formats.
	 * @return int|false
	 */
	public function update( $table, array $data, array $where, array $format = array(), array $where_format = array() ) {
		$table_name = $this->get_table_name( $table );

		if ( '' === $table_name ) {
			return false;
		}

		return $this->wpdb->update( $table_name, $data, $where, $format, $where_format );
	}

	/**
	 * Get a single row by conditions.
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

		$query = "SELECT * FROM {$table_name}";
		$args  = array();

		$query .= $this->build_where_clause( $where, $args );
		$query .= ' LIMIT 1';

		$result = $this->wpdb->get_row( $this->wpdb->prepare( $query, $args ), ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Get multiple rows by conditions.
	 *
	 * @param string $table Logical table key.
	 * @param array  $where Where clauses.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 * @param string $order_by Order by column.
	 * @param string $order Sort direction.
	 * @return array
	 */
	public function get_results_by( $table, array $where = array(), $limit = 100, $offset = 0, $order_by = 'id', $order = 'DESC' ) {
		$table_name = $this->get_table_name( $table );

		if ( '' === $table_name ) {
			return array();
		}

		$allowed_order_by = array(
			'id',
			'customer_id',
			'created_at',
			'updated_at',
			'calculated_at',
			'last_synced_at',
			'last_billed_at',
		);

		$order_by = in_array( $order_by, $allowed_order_by, true ) ? $order_by : 'id';
		$order    = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$limit    = max( 1, absint( $limit ) );
		$offset   = max( 0, absint( $offset ) );

		$query = "SELECT * FROM {$table_name}";
		$args  = array();

		if ( ! empty( $where ) ) {
			$query .= $this->build_where_clause( $where, $args );
		}

		$query .= " ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
		$args[] = $limit;
		$args[] = $offset;

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $query, $args ), ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Create or retrieve a customer wallet.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array|null
	 */
	public function ensure_wallet( $customer_id ) {
		$customer_id = absint( $customer_id );

		if ( $customer_id <= 0 ) {
			return null;
		}

		$wallet = $this->get_wallet_by_customer_id( $customer_id );

		if ( null !== $wallet ) {
			return $wallet;
		}

		$now = current_time( 'mysql', true );

		$insert_id = $this->insert(
			'wallets',
			array(
				'customer_id' => $customer_id,
				'balance'     => '0.00',
				'threshold'   => '0.00',
				'status'      => 'active',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%f', '%f', '%s', '%s', '%s' )
		);

		if ( false === $insert_id ) {
			return $this->get_wallet_by_customer_id( $customer_id );
		}

		return $this->get_wallet_by_customer_id( $customer_id );
	}

	/**
	 * Get a wallet by customer ID.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array|null
	 */
	public function get_wallet_by_customer_id( $customer_id ) {
		return $this->get_row_by(
			'wallets',
			array(
				'customer_id' => absint( $customer_id ),
			)
		);
	}

	/**
	 * Create a transaction ledger entry.
	 *
	 * @param array $data Transaction data.
	 * @return int|false
	 */
	public function create_transaction( array $data ) {
		$defaults = array(
			'customer_id'      => 0,
			'transaction_type' => '',
			'amount'           => '0.00',
			'balance_before'   => '0.00',
			'balance_after'    => '0.00',
			'reference_type'   => '',
			'reference_id'     => '',
			'description'      => '',
			'created_at'       => current_time( 'mysql', true ),
		);

		$data = wp_parse_args( $data, $defaults );

		return $this->insert(
			'transactions',
			$data,
			array( '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Check whether a ledger entry already exists for a reference.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $reference_type Reference type.
	 * @param string $reference_id Reference ID.
	 * @param string $transaction_type Transaction type.
	 * @return bool
	 */
	public function transaction_exists( $customer_id, $reference_type, $reference_id, $transaction_type ) {
		$table_name = $this->get_table_name( 'transactions' );

		if ( '' === $table_name ) {
			return false;
		}

		$query = $this->wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE customer_id = %d AND reference_type = %s AND reference_id = %s AND transaction_type = %s LIMIT 1",
			absint( $customer_id ),
			(string) $reference_type,
			(string) $reference_id,
			(string) $transaction_type
		);

		return (bool) $this->wpdb->get_var( $query );
	}

	/**
	 * Get transaction history for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_transactions_by_customer_id( $customer_id, $limit = 50 ) {
		return $this->get_results_by(
			'transactions',
			array(
				'customer_id' => absint( $customer_id ),
			),
			$limit
		);
	}

	/**
	 * Save a resource mapping row.
	 *
	 * @param array $data Resource data.
	 * @return int|false
	 */
	public function save_resource( array $data ) {
		$existing = null;

		if ( ! empty( $data['resource_id'] ) ) {
			$existing = $this->get_resource_by_arvan_id( $data['resource_id'] );
		}

		$defaults = array(
			'customer_id'     => 0,
			'resource_id'     => '',
			'product_type'    => '',
			'status'          => 'pending',
			'remote_payload'  => '',
			'created_at'      => current_time( 'mysql', true ),
			'updated_at'      => current_time( 'mysql', true ),
			'last_synced_at'  => null,
			'last_billed_at'  => null,
		);

		$data = wp_parse_args( $data, $defaults );

		if ( null !== $existing ) {
			$this->update(
				'resources',
				array(
					'customer_id'    => absint( $data['customer_id'] ),
					'product_type'   => (string) $data['product_type'],
					'status'         => (string) $data['status'],
					'remote_payload' => (string) $data['remote_payload'],
					'updated_at'     => (string) $data['updated_at'],
					'last_synced_at' => $data['last_synced_at'],
					'last_billed_at' => $data['last_billed_at'],
				),
				array(
					'id' => (int) $existing['id'],
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return (int) $existing['id'];
		}

		return $this->insert(
			'resources',
			array(
				'customer_id'    => absint( $data['customer_id'] ),
				'resource_id'    => (string) $data['resource_id'],
				'product_type'   => (string) $data['product_type'],
				'status'         => (string) $data['status'],
				'remote_payload' => (string) $data['remote_payload'],
				'created_at'     => (string) $data['created_at'],
				'updated_at'     => (string) $data['updated_at'],
				'last_synced_at' => $data['last_synced_at'],
				'last_billed_at' => $data['last_billed_at'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get a resource by Arvan resource ID.
	 *
	 * @param string $resource_id Resource ID.
	 * @return array|null
	 */
	public function get_resource_by_arvan_id( $resource_id ) {
		return $this->get_row_by(
			'resources',
			array(
				'resource_id' => (string) $resource_id,
			)
		);
	}

	/**
	 * Get resources by customer ID.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public function get_resources_by_customer_id( $customer_id ) {
		return $this->get_results_by(
			'resources',
			array(
				'customer_id' => absint( $customer_id ),
			),
			500
		);
	}

	/**
	 * Get resources eligible for hourly billing.
	 *
	 * @return array
	 */
	public function get_billable_resources() {
		$table_name = $this->get_table_name( 'resources' );

		if ( '' === $table_name ) {
			return array();
		}

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE status IN (%s, %s, %s) ORDER BY id ASC",
			'active',
			'provisioned',
			'suspended'
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Create a usage log.
	 *
	 * @param array $data Usage data.
	 * @return int|false
	 */
	public function create_usage_log( array $data ) {
		$defaults = array(
			'customer_id'      => 0,
			'resource_id'      => '',
			'usage_amount'     => '0.0000',
			'unit'             => '',
			'usage_start'      => current_time( 'mysql', true ),
			'usage_end'        => current_time( 'mysql', true ),
			'cost'             => '0.0000',
			'reseller_share'   => '0.0000',
			'billing_reference' => '',
			'api_payload'      => '',
			'calculated_at'    => current_time( 'mysql', true ),
		);

		$data = wp_parse_args( $data, $defaults );

		return $this->insert(
			'usage_logs',
			$data,
			array( '%d', '%s', '%f', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s' )
		);
	}

	/**
	 * Check whether a usage log already exists for a billing reference.
	 *
	 * @param string $billing_reference Billing reference.
	 * @return bool
	 */
	public function usage_log_exists( $billing_reference ) {
		$table_name = $this->get_table_name( 'usage_logs' );

		if ( '' === $table_name || '' === $billing_reference ) {
			return false;
		}

		$query = $this->wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE billing_reference = %s LIMIT 1",
			$billing_reference
		);

		return (bool) $this->wpdb->get_var( $query );
	}

	/**
	 * Save an order.
	 *
	 * @param array $data Order data.
	 * @return int|false
	 */
	public function create_order( array $data ) {
		$defaults = array(
			'customer_id'    => 0,
			'product_type'   => '',
			'status'         => 'pending',
			'resource_id'    => '',
			'order_reference'=> '',
			'details'        => '',
			'created_at'     => current_time( 'mysql', true ),
			'updated_at'     => current_time( 'mysql', true ),
		);

		$data = wp_parse_args( $data, $defaults );

		return $this->insert(
			'orders',
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Build a prepared WHERE clause.
	 *
	 * @param array $where Where conditions.
	 * @param array $args Query arguments.
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
			} elseif ( is_float( $value ) ) {
				$clauses[] = "{$column} = %f";
			} else {
				$clauses[] = "{$column} = %s";
			}

			$args[] = $value;
		}

		return empty( $clauses ) ? '' : ' WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Get the last database error.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return (string) $this->wpdb->last_error;
	}
}
