<?php
/**
 * Security helpers.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Security {

	/**
	 * Check whether the current user can manage plugin operations.
	 *
	 * @param string $capability Required capability.
	 * @return bool
	 */
	public static function can_manage_plugin( $capability = 'manage_options' ) {
		return current_user_can( $capability );
	}

	/**
	 * Assert a capability requirement.
	 *
	 * @param string $capability Required capability.
	 * @throws Exception When the current user lacks access.
	 * @return void
	 */
	public static function assert_capability( $capability = 'manage_options' ) {
		if ( ! self::can_manage_plugin( $capability ) ) {
			throw new Exception( esc_html__( 'You are not allowed to perform this action.', 'arvan-reseller' ) );
		}
	}

	/**
	 * Verify a nonce value.
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 * @return bool
	 */
	public static function verify_nonce( $nonce, $action ) {
		$verified = wp_verify_nonce( (string) $nonce, $action );

		return 1 === $verified || 2 === $verified;
	}

	/**
	 * Assert nonce validity.
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 * @throws Exception When the nonce is invalid.
	 * @return void
	 */
	public static function assert_nonce( $nonce, $action ) {
		if ( ! self::verify_nonce( $nonce, $action ) ) {
			throw new Exception( esc_html__( 'Security validation failed.', 'arvan-reseller' ) );
		}
	}

	/**
	 * Sanitize plain text.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function sanitize_text( $value ) {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitize textarea content.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function sanitize_textarea( $value ) {
		return sanitize_textarea_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitize a positive integer.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return int
	 */
	public static function sanitize_absint( $value ) {
		return absint( $value );
	}

	/**
	 * Sanitize a decimal amount.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param int   $precision Decimal precision.
	 * @return string
	 */
	public static function sanitize_decimal( $value, $precision = 4 ) {
		$normalized = is_string( $value ) ? str_replace( ',', '', wp_unslash( $value ) ) : (string) $value;
		$number     = is_numeric( $normalized ) ? (float) $normalized : 0.0;

		return number_format( $number, $precision, '.', '' );
	}

	/**
	 * Sanitize a key.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function sanitize_key( $value ) {
		return sanitize_key( wp_unslash( (string) $value ) );
	}

	/**
	 * Escape plain text.
	 *
	 * @param mixed $value Value to escape.
	 * @return string
	 */
	public static function escape_text( $value ) {
		return esc_html( (string) $value );
	}

	/**
	 * Escape a URL.
	 *
	 * @param mixed $value Value to escape.
	 * @return string
	 */
	public static function escape_url( $value ) {
		return esc_url( (string) $value );
	}

	/**
	 * Sanitize API key material.
	 *
	 * @param mixed $api_key API key.
	 * @return string
	 */
	public static function sanitize_api_key( $api_key ) {
		return preg_replace( '/[^A-Za-z0-9_\-\.\~]/', '', trim( (string) wp_unslash( $api_key ) ) );
	}

	/**
	 * Store an encrypted option value.
	 *
	 * @param string $option_name Option name.
	 * @param string $api_key Raw API key.
	 * @return bool
	 */
	public static function store_encrypted_option( $option_name, $api_key ) {
		$sanitized = self::sanitize_api_key( $api_key );
		$encrypted = self::encrypt( $sanitized );

		if ( '' === $encrypted ) {
			return false;
		}

		return (bool) update_option( $option_name, $encrypted, false );
	}

	/**
	 * Read and decrypt an option value.
	 *
	 * @param string $option_name Option name.
	 * @return string
	 */
	public static function get_decrypted_option( $option_name ) {
		$stored_value = get_option( $option_name, '' );

		if ( ! is_string( $stored_value ) || '' === $stored_value ) {
			return '';
		}

		return self::decrypt( $stored_value );
	}

	/**
	 * Encrypt sensitive data.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function encrypt( $value ) {
		if ( '' === $value ) {
			return '';
		}

		$iv        = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', wp_salt( 'auth' ), 0, $iv );

		return false !== $encrypted ? base64_encode( $encrypted ) : '';
	}

	/**
	 * Decrypt sensitive data.
	 *
	 * @param string $encrypted_value Encrypted value.
	 * @return string
	 */
	public static function decrypt( $encrypted_value ) {
		if ( '' === $encrypted_value ) {
			return '';
		}

		$iv        = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
		$decoded   = base64_decode( $encrypted_value, true );
		$decrypted = false !== $decoded ? openssl_decrypt( $decoded, 'AES-256-CBC', wp_salt( 'auth' ), 0, $iv ) : false;

		return false !== $decrypted ? (string) $decrypted : '';
	}
}
