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
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_filter( 'show_admin_bar', array( $this, 'maybe_hide_customer_admin_bar' ) );
	}

	/**
	 * Mark only shortcode product pages for scoped theme isolation.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function body_classes( array $classes ) {
		$context = $this->product_page_context();
		if ( '' === $context ) {
			return $classes;
		}

		$classes[] = 'arvan-reseller-product-page';
		$classes[] = 'arvan-reseller-' . $context . '-page';
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Keep customer product pages independent from the WordPress toolbar.
	 * Administrators retain normal toolbar and wp-admin access.
	 *
	 * @param bool $show Current toolbar decision.
	 * @return bool
	 */
	public function maybe_hide_customer_admin_bar( $show ) {
		if ( is_admin() || current_user_can( 'manage_arvan_reseller' ) ) {
			return (bool) $show;
		}

		return '' !== $this->product_page_context() ? false : (bool) $show;
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

	/** Return the isolated customer surface on the current singular page. @return string */
	private function product_page_context() {
		if ( ! is_singular() ) {
			return '';
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		if ( has_shortcode( $post->post_content, 'arvan_reseller_portal' ) ) {
			return 'portal';
		}

		return has_shortcode( $post->post_content, 'arvan_reseller_store' ) ? 'store' : '';
	}
}
