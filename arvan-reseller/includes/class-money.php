<?php
/**
 * Integer-only money helpers.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Money {

	/**
	 * Convert a decimal amount into internal minor units without using floats.
	 *
	 * Values with more than four fractional digits are rounded half up.
	 * Scientific notation is intentionally rejected.
	 *
	 * @param int|string $amount Decimal amount.
	 * @return int|WP_Error
	 */
	public static function to_minor( $amount ) {
		if ( is_int( $amount ) ) {
			return self::checked_multiply( $amount, self::scale() );
		}

		if ( ! is_string( $amount ) ) {
			return new WP_Error( 'arvan_reseller_invalid_money', __( 'Money amount must be a plain decimal value.', 'arvan-reseller' ) );
		}

		$value = trim( str_replace( ',', '', (string) $amount ) );

		if ( ! preg_match( '/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches ) ) {
			return new WP_Error( 'arvan_reseller_invalid_money', __( 'Money amount must be a plain decimal value.', 'arvan-reseller' ) );
		}

		$negative = '-' === $matches[1];
		$whole    = ltrim( $matches[2], '0' );
		$whole    = '' === $whole ? '0' : $whole;
		$fraction = isset( $matches[3] ) ? $matches[3] : '';
		$digits   = self::precision();

		if ( strlen( $whole ) > strlen( (string) PHP_INT_MAX ) - $digits ) {
			return new WP_Error( 'arvan_reseller_money_overflow', __( 'Money amount is too large.', 'arvan-reseller' ) );
		}

		$padded_fraction = str_pad( substr( $fraction, 0, $digits ), $digits, '0' );
		$minor_string    = $whole . $padded_fraction;
		$minor_string    = ltrim( $minor_string, '0' );
		$minor_string    = '' === $minor_string ? '0' : $minor_string;

		if ( strlen( $minor_string ) > strlen( (string) PHP_INT_MAX ) ||
			( strlen( $minor_string ) === strlen( (string) PHP_INT_MAX ) && strcmp( $minor_string, (string) PHP_INT_MAX ) > 0 ) ) {
			return new WP_Error( 'arvan_reseller_money_overflow', __( 'Money amount is too large.', 'arvan-reseller' ) );
		}

		$minor = (int) $minor_string;

		if ( strlen( $fraction ) > $digits && (int) $fraction[ $digits ] >= 5 ) {
			if ( PHP_INT_MAX === $minor ) {
				return new WP_Error( 'arvan_reseller_money_overflow', __( 'Money amount is too large.', 'arvan-reseller' ) );
			}

			++$minor;
		}

		return $negative ? -$minor : $minor;
	}

	/**
	 * Format internal minor units as a fixed decimal string.
	 *
	 * @param int $minor Minor units.
	 * @return string
	 */
	public static function format( $minor ) {
		$minor    = (int) $minor;
		$negative = $minor < 0;
		$absolute = abs( $minor );
		$scale    = self::scale();
		$whole    = intdiv( $absolute, $scale );
		$fraction = $absolute % $scale;

		return ( $negative ? '-' : '' ) . $whole . '.' . str_pad( (string) $fraction, self::precision(), '0', STR_PAD_LEFT );
	}

	/**
	 * Multiply a scaled quantity by a money value with half-up rounding.
	 *
	 * @param int $quantity_scaled Quantity scaled by ARVAN_RESELLER_MONEY_SCALE.
	 * @param int $unit_price_minor Unit price in minor units.
	 * @return int|WP_Error
	 */
	public static function multiply_scaled( $quantity_scaled, $unit_price_minor ) {
		$product = self::checked_multiply( (int) $quantity_scaled, (int) $unit_price_minor );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		return self::divide_half_up( $product, self::scale() );
	}

	/**
	 * Calculate a basis-point percentage of a minor-unit amount.
	 *
	 * @param int $amount_minor Amount in minor units.
	 * @param int $basis_points Percentage in basis points (20% = 2000).
	 * @return int|WP_Error
	 */
	public static function percentage( $amount_minor, $basis_points ) {
		$product = self::checked_multiply( (int) $amount_minor, (int) $basis_points );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		return self::divide_half_up( $product, 10000 );
	}

	/**
	 * Return the configured internal scale.
	 *
	 * @return int
	 */
	public static function scale() {
		return defined( 'ARVAN_RESELLER_MONEY_SCALE' ) ? (int) ARVAN_RESELLER_MONEY_SCALE : 10000;
	}

	/**
	 * Return decimal precision for the configured scale.
	 *
	 * @return int
	 */
	private static function precision() {
		return strlen( (string) self::scale() ) - 1;
	}

	/**
	 * Multiply two integers with overflow protection.
	 *
	 * @param int $left Left operand.
	 * @param int $right Right operand.
	 * @return int|WP_Error
	 */
	private static function checked_multiply( $left, $right ) {
		if ( 0 !== $left && abs( $right ) > intdiv( PHP_INT_MAX, abs( $left ) ) ) {
			return new WP_Error( 'arvan_reseller_money_overflow', __( 'Money calculation exceeded the supported range.', 'arvan-reseller' ) );
		}

		return $left * $right;
	}

	/**
	 * Divide integers with half-up rounding.
	 *
	 * @param int $numerator Numerator.
	 * @param int $denominator Positive denominator.
	 * @return int
	 */
	private static function divide_half_up( $numerator, $denominator ) {
		$negative  = $numerator < 0;
		$absolute  = abs( $numerator );
		$result    = intdiv( $absolute, $denominator );
		$remainder = $absolute % $denominator;

		if ( $remainder >= intdiv( $denominator, 2 ) ) {
			++$result;
		}

		return $negative ? -$result : $result;
	}
}
