<?php
/** Real WordPress Mock Mode end-to-end scenario executed only in disposable Docker. */

$fail = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$http_count = 0;
add_filter(
	'pre_http_request',
	static function () use ( &$http_count ) {
		++$http_count;
		return new WP_Error( 'arvan_test_network_forbidden', 'Network is forbidden in Mock Mode tests.' );
	},
	10,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );

update_option(
	'arvan_reseller_settings',
	array(
		'mode'                     => 'mock',
		'region'                   => 'ir-thr-mock',
		'availability_zone'        => 'mock-zone-1',
		'currency'                 => 'IRR',
		'reseller_share_percent'   => '0.0000',
		'default_wallet_threshold' => '1000.0000',
		'minimum_topup'            => '1.0000',
		'maximum_topup'            => '1000000000.0000',
		'termination_policy'       => 'immediate',
		'termination_grace_hours'  => 1,
	),
	false
);

$customer_id = wp_insert_user(
	array(
		'user_login' => 'arvan-e2e-customer',
		'user_pass'  => wp_generate_password( 32, true, true ),
		'user_email' => 'arvan-e2e@example.test',
		'role'       => 'subscriber',
	)
);
$fail( ! is_wp_error( $customer_id ), 'Customer creation failed.' );

$loader       = arvan_reseller();
$database     = $loader->get_service( 'database' );
$payment      = $loader->get_service( 'payment' );
$provisioning = $loader->get_service( 'provisioning' );
$cron         = $loader->get_service( 'cron' );
$settlement   = $loader->get_service( 'settlement' );

$intent = $payment->create_payment( $customer_id, '25000.0000', 'docker-e2e-payment' );
$fail( ! is_wp_error( $intent ), 'Payment intent failed.' );
$confirmed = $payment->confirm_payment( $customer_id, $intent['payment_reference'] );
$fail( ! is_wp_error( $confirmed ) && 'completed' === $confirmed['status'], 'Payment confirmation failed.' );

$order = $provisioning->create_server_order(
	$customer_id,
	array(
		'region'                   => 'ir-thr-mock',
		'availabilityZone'         => 'mock-zone-1',
		'flavorId'                 => 'mock-g2-1-2',
		'imageId'                  => '00000000-0000-4000-8000-000000000101',
		'name'                     => 'docker-e2e-server',
		'rootVolumeSizeGigaBytes' => 25,
	),
	'docker-e2e-order'
);
$fail( ! is_wp_error( $order ) && 'provisioned' === $order['status'], 'Provisioning failed.' );

$database->update(
	'resources',
	array( 'last_billed_at' => gmdate( 'Y-m-d H:00:00', time() - HOUR_IN_SECONDS ) ),
	array( 'id' => (int) $order['resource_record_id'] )
);
$health = $cron->run_hourly_usage_sync();
$fail( ! is_wp_error( $health ) && 1 === $health['processed'], 'Usage Cron failed.' );

$wallet = $database->get_wallet_by_customer_id( $customer_id );
$resource = $database->get_row_by( 'resources', array( 'id' => (int) $order['resource_record_id'], 'customer_id' => $customer_id ) );
$notifications = $database->get_notifications_by_customer_id( $customer_id );
$fail( 0 === (int) $wallet['balance_minor'], 'Wallet was not deducted to zero.' );
$fail( 'terminated' === $resource['status'], 'Immediate termination policy failed.' );
$fail( ! empty( $notifications ) && 'sent' === $notifications[0]['status'], 'Low-balance notification was not deduplicated/sent.' );

$settled = $settlement->settle_period( gmdate( 'Y-m-d 00:00:00', time() - DAY_IN_SECONDS ), gmdate( 'Y-m-d 23:59:59', time() + DAY_IN_SECONDS ), 'IRR' );
$fail( ! is_wp_error( $settled ) && 1 === (int) $settled['usage_count'], 'Settlement failed.' );
$fail( 0 === $http_count, 'Mock Mode attempted a network request.' );

echo wp_json_encode(
	array(
		'payment'     => $confirmed['status'],
		'order'       => $order['status'],
		'resource_id' => $order['resource_id'],
		'billing'     => $health['status'],
		'wallet_minor'=> (int) $wallet['balance_minor'],
		'notification'=> $notifications[0]['status'],
		'resource'    => $resource['status'],
		'settlement'  => $settled['status'],
		'http_calls'  => $http_count,
	),
	JSON_PRETTY_PRINT
) . PHP_EOL;
