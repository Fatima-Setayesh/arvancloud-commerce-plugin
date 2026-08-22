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
	private $capability = 'manage_arvan_reseller';

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
		$this->register_field( 'company_about', 'About', 'textarea', 'arvan_reseller_company_section' );
		$this->register_field( 'logo_attachment_id', 'Logo Attachment ID', 'number', 'arvan_reseller_company_section' );
		$this->register_field( 'mode', 'API Mode (mock/live)', 'text', 'arvan_reseller_connection_section' );
		$this->register_field( 'region', 'ArvanCloud Region', 'text', 'arvan_reseller_connection_section' );
		$this->register_field( 'availability_zone', 'Availability Zone', 'text', 'arvan_reseller_connection_section' );
		$this->register_field( 'api_key', 'API Key', 'password', 'arvan_reseller_connection_section' );
		$this->register_field( 'reseller_share_percent', 'Reseller Share Percentage', 'number', 'arvan_reseller_reseller_section' );
		$this->register_field( 'currency', 'Currency Code', 'text', 'arvan_reseller_reseller_section' );
		$this->register_field( 'default_wallet_threshold', 'Default Wallet Threshold', 'number', 'arvan_reseller_wallet_section' );
		$this->register_field( 'minimum_topup', 'Minimum Top-up', 'number', 'arvan_reseller_wallet_section' );
		$this->register_field( 'maximum_topup', 'Maximum Top-up', 'number', 'arvan_reseller_wallet_section' );
		$this->register_field( 'termination_policy', 'Termination Policy (disabled/immediate/grace)', 'text', 'arvan_reseller_wallet_section' );
		$this->register_field( 'termination_grace_hours', 'Termination Grace Hours', 'number', 'arvan_reseller_wallet_section' );
		$this->register_field( 'suspend_policy', 'Suspend Policy (zero_balance/disabled)', 'text', 'arvan_reseller_wallet_section' );
		$this->register_field( 'notification_enabled', 'Low-balance Email (1/0)', 'number', 'arvan_reseller_wallet_section' );
		$this->register_field( 'delete_data_on_uninstall', 'Delete Data on Uninstall (1/0)', 'number', 'arvan_reseller_wallet_section' );
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

		$current         = $this->get_settings();
		$input           = is_array( $input ) ? $input : array();
		$share_minor     = Arvan_Reseller_Money::to_minor( $this->get_input_value( $input, 'reseller_share_percent' ) );
		$threshold_minor = Arvan_Reseller_Money::to_minor( $this->get_input_value( $input, 'default_wallet_threshold' ) );
		$share_minor     = is_wp_error( $share_minor ) ? 0 : max( 0, min( 200000, $share_minor ) );
		$threshold_minor = is_wp_error( $threshold_minor ) ? 0 : max( 0, $threshold_minor );
		$minimum_minor   = Arvan_Reseller_Money::to_minor( $this->get_input_value( $input, 'minimum_topup' ) );
		$maximum_minor   = Arvan_Reseller_Money::to_minor( $this->get_input_value( $input, 'maximum_topup' ) );
		$minimum_minor   = is_wp_error( $minimum_minor ) || $minimum_minor <= 0 ? Arvan_Reseller_Money::scale() : $minimum_minor;
		$maximum_minor   = is_wp_error( $maximum_minor ) || $maximum_minor < $minimum_minor ? $minimum_minor : $maximum_minor;

		$mode           = sanitize_key( $this->get_input_value( $input, 'mode' ) );
		$policy         = sanitize_key( $this->get_input_value( $input, 'termination_policy' ) );
		$suspend_policy = sanitize_key( $this->get_input_value( $input, 'suspend_policy' ) );
		$currency       = strtoupper( preg_replace( '/[^A-Za-z]/', '', $this->get_input_value( $input, 'currency' ) ) );
		$region         = strtolower( trim( $this->get_input_value( $input, 'region' ) ) );
		$region         = preg_match( '/^[a-z0-9][a-z0-9-]{1,39}$/', $region ) && false === strpos( $region, '--' ) ? $region : '';
		$sanitized      = array(
			'version'                  => ARVAN_RESELLER_VERSION,
			'company_name'             => Arvan_Reseller_Security::sanitize_text( $this->get_input_value( $input, 'company_name' ) ),
			'company_logo_url'         => esc_url_raw( $this->get_input_value( $input, 'company_logo_url' ) ),
			'company_contact_info'     => Arvan_Reseller_Security::sanitize_textarea( $this->get_input_value( $input, 'company_contact_info' ) ),
			'company_about'            => Arvan_Reseller_Security::sanitize_textarea( $this->get_input_value( $input, 'company_about' ) ),
			'logo_attachment_id'       => absint( $this->get_input_value( $input, 'logo_attachment_id' ) ),
			'mode'                     => in_array( $mode, array( 'mock', 'live' ), true ) ? $mode : 'mock',
			'region'                   => $region,
			'availability_zone'        => Arvan_Reseller_Security::sanitize_text( $this->get_input_value( $input, 'availability_zone' ) ),
			'api_timeout'              => isset( $current['api_timeout'] ) ? absint( $current['api_timeout'] ) : 20,
			'product_type'             => 'cloud_server',
			'currency'                 => 3 === strlen( $currency ) ? $currency : 'IRR',
			'suspend_policy'           => in_array( $suspend_policy, array( 'zero_balance', 'disabled' ), true ) ? $suspend_policy : 'zero_balance',
			'termination_policy'       => in_array( $policy, array( 'disabled', 'immediate', 'grace' ), true ) ? $policy : 'disabled',
			'termination_grace_hours'  => max( 1, min( 8760, absint( $this->get_input_value( $input, 'termination_grace_hours' ) ) ) ),
			'notification_enabled'     => empty( $input['notification_enabled'] ) ? 0 : 1,
			'delete_data_on_uninstall' => empty( $input['delete_data_on_uninstall'] ) ? 0 : 1,
			'reseller_share_percent'   => Arvan_Reseller_Money::format( $share_minor ),
			'default_wallet_threshold' => Arvan_Reseller_Money::format( $threshold_minor ),
			'minimum_topup'            => Arvan_Reseller_Money::format( $minimum_minor ),
			'maximum_topup'            => Arvan_Reseller_Money::format( $maximum_minor ),
		);

		$api_key = Arvan_Reseller_Security::sanitize_api_key( $this->get_input_value( $input, 'api_key' ) );

		if ( '' !== $api_key ) {
			Arvan_Reseller_Security::store_encrypted_option( $this->api_key_option, $api_key );
		}

		return array_merge( $this->get_defaults(), $current, $sanitized );
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
			'version'                  => ARVAN_RESELLER_VERSION,
			'company_name'             => '',
			'company_logo_url'         => '',
			'company_contact_info'     => '',
			'company_about'            => '',
			'logo_attachment_id'       => 0,
			'mode'                     => 'mock',
			'region'                   => 'ir-thr-mock',
			'availability_zone'        => 'mock-zone-1',
			'api_timeout'              => 20,
			'product_type'             => 'cloud_server',
			'currency'                 => 'IRR',
			'suspend_policy'           => 'zero_balance',
			'termination_policy'       => 'disabled',
			'termination_grace_hours'  => 72,
			'notification_enabled'     => 1,
			'delete_data_on_uninstall' => 0,
			'reseller_share_percent'   => 0,
			'default_wallet_threshold' => 0,
			'minimum_topup'            => '1.0000',
			'maximum_topup'            => '1000000000.0000',
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
