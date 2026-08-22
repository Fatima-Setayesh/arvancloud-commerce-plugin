<?php
/** Real WordPress REST registration, permission, redaction and ownership smoke test. */

$fail = static function ( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
};

$server   = rest_get_server();
$routes   = $server->get_routes();
$required = array(
	'/arvan-reseller/v1/wallet',
	'/arvan-reseller/v1/orders',
	'/arvan-reseller/v1/usage',
	'/arvan-reseller/v1/admin/settings',
	'/arvan-reseller/v1/admin/reconciliation/run',
	'/arvan-reseller/v1/admin/audit-logs',
);
foreach ( $required as $route ) {
	$fail( isset( $routes[ $route ] ), 'Missing REST route: ' . $route );
}

wp_set_current_user( 0 );
unset( $_SERVER['HTTP_X_WP_NONCE'] );
$unauthorized = $server->dispatch( new WP_REST_Request( 'GET', '/arvan-reseller/v1/wallet' ) );
$fail( 401 === $unauthorized->get_status(), 'Unauthenticated wallet request was not rejected.' );

wp_set_current_user( 1 );
$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
$admin = $server->dispatch( new WP_REST_Request( 'GET', '/arvan-reseller/v1/admin/settings' ) );
$fail( 200 === $admin->get_status(), 'Administrator settings request failed.' );
$admin_data = $admin->get_data();
$fail( is_array( $admin_data ) && ! isset( $admin_data['api_key'], $admin_data['encrypted_api_key'] ), 'Secret material appeared in settings response.' );

$customer_one = wp_create_user( 'rest-customer-one', wp_generate_password( 32, true, true ), 'rest-one@example.test' );
$customer_two = wp_create_user( 'rest-customer-two', wp_generate_password( 32, true, true ), 'rest-two@example.test' );
$fail( ! is_wp_error( $customer_one ) && ! is_wp_error( $customer_two ), 'REST test customers could not be created.' );

$database    = new Arvan_Reseller_Database();
$resource_id = $database->save_resource(
	array(
		'customer_id'        => $customer_one,
		'resource_id'        => 'rest-ownership-resource',
		'product_type'       => 'cloud_server',
		'region'             => 'ir-thr-mock',
		'status'             => 'active',
		'remote_status'      => 'ACTIVE',
		'hourly_price_minor' => 10000,
		'last_synced_at'     => current_time( 'mysql', true ),
	)
);
$fail( false !== $resource_id, 'REST ownership fixture could not be persisted.' );

wp_set_current_user( $customer_two );
$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
$foreign = $server->dispatch( new WP_REST_Request( 'GET', '/arvan-reseller/v1/resources/' . $resource_id ) );
$fail( 404 === $foreign->get_status(), 'Cross-customer resource access was not hidden.' );

WP_CLI::success( 'REST routes, nonce, capability, redaction and IDOR checks passed.' );
