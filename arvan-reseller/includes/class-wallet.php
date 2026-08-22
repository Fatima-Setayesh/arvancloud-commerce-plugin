<?php
/**
 * Transaction-safe wallet and immutable ledger service.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Wallet {

	/** @var Arvan_Reseller_Database */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param Arvan_Reseller_Database $database Database service.
	 */
	public function __construct( Arvan_Reseller_Database $database ) {
		$this->database = $database;
	}

	/** @return array|null */
	public function create_wallet( $customer_id, $currency = 'IRR' ) {
		return $this->database->ensure_wallet( $customer_id, $currency );
	}

	/**
	 * Return a fixed-decimal balance string.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $currency Currency.
	 * @return string
	 */
	public function get_balance( $customer_id, $currency = 'IRR' ) {
		return Arvan_Reseller_Money::format( $this->get_balance_minor( $customer_id, $currency ) );
	}

	/** @return int */
	public function get_balance_minor( $customer_id, $currency = 'IRR' ) {
		$wallet = $this->database->ensure_wallet( $customer_id, $currency );

		return null === $wallet ? 0 : (int) $wallet['balance_minor'];
	}

	/**
	 * Credit a decimal amount.
	 *
	 * @return array|WP_Error
	 */
	public function increase_balance( $customer_id, $amount, $reference_type, $reference_id, $description = '', $currency = 'IRR' ) {
		$minor = Arvan_Reseller_Money::to_minor( $amount );

		return is_wp_error( $minor ) ? $minor : $this->credit_minor( $customer_id, $minor, $reference_type, $reference_id, $description, $currency );
	}

	/**
	 * Debit a decimal amount, failing if the full amount is unavailable.
	 *
	 * @return array|WP_Error
	 */
	public function decrease_balance( $customer_id, $amount, $reference_type, $reference_id, $description = '', $currency = 'IRR' ) {
		$minor = Arvan_Reseller_Money::to_minor( $amount );

		return is_wp_error( $minor ) ? $minor : $this->debit_minor( $customer_id, $minor, $reference_type, $reference_id, $description, $currency );
	}

	/** @return array|WP_Error */
	public function credit_minor( $customer_id, $amount_minor, $reference_type, $reference_id, $description = '', $currency = 'IRR' ) {
		return $this->change_balance_minor( $customer_id, $amount_minor, 'credit', $reference_type, $reference_id, $description, $currency, false );
	}

	/** @return array|WP_Error */
	public function debit_minor( $customer_id, $amount_minor, $reference_type, $reference_id, $description = '', $currency = 'IRR' ) {
		return $this->change_balance_minor( $customer_id, $amount_minor, 'debit', $reference_type, $reference_id, $description, $currency, false );
	}

	/**
	 * Debit up to the available prepaid balance and report uncovered usage.
	 *
	 * @return array|WP_Error
	 */
	public function charge_up_to_available_minor( $customer_id, $amount_minor, $reference_type, $reference_id, $description = '', $currency = 'IRR' ) {
		return $this->change_balance_minor( $customer_id, $amount_minor, 'debit', $reference_type, $reference_id, $description, $currency, true );
	}

	/** @return array */
	public function get_balance_history( $customer_id, $limit = 50 ) {
		return $this->database->get_transactions_by_customer_id( $customer_id, $limit );
	}

	/**
	 * Compare the cached wallet balance with the immutable ledger.
	 *
	 * Repair is admin-only and itself only updates the disposable cache; ledger
	 * rows are never edited.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param bool   $repair Whether to repair the cached balance.
	 * @param string $currency Currency.
	 * @return array|WP_Error
	 */
	public function reconcile( $customer_id, $repair = false, $currency = 'IRR' ) {
		$customer_id = absint( $customer_id );

		if ( $customer_id <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_customer', __( 'Invalid customer ID.', 'arvan-reseller' ) );
		}

		if ( $repair && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'arvan_reseller_reconciliation_forbidden', __( 'You are not allowed to repair wallet balances.', 'arvan-reseller' ) );
		}

		$owns_transaction = ! $this->database->in_transaction();

		if ( $owns_transaction && ! $this->database->begin_transaction() ) {
			return new WP_Error( 'arvan_reseller_transaction_start_failed', __( 'Unable to start wallet reconciliation.', 'arvan-reseller' ) );
		}

		$wallet = $this->database->lock_wallet( $customer_id, $currency );

		if ( null === $wallet ) {
			return $this->fail( $owns_transaction, 'arvan_reseller_wallet_error', __( 'Unable to lock the wallet.', 'arvan-reseller' ) );
		}

		$cached_minor = (int) $wallet['balance_minor'];
		$ledger_minor = $this->database->get_ledger_balance_minor( (int) $wallet['id'] );

		if ( null === $ledger_minor || $ledger_minor < 0 ) {
			return $this->fail( $owns_transaction, 'arvan_reseller_invalid_ledger_balance', __( 'The wallet ledger is inconsistent.', 'arvan-reseller' ) );
		}

		$matches  = $cached_minor === $ledger_minor;
		$repaired = false;

		if ( $repair && ! $matches ) {
			if ( ! $this->database->update_locked_wallet_balance( (int) $wallet['id'], $cached_minor, $ledger_minor ) ) {
				return $this->fail( $owns_transaction, 'arvan_reseller_reconciliation_failed', __( 'Unable to repair the cached wallet balance.', 'arvan-reseller' ) );
			}

			$cached_minor = $ledger_minor;
			$matches      = true;
			$repaired     = true;
		}

		if ( $owns_transaction && ! $this->database->commit() ) {
			return new WP_Error( 'arvan_reseller_transaction_commit_failed', __( 'Unable to commit wallet reconciliation.', 'arvan-reseller' ) );
		}

		return array(
			'wallet_id'            => (int) $wallet['id'],
			'customer_id'          => $customer_id,
			'cached_balance_minor' => $cached_minor,
			'ledger_balance_minor' => $ledger_minor,
			'matches'              => $matches,
			'repaired'             => $repaired,
		);
	}

	/**
	 * Atomically lock, append ledger, and update cached balance.
	 *
	 * @return array|WP_Error
	 */
	private function change_balance_minor( $customer_id, $amount_minor, $transaction_type, $reference_type, $reference_id, $description, $currency, $allow_partial ) {
		$customer_id    = absint( $customer_id );
		$amount_minor   = is_int( $amount_minor ) ? $amount_minor : 0;
		$reference_type = sanitize_key( (string) $reference_type );
		$reference_id   = sanitize_text_field( (string) $reference_id );
		$currency       = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $currency ) );
		$currency       = 3 === strlen( $currency ) ? $currency : 'IRR';

		if ( $customer_id <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_customer', __( 'Invalid customer ID.', 'arvan-reseller' ) );
		}

		if ( $amount_minor <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_amount', __( 'Wallet amount must be greater than zero.', 'arvan-reseller' ) );
		}

		if ( ! in_array( $transaction_type, array( 'credit', 'debit' ), true ) || '' === $reference_type || '' === $reference_id ) {
			return new WP_Error( 'arvan_reseller_invalid_transaction_reference', __( 'A valid wallet transaction reference is required.', 'arvan-reseller' ) );
		}

		$idempotency_key  = hash( 'sha256', implode( '|', array( 'wallet-v1', $customer_id, $currency, $transaction_type, $reference_type, $reference_id ) ) );
		$owns_transaction = ! $this->database->in_transaction();

		if ( $owns_transaction && ! $this->database->begin_transaction() ) {
			return new WP_Error( 'arvan_reseller_transaction_start_failed', __( 'Unable to start wallet transaction.', 'arvan-reseller' ) );
		}

		$wallet = $this->database->lock_wallet( $customer_id, $currency );

		if ( null === $wallet ) {
			return $this->fail( $owns_transaction, 'arvan_reseller_wallet_error', __( 'Unable to create or lock the wallet.', 'arvan-reseller' ) );
		}

		$existing = $this->database->get_transaction_by_idempotency_key( $idempotency_key );

		if ( null !== $existing ) {
			if ( $owns_transaction ) {
				$this->database->commit();
			}

			return $this->format_result( $existing, true, max( 0, $amount_minor - (int) $existing['amount_minor'] ) );
		}

		$before_minor    = (int) $wallet['balance_minor'];
		$applied_minor   = $amount_minor;
		$uncovered_minor = 0;

		if ( 'debit' === $transaction_type && $amount_minor > $before_minor ) {
			if ( ! $allow_partial ) {
				return $this->fail( $owns_transaction, 'arvan_reseller_insufficient_balance', __( 'Insufficient wallet balance.', 'arvan-reseller' ) );
			}

			$applied_minor   = $before_minor;
			$uncovered_minor = $amount_minor - $before_minor;
		}

		if ( 'credit' === $transaction_type && $applied_minor > PHP_INT_MAX - $before_minor ) {
			return $this->fail( $owns_transaction, 'arvan_reseller_money_overflow', __( 'Wallet balance is outside the supported range.', 'arvan-reseller' ) );
		}

		$after_minor = 'credit' === $transaction_type ? $before_minor + $applied_minor : $before_minor - $applied_minor;

		if ( $after_minor < 0 || $after_minor > PHP_INT_MAX ) {
			return $this->fail( $owns_transaction, 'arvan_reseller_money_overflow', __( 'Wallet balance is outside the supported range.', 'arvan-reseller' ) );
		}

		if ( 0 === $applied_minor ) {
			if ( $owns_transaction && ! $this->database->commit() ) {
				return new WP_Error( 'arvan_reseller_transaction_commit_failed', __( 'Unable to commit wallet transaction.', 'arvan-reseller' ) );
			}

			return array(
				'transaction_id'       => 0,
				'balance_before_minor' => $before_minor,
				'balance_after_minor'  => $after_minor,
				'applied_minor'        => 0,
				'uncovered_minor'      => $uncovered_minor,
				'idempotent'           => false,
			);
		}

		$transaction_id = $this->database->create_transaction(
			array(
				'wallet_id'            => (int) $wallet['id'],
				'customer_id'          => $customer_id,
				'transaction_type'     => $transaction_type,
				'amount_minor'         => $applied_minor,
				'balance_before_minor' => $before_minor,
				'balance_after_minor'  => $after_minor,
				'currency'             => $currency,
				'reference_type'       => $reference_type,
				'reference_id'         => $reference_id,
				'idempotency_key'      => $idempotency_key,
				'description'          => sanitize_text_field( (string) $description ),
			)
		);

		if ( false === $transaction_id ) {
			$is_duplicate = $this->database->is_duplicate_error();

			if ( $is_duplicate && $owns_transaction ) {
				$this->database->rollback();
				$duplicate = $this->database->get_transaction_by_idempotency_key( $idempotency_key );

				if ( null !== $duplicate ) {
					return $this->format_result( $duplicate, true, max( 0, $amount_minor - (int) $duplicate['amount_minor'] ) );
				}
			}

			return $this->fail( $owns_transaction, $is_duplicate ? 'arvan_reseller_duplicate_transaction' : 'arvan_reseller_ledger_write_failed', __( 'Failed to append the wallet ledger.', 'arvan-reseller' ) );
		}

		if ( ! $this->database->update_locked_wallet_balance( (int) $wallet['id'], $before_minor, $after_minor ) ) {
			return $this->fail( $owns_transaction, 'arvan_reseller_wallet_update_failed', __( 'Failed to update wallet balance.', 'arvan-reseller' ) );
		}

		if ( $owns_transaction && ! $this->database->commit() ) {
			return new WP_Error( 'arvan_reseller_transaction_commit_failed', __( 'Unable to commit wallet transaction.', 'arvan-reseller' ) );
		}

		return array(
			'transaction_id'       => (int) $transaction_id,
			'balance_before_minor' => $before_minor,
			'balance_after_minor'  => $after_minor,
			'applied_minor'        => $applied_minor,
			'uncovered_minor'      => $uncovered_minor,
			'idempotent'           => false,
		);
	}

	/** @return WP_Error */
	private function fail( $owns_transaction, $code, $message ) {
		if ( $owns_transaction ) {
			$this->database->rollback();
		}

		return new WP_Error( $code, $message );
	}

	/** @return array */
	private function format_result( array $transaction, $idempotent, $uncovered_minor ) {
		return array(
			'transaction_id'       => (int) $transaction['id'],
			'balance_before_minor' => (int) $transaction['balance_before_minor'],
			'balance_after_minor'  => (int) $transaction['balance_after_minor'],
			'applied_minor'        => (int) $transaction['amount_minor'],
			'uncovered_minor'      => (int) $uncovered_minor,
			'idempotent'           => (bool) $idempotent,
		);
	}
}
