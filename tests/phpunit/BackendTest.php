<?php
use PHPUnit\Framework\TestCase;

final class BackendTest extends TestCase {
	protected function setUp(): void { $GLOBALS['arvan_test_options'] = array( 'arvan_reseller_settings' => array( 'mode' => 'mock', 'region' => 'ir-thr-mock', 'currency' => 'IRR' ) ); $GLOBALS['arvan_test_can_manage'] = false; }
	public function test_mock_mode_never_uses_http(): void { $GLOBALS['arvan_test_http_count'] = 0; $api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() ); $this->assertFalse( is_wp_error( $api->test_connection() ) ); $this->assertSame( 0, $GLOBALS['arvan_test_http_count'] ); }
	public function test_live_mode_handles_corrupt_key_without_network_or_secret_error(): void { $GLOBALS['arvan_test_options']['arvan_reseller_api_key'] = 'arv2:corrupt-envelope'; $GLOBALS['arvan_test_http_count'] = 0; $result = ( new Arvan_Reseller_Live_Cloud_Adapter() )->test_connection( 'ir-thr-at1' ); $this->assertSame( 'arvan_reseller_api_key_unavailable', $result->get_error_code() ); $this->assertSame( 0, $GLOBALS['arvan_test_http_count'] ); }
	public function test_payment_idor_and_idempotency(): void { $db = new Arvan_Test_Database(); $service = new Arvan_Reseller_Payment( new Arvan_Reseller_Wallet( $db ), $db ); $p = $service->create_payment( 7, '10.0000', 'phpunit-payment' ); $this->assertSame( 'arvan_reseller_payment_not_found', $service->confirm_payment( 8, $p['payment_reference'] )->get_error_code() ); $this->assertSame( 'completed', $service->confirm_payment( 7, $p['payment_reference'] )['status'] ); $this->assertTrue( $service->confirm_payment( 7, $p['payment_reference'] )['idempotent'] ); }
	public function test_authenticated_encryption_detects_tampering(): void { if ( ! function_exists( 'sodium_crypto_secretbox' ) && ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', array_map( 'strtolower', openssl_get_cipher_methods() ), true ) ) ) { $this->markTestSkipped( 'No authenticated-encryption backend is enabled.' ); } $cipher = Arvan_Reseller_Security::encrypt( 'machine-user-key-123456' ); $this->assertFalse( is_wp_error( $cipher ) ); $this->assertSame( 'machine-user-key-123456', Arvan_Reseller_Security::decrypt( $cipher ) ); $this->assertTrue( is_wp_error( Arvan_Reseller_Security::decrypt( substr( $cipher, 0, -2 ) . 'aa' ) ) ); }
	public function test_money_rejects_float(): void { $this->assertTrue( is_wp_error( Arvan_Reseller_Money::to_minor( 0.1 ) ) ); $this->assertSame( 12346, Arvan_Reseller_Money::to_minor( '1.23456' ) ); }
	public function test_rest_nonce_and_resource_idor(): void {
		$db = new Arvan_Test_Database(); $wallet = new Arvan_Reseller_Wallet( $db ); $api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() ); $billing = new Arvan_Reseller_Billing( $db, $wallet, $api ); $provisioning = new Arvan_Reseller_Provisioning( $db, $api ); $settlement = new Arvan_Reseller_Settlement( $db ); $cron = new Arvan_Reseller_Cron( $billing, $db, $api, new Arvan_Reseller_Notifications( $db ), $provisioning, $settlement );
		$rest = new Arvan_Reseller_REST_API( $db, $wallet, new Arvan_Reseller_Payment( $wallet, $db ), $provisioning, $billing, $api, $cron, new Arvan_Reseller_Settings() );
		$_SERVER['HTTP_X_WP_NONCE'] = 'invalid'; $this->assertSame( 'arvan_reseller_unauthorized', $rest->customer_permission()->get_error_code() );
		$_SERVER['HTTP_X_WP_NONCE'] = 'valid-wp_rest'; $this->assertTrue( $rest->customer_permission() );
		$db->save_resource( array( 'customer_id'=>7,'resource_id'=>'mock-own','product_type'=>'cloud_server','region'=>'ir-thr-mock','status'=>'active','remote_status'=>'active','last_synced_at'=>'','last_billed_at'=>'' ) );
		$db->save_resource( array( 'customer_id'=>8,'resource_id'=>'mock-foreign','product_type'=>'cloud_server','region'=>'ir-thr-mock','status'=>'active','remote_status'=>'active','last_synced_at'=>'','last_billed_at'=>'' ) );
		$this->assertSame( 1, $rest->resource( new ArrayObject( array( 'id'=>1 ) ) )['id'] );
		$this->assertSame( 'arvan_reseller_resource_not_found', $rest->resource( new ArrayObject( array( 'id'=>2 ) ) )->get_error_code() );
	}

	public function test_payment_terminal_states_and_expiration(): void {
		foreach ( array( 'failed', 'cancelled' ) as $state ) {
			$db = new Arvan_Test_Database();
			$service = new Arvan_Reseller_Payment( new Arvan_Reseller_Wallet( $db ), $db );
			$payment = $service->create_payment( 7, '10.0000', 'terminal-' . $state );
			$this->assertSame( $state, $service->close_payment( 7, $payment['payment_reference'], $state )['status'] );
			$this->assertSame( 'arvan_reseller_payment_not_confirmable', $service->confirm_payment( 7, $payment['payment_reference'] )->get_error_code() );
			$this->assertSame( 0, $db->wallets[7]['balance_minor'] );
		}

		$db = new Arvan_Test_Database();
		$service = new Arvan_Reseller_Payment( new Arvan_Reseller_Wallet( $db ), $db );
		$payment = $service->create_payment( 7, '10.0000', 'expired' );
		$db->payments[1]['expires_at'] = '2000-01-01 00:00:00';
		$this->assertSame( 'arvan_reseller_payment_expired', $service->confirm_payment( 7, $payment['payment_reference'] )->get_error_code() );
		$this->assertSame( 'expired', $db->payments[1]['status'] );
		$this->assertSame( 0, $db->wallets[7]['balance_minor'] );
	}

	public function test_provisioning_failures_and_recovery_marker(): void {
		$configuration = array( 'region'=>'ir-thr-mock','availabilityZone'=>'mock-zone-1','flavorId'=>'flavor-1','imageId'=>'image-1','name'=>'server','rootVolumeSizeGigaBytes'=>25 );

		$error_adapter = $this->provisioning_adapter( 'error' );
		$error_db = new Arvan_Test_Database();
		$error_wallet = new Arvan_Reseller_Wallet( $error_db ); $error_wallet->increase_balance( 7, '100.0000', 'payment', 'error-funds' );
		$error_result = ( new Arvan_Reseller_Provisioning( $error_db, new Arvan_Reseller_API_Client( $error_adapter ), $error_wallet ) )->create_server_order( 7, $configuration, 'api-error' );
		$this->assertSame( 'arvan_reseller_provisioning_recovery_required', $error_result->get_error_code() );
		$this->assertSame( 'failed', $error_db->orders[1]['status'] );
		$this->assertSame( 1, $error_db->orders[1]['recovery_required'] );

		$malformed_db = new Arvan_Test_Database();
		$malformed_wallet = new Arvan_Reseller_Wallet( $malformed_db ); $malformed_wallet->increase_balance( 7, '100.0000', 'payment', 'malformed-funds' );
		$malformed_result = ( new Arvan_Reseller_Provisioning( $malformed_db, new Arvan_Reseller_API_Client( $this->provisioning_adapter( 'malformed' ) ), $malformed_wallet ) )->create_server_order( 7, $configuration, 'malformed' );
		$this->assertSame( 'arvan_reseller_missing_resource_id', $malformed_result->get_error_code() );
		$this->assertSame( 'failed', $malformed_db->orders[1]['status'] );
		$this->assertSame( 1000000, $malformed_db->wallets[7]['balance_minor'] );

		$recovery_db = new Arvan_Test_Database();
		$recovery_db->fail_resource_save = true;
		$recovery_wallet = new Arvan_Reseller_Wallet( $recovery_db ); $recovery_wallet->increase_balance( 7, '100.0000', 'payment', 'recovery-funds' );
		$recovery_result = ( new Arvan_Reseller_Provisioning( $recovery_db, new Arvan_Reseller_API_Client( $this->provisioning_adapter( 'success' ) ), $recovery_wallet ) )->create_server_order( 7, $configuration, 'local-failure' );
		$this->assertSame( 'arvan_reseller_provisioning_recovery_required', $recovery_result->get_error_code() );
		$this->assertSame( 1, $recovery_db->orders[1]['recovery_required'] );
		$this->assertSame( 'remote-server-1', $recovery_db->orders[1]['resource_id'] );
	}

	public function test_admin_permission_rate_limit_and_input_sanitization(): void {
		$db = new Arvan_Test_Database(); $wallet = new Arvan_Reseller_Wallet( $db ); $api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() ); $billing = new Arvan_Reseller_Billing( $db, $wallet, $api ); $provisioning = new Arvan_Reseller_Provisioning( $db, $api ); $settlement = new Arvan_Reseller_Settlement( $db ); $cron = new Arvan_Reseller_Cron( $billing, $db, $api, new Arvan_Reseller_Notifications( $db ), $provisioning, $settlement );
		$rest = new Arvan_Reseller_REST_API( $db, $wallet, new Arvan_Reseller_Payment( $wallet, $db ), $provisioning, $billing, $api, $cron, new Arvan_Reseller_Settings() );
		$_SERVER['HTTP_X_WP_NONCE'] = 'valid-wp_rest';
		$this->assertSame( 'arvan_reseller_forbidden', $rest->admin_permission( new ArrayObject() )->get_error_code() );
		$GLOBALS['arvan_test_can_manage'] = true;
		$this->assertTrue( $rest->admin_permission( new ArrayObject() ) );
		$this->assertTrue( Arvan_Reseller_Rate_Limiter::allow( 'phpunit-sensitive', 7, 1 ) );
		$this->assertFalse( Arvan_Reseller_Rate_Limiter::allow( 'phpunit-sensitive', 7, 1 ) );
		$settings = ( new Arvan_Reseller_Settings() )->sanitize_settings( array( 'company_name'=>'<script>alert(1)</script> Safe','region'=>'http://127.0.0.1','currency'=>'<b>usd</b>' ) );
		$this->assertStringNotContainsString( '<script>', $settings['company_name'] );
		$this->assertSame( '', $settings['region'] );
		$this->assertSame( 'IRR', $settings['currency'] );
		$this->assertSame( '', $db->get_table_name( 'wallets; DROP TABLE wp_users' ) );
	}

	public function test_cron_lock_and_customer_resource_isolation(): void {
		$GLOBALS['arvan_test_options']['arvan_reseller_settings'] = array( 'mode'=>'mock','region'=>'ir-thr-mock','availability_zone'=>'mock-zone-1','currency'=>'IRR','termination_policy'=>'immediate','suspend_policy'=>'zero_balance' );
		$GLOBALS['arvan_test_options']['arvan_reseller_mock_resources'] = array(
			'resource-seven' => array( 'id'=>'resource-seven','status'=>'ACTIVE','availabilityZone'=>'mock-zone-1' ),
			'resource-eight' => array( 'id'=>'resource-eight','status'=>'ACTIVE','availabilityZone'=>'mock-zone-1' ),
		);
		$db = new Arvan_Test_Database();
		$db->ensure_wallet( 7 );
		$db->ensure_wallet( 8 );
		$db->wallets[8]['balance_minor'] = 100000;
		$created = gmdate( 'Y-m-d H:00:00' );
		$db->save_resource( array( 'customer_id'=>7,'resource_id'=>'resource-seven','product_type'=>'cloud_server','region'=>'ir-thr-mock','status'=>'active','remote_status'=>'active','hourly_price_minor'=>10000,'created_at'=>$created,'last_synced_at'=>'','last_billed_at'=>'' ) );
		$db->save_resource( array( 'customer_id'=>8,'resource_id'=>'resource-eight','product_type'=>'cloud_server','region'=>'ir-thr-mock','status'=>'active','remote_status'=>'active','hourly_price_minor'=>10000,'created_at'=>$created,'last_synced_at'=>'','last_billed_at'=>'' ) );
		$wallet = new Arvan_Reseller_Wallet( $db ); $api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() ); $billing = new Arvan_Reseller_Billing( $db, $wallet, $api ); $provisioning = new Arvan_Reseller_Provisioning( $db, $api ); $settlement = new Arvan_Reseller_Settlement( $db );
		$cron = new Arvan_Reseller_Cron( $billing, $db, $api, new Arvan_Reseller_Notifications( $db ), $provisioning, $settlement );
		$health = $cron->run_hourly_usage_sync();
		$this->assertSame( 'healthy', $health['status'] );
		$this->assertSame( 'terminated', $db->resources[1]['status'] );
		$this->assertSame( 'active', $db->resources[2]['status'] );
		$this->assertSame( 100000, $db->wallets[8]['balance_minor'] );
		$GLOBALS['arvan_test_options']['arvan_reseller_usage_sync_lock'] = array( 'token'=>'another-worker', 'expires'=>time() + 300 );
		$this->assertSame( 'arvan_reseller_cron_locked', $cron->run_hourly_usage_sync()->get_error_code() );
	}

	public function test_cron_records_bounded_retry_state(): void {
		$db = new Arvan_Test_Database();
		$db->ensure_wallet( 7 );
		$db->wallets[7]['balance_minor'] = 100000;
		$db->save_resource( array( 'customer_id'=>7,'resource_id'=>'retry-resource','product_type'=>'cloud_server','region'=>'ir-thr-mock','status'=>'active','remote_status'=>'active','hourly_price_minor'=>10000,'sync_failure_count'=>0,'created_at'=>gmdate( 'Y-m-d H:00:00' ),'last_synced_at'=>'','last_billed_at'=>'' ) );
		$wallet = new Arvan_Reseller_Wallet( $db ); $api = new Arvan_Reseller_API_Client( $this->provisioning_adapter( 'get-error' ) ); $billing = new Arvan_Reseller_Billing( $db, $wallet, $api ); $provisioning = new Arvan_Reseller_Provisioning( $db, $api ); $settlement = new Arvan_Reseller_Settlement( $db );
		$cron = new Arvan_Reseller_Cron( $billing, $db, $api, new Arvan_Reseller_Notifications( $db ), $provisioning, $settlement );
		$health = $cron->run_hourly_usage_sync();
		$this->assertSame( 'degraded', $health['status'] );
		$this->assertSame( 1, $db->resources[1]['sync_failure_count'] );
		$this->assertSame( 'arvan_reseller_api_server_error', $db->resources[1]['last_error_code'] );
		$this->assertGreaterThan( time(), strtotime( $db->resources[1]['next_retry_at'] . ' UTC' ) );
	}

	private function provisioning_adapter( string $result ): Arvan_Reseller_Cloud_Adapter_Interface {
		return new class( $result ) implements Arvan_Reseller_Cloud_Adapter_Interface {
			private string $result;
			public function __construct( string $result ) { $this->result = $result; }
			public function get_mode() { return 'mock'; }
			public function test_connection( $region ) { return array( 'status_code'=>200, 'body'=>array( 'data'=>array() ) ); }
			public function list_regions( $region ) { return array( 'status_code'=>200, 'body'=>array( 'data'=>array() ) ); }
			public function list_images( $region, array $query = array() ) { return array( 'status_code'=>200, 'body'=>array( 'data'=>array() ) ); }
			public function list_flavors( $region, array $query = array() ) { return array( 'status_code'=>200, 'body'=>array( 'data'=>array( array( 'id'=>'flavor-1', 'pricePerHour'=>'1.0000' ) ) ) ); }
			public function create_server( $region, array $payload, $idempotency_key ) {
				if ( 'error' === $this->result ) { return new WP_Error( 'arvan_reseller_api_server_error', 'Sanitized failure.', array( 'retryable'=>true ) ); }
				$data = 'malformed' === $this->result ? array( 'status'=>'ACTIVE' ) : array( 'id'=>'remote-server-1', 'status'=>'ACTIVE' );
				return array( 'status_code'=>201, 'body'=>array( 'data'=>$data ) );
			}
			public function get_server( $region, $resource_id ) { return 'get-error' === $this->result ? new WP_Error( 'arvan_reseller_api_server_error', 'Sanitized failure.', array( 'retryable'=>true ) ) : array( 'status_code'=>200, 'body'=>array( 'data'=>array( 'id'=>$resource_id, 'status'=>'ACTIVE' ) ) ); }
			public function power_off_server( $region, $resource_id, $availability_zone ) { return array( 'status_code'=>202, 'body'=>array( 'data'=>array( 'id'=>$resource_id, 'status'=>'SHUTOFF' ) ) ); }
			public function terminate_server( $region, $resource_id, $availability_zone ) { return array( 'status_code'=>202, 'body'=>array( 'data'=>array( 'id'=>$resource_id, 'status'=>'TERMINATED' ) ) ); }
			public function get_usage( $region, $resource_id, array $window, array $resource = array() ) { return new WP_Error( 'arvan_reseller_usage_api_unavailable', 'Fallback.' ); }
		};
	}
}
