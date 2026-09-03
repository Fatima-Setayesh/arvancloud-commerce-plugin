<?php
/** Customer notification service. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Arvan_Reseller_Notifications {
	private $database;
	public function __construct( Arvan_Reseller_Database $database ) {
		$this->database = $database; }

	/** Send at most one low-balance email until the wallet is recharged above threshold. */
	public function maybe_send_low_balance( $customer_id, $currency = '' ) {
		$settings = get_option( 'arvan_reseller_settings', array() );
		$email_enabled = array_key_exists( 'email_notifications_enabled', $settings )
			? ! empty( $settings['email_notifications_enabled'] )
			: ! isset( $settings['notification_enabled'] ) || ! empty( $settings['notification_enabled'] );
		if ( ! $email_enabled ) {
			return array(
				'skipped' => true,
				'reason'  => 'email_disabled',
			);
		}
		$currency = $this->normalize_currency( $currency );
		$wallet   = $this->database->get_wallet_by_customer_id( absint( $customer_id ), $currency );
		if ( null === $wallet || (int) $wallet['threshold_minor'] <= 0 || (int) $wallet['balance_minor'] > (int) $wallet['threshold_minor'] || ! empty( $wallet['low_balance_notified'] ) ) {
			return array( 'skipped' => true ); }
		$event_key = hash( 'sha256', 'low-balance-v1|' . (int) $wallet['id'] . '|' . (string) $wallet['updated_at'] );
		$existing  = $this->database->get_notification_by_event_key( $event_key );
		if ( null !== $existing && 'failed' !== (string) $existing['status'] ) {
			return array(
				'id'         => (int) $existing['id'],
				'status'     => (string) $existing['status'],
				'idempotent' => true,
			); }
		$user    = get_userdata( absint( $customer_id ) );
		$email   = $user && is_email( $user->user_email ) ? $user->user_email : '';
		$payload = array(
			'balance'   => Arvan_Reseller_Money::format( (int) $wallet['balance_minor'] ),
			'threshold' => Arvan_Reseller_Money::format( (int) $wallet['threshold_minor'] ),
			'currency'  => (string) $wallet['currency'],
		);
		$id      = null !== $existing ? (int) $existing['id'] : $this->database->create_notification(
			array(
				'customer_id'       => absint( $customer_id ),
				'notification_type' => 'low_balance',
				'event_key'         => $event_key,
				'payload'           => wp_json_encode( $payload ),
			)
		);
		if ( false === $id ) {
			return new WP_Error( 'arvan_reseller_notification_create_failed', __( 'Unable to record the notification.', 'arvan-reseller' ) ); }
		$sent = '' !== $email && wp_mail(
			$email,
			__( 'Low wallet balance', 'arvan-reseller' ),
			sprintf(
				/* translators: 1: wallet balance, 2: currency code. */
				__( 'Your wallet balance is %1$s %2$s. Please recharge it.', 'arvan-reseller' ),
				$payload['balance'],
				$payload['currency']
			)
		);
		$this->database->update(
			'notifications',
			array(
				'status'     => $sent ? 'sent' : 'failed',
				'error_code' => $sent ? '' : 'mail_failed',
				'sent_at'    => $sent ? current_time( 'mysql', true ) : null,
			),
			array( 'id' => (int) $id )
		);
		if ( $sent ) {
			$this->database->mark_wallet_low_balance_notified( (int) $wallet['id'] );
		}
		$this->database->create_audit_log( $sent ? 'low_balance_email_sent' : 'low_balance_email_failed', 'wallet', (string) $wallet['id'], array( 'notification_id' => (int) $id ), absint( $customer_id ), 0 );
		return array(
			'id'         => (int) $id,
			'status'     => $sent ? 'sent' : 'failed',
			'idempotent' => null !== $existing,
		);
	}

	private function normalize_currency( $currency ) {
		if ( '' === (string) $currency ) {
			$settings = get_option( 'arvan_reseller_settings', array() );
			$currency = (string) ( $settings['currency'] ?? 'IRR' );
		}
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $currency ) );
		return 3 === strlen( $currency ) ? $currency : 'IRR';
	}
}
