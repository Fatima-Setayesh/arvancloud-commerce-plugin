<?php
/**
 * Theme-independent customer entry shortcodes.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_Shortcodes {

	/** @var Arvan_Reseller_Dashboard */
	private $dashboard;

	/** Constructor. */
	public function __construct() {
		$this->dashboard = new Arvan_Reseller_Dashboard();
	}

	/** Register shortcode hooks. @return void */
	public function register_hooks() {
		add_shortcode( 'arvan_reseller_store', array( $this, 'store' ) );
		add_shortcode( 'arvan_reseller_portal', array( $this, 'portal' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	/** Enqueue before wp_head when a generated or manually configured page contains a product shortcode. @return void */
	public function maybe_enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'arvan_reseller_store' ) || has_shortcode( $post->post_content, 'arvan_reseller_portal' ) ) {
			Arvan_Reseller_Presentation::enqueue( 'customer' );
		}
	}

	/** @return string */
	public function store() {
		return $this->dashboard->storefront();
	}

	/** @return string */
	public function portal() {
		return $this->dashboard->portal();
	}
}
