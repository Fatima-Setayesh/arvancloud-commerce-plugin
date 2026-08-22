<?php
/** Authenticated customer portal shell. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings   = wp_parse_args( get_option( 'arvan_reseller_settings', array() ), array( 'company_name' => '', 'mode' => 'mock' ) );
$mode       = 'live' === $settings['mode'] ? 'live' : 'mock';
$mode_label = 'live' === $mode ? __( 'زنده', 'arvan-reseller' ) : __( 'آزمایشی', 'arvan-reseller' );
$nav        = array(
	'dashboard'     => array( __( 'داشبورد', 'arvan-reseller' ), 'grid' ),
	'services'      => array( __( 'سرویس‌ها', 'arvan-reseller' ), 'server' ),
	'create-server' => array( __( 'ساخت سرور', 'arvan-reseller' ), 'plus' ),
	'wallet'        => array( __( 'کیف پول', 'arvan-reseller' ), 'wallet' ),
	'billing'       => array( __( 'مصرف و صورتحساب‌ها', 'arvan-reseller' ), 'chart' ),
	'orders'        => array( __( 'سفارش‌ها', 'arvan-reseller' ), 'receipt' ),
	'notifications' => array( __( 'اعلان‌ها', 'arvan-reseller' ), 'bell' ),
);
?>
<section class="arvan-reseller-app ar-customer-portal" dir="rtl" lang="fa" data-ar-context="customer" data-ar-route="dashboard">
	<a class="ar-skip-link" href="#ar-customer-main"><?php esc_html_e( 'پرش به محتوای اصلی', 'arvan-reseller' ); ?></a>
	<div class="ar-app-frame">
		<aside class="ar-sidebar" aria-label="<?php esc_attr_e( 'ناوبری پنل مشتری', 'arvan-reseller' ); ?>">
			<a class="ar-brand" href="<?php echo esc_url( get_permalink() ); ?>"><span class="ar-brand__mark" aria-hidden="true">A</span><span><strong><?php echo esc_html( (string) $settings['company_name'] ?: __( 'فروش ابری آروان', 'arvan-reseller' ) ); ?></strong><small><?php esc_html_e( 'پنل ابری', 'arvan-reseller' ); ?></small></span></a>
			<nav class="ar-sidebar__nav">
				<?php foreach ( $nav as $key => $item ) : ?><button class="ar-nav-item<?php echo 'dashboard' === $key ? ' is-active' : ''; ?>" type="button" data-ar-route="<?php echo esc_attr( $key ); ?>"<?php echo 'dashboard' === $key ? ' aria-current="page"' : ''; ?>><span class="ar-icon" data-icon="<?php echo esc_attr( $item[1] ); ?>" aria-hidden="true"></span><span><?php echo esc_html( $item[0] ); ?></span></button><?php endforeach; ?>
			</nav>
			<div class="ar-sidebar__footer"><span class="ar-env ar-env--<?php echo esc_attr( $mode ); ?>"><span></span><?php echo esc_html( $mode_label ); ?></span><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'خروج از حساب', 'arvan-reseller' ); ?></a></div>
		</aside>
		<div class="ar-workspace">
			<header class="ar-topbar"><button class="ar-icon-button ar-mobile-menu" type="button" data-ar-action="toggle-sidebar" aria-label="<?php esc_attr_e( 'بازکردن ناوبری', 'arvan-reseller' ); ?>" aria-expanded="false"><span class="ar-icon" data-icon="menu" aria-hidden="true"></span></button><div><strong id="ar-customer-page-title"><?php esc_html_e( 'داشبورد', 'arvan-reseller' ); ?></strong><small><?php printf( esc_html__( 'خوش آمدید، %s', 'arvan-reseller' ), esc_html( $user->display_name ) ); ?></small></div><div class="ar-topbar__actions"><span class="ar-env ar-env--<?php echo esc_attr( $mode ); ?>"><span></span><?php echo esc_html( $mode_label ); ?></span><button class="ar-icon-button" type="button" data-ar-route="notifications" aria-label="<?php esc_attr_e( 'اعلان‌ها', 'arvan-reseller' ); ?>"><span class="ar-icon" data-icon="bell" aria-hidden="true"></span></button><span class="ar-avatar" aria-hidden="true"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( $user->display_name, 0, 1 ) : substr( $user->display_name, 0, 1 ) ); ?></span></div></header>
			<main class="ar-main" id="ar-customer-main" tabindex="-1"><div id="ar-customer-content" class="ar-page" aria-live="polite"><div class="ar-loading-state"><span class="ar-spinner" aria-hidden="true"></span><p><?php esc_html_e( 'در حال آماده‌سازی پنل ابری...', 'arvan-reseller' ); ?></p></div></div></main>
		</div>
	</div>
	<nav class="ar-bottom-nav" aria-label="<?php esc_attr_e( 'ناوبری موبایل', 'arvan-reseller' ); ?>"><?php foreach ( array( 'dashboard', 'services', 'wallet', 'notifications' ) as $key ) : ?><button type="button" data-ar-route="<?php echo esc_attr( $key ); ?>" class="<?php echo 'dashboard' === $key ? 'is-active' : ''; ?>"><span class="ar-icon" data-icon="<?php echo esc_attr( $nav[ $key ][1] ); ?>" aria-hidden="true"></span><span><?php echo esc_html( $nav[ $key ][0] ); ?></span></button><?php endforeach; ?></nav>
	<div class="ar-toast-region" aria-live="polite" aria-atomic="true"></div><div class="ar-modal-root"></div><div class="ar-sidebar-scrim" data-ar-action="close-sidebar" hidden></div>
</section>
