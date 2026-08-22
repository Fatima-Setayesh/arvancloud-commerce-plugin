<?php
/** Admin operations console shell. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$mode       = 'live' === (string) $settings['mode'] ? 'live' : 'mock';
$mode_label = 'live' === $mode ? __( 'Live', 'arvan-reseller' ) : __( 'Mock', 'arvan-reseller' );
$nav        = array(
	'dashboard'   => array( __( 'Dashboard', 'arvan-reseller' ), 'grid' ),
	'setup'       => array( __( 'Setup', 'arvan-reseller' ), 'spark' ),
	'customers'   => array( __( 'Customers', 'arvan-reseller' ), 'users' ),
	'payments'    => array( __( 'Payments', 'arvan-reseller' ), 'card' ),
	'orders'      => array( __( 'Orders', 'arvan-reseller' ), 'receipt' ),
	'resources'   => array( __( 'Cloud Servers', 'arvan-reseller' ), 'server' ),
	'usage'       => array( __( 'Usage / Billing', 'arvan-reseller' ), 'chart' ),
	'settlements' => array( __( 'Internal Settlement', 'arvan-reseller' ), 'wallet' ),
	'health'      => array( __( 'System Health', 'arvan-reseller' ), 'shield' ),
	'audit'       => array( __( 'Audit Log', 'arvan-reseller' ), 'list' ),
	'settings'    => array( __( 'Settings', 'arvan-reseller' ), 'settings' ),
);
?>
<div class="arvan-reseller-app arvan-reseller-admin" dir="rtl" lang="fa" data-ar-context="admin" data-ar-page="<?php echo esc_attr( $page ); ?>">
	<a class="ar-skip-link" href="#ar-main"><?php esc_html_e( 'Skip to main content', 'arvan-reseller' ); ?></a>
	<div class="ar-app-frame">
		<aside class="ar-sidebar" aria-label="<?php esc_attr_e( 'Primary navigation', 'arvan-reseller' ); ?>">
			<a class="ar-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=arvan-reseller' ) ); ?>">
				<span class="ar-brand__mark" aria-hidden="true">A</span>
				<span><strong><?php esc_html_e( 'Arvan Reseller', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'Cloud operations', 'arvan-reseller' ); ?></small></span>
			</a>
			<nav class="ar-sidebar__nav">
				<?php foreach ( $nav as $key => $item ) : ?>
					<a class="ar-nav-item<?php echo $page === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . ( 'dashboard' === $key ? 'arvan-reseller' : 'arvan-reseller-' . $key ) ) ); ?>"<?php echo $page === $key ? ' aria-current="page"' : ''; ?>>
						<span class="ar-icon" data-icon="<?php echo esc_attr( $item[1] ); ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( $item[0] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="ar-sidebar__footer">
				<span class="ar-env ar-env--<?php echo esc_attr( $mode ); ?>"><span></span><?php echo esc_html( $mode_label ); ?></span>
				<small><?php esc_html_e( 'Cloud Server only', 'arvan-reseller' ); ?></small>
			</div>
		</aside>
		<div class="ar-workspace">
			<header class="ar-topbar">
				<button class="ar-icon-button ar-mobile-menu" type="button" data-ar-action="toggle-sidebar" aria-label="<?php esc_attr_e( 'Open navigation', 'arvan-reseller' ); ?>" aria-expanded="false"><span class="ar-icon" data-icon="menu" aria-hidden="true"></span></button>
				<div><strong id="ar-page-context"><?php esc_html_e( 'ArvanCloud Commerce', 'arvan-reseller' ); ?></strong><small><?php echo esc_html( (string) $settings['company_name'] ?: __( 'Reseller workspace', 'arvan-reseller' ) ); ?></small></div>
				<div class="ar-topbar__actions"><span class="ar-env ar-env--<?php echo esc_attr( $mode ); ?>"><span></span><?php echo esc_html( $mode_label ); ?></span><span class="ar-avatar" aria-hidden="true"><?php $display_name = wp_get_current_user()->display_name; echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( $display_name, 0, 1 ) : substr( $display_name, 0, 1 ) ); ?></span></div>
			</header>
			<main class="ar-main" id="ar-main" tabindex="-1">
				<div id="ar-admin-content" class="ar-page" aria-live="polite">
					<div class="ar-loading-state"><span class="ar-spinner" aria-hidden="true"></span><p><?php esc_html_e( 'Loading operational data…', 'arvan-reseller' ); ?></p></div>
				</div>
				<?php if ( 'setup' === $page || 'settings' === $page ) : ?>
					<form class="ar-page-setup-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
						<input type="hidden" name="action" value="arvan_reseller_create_pages" />
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $create_nonce ); ?>" />
					</form>
				<?php endif; ?>
			</main>
		</div>
	</div>
	<div class="ar-toast-region" aria-live="polite" aria-atomic="true"></div>
	<div class="ar-modal-root"></div>
	<div class="ar-sidebar-scrim" data-ar-action="close-sidebar" hidden></div>
</div>
