<?php
/**
 * Settings page view.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Arvan Reseller Settings', 'arvan-reseller' ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'arvan_reseller_settings_group' ); ?>
		<?php do_settings_sections( $page_slug ); ?>
		<?php submit_button( esc_html__( 'Save Settings', 'arvan-reseller' ) ); ?>
	</form>
</div>
