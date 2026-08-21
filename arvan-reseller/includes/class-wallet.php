<?php
/**
 * Wallet domain service.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Wallet {

	/**
	 * Database service.
	 *
	 * @var Arvan_Reseller_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param Arvan_Reseller_Database $database Database service.
	 */
	public function __construct( Arvan_Reseller_Database $database ) {
		$this->database = $database;
	}

	/**
	 * Ensure a wallet exists for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array|null
	 */
	public function create_wallet( $customer_id ) {
		return $this->database->ensure_wallet( $customer_id );
	}

	/**
	 * Get the customer's balance.
	 *
	 * @param int $customer_id Customer ID.
	 * @return float
	 */
	public function get_balance( $customer_id ) {
		$wallet = $this->database->ensure_wallet( $customer_id );

		return null === $wallet ? 0.0 : (float) $wallet['balance'];
	}

	/**
	 * Increase wallet balance and create a transaction.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param float  $amount Credit amount.
	 * @param string $reference_type Reference type.
	 * @param string $reference_id Reference ID.
	 * @param string $description Transaction description.
	 * @return array|WP_Error
	 */
	public function increase_balance( $customer_id, $amount, $reference_type, $reference_id, $description = '' ) {
		return $this->change_balance( $customer_id, abs( (float) $amount ), 'credit', $reference_type, $reference_id, $description );
	}

	/**
	 * Decrease wallet balance and create a transaction.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param float  $amount Debit amount.
	 * @param string $reference_type Reference type.
	 * @param string $reference_id Reference ID.
	 * @param string $description Transaction description.
	 * @return array|WP_Error
	 */
	public function decrease_balance( $customer_id, $amount, $reference_type, $reference_id, $description = '' ) {
		return $this->change_balance( $customer_id, abs( (float) $amount ), 'debit', $reference_type, $reference_id, $description );
	}

	/**
	 * Get transaction history for a wallet.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_balance_history( $customer_id, $limit = 50 ) {
		return $this->database->get_transactions_by_customer_id( $customer_id, $limit );
	}

	/**
	 * Apply a wallet balance change and create its ledger row.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param float  $amount Transaction amount.
	 * @param string $transaction_type Transaction type.
	 * @param string $reference_type Reference type.
	 * @param string $reference_id Reference ID.
	 * @param string $description Description.
	 * @return array|WP_Error
	 */
	private function change_balance( $customer_id, $amount, $transaction_type, $reference_type, $reference_id, $description ) {
		$customer_id = absint( $customer_id );
		$amount      = round( (float) $amount, 4 );

		if ( $customer_id <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_customer', __( 'Invalid customer ID.', 'arvan-reseller' ) );
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_amount', __( 'Wallet amount must be greater than zero.', 'arvan-reseller' ) );
		}

		if ( $this->database->transaction_exists( $customer_id, $reference_type, $reference_id, $transaction_type ) ) {
			return new WP_Error( 'arvan_reseller_duplicate_transaction', __( 'Duplicate wallet transaction blocked.', 'arvan-reseller' ) );
		}

		$wallet = $this->database->ensure_wallet( $customer_id );

		if ( null === $wallet ) {
			return new WP_Error( 'arvan_reseller_wallet_error', __( 'Unable to create wallet.', 'arvan-reseller' ) );
		}

		$before = round( (float) $wallet['balance'], 4 );
		$after  = 'credit' === $transaction_type ? $before + $amount : $before - $amount;

		if ( 'debit' === $transaction_type && $after < 0 ) {
			return new WP_Error( 'arvan_reseller_insufficient_balance', __( 'Insufficient wallet balance.', 'arvan-reseller' ) );
		}

		$updated = $this->database->update(
			'wallets',
			array(
				'balance'    => number_format( $after, 4, '.', '' ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id' => (int) $wallet['id'],
			),
			array( '%f', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'arvan_reseller_wallet_update_failed', __( 'Failed to update wallet balance.', 'arvan-reseller' ) );
		}

		$transaction_id = $this->database->create_transaction(
			array(
				'customer_id'      => $customer_id,
				'transaction_type' => $transaction_type,
				'amount'           => number_format( $amount, 4, '.', '' ),
				'balance_before'   => number_format( $before, 4, '.', '' ),
				'balance_after'    => number_format( $after, 4, '.', '' ),
				'reference_type'   => (string) $reference_type,
				'reference_id'     => (string) $reference_id,
				'description'      => (string) $description,
			)
		);

		if ( false === $transaction_id ) {
			$this->database->update(
				'wallets',
				array(
					'balance'    => number_format( $before, 4, '.', '' ),
					'updated_at' => current_time( 'mysql', true ),
				),
				array(
					'id' => (int) $wallet['id'],
				),
				array( '%f', '%s' ),
				array( '%d' )
			);

			return new WP_Error( 'arvan_reseller_ledger_write_failed', __( 'Failed to save wallet transaction.', 'arvan-reseller' ) );
		}

		return array(
			'transaction_id' => $transaction_id,
			'balance_before' => $before,
			'balance_after'  => $after,
		);
	}
}
