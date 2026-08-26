<?php
/**
 * Admin operations-console navigation and page setup controller.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Admin_Menu {

	/** @var Arvan_Reseller_Settings */
	private $settings;

	/** @param Arvan_Reseller_Settings $settings Settings controller. */
	public function __construct( Arvan_Reseller_Settings $settings ) {
		$this->settings = $settings;
	}

	/** Register admin hooks. @return void */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'admin_post_arvan_reseller_create_pages', array( $this, 'create_portal_pages' ) );
	}

	/** Enqueue console assets before the admin page head is printed. @return void */
	public function maybe_enqueue_assets() {
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 === strpos( $slug, 'arvan-reseller' ) ) {
			Arvan_Reseller_Presentation::enqueue( 'admin' );
		}
	}

	/** Register the complete operations console. @return void */
	public function register_menu() {
		if ( ! Arvan_Reseller_Security::can_manage_plugin( $this->settings->get_capability() ) ) {
			return;
		}

		$capability = $this->settings->get_capability();
		$callback   = array( $this, 'render_app' );

		add_menu_page(
			esc_html__( 'Arvan Reseller', 'arvan-reseller' ),
			esc_html__( 'Arvan Reseller', 'arvan-reseller' ),
			$capability,
			'arvan-reseller',
			$callback,
			'dashicons-cloud',
			56
		);

		$pages = array(
			'arvan-reseller'             => array( __( 'Operations Dashboard', 'arvan-reseller' ), __( 'Dashboard', 'arvan-reseller' ) ),
			'arvan-reseller-setup'       => array( __( 'Reseller Setup', 'arvan-reseller' ), __( 'Setup', 'arvan-reseller' ) ),
			'arvan-reseller-customers'   => array( __( 'Customers', 'arvan-reseller' ), __( 'Customers', 'arvan-reseller' ) ),
			'arvan-reseller-payments'    => array( __( 'Payments', 'arvan-reseller' ), __( 'Payments', 'arvan-reseller' ) ),
			'arvan-reseller-orders'      => array( __( 'Orders', 'arvan-reseller' ), __( 'Orders', 'arvan-reseller' ) ),
			'arvan-reseller-resources'   => array( __( 'Cloud Servers', 'arvan-reseller' ), __( 'Cloud Servers', 'arvan-reseller' ) ),
			'arvan-reseller-usage'       => array( __( 'Usage and Billing', 'arvan-reseller' ), __( 'Usage / Billing', 'arvan-reseller' ) ),
			'arvan-reseller-settlements' => array( __( 'Internal Settlements', 'arvan-reseller' ), __( 'Settlements', 'arvan-reseller' ) ),
			'arvan-reseller-health'      => array( __( 'System Health', 'arvan-reseller' ), __( 'Health', 'arvan-reseller' ) ),
			'arvan-reseller-audit'       => array( __( 'Audit Log', 'arvan-reseller' ), __( 'Audit', 'arvan-reseller' ) ),
			'arvan-reseller-settings'    => array( __( 'Settings', 'arvan-reseller' ), __( 'Settings', 'arvan-reseller' ) ),
		);

		foreach ( $pages as $slug => $labels ) {
			add_submenu_page(
				'arvan-reseller',
				esc_html( $labels[0] ),
				esc_html( $labels[1] ),
				$capability,
				$slug,
				$callback
			);
		}
	}

	/** Render a page of the operations console. @return void */
	public function render_app() {
		Arvan_Reseller_Security::assert_capability( $this->settings->get_capability() );
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'arvan-reseller'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = $this->page_key( $slug );

		Arvan_Reseller_Presentation::enqueue( 'admin' );
		echo Arvan_Reseller_Presentation::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'admin/views/app.php',
			array(
				'page'         => $page,
				'settings'     => $this->settings->get_settings(),
				'create_nonce' => wp_create_nonce( 'arvan_reseller_create_pages' ),
			)
		);
	}

	/** Create required theme-independent pages without duplication. @return void */
	public function create_portal_pages() {
		Arvan_Reseller_Security::assert_capability( $this->settings->get_capability() );
		check_admin_referer( 'arvan_reseller_create_pages' );

		$pages = Arvan_Reseller_Activator::create_customer_pages();
		if ( is_wp_error( $pages ) ) {
				wp_die( esc_html__( 'The customer pages could not be created safely.', 'arvan-reseller' ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'arvan-reseller-setup', 'pages-created' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** @param string $slug Menu slug. @return string */
	private function page_key( $slug ) {
		$map = array(
			'arvan-reseller'             => 'dashboard',
			'arvan-reseller-setup'       => 'setup',
			'arvan-reseller-customers'   => 'customers',
			'arvan-reseller-payments'    => 'payments',
			'arvan-reseller-orders'      => 'orders',
			'arvan-reseller-resources'   => 'resources',
			'arvan-reseller-usage'       => 'usage',
			'arvan-reseller-settlements' => 'settlements',
			'arvan-reseller-health'      => 'health',
			'arvan-reseller-audit'       => 'audit',
			'arvan-reseller-settings'    => 'settings',
		);
		return isset( $map[ $slug ] ) ? $map[ $slug ] : 'dashboard';
	}
}
