<?php
/**
 * Admin settings controller.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Settings {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	private $option_name = 'arvan_reseller_settings';

	/**
	 * Encrypted API key option name.
	 *
	 * @var string
	 */
	private $api_key_option = 'arvan_reseller_api_key';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'arvan-reseller-settings';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private $capability = 'manage_options';

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'arvan_reseller_settings_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_defaults(),
			)
		);

		add_settings_section(
			'arvan_reseller_company_section',
			esc_html__( 'Company Information', 'arvan-reseller' ),
			array( $this, 'render_company_section' ),
			$this->page_slug
		);

		add_settings_section(
			'arvan_reseller_connection_section',
			esc_html__( 'Arvan Cloud Connection', 'arvan-reseller' ),
			array( $this, 'render_connection_section' ),
			$this->page_slug
		);

		add_settings_section(
			'arvan_reseller_reseller_section',
			esc_html__( 'Reseller Configuration', 'arvan-reseller' ),
			array( $this, 'render_reseller_section' ),
			$this->page_slug
		);

		add_settings_section(
			'arvan_reseller_wallet_section',
			esc_html__( 'Wallet Configuration', 'arvan-reseller' ),
			array( $this, 'render_wallet_section' ),
			$this->page_slug
		);

		$this->register_field( 'company_name', 'Company Name', 'text', 'arvan_reseller_company_section' );
		$this->register_field( 'company_logo_url', 'Logo URL', 'url', 'arvan_reseller_company_section' );
		$this->register_field( 'company_contact_info', 'Contact Information', 'textarea', 'arvan_reseller_company_section' );
		$this->register_field( 'api_base_url', 'API URL', 'url', 'arvan_reseller_connection_section' );
		$this->register_field( 'api_key', 'API Key', 'password', 'arvan_reseller_connection_section' );
		$this->register_field( 'reseller_share_percent', 'Reseller Share Percentage', 'number', 'arvan_reseller_reseller_section' );
		$this->register_field( 'default_wallet_threshold', 'Default Wallet Threshold', 'number', 'arvan_reseller_wallet_section' );
	}

	/**
	 * Register a settings field.
	 *
	 * @param string $key Field key.
	 * @param string $label Label text.
	 * @param string $type Field type.
	 * @param string $section Settings section ID.
	 * @return void
	 */
	private function register_field( $key, $label, $type, $section ) {
		add_settings_field(
			'arvan_reseller_' . $key,
			esc_html( $label ),
			array( $this, 'render_field' ),
			$this->page_slug,
			$section,
			array(
				'key'   => $key,
				'type'  => $type,
				'label' => $label,
			)
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw submitted values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! Arvan_Reseller_Security::can_manage_plugin( $this->capability ) ) {
			add_settings_error(
				$this->option_name,
				'arvan_reseller_settings_denied',
				esc_html__( 'You are not allowed to update these settings.', 'arvan-reseller' ),
				'error'
			);

			return $this->get_settings();
		}

		$current = $this->get_settings();
		$input   = is_array( $input ) ? $input : array();

		$sanitized = array(
			'version'                  => ARVAN_RESELLER_VERSION,
			'company_name'             => Arvan_Reseller_Security::sanitize_text( $this->get_input_value( $input, 'company_name' ) ),
			'company_logo_url'         => esc_url_raw( $this->get_input_value( $input, 'company_logo_url' ) ),
			'company_contact_info'     => Arvan_Reseller_Security::sanitize_textarea( $this->get_input_value( $input, 'company_contact_info' ) ),
			'api_base_url'             => esc_url_raw( $this->get_input_value( $input, 'api_base_url' ) ),
			'api_timeout'              => isset( $current['api_timeout'] ) ? absint( $current['api_timeout'] ) : 20,
			'usage_endpoint'           => isset( $current['usage_endpoint'] ) ? (string) $current['usage_endpoint'] : '',
			'resource_create_endpoint' => isset( $current['resource_create_endpoint'] ) ? (string) $current['resource_create_endpoint'] : '',
			'resource_get_endpoint'    => isset( $current['resource_get_endpoint'] ) ? (string) $current['resource_get_endpoint'] : '',
			'resource_suspend_endpoint' => isset( $current['resource_suspend_endpoint'] ) ? (string) $current['resource_suspend_endpoint'] : '',
			'resource_terminate_endpoint' => isset( $current['resource_terminate_endpoint'] ) ? (string) $current['resource_terminate_endpoint'] : '',
			'reseller_share_percent'   => min( 20, max( 0, (float) Arvan_Reseller_Security::sanitize_decimal( $this->get_input_value( $input, 'reseller_share_percent' ), 2 ) ) ),
			'default_wallet_threshold' => max( 0, (float) Arvan_Reseller_Security::sanitize_decimal( $this->get_input_value( $input, 'default_wallet_threshold' ), 4 ) ),
		);

		$api_key = Arvan_Reseller_Security::sanitize_api_key( $this->get_input_value( $input, 'api_key' ) );

		if ( '' !== $api_key ) {
			Arvan_Reseller_Security::store_encrypted_option( $this->api_key_option, $api_key );
		}

		return wp_parse_args( $sanitized, $this->get_defaults() );
	}

	/**
	 * Render a section description.
	 *
	 * @return void
	 */
	public function render_company_section() {
		echo '<p>' . esc_html__( 'Store your company profile used by the reseller backend configuration.', 'arvan-reseller' ) . '</p>';
	}

	/**
	 * Render a section description.
	 *
	 * @return void
	 */
	public function render_connection_section() {
		echo '<p>' . esc_html__( 'Configure the Arvan Cloud API connection. API keys are stored encrypted.', 'arvan-reseller' ) . '</p>';
	}

	/**
	 * Render a section description.
	 *
	 * @return void
	 */
	public function render_reseller_section() {
		echo '<p>' . esc_html__( 'Define reseller pricing rules. Share percentage is capped at 20%.', 'arvan-reseller' ) . '</p>';
	}

	/**
	 * Render a section description.
	 *
	 * @return void
	 */
	public function render_wallet_section() {
		echo '<p>' . esc_html__( 'Set the default wallet alert threshold for new customer wallets.', 'arvan-reseller' ) . '</p>';
	}

	/**
	 * Render a settings field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field( $args ) {
		$key      = isset( $args['key'] ) ? (string) $args['key'] : '';
		$type     = isset( $args['type'] ) ? (string) $args['type'] : 'text';
		$settings = $this->get_settings();
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$name     = $this->option_name . '[' . $key . ']';
		$id       = 'arvan_reseller_' . $key;

		if ( 'api_key' === $key ) {
			$value = '';
		}

		if ( 'textarea' === $type ) {
			printf(
				'<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_textarea( (string) $value )
			);
		} else {
			$step = 'number' === $type ? '0.01' : '';
			$min  = 'number' === $type ? '0' : '';
			$max  = 'reseller_share_percent' === $key ? '20' : '';

			printf(
				'<input id="%1$s" name="%2$s" type="%3$s" value="%4$s" class="regular-text" step="%5$s" min="%6$s" max="%7$s" autocomplete="%8$s" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $type ),
				esc_attr( (string) $value ),
				esc_attr( $step ),
				esc_attr( $min ),
				esc_attr( $max ),
				esc_attr( 'api_key' === $key ? 'new-password' : 'off' )
			);
		}

		if ( 'api_key' === $key ) {
			echo '<p class="description">' . esc_html( $this->get_api_key_description() ) . '</p>';
		}
	}

	/**
	 * Return the settings page slug.
	 *
	 * @return string
	 */
	public function get_page_slug() {
		return $this->page_slug;
	}

	/**
	 * Return the required capability.
	 *
	 * @return string
	 */
	public function get_capability() {
		return $this->capability;
	}

	/**
	 * Return merged settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( $this->option_name, array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), $this->get_defaults() );
	}

	/**
	 * Default settings values.
	 *
	 * @return array
	 */
	private function get_defaults() {
		return array(
			'version'                   => ARVAN_RESELLER_VERSION,
			'company_name'              => '',
			'company_logo_url'          => '',
			'company_contact_info'      => '',
			'api_base_url'              => '',
			'api_timeout'               => 20,
			'usage_endpoint'            => '',
			'resource_create_endpoint'  => '',
			'resource_get_endpoint'     => '',
			'resource_suspend_endpoint' => '',
			'resource_terminate_endpoint' => '',
			'reseller_share_percent'    => 0,
			'default_wallet_threshold'  => 0,
		);
	}

	/**
	 * Safely fetch an input value.
	 *
	 * @param array  $input Input array.
	 * @param string $key Requested key.
	 * @return string
	 */
	private function get_input_value( array $input, $key ) {
		return isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
	}

	/**
	 * Build the API key field description.
	 *
	 * @return string
	 */
	private function get_api_key_description() {
		$stored_key = Arvan_Reseller_Security::get_decrypted_option( $this->api_key_option );

		if ( '' === $stored_key ) {
			return __( 'No API key stored yet. Enter a value to save it encrypted.', 'arvan-reseller' );
		}

		return __( 'An encrypted API key is already stored. Leave this field empty to keep the current key.', 'arvan-reseller' );
	}
}
