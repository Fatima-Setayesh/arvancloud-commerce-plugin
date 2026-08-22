<?php
/** Secure WordPress authentication entry. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="arvan-reseller-app ar-auth-page" dir="rtl" lang="fa">
	<div class="ar-auth-aside"><a class="ar-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="ar-brand__mark" aria-hidden="true">A</span><span><strong><?php esc_html_e( 'فروش ابری آروان', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'پنل سرور ابری', 'arvan-reseller' ); ?></small></span></a><div><span class="ar-eyebrow"><?php esc_html_e( 'دسترسی امن مشتریان', 'arvan-reseller' ); ?></span><h1><?php esc_html_e( 'مدیریت یکپارچه زیرساخت ابری', 'arvan-reseller' ); ?></h1><p><?php esc_html_e( 'کیف پول، سفارش‌ها، سرورهای ابری، صورتحساب‌ها و اعلان‌های عملیاتی را با حساب امن وردپرس خود مدیریت کنید.', 'arvan-reseller' ); ?></p></div><small><?php esc_html_e( 'رمز عبور فقط توسط وردپرس پردازش می‌شود و این افزونه آن را ذخیره نمی‌کند.', 'arvan-reseller' ); ?></small></div>
	<main class="ar-auth-main" id="ar-main">
		<div class="ar-auth-card">
			<span class="ar-env ar-env--mock"><span></span><?php esc_html_e( 'نشست محافظت‌شده', 'arvan-reseller' ); ?></span>
			<h2><?php esc_html_e( 'ورود به پنل ابری', 'arvan-reseller' ); ?></h2>
			<p><?php esc_html_e( 'از اطلاعات حساب وردپرس خود استفاده کنید.', 'arvan-reseller' ); ?></p>
			<form method="post" action="<?php echo esc_url( wp_login_url() ); ?>" class="ar-form">
				<div class="ar-field"><label for="ar-user-login"><?php esc_html_e( 'نام کاربری یا ایمیل', 'arvan-reseller' ); ?></label><input id="ar-user-login" name="log" type="text" autocomplete="username" required /></div>
				<div class="ar-field"><label for="ar-user-pass"><?php esc_html_e( 'رمز عبور', 'arvan-reseller' ); ?></label><div class="ar-password-field"><input id="ar-user-pass" name="pwd" type="password" autocomplete="current-password" required /><button type="button" class="ar-icon-button" data-ar-action="toggle-password" aria-label="<?php esc_attr_e( 'نمایش رمز عبور', 'arvan-reseller' ); ?>"><span class="ar-icon" data-icon="eye" aria-hidden="true"></span></button></div></div>
				<label class="ar-check"><input name="rememberme" type="checkbox" value="forever" /><span><?php esc_html_e( 'ورود من را حفظ کن', 'arvan-reseller' ); ?></span></label>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
				<button class="ar-button ar-button--primary ar-button--block" type="submit"><?php esc_html_e( 'ورود امن', 'arvan-reseller' ); ?></button>
			</form>
			<div class="ar-auth-links"><a href="<?php echo esc_url( wp_lostpassword_url( $redirect_to ) ); ?>"><?php esc_html_e( 'رمز عبور را فراموش کرده‌اید؟', 'arvan-reseller' ); ?></a><?php if ( $can_register ) : ?><a href="<?php echo esc_url( $registration_url ); ?>"><?php esc_html_e( 'ایجاد حساب', 'arvan-reseller' ); ?></a><?php endif; ?></div>
		</div>
	</main>
</section>
