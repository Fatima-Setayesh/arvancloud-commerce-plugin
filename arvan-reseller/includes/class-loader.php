<?php
/**
 * Plugin loader.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Loader {

	/**
	 * Bootstrapped service instances.
	 *
	 * @var array<string, object>
	 */
	private $services = array();

	/**
	 * Class search directories.
	 *
	 * @var string[]
	 */
	private $class_directories = array(
		'includes',
		'admin',
		'frontend',
	);

	/**
	 * Register autoloading and runtime hooks.
	 *
	 * @return void
	 */
	public function load() {
		spl_autoload_register( array( $this, 'autoload' ) );

		add_action( 'plugins_loaded', array( $this, 'maybe_run_migrations' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'bootstrap_services' ) );
	}

	/**
	 * Autoload plugin classes.
	 *
	 * @param string $class_name Requested class.
	 * @return void
	 */
	public function autoload( $class_name ) {
		if ( strpos( $class_name, 'Arvan_Reseller_' ) !== 0 ) {
			return;
		}

		$file_name = 'class-' . strtolower( str_replace( '_', '-', str_replace( 'Arvan_Reseller_', '', $class_name ) ) ) . '.php';

		foreach ( $this->class_directories as $directory ) {
			$file = ARVAN_RESELLER_PATH . $directory . '/' . $file_name;

			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}

	/**
	 * Ensure database migrations run during normal plugin load.
	 *
	 * @return void
	 */
	public function maybe_run_migrations() {
		require_once ARVAN_RESELLER_PATH . 'database/migrations.php';

		arvan_reseller_run_migrations();
	}

	/**
	 * Bootstrap service classes after WordPress has loaded.
	 *
	 * @return void
	 */
	public function bootstrap_services() {
		$this->services['database']      = new Arvan_Reseller_Database();
		$this->services['api']           = new Arvan_Reseller_API_Client();
		$this->services['wallet']        = new Arvan_Reseller_Wallet( $this->services['database'] );
		$this->services['payment']       = new Arvan_Reseller_Payment( $this->services['wallet'], $this->services['database'] );
		$this->services['billing']       = new Arvan_Reseller_Billing( $this->services['database'], $this->services['wallet'], $this->services['api'] );
		$this->services['provisioning']  = new Arvan_Reseller_Provisioning( $this->services['database'], $this->services['api'] );
		$this->services['notifications'] = new Arvan_Reseller_Notifications( $this->services['database'] );
		$this->services['settlement']    = new Arvan_Reseller_Settlement( $this->services['database'] );
		$this->services['settings']      = new Arvan_Reseller_Settings();
		$this->services['cron']          = new Arvan_Reseller_Cron( $this->services['billing'], $this->services['database'], $this->services['api'], $this->services['notifications'], $this->services['provisioning'], $this->services['settlement'] );
		$this->services['rest']          = new Arvan_Reseller_REST_API( $this->services['database'], $this->services['wallet'], $this->services['payment'], $this->services['provisioning'], $this->services['billing'], $this->services['api'], $this->services['cron'], $this->services['settings'] );

		$this->services['cron']->register_hooks();
		$this->services['rest']->register_hooks();

		if ( is_admin() ) {
			$this->services['admin_menu'] = new Arvan_Reseller_Admin_Menu( $this->services['settings'] );

			$this->services['settings']->register_hooks();
			$this->services['admin_menu']->register_hooks();
		}
	}

	/**
	 * Return a bootstrapped service if available.
	 *
	 * @param string $service Service key.
	 * @return object|null
	 */
	public function get_service( $service ) {
		return isset( $this->services[ $service ] ) ? $this->services[ $service ] : null;
	}
}
