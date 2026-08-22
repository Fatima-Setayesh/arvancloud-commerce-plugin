<?php
/** Customer storefront. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pages      = get_option( 'arvan_reseller_pages', array() );
$portal_id  = isset( $pages['portal'] ) ? absint( $pages['portal'] ) : 0;
$portal_url = $portal_id ? get_permalink( $portal_id ) : wp_login_url();
$settings   = wp_parse_args( get_option( 'arvan_reseller_settings', array() ), array( 'company_name' => '', 'mode' => 'mock' ) );
?>
<section class="arvan-reseller-app ar-storefront" dir="rtl" lang="fa">
	<a class="ar-skip-link" href="#ar-store-main"><?php esc_html_e( 'Skip to main content', 'arvan-reseller' ); ?></a>
	<header class="ar-store-header">
		<a class="ar-brand ar-brand--light" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="ar-brand__mark" aria-hidden="true">A</span><span><strong><?php echo esc_html( (string) $settings['company_name'] ?: __( 'Arvan Reseller', 'arvan-reseller' ) ); ?></strong><small><?php esc_html_e( 'Cloud Server', 'arvan-reseller' ); ?></small></span></a>
		<nav aria-label="<?php esc_attr_e( 'Store navigation', 'arvan-reseller' ); ?>"><a href="#capabilities"><?php esc_html_e( 'Capabilities', 'arvan-reseller' ); ?></a><a href="#billing"><?php esc_html_e( 'Billing', 'arvan-reseller' ); ?></a><a class="ar-button ar-button--ghost" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Customer portal', 'arvan-reseller' ); ?></a></nav>
	</header>
	<main id="ar-store-main">
		<section class="ar-hero">
			<div class="ar-hero__content">
				<span class="ar-eyebrow"><span class="ar-env ar-env--<?php echo 'live' === $settings['mode'] ? 'live' : 'mock'; ?>"><span></span><?php echo esc_html( 'live' === $settings['mode'] ? __( 'Live', 'arvan-reseller' ) : __( 'Mock demo', 'arvan-reseller' ) ); ?></span><?php esc_html_e( 'Standalone WordPress cloud commerce', 'arvan-reseller' ); ?></span>
				<h1><?php esc_html_e( 'A clear path from wallet to Cloud Server.', 'arvan-reseller' ); ?></h1>
				<p><?php esc_html_e( 'Configure an ArvanCloud Cloud Server from live catalog data, review the authoritative backend estimate, and manage prepaid usage from one focused portal.', 'arvan-reseller' ); ?></p>
				<div class="ar-hero__actions"><a class="ar-button ar-button--accent ar-button--large" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Open cloud portal', 'arvan-reseller' ); ?><span class="ar-icon" data-icon="arrow" aria-hidden="true"></span></a><a class="ar-button ar-button--secondary ar-button--large" href="#capabilities"><?php esc_html_e( 'How it works', 'arvan-reseller' ); ?></a></div>
				<p class="ar-fine-print"><?php esc_html_e( 'Cloud Server only. This reseller service does not claim official partnership or external settlement capabilities.', 'arvan-reseller' ); ?></p>
			</div>
			<div class="ar-hero-console" aria-label="<?php esc_attr_e( 'Cloud Server workflow preview', 'arvan-reseller' ); ?>">
				<div class="ar-console-head"><span><i></i><i></i><i></i></span><small>cloud-server / mock</small></div>
				<div class="ar-console-server"><span class="ar-server-illustration"><i></i><i></i><i></i></span><div><span class="ar-status ar-status--success"><?php esc_html_e( 'Active', 'arvan-reseller' ); ?></span><h2><?php esc_html_e( 'Production server', 'arvan-reseller' ); ?></h2><code dir="ltr">mock-4f92a1c76d80</code></div></div>
				<div class="ar-console-grid"><div><small><?php esc_html_e( 'Region', 'arvan-reseller' ); ?></small><strong>ir-thr-mock</strong></div><div><small><?php esc_html_e( 'Billing', 'arvan-reseller' ); ?></small><strong><?php esc_html_e( 'Hourly prepaid', 'arvan-reseller' ); ?></strong></div></div>
				<div class="ar-mini-chart" aria-hidden="true"><span></span><svg viewBox="0 0 440 100" preserveAspectRatio="none"><path d="M0 76 C44 68 65 82 105 58 S170 60 218 38 S290 55 330 28 S395 25 440 12"/></svg></div>
			</div>
		</section>
		<section class="ar-section" id="capabilities">
			<div class="ar-section-heading"><span><?php esc_html_e( 'Operational by design', 'arvan-reseller' ); ?></span><h2><?php esc_html_e( 'Everything needed for a credible Cloud Server workflow', 'arvan-reseller' ); ?></h2></div>
			<div class="ar-feature-grid">
				<article class="ar-feature"><span class="ar-feature__icon ar-icon" data-icon="server" aria-hidden="true"></span><h3><?php esc_html_e( 'Server configurator', 'arvan-reseller' ); ?></h3><p><?php esc_html_e( 'Choose region, image, flavor and supported options directly from backend catalog routes.', 'arvan-reseller' ); ?></p></article>
				<article class="ar-feature"><span class="ar-feature__icon ar-icon" data-icon="wallet" aria-hidden="true"></span><h3><?php esc_html_e( 'Prepaid wallet', 'arvan-reseller' ); ?></h3><p><?php esc_html_e( 'Top up in Mock mode, inspect the immutable ledger, and see low-balance warnings clearly.', 'arvan-reseller' ); ?></p></article>
				<article class="ar-feature"><span class="ar-feature__icon ar-icon" data-icon="shield" aria-hidden="true"></span><h3><?php esc_html_e( 'Secure control plane', 'arvan-reseller' ); ?></h3><p><?php esc_html_e( 'WordPress sessions, REST nonces, ownership checks and protected administrative capabilities.', 'arvan-reseller' ); ?></p></article>
			</div>
		</section>
		<section class="ar-section ar-billing-explainer" id="billing"><div><span class="ar-eyebrow"><?php esc_html_e( 'Transparent billing', 'arvan-reseller' ); ?></span><h2><?php esc_html_e( 'Backend-authoritative estimates, exact usage windows.', 'arvan-reseller' ); ?></h2><p><?php esc_html_e( 'The browser never decides the price. Estimates and charges come from the validated backend using the configured currency and reseller share.', 'arvan-reseller' ); ?></p></div><ol><li><strong>01</strong><?php esc_html_e( 'Add prepaid wallet credit', 'arvan-reseller' ); ?></li><li><strong>02</strong><?php esc_html_e( 'Configure and estimate', 'arvan-reseller' ); ?></li><li><strong>03</strong><?php esc_html_e( 'Provision and monitor', 'arvan-reseller' ); ?></li></ol></section>
	</main>
	<footer class="ar-store-footer"><span><?php esc_html_e( 'Independent Cloud Server reseller console', 'arvan-reseller' ); ?></span><a href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Go to portal', 'arvan-reseller' ); ?></a></footer>
</section>
