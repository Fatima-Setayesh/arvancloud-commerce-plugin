<?php
/**
 * Dedicated, transaction-safe mock payment lifecycle.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Payment {

	/** @var Arvan_Reseller_Wallet */
	private $wallet;

	/** @var Arvan_Reseller_Database */
	private $database;

	/** @param Arvan_Reseller_Wallet $wallet @param Arvan_Reseller_Database $database */
	public function __construct( Arvan_Reseller_Wallet $wallet, Arvan_Reseller_Database $database ) {
		$this->wallet   = $wallet;
		$this->database = $database;
	}

	/**
	 * Create an idempotent mock payment intent.
	 *
	 * @param int        $customer_id Owner user ID.
	 * @param int|string $amount Plain-decimal amount.
	 * @param string     $client_key Client idempotency key.
	 * @return array|WP_Error
	 */
	public function create_payment( $customer_id, $amount, $client_key = '' ) {
		$customer_id  = absint( $customer_id );
		$amount_minor = Arvan_Reseller_Money::to_minor( $amount );
		$client_key   = sanitize_text_field( (string) $client_key );

		if ( $customer_id <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_customer', __( 'Invalid payment owner.', 'arvan-reseller' ) );
		}

		if ( is_wp_error( $amount_minor ) || $amount_minor < $this->get_minimum_minor() || $amount_minor > $this->get_maximum_minor() ) {
			return new WP_Error( 'arvan_reseller_invalid_topup_amount', __( 'Top-up amount is outside the configured limits.', 'arvan-reseller' ) );
		}

		if ( '' === $client_key || strlen( $client_key ) > 191 ) {
			return new WP_Error( 'arvan_reseller_missing_idempotency_key', __( 'A valid idempotency key is required.', 'arvan-reseller' ) );
		}

		$idempotency_key = hash( 'sha256', 'mock-payment-v1|' . $customer_id . '|' . $client_key );
		$existing        = $this->database->get_payment_by_idempotency_key( $idempotency_key );

		if ( null !== $existing ) {
			return $this->serialize_payment( $existing, true );
		}

		$wallet = $this->database->ensure_wallet( $customer_id, $this->get_currency() );

		if ( null === $wallet ) {
			return new WP_Error( 'arvan_reseller_wallet_error', __( 'Unable to prepare the customer wallet.', 'arvan-reseller' ) );
		}

		$reference  = 'pay_' . str_replace( '-', '', wp_generate_uuid4() );
		$payment_id = $this->database->create_payment(
			array(
				'customer_id'       => $customer_id,
				'wallet_id'         => (int) $wallet['id'],
				'payment_reference' => $reference,
				'idempotency_key'   => $idempotency_key,
				'amount_minor'      => $amount_minor,
				'currency'          => $this->get_currency(),
				'status'            => 'pending',
				'provider'          => 'mock',
				'expires_at'        => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
			)
		);

		if ( false === $payment_id ) {
			$existing = $this->database->get_payment_by_idempotency_key( $idempotency_key );

			return null !== $existing ? $this->serialize_payment( $existing, true ) : new WP_Error( 'arvan_reseller_payment_create_failed', __( 'Unable to create payment.', 'arvan-reseller' ) );
		}

		$payment = $this->database->get_payment_by_reference( $reference );
		$this->database->create_audit_log(
			'payment_created',
			'payment',
			(string) $payment_id,
			array(
				'provider'     => 'mock',
				'amount_minor' => $amount_minor,
			),
			$customer_id
		);

		return $this->serialize_payment( $payment, false );
	}

	/**
	 * Confirm a pending mock payment atomically with wallet credit.
	 *
	 * @param int    $customer_id Authenticated owner ID.
	 * @param string $payment_reference Payment reference.
	 * @return array|WP_Error
	 */
	public function confirm_payment( $customer_id, $payment_reference = '' ) {
		/* Compatibility: older internal callers passed only the reference. */
		if ( '' === $payment_reference && is_string( $customer_id ) ) {
			$payment_reference = $customer_id;
			$customer_id       = get_current_user_id();
		}

		$customer_id       = absint( $customer_id );
		$payment_reference = sanitize_text_field( (string) $payment_reference );

		if ( $customer_id <= 0 || '' === $payment_reference ) {
			return new WP_Error( 'arvan_reseller_invalid_payment_reference', __( 'Invalid payment request.', 'arvan-reseller' ) );
		}

		$owns_transaction = ! $this->database->in_transaction();
		if ( $owns_transaction && ! $this->database->begin_transaction() ) {
			return new WP_Error( 'arvan_reseller_transaction_start_failed', __( 'Unable to start payment confirmation.', 'arvan-reseller' ) );
		}

		$payment = $this->database->lock_payment_by_reference( $payment_reference );

		if ( null === $payment || ! $this->can_access_payment( $customer_id, $payment ) ) {
			if ( $owns_transaction ) {
				$this->database->rollback(); }
			return new WP_Error( 'arvan_reseller_payment_not_found', __( 'Payment was not found.', 'arvan-reseller' ) );
		}

		if ( 'completed' === $payment['status'] ) {
			if ( $owns_transaction ) {
				$this->database->commit(); }
			return $this->serialize_payment( $payment, true );
		}

		if ( 'pending' !== $payment['status'] ) {
			if ( $owns_transaction ) {
				$this->database->rollback(); }
			return new WP_Error( 'arvan_reseller_payment_not_confirmable', __( 'Payment cannot be confirmed in its current state.', 'arvan-reseller' ) );
		}

		if ( ! empty( $payment['expires_at'] ) && strtotime( $payment['expires_at'] . ' UTC' ) <= time() ) {
			$this->database->transition_payment_status( (int) $payment['id'], 'pending', 'expired' );
			$this->database->create_audit_log( 'payment_expired', 'payment', (string) $payment['id'], array(), (int) $payment['customer_id'] );
			if ( $owns_transaction ) {
				$this->database->commit(); }
			return new WP_Error( 'arvan_reseller_payment_expired', __( 'Payment has expired.', 'arvan-reseller' ) );
		}

		$credit = $this->wallet->credit_minor(
			(int) $payment['customer_id'],
			(int) $payment['amount_minor'],
			'payment',
			(string) $payment['payment_reference'],
			__( 'Mock wallet top-up', 'arvan-reseller' ),
			(string) $payment['currency']
		);

		if ( is_wp_error( $credit ) ) {
			if ( $owns_transaction ) {
				$this->database->rollback(); }
			return $credit;
		}

		$completed_at = current_time( 'mysql', true );
		$transitioned = $this->database->transition_payment_status(
			(int) $payment['id'],
			'pending',
			'completed',
			array(
				'completed_at'       => $completed_at,
				'provider_reference' => 'mock_' . $payment['payment_reference'],
			)
		);

		if ( ! $transitioned ) {
			if ( $owns_transaction ) {
				$this->database->rollback(); }
			return new WP_Error( 'arvan_reseller_payment_transition_failed', __( 'Payment confirmation could not be committed.', 'arvan-reseller' ) );
		}

		if ( false === $this->database->create_audit_log( 'payment_completed', 'payment', (string) $payment['id'], array( 'transaction_id' => $credit['transaction_id'] ), (int) $payment['customer_id'] ) ) {
			if ( $owns_transaction ) {
				$this->database->rollback(); }
			return new WP_Error( 'arvan_reseller_audit_failed', __( 'Payment audit record could not be written.', 'arvan-reseller' ) );
		}

		if ( $owns_transaction && ! $this->database->commit() ) {
			return new WP_Error( 'arvan_reseller_transaction_commit_failed', __( 'Payment confirmation could not be committed.', 'arvan-reseller' ) );
		}

		$payment['status']             = 'completed';
		$payment['completed_at']       = $completed_at;
		$payment['provider_reference'] = 'mock_' . $payment['payment_reference'];
		$result                        = $this->serialize_payment( $payment, false );
		$result['wallet']              = $credit;

		return $result;
	}

	/**
	 * Authenticated nonce-protected confirmation entry point.
	 *
	 * @return array|WP_Error
	 */
	public function confirm_current_payment( $payment_reference, $nonce ) {
		if ( ! is_user_logged_in() || ! Arvan_Reseller_Security::verify_nonce( $nonce, 'arvan_reseller_confirm_payment' ) ) {
			return new WP_Error( 'arvan_reseller_invalid_nonce', __( 'Payment confirmation security check failed.', 'arvan-reseller' ) );
		}

		return $this->confirm_payment( get_current_user_id(), $payment_reference );
	}

	/**
	 * Move a pending payment to failed or cancelled.
	 *
	 * @return array|WP_Error
	 */
	public function close_payment( $customer_id, $payment_reference, $status ) {
		$status = sanitize_key( $status );

		if ( ! in_array( $status, array( 'failed', 'cancelled' ), true ) || ! $this->database->begin_transaction() ) {
			return new WP_Error( 'arvan_reseller_invalid_payment_status', __( 'Invalid payment state transition.', 'arvan-reseller' ) );
		}

		$payment = $this->database->lock_payment_by_reference( sanitize_text_field( (string) $payment_reference ) );

		if ( null === $payment || ! $this->can_access_payment( absint( $customer_id ), $payment ) ) {
			$this->database->rollback();
			return new WP_Error( 'arvan_reseller_payment_not_found', __( 'Payment was not found.', 'arvan-reseller' ) );
		}

		if ( 'pending' !== $payment['status'] || ! $this->database->transition_payment_status( (int) $payment['id'], 'pending', $status ) ) {
			$this->database->rollback();
			return new WP_Error( 'arvan_reseller_payment_not_closable', __( 'Payment cannot be changed in its current state.', 'arvan-reseller' ) );
		}

		$this->database->create_audit_log( 'payment_' . $status, 'payment', (string) $payment['id'], array(), (int) $payment['customer_id'] );
		$this->database->commit();
		$payment['status'] = $status;

		return $this->serialize_payment( $payment, false );
	}

	/** Reverse a completed Mock payment as an administrator. */
	public function refund_payment( $payment_reference ) {
		if ( ! Arvan_Reseller_Security::can_manage_plugin() ) {
			return new WP_Error( 'arvan_reseller_forbidden', __( 'Administrator capability is required.', 'arvan-reseller' ) );
		}

		if ( ! $this->database->begin_transaction() ) {
			return new WP_Error( 'arvan_reseller_transaction_start_failed', __( 'Unable to start the refund.', 'arvan-reseller' ) );
		}

		$payment = $this->database->lock_payment_by_reference( sanitize_text_field( (string) $payment_reference ) );
		if ( null === $payment ) {
			$this->database->rollback();
			return new WP_Error( 'arvan_reseller_payment_not_found', __( 'Payment was not found.', 'arvan-reseller' ) ); }
		if ( 'refunded' === $payment['status'] ) {
			$this->database->commit();
			return $this->serialize_payment( $payment, true ); }
		if ( 'completed' !== $payment['status'] ) {
			$this->database->rollback();
			return new WP_Error( 'arvan_reseller_payment_not_refundable', __( 'Only a completed payment can be refunded.', 'arvan-reseller' ) ); }

		$debit = $this->wallet->debit_minor( (int) $payment['customer_id'], (int) $payment['amount_minor'], 'payment_refund', (string) $payment['payment_reference'], __( 'Mock payment refund', 'arvan-reseller' ), (string) $payment['currency'] );
		if ( is_wp_error( $debit ) ) {
			$this->database->rollback();
			return $debit; }
		if ( ! $this->database->transition_payment_status( (int) $payment['id'], 'completed', 'refunded' ) ) {
			$this->database->rollback();
			return new WP_Error( 'arvan_reseller_payment_transition_failed', __( 'Refund could not be committed.', 'arvan-reseller' ) ); }
		if ( false === $this->database->create_audit_log( 'payment_refunded', 'payment', (string) $payment['id'], array( 'transaction_id' => $debit['transaction_id'] ), (int) $payment['customer_id'] ) ) {
			$this->database->rollback();
			return new WP_Error( 'arvan_reseller_audit_failed', __( 'Refund audit record could not be written.', 'arvan-reseller' ) ); }
		if ( ! $this->database->commit() ) {
			return new WP_Error( 'arvan_reseller_transaction_commit_failed', __( 'Refund could not be committed.', 'arvan-reseller' ) ); }
		$payment['status'] = 'refunded';
		$result            = $this->serialize_payment( $payment, false );
		$result['wallet']  = $debit;
		return $result;
	}

	/** @return array|WP_Error */
	public function get_payment( $customer_id, $reference ) {
		$payment = $this->database->get_payment_by_reference( sanitize_text_field( (string) $reference ) );

		return null !== $payment && $this->can_access_payment( absint( $customer_id ), $payment ) ? $this->serialize_payment( $payment, false ) : new WP_Error( 'arvan_reseller_payment_not_found', __( 'Payment was not found.', 'arvan-reseller' ) );
	}

	/** @return array */
	public function list_customer_payments( $customer_id, $limit = 50 ) {
		return array_map( array( $this, 'serialize_payment_row' ), $this->database->get_payments_by_customer_id( absint( $customer_id ), $limit ) );
	}

	/** @return array */
	public function list_admin_payments( $status = '', $customer_id = 0, $limit = 100 ) {
		if ( ! Arvan_Reseller_Security::can_manage_plugin() ) {
			return array();
		}

		$where = array();
		if ( '' !== $status && Arvan_Reseller_Status::is_valid( 'payments', $status ) ) {
			$where['status'] = $status;
		}
		if ( $customer_id > 0 ) {
			$where['customer_id'] = absint( $customer_id );
		}

		return array_map( array( $this, 'serialize_payment_row' ), $this->database->get_results_by( 'payments', $where, $limit ) );
	}

	/** @return array */
	public function serialize_payment_row( array $payment ) {
		return $this->serialize_payment( $payment, false );
	}

	/** @return bool */
	private function can_access_payment( $customer_id, array $payment ) {
		return (int) $payment['customer_id'] === (int) $customer_id || Arvan_Reseller_Security::can_manage_plugin();
	}

	/** @return array */
	private function serialize_payment( $payment, $idempotent ) {
		if ( ! is_array( $payment ) ) {
			return array();
		}

		return array(
			'id'                => (int) $payment['id'],
			'payment_reference' => (string) $payment['payment_reference'],
			'amount'            => Arvan_Reseller_Money::format( (int) $payment['amount_minor'] ),
			'amount_minor'      => (int) $payment['amount_minor'],
			'currency'          => (string) $payment['currency'],
			'status'            => (string) $payment['status'],
			'provider'          => (string) $payment['provider'],
			'created_at'        => (string) $payment['created_at'],
			'expires_at'        => isset( $payment['expires_at'] ) ? (string) $payment['expires_at'] : '',
			'completed_at'      => isset( $payment['completed_at'] ) ? (string) $payment['completed_at'] : '',
			'idempotent'        => (bool) $idempotent,
		);
	}

	/** @return int */
	private function get_minimum_minor() {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$value    = Arvan_Reseller_Money::to_minor( isset( $settings['minimum_topup'] ) ? (string) $settings['minimum_topup'] : '1' );

		return is_wp_error( $value ) || $value <= 0 ? Arvan_Reseller_Money::scale() : $value;
	}

	/** @return int */
	private function get_maximum_minor() {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$value    = Arvan_Reseller_Money::to_minor( isset( $settings['maximum_topup'] ) ? (string) $settings['maximum_topup'] : '1000000000' );

		return is_wp_error( $value ) || $value <= 0 ? 1000000000 * Arvan_Reseller_Money::scale() : $value;
	}

	/** @return string */
	private function get_currency() {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$currency = isset( $settings['currency'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $settings['currency'] ) ) : 'IRR';

		return 3 === strlen( $currency ) ? $currency : 'IRR';
	}
}
