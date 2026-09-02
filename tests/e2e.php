<?php
require_once __DIR__ . '/bootstrap.php';

$GLOBALS['arvan_test_options']['arvan_reseller_settings'] = array(
	'mode' => 'mock', 'region' => 'ir-thr-mock', 'availability_zone' => 'mock-zone-1',
	'currency' => 'IRR', 'reseller_share_percent' => '0.0000',
	'minimum_topup' => '1.0000', 'maximum_topup' => '1000000000.0000',
	'termination_policy' => 'immediate', 'termination_grace_hours' => 1,
);
$GLOBALS['arvan_test_options']['arvan_reseller_mock_resources'] = array();
$GLOBALS['arvan_test_mail_count'] = 0; $GLOBALS['arvan_test_http_count'] = 0;

$db = new Arvan_Test_Database(); $wallet = new Arvan_Reseller_Wallet( $db );
$payment = new Arvan_Reseller_Payment( $wallet, $db );
$api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() );
$billing = new Arvan_Reseller_Billing( $db, $wallet, $api );
$provisioning = new Arvan_Reseller_Provisioning( $db, $api, $wallet );
$notifications = new Arvan_Reseller_Notifications( $db );
$settlement = new Arvan_Reseller_Settlement( $db );
$cron = new Arvan_Reseller_Cron( $billing, $db, $api, $notifications, $provisioning, $settlement );

$db->ensure_wallet( 7 ); $db->wallets[7]['threshold_minor'] = 10000000;
$intent = $payment->create_payment( 7, '25000.0000', 'e2e-payment' );
arvan_test_assert_true( ! is_wp_error( $intent ), 'E2E payment intent failed' );
$topup = $payment->confirm_payment( 7, $intent['payment_reference'] );
arvan_test_assert_same( 'completed', $topup['status'], 'E2E payment confirmation failed' );

$order = $provisioning->create_server_order( 7, array( 'region' => 'ir-thr-mock', 'availabilityZone' => 'mock-zone-1', 'flavorId' => 'mock-g2-1-2', 'imageId' => '00000000-0000-4000-8000-000000000101', 'name' => 'e2e-server', 'rootVolumeSizeGigaBytes' => 25 ), 'e2e-order' );
arvan_test_assert_true( ! is_wp_error( $order ), 'E2E provisioning failed' );
$db->resources[1]['last_billed_at'] = gmdate( 'Y-m-d H:00:00', strtotime( '-1 hour UTC' ) );
$health = $cron->run_hourly_usage_sync();
arvan_test_assert_true( ! is_wp_error( $health ) && 1 === $health['processed'], 'E2E usage Cron failed' );
arvan_test_assert_same( 0, $db->wallets[7]['balance_minor'], 'E2E billing did not deduct wallet to zero' );
arvan_test_assert_same( 1, $GLOBALS['arvan_test_mail_count'], 'E2E threshold notification missing' );
arvan_test_assert_same( 'terminated', $db->resources[1]['status'], 'E2E zero-balance termination policy failed' );
$remote = $api->get_server( 'ir-thr-mock', $order['resource_id'] );
arvan_test_assert_same( 'TERMINATED', $remote['body']['data']['status'], 'E2E remote mock state mismatch' );
$settled = $settlement->settle_period( '2026-01-01 00:00:00', '2027-01-01 00:00:00', 'IRR' );
arvan_test_assert_true( ! is_wp_error( $settled ) && 1 === $settled['usage_count'], 'E2E settlement failed' );
arvan_test_assert_same( 0, $GLOBALS['arvan_test_http_count'], 'E2E Mock Mode attempted network access' );

echo "PASS: installation/settings -> top-up -> order -> provisioning -> mapping -> billing -> warning -> suspension -> termination -> settlement\n";
