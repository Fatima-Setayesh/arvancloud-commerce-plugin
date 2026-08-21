<?php
/**
 * Mock payment service.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Payment {

	/**
	 * Wallet service.
	 *
	 * @var Arvan_Reseller_Wallet
	 */
	private $wallet;

	/**
	 * Database service.
	 *
	 * @var Arvan_Reseller_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param Arvan_Reseller_Wallet   $wallet Wallet service.
	 * @param Arvan_Reseller_Database $database Database service.
	 */
	public function __construct( Arvan_Reseller_Wallet $wallet, Arvan_Reseller_Database $database ) {
		$this->wallet   = $wallet;
		$this->database = $database;
	}

	/**
	 * Create a mock payment intent.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param float $amount Payment amount.
	 * @return array|WP_Error
	 */
	public function create_payment( $customer_id, $amount ) {
		$customer_id = absint( $customer_id );
		$amount      = round( (float) $amount, 4 );

		if ( $customer_id <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_customer', __( 'Invalid customer ID for payment.', 'arvan-reseller' ) );
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_amount', __( 'Payment amount must be greater than zero.', 'arvan-reseller' ) );
		}

		$existing_payment = $this->get_existing_pending_payment( $customer_id, $amount );

		if ( null !== $existing_payment ) {
			$details = $this->decode_payment_details( $existing_payment );

			return array(
				'payment_id'   => (int) $existing_payment['id'],
				'reference_id' => (string) $existing_payment['order_reference'],
				'amount'       => isset( $details['amount'] ) ? round( (float) $details['amount'], 4 ) : $amount,
				'status'       => 'pending',
			);
		}

		$reference_id = $this->generate_payment_reference();
		$order_id     = $this->database->create_order(
			array(
				'customer_id'     => $customer_id,
				'product_type'    => 'wallet_topup',
				'status'          => 'pending',
				'resource_id'     => '',
				'order_reference' => $reference_id,
				'details'         => wp_json_encode(
					array(
						'type'         => 'payment',
						'amount'       => number_format( $amount, 4, '.', '' ),
						'status'       => 'pending',
						'customer_id'  => $customer_id,
						'created_at'   => current_time( 'mysql', true ),
					)
				),
			)
		);

		if ( false === $order_id ) {
			return new WP_Error(
				'arvan_reseller_payment_create_failed',
				__( 'Unable to create payment intent.', 'arvan-reseller' ),
				array(
					'db_error' => $this->database->get_last_error(),
				)
			);
		}

		return array(
			'payment_id'    => (int) $order_id,
			'reference_id'  => $reference_id,
			'amount'        => $amount,
			'status'        => 'pending',
		);
	}

	/**
	 * Confirm a mock payment and top up the wallet.
	 *
	 * @param string $payment_reference Payment reference.
	 * @return array|WP_Error
	 */
	public function confirm_payment( $payment_reference ) {
		$payment_reference = sanitize_text_field( wp_unslash( (string) $payment_reference ) );

		if ( '' === $payment_reference ) {
			return new WP_Error( 'arvan_reseller_invalid_payment_reference', __( 'Invalid payment reference.', 'arvan-reseller' ) );
		}

		$payment = $this->get_payment_by_reference( $payment_reference );

		if ( null === $payment ) {
			return new WP_Error( 'arvan_reseller_payment_not_found', __( 'Payment record not found.', 'arvan-reseller' ) );
		}

		if ( 'completed' === (string) $payment['status'] ) {
			return new WP_Error( 'arvan_reseller_payment_already_confirmed', __( 'Payment has already been confirmed.', 'arvan-reseller' ) );
		}

		$details = $this->decode_payment_details( $payment );
		$amount  = isset( $details['amount'] ) ? round( (float) $details['amount'], 4 ) : 0.0;

		if ( $amount <= 0 ) {
			return new WP_Error( 'arvan_reseller_invalid_payment_amount', __( 'Stored payment amount is invalid.', 'arvan-reseller' ) );
		}

		$wallet_result = $this->wallet->increase_balance(
			(int) $payment['customer_id'],
			$amount,
			'payment',
			$payment_reference,
			__( 'Wallet top-up payment', 'arvan-reseller' )
		);

		if ( is_wp_error( $wallet_result ) ) {
			return $wallet_result;
		}

		$details['status']       = 'completed';
		$details['confirmed_at'] = current_time( 'mysql', true );

		$updated = $this->database->update(
			'orders',
			array(
				'status'     => 'completed',
				'details'    => wp_json_encode( $details ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id' => (int) $payment['id'],
			),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error(
				'arvan_reseller_payment_confirm_update_failed',
				__( 'Payment was applied to the wallet, but payment status update failed.', 'arvan-reseller' ),
				array(
					'payment_id' => (int) $payment['id'],
					'db_error'   => $this->database->get_last_error(),
				)
			);
		}

		$payment['status']     = 'completed';
		$payment['updated_at'] = current_time( 'mysql', true );
		$payment['details']    = wp_json_encode( $details );

		return array(
			'payment'       => $payment,
			'wallet_result' => $wallet_result,
		);
	}

	/**
	 * Generate a unique payment reference.
	 *
	 * @return string
	 */
	private function generate_payment_reference() {
		return 'pay_' . wp_generate_password( 12, false, false ) . '_' . time();
	}

	/**
	 * Find a payment order by reference.
	 *
	 * @param string $payment_reference Payment reference.
	 * @return array|null
	 */
	private function get_payment_by_reference( $payment_reference ) {
		$orders = $this->database->get_results_by(
			'orders',
			array(
				'order_reference' => $payment_reference,
				'product_type'    => 'wallet_topup',
			),
			1
		);

		return ! empty( $orders[0] ) && is_array( $orders[0] ) ? $orders[0] : null;
	}

	/**
	 * Decode stored payment metadata.
	 *
	 * @param array $payment Payment row.
	 * @return array
	 */
	private function decode_payment_details( array $payment ) {
		$details = isset( $payment['details'] ) ? json_decode( (string) $payment['details'], true ) : array();

		return is_array( $details ) ? $details : array();
	}

	/**
	 * Find an existing pending payment intent for the same customer and amount.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param float $amount Payment amount.
	 * @return array|null
	 */
	private function get_existing_pending_payment( $customer_id, $amount ) {
		$orders = $this->database->get_results_by(
			'orders',
			array(
				'customer_id'  => absint( $customer_id ),
				'product_type' => 'wallet_topup',
				'status'       => 'pending',
			),
			20
		);

		$target_amount = number_format( round( (float) $amount, 4 ), 4, '.', '' );

		foreach ( $orders as $order ) {
			if ( ! is_array( $order ) ) {
				continue;
			}

			$details = $this->decode_payment_details( $order );

			if ( isset( $details['amount'] ) && number_format( round( (float) $details['amount'], 4 ), 4, '.', '' ) === $target_amount ) {
				return $order;
			}
		}

		return null;
	}
}
