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
			return Arvan_Reseller_Presentation::render(
				'frontend/views/auth.php',
				array(
					'redirect_to'    => get_permalink(),
					'can_register'   => (bool) get_option( 'users_can_register' ),
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
