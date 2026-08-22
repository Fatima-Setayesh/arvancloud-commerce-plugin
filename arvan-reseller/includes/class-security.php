<?php
/**
 * Security, authorization, secret storage, and redaction helpers.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Security {

	const ENVELOPE_PREFIX = 'arv2:';

	/** @return bool */
	public static function can_manage_plugin( $capability = 'manage_arvan_reseller' ) {
		return current_user_can( $capability ) || ( 'manage_arvan_reseller' === $capability && current_user_can( 'manage_options' ) );
	}

	/** @throws Exception When access is denied. */
	public static function assert_capability( $capability = 'manage_arvan_reseller' ) {
		if ( ! self::can_manage_plugin( $capability ) ) {
			throw new Exception( esc_html__( 'You are not allowed to perform this action.', 'arvan-reseller' ) );
		}
	}

	/** @return bool */
	public static function verify_nonce( $nonce, $action ) {
		$verified = wp_verify_nonce( (string) $nonce, $action );

		return 1 === $verified || 2 === $verified;
	}

	/** @throws Exception When nonce validation fails. */
	public static function assert_nonce( $nonce, $action ) {
		if ( ! self::verify_nonce( $nonce, $action ) ) {
			throw new Exception( esc_html__( 'Security validation failed.', 'arvan-reseller' ) );
		}
	}

	/** @return bool */
	public static function verify_rest_nonce() {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		return is_user_logged_in() && self::verify_nonce( $nonce, 'wp_rest' );
	}

	/** @return string */
	public static function sanitize_text( $value ) {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/** @return string */
	public static function sanitize_textarea( $value ) {
		return sanitize_textarea_field( wp_unslash( (string) $value ) );
	}

	/** @return int */
	public static function sanitize_absint( $value ) {
		return absint( $value );
	}

	/**
	 * Sanitize a fixed decimal without float arithmetic.
	 *
	 * @return string
	 */
	public static function sanitize_decimal( $value, $precision = 4 ) {
		$normalized = is_string( $value ) ? str_replace( ',', '', wp_unslash( $value ) ) : (string) $value;
		$minor      = Arvan_Reseller_Money::to_minor( $normalized );
		$precision  = max( 0, min( 4, absint( $precision ) ) );

		if ( is_wp_error( $minor ) ) {
			$minor = 0;
		}

		$rounding_factor = 10 ** ( 4 - $precision );
		$negative        = $minor < 0;
		$absolute        = abs( $minor );
		$rounded         = intdiv( $absolute + intdiv( $rounding_factor, 2 ), $rounding_factor ) * $rounding_factor;
		$formatted       = Arvan_Reseller_Money::format( $negative ? -$rounded : $rounded );

		return 0 === $precision ? strstr( $formatted, '.', true ) : substr( $formatted, 0, strlen( $formatted ) - ( 4 - $precision ) );
	}

	/** @return string */
	public static function sanitize_key( $value ) {
		return sanitize_key( wp_unslash( (string) $value ) );
	}

	/** @return string */
	public static function escape_text( $value ) {
		return esc_html( (string) $value );
	}

	/** @return string */
	public static function escape_url( $value ) {
		return esc_url( (string) $value );
	}

	/**
	 * Normalize Machine User API key material without logging it.
	 *
	 * @return string
	 */
	public static function sanitize_api_key( $api_key ) {
		$value = trim( (string) wp_unslash( $api_key ) );
		$value = preg_replace( '/^apikey\s+/i', '', $value );

		return preg_match( '/^[A-Za-z0-9_\-.~]{16,255}$/', $value ) ? $value : '';
	}

	/**
	 * Store a versioned authenticated-encryption envelope with autoload off.
	 *
	 * @return bool
	 */
	public static function store_encrypted_option( $option_name, $api_key ) {
		$sanitized = self::sanitize_api_key( $api_key );

		if ( '' === $sanitized ) {
			return false;
		}

		$encrypted = self::encrypt( $sanitized );

		if ( is_wp_error( $encrypted ) ) {
			return false;
		}

		$result = update_option( $option_name, $encrypted, false );
		self::disable_option_autoload( $option_name );

		return (bool) $result || get_option( $option_name, '' ) === $encrypted;
	}

	/**
	 * Rotate a stored key after the replacement is validated.
	 *
	 * @return bool
	 */
	public static function rotate_encrypted_option( $option_name, $api_key ) {
		return self::store_encrypted_option( $option_name, $api_key );
	}

	/** @return bool */
	public static function delete_encrypted_option( $option_name ) {
		return delete_option( $option_name );
	}

	/**
	 * Decrypt an option and transparently migrate a valid legacy CBC value.
	 *
	 * @return string
	 */
	public static function get_decrypted_option( $option_name ) {
		$stored = get_option( $option_name, '' );

		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}

		$decrypted = self::decrypt( $stored );

		if ( is_wp_error( $decrypted ) ) {
			return '';
		}

		if ( 0 !== strpos( $stored, self::ENVELOPE_PREFIX ) && '' !== $decrypted ) {
			self::store_encrypted_option( $option_name, $decrypted );
		}

		return $decrypted;
	}

	/**
	 * Encrypt using sodium secretbox or AES-256-GCM with a random nonce.
	 *
	 * @return string|WP_Error
	 */
	public static function encrypt( $value ) {
		if ( '' === (string) $value ) {
			return new WP_Error( 'arvan_reseller_empty_secret', __( 'Secret value cannot be empty.', 'arvan-reseller' ) );
		}

		$key = self::derive_key();

		try {
			if ( function_exists( 'sodium_crypto_secretbox' ) ) {
				$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
				$ciphertext = sodium_crypto_secretbox( (string) $value, $nonce, $key );
				$envelope   = array(
					'v'          => 2,
					'alg'        => 'secretbox',
					'nonce'      => base64_encode( $nonce ),
					'ciphertext' => base64_encode( $ciphertext ),
				);
			} elseif ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
				$nonce      = random_bytes( 12 );
				$tag        = '';
				$ciphertext = openssl_encrypt( (string) $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'arvan-reseller-v2', 16 );

				if ( false === $ciphertext ) {
					throw new RuntimeException( 'Encryption failed.' );
				}

				$envelope = array(
					'v'          => 2,
					'alg'        => 'aes-256-gcm',
					'nonce'      => base64_encode( $nonce ),
					'ciphertext' => base64_encode( $ciphertext ),
					'tag'        => base64_encode( (string) $tag ),
				);
			} else {
				return new WP_Error( 'arvan_reseller_encryption_unavailable', __( 'Authenticated encryption is unavailable on this server.', 'arvan-reseller' ) );
			}
		} catch ( Throwable $exception ) {
			return new WP_Error( 'arvan_reseller_encryption_failed', __( 'Secret encryption failed.', 'arvan-reseller' ) );
		}

		return self::ENVELOPE_PREFIX . base64_encode( wp_json_encode( $envelope ) );
	}

	/**
	 * Decrypt and authenticate a v2 envelope, or read a legacy CBC value.
	 *
	 * @return string|WP_Error
	 */
	public static function decrypt( $encrypted_value ) {
		if ( 0 !== strpos( (string) $encrypted_value, self::ENVELOPE_PREFIX ) ) {
			return self::decrypt_legacy( (string) $encrypted_value );
		}

		$encoded  = substr( (string) $encrypted_value, strlen( self::ENVELOPE_PREFIX ) );
		$json     = base64_decode( $encoded, true );
		$envelope = false !== $json ? json_decode( $json, true ) : null;

		if ( ! is_array( $envelope ) || 2 !== (int) ( $envelope['v'] ?? 0 ) || empty( $envelope['alg'] ) || empty( $envelope['nonce'] ) || empty( $envelope['ciphertext'] ) ) {
			return new WP_Error( 'arvan_reseller_invalid_secret_envelope', __( 'Stored secret is invalid.', 'arvan-reseller' ) );
		}

		$nonce      = base64_decode( (string) $envelope['nonce'], true );
		$ciphertext = base64_decode( (string) $envelope['ciphertext'], true );

		if ( false === $nonce || false === $ciphertext ) {
			return new WP_Error( 'arvan_reseller_invalid_secret_envelope', __( 'Stored secret is invalid.', 'arvan-reseller' ) );
		}

		$key       = self::derive_key();
		$decrypted = false;

		if ( 'secretbox' === $envelope['alg'] && function_exists( 'sodium_crypto_secretbox_open' ) && strlen( $nonce ) === SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			$decrypted = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
		} elseif ( 'aes-256-gcm' === $envelope['alg'] && function_exists( 'openssl_decrypt' ) && ! empty( $envelope['tag'] ) ) {
			$tag = base64_decode( (string) $envelope['tag'], true );

			if ( false !== $tag ) {
				$decrypted = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'arvan-reseller-v2' );
			}
		}

		return false === $decrypted ? new WP_Error( 'arvan_reseller_secret_authentication_failed', __( 'Stored secret could not be authenticated.', 'arvan-reseller' ) ) : (string) $decrypted;
	}

	/**
	 * Recursively redact secrets and unsafe response material for audit logs.
	 *
	 * @param mixed $value Value to redact.
	 * @return mixed
	 */
	public static function redact( $value ) {
		if ( is_array( $value ) ) {
			$output = array();

			foreach ( $value as $key => $item ) {
				$output[ $key ] = preg_match( '/token|secret|password|authorization|api[_-]?key|raw_body|sql|stack|path/i', (string) $key ) ? '[REDACTED]' : self::redact( $item );
			}

			return $output;
		}

		if ( is_string( $value ) ) {
			$value = preg_replace( '/\b(?:apikey|bearer)\s+[A-Za-z0-9_.~\-]+/i', '[REDACTED]', $value );
		}

		return $value;
	}

	/** @return string */
	private static function derive_key() {
		$material = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' );
		$salt     = hash( 'sha256', wp_salt( 'nonce' ), true );

		return hash_hkdf( 'sha256', $material, 32, 'arvan-reseller-secret-v2', $salt );
	}

	/** @return string|WP_Error */
	private static function decrypt_legacy( $encrypted_value ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return new WP_Error( 'arvan_reseller_legacy_decryption_unavailable', __( 'Legacy secret decryption is unavailable.', 'arvan-reseller' ) );
		}

		$iv        = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
		$decoded   = base64_decode( $encrypted_value, true );
		$decrypted = false !== $decoded ? openssl_decrypt( $decoded, 'AES-256-CBC', wp_salt( 'auth' ), 0, $iv ) : false;

		return false === $decrypted ? new WP_Error( 'arvan_reseller_legacy_decryption_failed', __( 'Stored legacy secret could not be decrypted.', 'arvan-reseller' ) ) : (string) $decrypted;
	}

	/** @return void */
	private static function disable_option_autoload( $option_name ) {
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( $option_name, false );
			return;
		}

		global $wpdb;
		$wpdb->update( $wpdb->options, array( 'autoload' => 'no' ), array( 'option_name' => (string) $option_name ), array( '%s' ), array( '%s' ) );
	}
}
