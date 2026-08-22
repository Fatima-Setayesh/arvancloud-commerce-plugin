<?php
/**
 * Customer-facing application renderer.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Dashboard {

	/** Render the storefront. @return string */
	public function storefront() {
		Arvan_Reseller_Presentation::enqueue( 'customer' );
		return Arvan_Reseller_Presentation::render( 'frontend/views/storefront.php' );
	}

	/** Render the authenticated portal or secure WordPress auth entry. @return string */
	public function portal() {
		Arvan_Reseller_Presentation::enqueue( 'customer' );

		if ( ! is_user_logged_in() ) {
			$redirect_to     = get_permalink();
			$requested_route = isset( $_GET['ar-route'] ) ? sanitize_key( wp_unslash( $_GET['ar-route'] ) ) : '';
			$allowed_routes  = array( 'dashboard', 'services', 'create-server', 'wallet', 'billing', 'orders', 'notifications' );
			if ( in_array( $requested_route, $allowed_routes, true ) ) {
				$redirect_to = add_query_arg( 'ar-route', $requested_route, $redirect_to );
			}

			return Arvan_Reseller_Presentation::render(
				'frontend/views/auth.php',
				array(
					'redirect_to'      => $redirect_to,
					'can_register'     => (bool) get_option( 'users_can_register' ),
					'registration_url' => wp_registration_url(),
				)
			);
		}

		return Arvan_Reseller_Presentation::render(
			'frontend/views/portal.php',
			array( 'user' => wp_get_current_user() )
		);
	}
}
