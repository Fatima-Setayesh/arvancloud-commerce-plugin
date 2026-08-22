<?php
/**
 * Plugin Name: Arvan Reseller
 * Plugin URI: https://github.com/
 * Description: A standalone reseller plugin for Arvan Cloud products.
 * Version: 1.1.0
 * Author: Fatima Team
 * License: GPL-2.0+
 * Requires PHP: 8.2
 * Text Domain: arvan-reseller
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARVAN_RESELLER_VERSION', '1.1.0' );
define( 'ARVAN_RESELLER_DB_VERSION', '1.2.0' );
/**
 * Monetary values are persisted as integers at four decimal places.
 *
 * One major currency unit equals 10,000 internal minor units. This preserves
 * the precision of the legacy DECIMAL(18,4) columns without using floats.
 */
define( 'ARVAN_RESELLER_MONEY_SCALE', 10000 );
define( 'ARVAN_RESELLER_FILE', __FILE__ );
define( 'ARVAN_RESELLER_PATH', plugin_dir_path( __FILE__ ) );
define( 'ARVAN_RESELLER_URL', plugin_dir_url( __FILE__ ) );

require_once ARVAN_RESELLER_PATH . 'includes/class-loader.php';
require_once ARVAN_RESELLER_PATH . 'includes/class-activator.php';
require_once ARVAN_RESELLER_PATH . 'includes/class-deactivator.php';

register_activation_hook(
	__FILE__,
	array(
		'Arvan_Reseller_Activator',
		'activate',
	)
);

register_deactivation_hook(
	__FILE__,
	array(
		'Arvan_Reseller_Deactivator',
		'deactivate',
	)
);

/**
 * Bootstrap the plugin runtime.
 *
 * @return Arvan_Reseller_Loader
 */
function arvan_reseller() {
	static $loader = null;

	if ( null === $loader ) {
		$loader = new Arvan_Reseller_Loader();
		$loader->load();
	}

	return $loader;
}

arvan_reseller();
