<?php
/** Customer Cloud Server storefront. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pages       = get_option( 'arvan_reseller_pages', array() );
$portal_id   = isset( $pages['portal'] ) ? absint( $pages['portal'] ) : 0;
$portal_url  = $portal_id ? get_permalink( $portal_id ) : wp_login_url();
$create_url  = add_query_arg( 'ar-route', 'create-server', $portal_url );
$settings    = wp_parse_args(
	get_option( 'arvan_reseller_settings', array() ),
	array(
		'company_name'     => '',
		'mode'             => 'mock',
		'region'           => '',
		'currency'         => '',
		'company_about'    => '',
		'company_logo_url' => '',
	)
);
$mode        = 'live' === $settings['mode'] ? 'live' : 'mock';
$mode_label  = 'live' === $mode ? __( 'زنده', 'arvan-reseller' ) : __( 'آزمایشی', 'arvan-reseller' );
$company     = (string) $settings['company_name'] ?: __( 'فروش ابری آروان', 'arvan-reseller' );
$description = (string) $settings['company_about'] ?: __( 'خرید و مدیریت سرور ابری با کیف پول پیش‌پرداخت و صورتحساب شفاف.', 'arvan-reseller' );
?>
<section class="arvan-reseller-app ar-storefront" dir="rtl" lang="fa">
	<a class="ar-skip-link" href="#ar-store-main"><?php esc_html_e( 'پرش به محتوای اصلی', 'arvan-reseller' ); ?></a>

	<header class="ar-store-header">
		<div class="ar-store-header__inner">
			<a class="ar-brand ar-brand--light" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php if ( '' !== (string) $settings['company_logo_url'] ) : ?>
					<img class="ar-brand__logo" src="<?php echo esc_url( $settings['company_logo_url'] ); ?>" alt="" />
				<?php else : ?>
					<span class="ar-brand__mark" aria-hidden="true"><span class="ar-icon"><svg viewBox="0 0 24 24"><path d="M17.5 19H7a5 5 0 0 1-.8-9.94A7 7 0 0 1 19.5 11a4 4 0 0 1-2 8z"/></svg></span></span>
				<?php endif; ?>
				<span><strong><?php echo esc_html( $company ); ?></strong><small><?php esc_html_e( 'مبتنی بر زیرساخت آروان‌کلاد', 'arvan-reseller' ); ?></small></span>
			</a>
			<nav class="ar-store-nav" aria-label="<?php esc_attr_e( 'ناوبری فروشگاه', 'arvan-reseller' ); ?>">
				<a href="#ar-product"><?php esc_html_e( 'سرور ابری', 'arvan-reseller' ); ?></a>
				<a href="#ar-billing"><?php esc_html_e( 'کیف پول و مصرف', 'arvan-reseller' ); ?></a>
				<a href="#ar-security"><?php esc_html_e( 'امنیت', 'arvan-reseller' ); ?></a>
				<a href="#ar-guide"><?php esc_html_e( 'راهنما', 'arvan-reseller' ); ?></a>
			</nav>
			<div class="ar-store-header__actions">
				<span class="ar-env ar-env--<?php echo esc_attr( $mode ); ?>" title="<?php echo esc_attr( $mode_label ); ?>"><span></span><?php echo esc_html( $mode_label ); ?></span>
				<a class="ar-store-entry" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'ورود', 'arvan-reseller' ); ?></a>
				<a class="ar-button ar-button--secondary ar-store-login" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'پنل مشتری', 'arvan-reseller' ); ?></a>
			</div>
		</div>
	</header>

	<main id="ar-store-main">
		<section class="ar-store-hero" id="ar-product">
			<div class="ar-store-hero__copy">
				<span class="ar-eyebrow"><span class="ar-icon" data-icon="server" aria-hidden="true"></span><?php esc_html_e( 'زیرساخت ابری بدون پیچیدگی', 'arvan-reseller' ); ?></span>
				<h1><?php esc_html_e( 'سرور ابری، سریع، شفاف و قابل مدیریت', 'arvan-reseller' ); ?></h1>
				<p><span><?php echo esc_html( $description ); ?></span> <span><?php esc_html_e( 'برآورد قیمت، ثبت سفارش و مدیریت چرخه سرویس در یک تجربه یکپارچه انجام می‌شود.', 'arvan-reseller' ); ?></span></p>
				<div class="ar-hero__actions">
					<a class="ar-button ar-button--large" href="<?php echo esc_url( $create_url ); ?>"><span class="ar-icon" data-icon="plus" aria-hidden="true"></span><?php esc_html_e( 'ساخت سرور ابری', 'arvan-reseller' ); ?></a>
					<a class="ar-button ar-button--secondary ar-button--large" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'ورود به پنل مشتری', 'arvan-reseller' ); ?></a>
				</div>
				<ul class="ar-hero-points">
					<li><span class="ar-icon" data-icon="check" aria-hidden="true"></span><?php esc_html_e( 'انتخاب منطقه، تصویر و پلن از کاتالوگ سرویس', 'arvan-reseller' ); ?></li>
					<li><span class="ar-icon" data-icon="check" aria-hidden="true"></span><?php esc_html_e( 'برآورد معتبر قیمت پیش از ثبت سفارش', 'arvan-reseller' ); ?></li>
					<li><span class="ar-icon" data-icon="check" aria-hidden="true"></span><?php esc_html_e( 'مدیریت کیف پول، مصرف و صورتحساب در یک پنل', 'arvan-reseller' ); ?></li>
				</ul>
			</div>

			<article class="ar-cloud-product-card" aria-label="<?php esc_attr_e( 'معرفی محصول سرور ابری', 'arvan-reseller' ); ?>">
				<div class="ar-cloud-product-card__top">
					<div class="ar-product-symbol" aria-hidden="true"><span class="ar-icon" data-icon="server"></span></div>
					<div><small dir="ltr">CLOUD SERVER</small><h2><?php esc_html_e( 'سرور ابری قابل پیکربندی', 'arvan-reseller' ); ?></h2></div>
					<?php echo wp_kses_post( '<span class="ar-status ar-status--success">' . esc_html__( 'آماده سفارش', 'arvan-reseller' ) . '</span>' ); ?>
				</div>
				<dl class="ar-product-specs">
					<div><dt><?php esc_html_e( 'منطقه پیش‌فرض', 'arvan-reseller' ); ?></dt><dd><?php if ( '' !== (string) $settings['region'] ) : ?><code dir="ltr"><?php echo esc_html( (string) $settings['region'] ); ?></code><?php else : esc_html_e( 'پس از پیکربندی', 'arvan-reseller' ); endif; ?></dd></div>
					<div><dt><?php esc_html_e( 'شیوه پرداخت', 'arvan-reseller' ); ?></dt><dd><?php esc_html_e( 'کیف پول پیش‌پرداخت', 'arvan-reseller' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'محاسبه مصرف', 'arvan-reseller' ); ?></dt><dd><?php esc_html_e( 'بازه‌های ساعتی', 'arvan-reseller' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'ارز تنظیم‌شده', 'arvan-reseller' ); ?></dt><dd><?php if ( '' !== (string) $settings['currency'] ) : ?><code dir="ltr"><?php echo esc_html( (string) $settings['currency'] ); ?></code><?php else : esc_html_e( 'پس از پیکربندی', 'arvan-reseller' ); endif; ?></dd></div>
				</dl>
				<div class="ar-product-estimate">
					<span class="ar-icon" data-icon="chart" aria-hidden="true"></span>
					<div><strong><?php esc_html_e( 'قیمت‌گذاری سمت سرور', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'مبلغ نهایی پس از انتخاب پلن از سامانه دریافت می‌شود؛ مرورگر قیمت را حدس نمی‌زند.', 'arvan-reseller' ); ?></small></div>
				</div>
				<a class="ar-button ar-button--block" href="<?php echo esc_url( $create_url ); ?>"><?php esc_html_e( 'شروع پیکربندی سرور', 'arvan-reseller' ); ?><span class="ar-icon" data-icon="arrow" aria-hidden="true"></span></a>
			</article>
		</section>

		<section class="ar-trust-strip" aria-label="<?php esc_attr_e( 'اصول تجربه سرویس', 'arvan-reseller' ); ?>">
			<div><span class="ar-icon" data-icon="chart" aria-hidden="true"></span><span><strong><?php esc_html_e( 'برآورد معتبر', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'قیمت از سرویس سمت سرور', 'arvan-reseller' ); ?></small></span></div>
			<div><span class="ar-icon" data-icon="shield" aria-hidden="true"></span><span><strong><?php esc_html_e( 'مالکیت امن', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'هر منبع در حساب مشتری', 'arvan-reseller' ); ?></small></span></div>
			<div><span class="ar-icon" data-icon="wallet" aria-hidden="true"></span><span><strong><?php esc_html_e( 'مالی شفاف', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'کیف پول، مصرف و صورتحساب', 'arvan-reseller' ); ?></small></span></div>
		</section>

		<section class="ar-store-features" id="ar-billing" aria-labelledby="ar-features-title">
			<div class="ar-section-heading ar-section-heading--center"><span><?php esc_html_e( 'تجربه یکپارچه سرویس', 'arvan-reseller' ); ?></span><h2 id="ar-features-title"><?php esc_html_e( 'هرآنچه برای خرید و مدیریت سرور نیاز دارید', 'arvan-reseller' ); ?></h2><p><?php esc_html_e( 'از برآورد تا صورتحساب، همه مراحل با داده معتبر سامانه و در یک رابط روشن انجام می‌شود.', 'arvan-reseller' ); ?></p></div>
			<div class="ar-store-facts">
				<article><span class="ar-feature__icon ar-icon" data-icon="chart" aria-hidden="true"></span><div><h3><?php esc_html_e( 'پرداخت به‌اندازه مصرف', 'arvan-reseller' ); ?></h3><p><?php esc_html_e( 'هزینه‌های ثبت‌شده و بازه‌های مصرف را با واحد و ارز واقعی دنبال کنید.', 'arvan-reseller' ); ?></p></div></article>
				<article><span class="ar-feature__icon ar-icon" data-icon="wallet" aria-hidden="true"></span><div><h3><?php esc_html_e( 'کیف پول پیش‌پرداخت', 'arvan-reseller' ); ?></h3><p><?php esc_html_e( 'موجودی، تراکنش‌ها و هشدار کاهش اعتبار همیشه در دسترس است.', 'arvan-reseller' ); ?></p></div></article>
				<article><span class="ar-feature__icon ar-icon" data-icon="server" aria-hidden="true"></span><div><h3><?php esc_html_e( 'کنترل کامل سرویس', 'arvan-reseller' ); ?></h3><p><?php esc_html_e( 'وضعیت ساخت، شناسه منبع، مصرف و چرخه سرویس را یکجا ببینید.', 'arvan-reseller' ); ?></p></div></article>
				<article id="ar-security"><span class="ar-feature__icon ar-icon" data-icon="shield" aria-hidden="true"></span><div><h3><?php esc_html_e( 'امنیت حساب', 'arvan-reseller' ); ?></h3><p><?php esc_html_e( 'نشست وردپرس، کنترل مالکیت و درخواست‌های احرازشده از حساب محافظت می‌کنند.', 'arvan-reseller' ); ?></p></div></article>
			</div>
		</section>

		<section class="ar-infrastructure" aria-labelledby="ar-infrastructure-title">
			<div class="ar-infrastructure__content">
				<span class="ar-eyebrow"><span class="ar-icon" data-icon="globe" aria-hidden="true"></span><?php esc_html_e( 'عملیات ابری یکپارچه', 'arvan-reseller' ); ?></span>
				<h2 id="ar-infrastructure-title"><?php esc_html_e( 'زیرساخت ابری برای کسب‌وکارهای در حال رشد', 'arvan-reseller' ); ?></h2>
				<p><?php esc_html_e( 'پیکربندی سرور، برآورد مصرف، کنترل کیف پول و مشاهده چرخه منبع در یک فضای عملیاتی منسجم کنار هم قرار گرفته‌اند.', 'arvan-reseller' ); ?></p>
				<ul><li><span class="ar-icon" data-icon="check" aria-hidden="true"></span><?php esc_html_e( 'انتخاب مستقیم از کاتالوگ سرویس', 'arvan-reseller' ); ?></li><li><span class="ar-icon" data-icon="check" aria-hidden="true"></span><?php esc_html_e( 'برآورد معتبر پیش از سفارش', 'arvan-reseller' ); ?></li><li><span class="ar-icon" data-icon="check" aria-hidden="true"></span><?php esc_html_e( 'مالکیت امن منابع در حساب مشتری', 'arvan-reseller' ); ?></li></ul>
			</div>
			<div class="ar-infrastructure__visual" aria-hidden="true">
				<div class="ar-network-orbit ar-network-orbit--one"></div><div class="ar-network-orbit ar-network-orbit--two"></div>
				<div class="ar-network-node ar-network-node--a"><span class="ar-icon" data-icon="server"></span></div>
				<div class="ar-network-node ar-network-node--b"><span class="ar-icon" data-icon="wallet"></span></div>
				<div class="ar-network-node ar-network-node--c"><span class="ar-icon" data-icon="chart"></span></div>
				<div class="ar-network-core"><span class="ar-icon" data-icon="globe"></span><strong><?php esc_html_e( 'پنل ابری', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'سرویس، مصرف، کیف پول', 'arvan-reseller' ); ?></small></div>
			</div>
		</section>

		<section class="ar-store-flow" id="ar-guide">
			<div class="ar-section-heading"><span><?php esc_html_e( 'مسیر سفارش', 'arvan-reseller' ); ?></span><h2><?php esc_html_e( 'از انتخاب تا شناسه سرویس، در یک فرایند روشن', 'arvan-reseller' ); ?></h2><p><?php esc_html_e( 'کاتالوگ و برآورد از سامانه می‌آیند و وضعیت ساخت تا دریافت شناسه منبع قابل مشاهده است.', 'arvan-reseller' ); ?></p></div>
			<ol>
				<li><span>۱</span><div><strong><?php esc_html_e( 'انتخاب پیکربندی', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'منطقه، سیستم‌عامل و منابع', 'arvan-reseller' ); ?></small></div></li>
				<li><span>۲</span><div><strong><?php esc_html_e( 'بررسی برآورد', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'قیمت معتبر و موجودی کیف پول', 'arvan-reseller' ); ?></small></div></li>
				<li><span>۳</span><div><strong><?php esc_html_e( 'ثبت و ساخت', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'سفارش تکرارپذیر امن و رهگیری وضعیت', 'arvan-reseller' ); ?></small></div></li>
				<li><span>۴</span><div><strong><?php esc_html_e( 'مدیریت سرویس', 'arvan-reseller' ); ?></strong><small><?php esc_html_e( 'شناسه منبع، مصرف و صورتحساب', 'arvan-reseller' ); ?></small></div></li>
			</ol>
		</section>
	</main>

		<footer class="ar-store-footer"><span><span><?php echo esc_html( $company ); ?></span> — <span><?php esc_html_e( 'فروش مستقل سرور ابری', 'arvan-reseller' ); ?></span></span><a href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'ورود به پنل مشتری', 'arvan-reseller' ); ?></a></footer>
</section>
