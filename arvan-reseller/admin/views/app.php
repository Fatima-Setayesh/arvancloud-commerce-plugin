<?php
/** Admin operations console shell. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$mode       = 'live' === (string) $settings['mode'] ? 'live' : 'mock';
$mode_label = 'live' === $mode ? __( 'زنده', 'arvan-reseller' ) : __( 'آزمایشی', 'arvan-reseller' );
$nav        = array(
	'dashboard'   => array( __( 'داشبورد', 'arvan-reseller' ), 'grid', 'nav.dashboard' ),
	'setup'       => array( __( 'راه‌اندازی', 'arvan-reseller' ), 'spark', 'nav.setup' ),
	'customers'   => array( __( 'مشتریان', 'arvan-reseller' ), 'users', 'nav.customers' ),
	'payments'    => array( __( 'پرداخت‌ها', 'arvan-reseller' ), 'card', 'nav.payments' ),
	'orders'      => array( __( 'سفارش‌ها', 'arvan-reseller' ), 'receipt', 'nav.orders' ),
	'resources'   => array( __( 'سرورهای ابری', 'arvan-reseller' ), 'server', 'nav.resources' ),
	'usage'       => array( __( 'مصرف و صورتحساب', 'arvan-reseller' ), 'chart', 'nav.billing' ),
	'settlements' => array( __( 'تسویه داخلی', 'arvan-reseller' ), 'wallet', 'nav.settlements' ),
	'health'      => array( __( 'سلامت سامانه', 'arvan-reseller' ), 'shield', 'nav.health' ),
	'audit'       => array( __( 'گزارش ممیزی', 'arvan-reseller' ), 'list', 'nav.audit' ),
	'settings'    => array( __( 'تنظیمات', 'arvan-reseller' ), 'settings', 'nav.settings' ),
);
?>
<div class="arvan-reseller-app arvan-reseller-admin" dir="rtl" lang="fa" data-ar-context="admin" data-ar-page="<?php echo esc_attr( $page ); ?>">
	<a class="ar-skip-link" href="#ar-main"><?php esc_html_e( 'پرش به محتوای اصلی', 'arvan-reseller' ); ?></a>
	<div class="ar-app-frame">
		<aside class="ar-sidebar" aria-label="<?php esc_attr_e( 'ناوبری اصلی مدیریت فروشنده', 'arvan-reseller' ); ?>">
			<a class="ar-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=arvan-reseller' ) ); ?>">
				<?php if ( ! empty( $settings['company_logo_url'] ) ) : ?><img class="ar-brand__logo" src="<?php echo esc_url( $settings['company_logo_url'] ); ?>" alt="" /><?php else : ?><span class="ar-brand__mark" aria-hidden="true"><span class="ar-icon"><svg viewBox="0 0 24 24"><path d="M17.5 19H7a5 5 0 0 1-.8-9.94A7 7 0 0 1 19.5 11a4 4 0 0 1-2 8z"/></svg></span></span><?php endif; ?>
				<span><strong><?php echo esc_html( (string) $settings['company_name'] ?: __( 'فروش ابری آروان', 'arvan-reseller' ) ); ?></strong><small data-ar-i18n="shell.operations"><?php esc_html_e( 'عملیات زیرساخت ابری', 'arvan-reseller' ); ?></small></span>
			</a>
			<nav class="ar-sidebar__nav">
				<?php foreach ( $nav as $key => $item ) : ?>
					<a class="ar-nav-item<?php echo $page === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . ( 'dashboard' === $key ? 'arvan-reseller' : 'arvan-reseller-' . $key ) ) ); ?>"<?php echo $page === $key ? ' aria-current="page"' : ''; ?>>
						<span class="ar-icon" data-icon="<?php echo esc_attr( $item[1] ); ?>" aria-hidden="true"></span>
						<span data-ar-i18n="<?php echo esc_attr( $item[2] ); ?>"><?php echo esc_html( $item[0] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="ar-sidebar__footer">
				<span class="ar-env ar-env--<?php echo esc_attr( $mode ); ?>"><span></span><?php echo esc_html( $mode_label ); ?></span>
				<small><?php esc_html_e( 'محصول فعال: سرور ابری', 'arvan-reseller' ); ?></small>
			</div>
		</aside>
		<div class="ar-workspace">
			<header class="ar-topbar">
				<button class="ar-icon-button ar-mobile-menu" type="button" data-ar-action="toggle-sidebar" aria-label="<?php esc_attr_e( 'بازکردن ناوبری', 'arvan-reseller' ); ?>" aria-expanded="false"><span class="ar-icon" data-icon="menu" aria-hidden="true"></span></button>
				<div><strong id="ar-page-context"><?php esc_html_e( 'تجارت ابری آروان', 'arvan-reseller' ); ?></strong><small><?php echo esc_html( (string) $settings['company_name'] ?: __( 'فضای کاری فروشنده', 'arvan-reseller' ) ); ?></small></div>
				<div class="ar-topbar__actions"><div class="ar-preference-controls"><div class="ar-segmented ar-language-toggle" data-ar-language-toggle role="group" aria-label="<?php esc_attr_e( 'زبان نمایش', 'arvan-reseller' ); ?>" data-ar-i18n-label="preference.language"><button type="button" data-ar-language-value="fa" aria-pressed="true"><bdi>FA</bdi></button><button type="button" data-ar-language-value="en" aria-pressed="false"><bdi>EN</bdi></button></div><div class="ar-segmented ar-theme-toggle" data-ar-theme-toggle role="group" aria-label="<?php esc_attr_e( 'پوسته نمایش', 'arvan-reseller' ); ?>" data-ar-i18n-label="preference.theme"><button type="button" data-ar-theme-value="light" aria-pressed="false"><span class="ar-icon" data-icon="theme" aria-hidden="true"></span><span data-ar-i18n="preference.light"><?php esc_html_e( 'روشن', 'arvan-reseller' ); ?></span></button><button type="button" data-ar-theme-value="dark" aria-pressed="false"><span class="ar-icon" data-icon="moon" aria-hidden="true"></span><span data-ar-i18n="preference.dark"><?php esc_html_e( 'تیره', 'arvan-reseller' ); ?></span></button></div></div><span class="ar-env ar-env--<?php echo esc_attr( $mode ); ?>"><span></span><?php echo esc_html( $mode_label ); ?></span><span class="ar-avatar" aria-hidden="true"><?php $display_name = wp_get_current_user()->display_name; echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( $display_name, 0, 1 ) : substr( $display_name, 0, 1 ) ); ?></span></div>
			</header>
			<main class="ar-main" id="ar-main" tabindex="-1">
				<div id="ar-admin-content" class="ar-page" aria-live="polite">
					<div class="ar-loading-state"><span class="ar-spinner" aria-hidden="true"></span><p><?php esc_html_e( 'در حال دریافت داده‌های عملیاتی...', 'arvan-reseller' ); ?></p></div>
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
