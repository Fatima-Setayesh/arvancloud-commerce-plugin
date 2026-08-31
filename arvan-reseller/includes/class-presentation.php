<?php
/**
 * Shared presentation runtime, asset loading, and view rendering.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Presentation {

	/** @var array<string, bool> */
	private static $enqueued = array();

	/** @var bool */
	private static $theme_bootstrapped = false;

	/**
	 * Enqueue the shared runtime and one application entry point.
	 *
	 * @param string $context Application context.
	 * @return void
	 */
	public static function enqueue( $context ) {
		$context = 'admin' === $context ? 'admin' : 'customer';
		if ( ! empty( self::$enqueued[ $context ] ) ) {
			return;
		}
		self::$enqueued[ $context ] = true;
		$version = ARVAN_RESELLER_VERSION;

		wp_enqueue_style(
			'arvan-reseller-design-system',
			ARVAN_RESELLER_URL . 'assets/css/design-system.css',
			array(),
			$version
		);
		wp_enqueue_style(
			'arvan-reseller-' . $context,
			ARVAN_RESELLER_URL . ( 'admin' === $context ? 'admin/assets/css/admin-app.css' : 'frontend/assets/css/customer-app.css' ),
			array( 'arvan-reseller-design-system' ),
			$version
		);

		wp_enqueue_script(
			'arvan-reseller-rest-client',
			ARVAN_RESELLER_URL . 'assets/js/rest-client.js',
			array(),
			$version,
			true
		);
		wp_add_inline_script(
			'arvan-reseller-rest-client',
			'window.ArvanResellerRuntime = ' . wp_json_encode( self::runtime_config( $context ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
			'before'
		);
		wp_enqueue_script(
			'arvan-reseller-ui',
			ARVAN_RESELLER_URL . 'assets/js/ui.js',
			array( 'arvan-reseller-rest-client' ),
			$version,
			true
		);
		wp_enqueue_script(
			'arvan-reseller-' . $context . '-app',
			ARVAN_RESELLER_URL . ( 'admin' === $context ? 'admin/assets/js/admin-app.js' : 'frontend/assets/js/customer-app.js' ),
			array( 'arvan-reseller-ui' ),
			$version,
			true
		);
	}

	/**
	 * Render a presentation view inside the plugin directory.
	 *
	 * @param string $relative_file Plugin-relative view path.
	 * @param array  $variables     Variables exposed to the view.
	 * @return string
	 */
	public static function render( $relative_file, array $variables = array() ) {
		$base = wp_normalize_path( ARVAN_RESELLER_PATH );
		$file = wp_normalize_path( ARVAN_RESELLER_PATH . ltrim( (string) $relative_file, '/\\' ) );

		if ( 0 !== strpos( $file, $base ) || ! is_readable( $file ) ) {
			return '';
		}

		extract( $variables, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		ob_start();
		require $file;
		$markup = (string) ob_get_clean();

		if ( self::$theme_bootstrapped ) {
			return $markup;
		}

		self::$theme_bootstrapped = true;
		return '<script>(function(){try{var m=localStorage.getItem("arvan-reseller-theme")||"system";if(["light","dark","system"].indexOf(m)<0){m="system";}var t=m==="system"?(matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):m;document.documentElement.dataset.arTheme=t;document.documentElement.dataset.arThemeMode=m;}catch(e){}}());</script>' . $markup;
	}

	/**
	 * Return non-secret browser runtime configuration.
	 *
	 * @param string $context Application context.
	 * @return array
	 */
	private static function runtime_config( $context ) {
		$settings = wp_parse_args(
			get_option( 'arvan_reseller_settings', array() ),
			array(
				'company_name'             => '',
				'company_logo_url'         => '',
				'mode'                     => 'mock',
				'currency'                 => 'IRR',
				'region'                   => 'ir-thr-mock',
				'availability_zone'        => 'mock-zone-1',
				'minimum_topup'            => '1.0000',
				'maximum_topup'            => '1000000000.0000',
				'default_wallet_threshold' => '0.0000',
			)
		);
		$user     = wp_get_current_user();
		$pages    = get_option( 'arvan_reseller_pages', array() );

		return array(
			'context'      => $context,
			'restRoot'     => esc_url_raw( rest_url( 'arvan-reseller/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'homeUrl'      => esc_url_raw( home_url( '/' ) ),
			'adminUrl'     => esc_url_raw( admin_url( 'admin.php?page=arvan-reseller' ) ),
			'loginUrl'     => esc_url_raw( wp_login_url( self::portal_url( $pages ) ) ),
			'logoutUrl'    => esc_url_raw( wp_logout_url( home_url( '/' ) ) ),
			'portalUrl'    => esc_url_raw( self::portal_url( $pages ) ),
			'storeUrl'     => esc_url_raw( self::store_url( $pages ) ),
			'isLoggedIn'   => is_user_logged_in(),
			'canManage'    => current_user_can( 'manage_arvan_reseller' ),
			'user'         => array(
				'id'   => (int) $user->ID,
				'name' => sanitize_text_field( (string) $user->display_name ),
			),
			'settings'     => array(
				'companyName'      => sanitize_text_field( (string) $settings['company_name'] ),
				'companyLogoUrl'   => esc_url_raw( (string) $settings['company_logo_url'] ),
				'mode'             => 'live' === $settings['mode'] ? 'live' : 'mock',
				'currency'         => strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $settings['currency'] ) ),
				'region'           => sanitize_key( (string) $settings['region'] ),
				'availabilityZone' => sanitize_text_field( (string) $settings['availability_zone'] ),
				'minimumTopup'     => (string) $settings['minimum_topup'],
				'maximumTopup'     => (string) $settings['maximum_topup'],
				'walletThreshold'  => (string) $settings['default_wallet_threshold'],
			),
			'locale'       => 'fa-IR',
			'timeoutMs'    => 20000,
			'pollInterval' => 8000,
		);
	}

	/** @param array $pages Stored page IDs. @return string */
	private static function portal_url( array $pages ) {
		$id = isset( $pages['portal'] ) ? absint( $pages['portal'] ) : 0;
		return $id && 'trash' !== get_post_status( $id ) ? get_permalink( $id ) : home_url( '/arvan-portal/' );
	}

	/** @param array $pages Stored page IDs. @return string */
	private static function store_url( array $pages ) {
		$id = isset( $pages['store'] ) ? absint( $pages['store'] ) : 0;
		return $id && 'trash' !== get_post_status( $id ) ? get_permalink( $id ) : home_url( '/cloud-server/' );
	}
}
