<?php
/** Small fixed-window REST rate limiter. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Arvan_Reseller_Rate_Limiter {
	public static function allow( $scope, $identity, $limit = 30, $window = MINUTE_IN_SECONDS ) {
		$key    = 'arvan_reseller_rate_' . hash( 'sha256', sanitize_key( $scope ) . '|' . (string) $identity );
		$now    = time();
		$record = get_transient( $key );
		if ( ! is_array( $record ) || $now >= (int) ( $record['reset'] ?? 0 ) ) {
			set_transient(
				$key,
				array(
					'count' => 1,
					'reset' => $now + absint( $window ),
				),
				absint( $window )
			);
			return true; }
		if ( (int) $record['count'] >= absint( $limit ) ) {
			return false; }
		$record['count'] = (int) $record['count'] + 1;
		set_transient( $key, $record, max( 1, (int) $record['reset'] - $now ) );
		return true;
	}
}
