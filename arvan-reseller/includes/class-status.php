<?php
/**
 * Allowlisted domain statuses persisted by the backend.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Status {

	/** @var array<string, string[]> */
	private static $allowed = array(
		'wallets'       => array( 'active', 'frozen', 'closed' ),
		'payments'      => array( 'pending', 'completed', 'failed', 'cancelled', 'expired', 'refunded' ),
		'orders'        => array( 'pending', 'provisioning', 'provisioned', 'failed', 'cancelled' ),
		'resources'     => array( 'pending', 'provisioning', 'active', 'provisioned', 'suspended', 'terminated', 'error' ),
		'invoices'      => array( 'draft', 'issued', 'paid', 'void' ),
		'settlements'   => array( 'pending', 'processing', 'completed', 'failed' ),
		'notifications' => array( 'pending', 'sent', 'failed' ),
	);

	/**
	 * Validate a status for a logical table.
	 *
	 * @param string $table Logical table name.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_valid( $table, $status ) {
		return isset( self::$allowed[ $table ] ) && in_array( (string) $status, self::$allowed[ $table ], true );
	}

	/**
	 * Return the allowlist for diagnostics and tests.
	 *
	 * @param string $table Logical table name.
	 * @return string[]
	 */
	public static function allowed_for( $table ) {
		return isset( self::$allowed[ $table ] ) ? self::$allowed[ $table ] : array();
	}
}
