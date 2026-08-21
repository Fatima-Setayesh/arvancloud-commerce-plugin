<?php
/**
 * Admin menu controller.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Admin_Menu {

	/**
	 * Settings controller.
	 *
	 * @var Arvan_Reseller_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Arvan_Reseller_Settings $settings Settings controller.
	 */
	public function __construct( Arvan_Reseller_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register plugin admin pages.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( ! Arvan_Reseller_Security::can_manage_plugin( $this->settings->get_capability() ) ) {
			return;
		}

		add_menu_page(
			esc_html__( 'Arvan Reseller', 'arvan-reseller' ),
			esc_html__( 'Arvan Reseller', 'arvan-reseller' ),
			$this->settings->get_capability(),
			'arvan-reseller',
			array( $this, 'render_settings_page' ),
			'dashicons-cloud'
		);

		add_submenu_page(
			'arvan-reseller',
			esc_html__( 'Settings', 'arvan-reseller' ),
			esc_html__( 'Settings', 'arvan-reseller' ),
			$this->settings->get_capability(),
			$this->settings->get_page_slug(),
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'arvan-reseller',
			esc_html__( 'Customers', 'arvan-reseller' ),
			esc_html__( 'Customers', 'arvan-reseller' ),
			$this->settings->get_capability(),
			'arvan-reseller-customers',
			array( $this, 'render_placeholder_page' )
		);

		add_submenu_page(
			'arvan-reseller',
			esc_html__( 'Resources', 'arvan-reseller' ),
			esc_html__( 'Resources', 'arvan-reseller' ),
			$this->settings->get_capability(),
			'arvan-reseller-resources',
			array( $this, 'render_placeholder_page' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		Arvan_Reseller_Security::assert_capability( $this->settings->get_capability() );

		$view_file = ARVAN_RESELLER_PATH . 'admin/views/settings-page.php';

		if ( file_exists( $view_file ) ) {
			$page_slug = $this->settings->get_page_slug();
			require $view_file;
		}
	}

	/**
	 * Render a placeholder admin page.
	 *
	 * @return void
	 */
	public function render_placeholder_page() {
		Arvan_Reseller_Security::assert_capability( $this->settings->get_capability() );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Arvan Reseller', 'arvan-reseller' ) . '</h1>';
		echo '<p>' . esc_html__( 'This section is reserved for a future admin module.', 'arvan-reseller' ) . '</p>';
		echo '</div>';
	}
}
