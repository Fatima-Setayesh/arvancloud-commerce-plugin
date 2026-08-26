<?php
require_once __DIR__ . '/bootstrap.php';

$tests = array();

$tests['money conversion is exact and rounds half up'] = static function () {
	arvan_test_assert_same( 1000, Arvan_Reseller_Money::to_minor( '0.1' ), '0.1 conversion failed' );
	arvan_test_assert_same( 12346, Arvan_Reseller_Money::to_minor( '1.23456' ), 'half-up rounding failed' );
	arvan_test_assert_same( '12.3400', Arvan_Reseller_Money::format( 123400 ), 'fixed formatting failed' );
	arvan_test_assert_true( is_wp_error( Arvan_Reseller_Money::to_minor( 0.1 ) ), 'float money input was not rejected' );
};

$tests['wallet credit debit and database idempotency'] = static function () {
	$db     = new Arvan_Test_Database();
	$wallet = new Arvan_Reseller_Wallet( $db );
	$credit = $wallet->increase_balance( 7, '10.1234', 'payment', 'pay-1' );

	arvan_test_assert_true( ! is_wp_error( $credit ), 'credit failed' );
	arvan_test_assert_same( 101234, $db->wallets[7]['balance_minor'], 'credit balance mismatch' );
	arvan_test_assert_same( 1, count( $db->transactions ), 'credit ledger count mismatch' );

	$duplicate = $wallet->increase_balance( 7, '10.1234', 'payment', 'pay-1' );
	arvan_test_assert_true( $duplicate['idempotent'], 'duplicate was not recognized as idempotent' );
	arvan_test_assert_same( 101234, $db->wallets[7]['balance_minor'], 'duplicate changed balance' );
	arvan_test_assert_same( 1, count( $db->transactions ), 'duplicate appended ledger' );

	$debit = $wallet->decrease_balance( 7, '0.1234', 'manual', 'debit-1' );
	arvan_test_assert_true( ! is_wp_error( $debit ), 'debit failed' );
	arvan_test_assert_same( 100000, $db->wallets[7]['balance_minor'], 'debit balance mismatch' );
};

$tests['insufficient debit rolls back without ledger mutation'] = static function () {
	$db     = new Arvan_Test_Database();
	$wallet = new Arvan_Reseller_Wallet( $db );
	$wallet->increase_balance( 8, '1.0000', 'payment', 'pay-2' );
	$result = $wallet->decrease_balance( 8, '2.0000', 'manual', 'too-large' );

	arvan_test_assert_true( is_wp_error( $result ), 'insufficient debit should fail' );
	arvan_test_assert_same( 'arvan_reseller_insufficient_balance', $result->get_error_code(), 'wrong insufficient error' );
	arvan_test_assert_same( 10000, $db->wallets[8]['balance_minor'], 'failed debit changed balance' );
	arvan_test_assert_same( 1, count( $db->transactions ), 'failed debit changed ledger' );
};

$tests['ledger or cached-balance failure rolls back atomically'] = static function () {
	$db     = new Arvan_Test_Database();
	$wallet = new Arvan_Reseller_Wallet( $db );
	$wallet->create_wallet( 9 );
	$db->fail_ledger = true;
	$result = $wallet->increase_balance( 9, '5.0000', 'payment', 'ledger-fail' );
	arvan_test_assert_same( 'arvan_reseller_ledger_write_failed', $result->get_error_code(), 'wrong ledger failure' );
	arvan_test_assert_same( 0, $db->wallets[9]['balance_minor'], 'ledger failure changed balance' );

	$db->fail_ledger = false;
	$db->fail_wallet_update = true;
	$result = $wallet->increase_balance( 9, '5.0000', 'payment', 'wallet-fail' );
	arvan_test_assert_same( 'arvan_reseller_wallet_update_failed', $result->get_error_code(), 'wrong wallet failure' );
	arvan_test_assert_same( 0, $db->wallets[9]['balance_minor'], 'wallet failure changed balance' );
	arvan_test_assert_same( 0, count( $db->transactions ), 'wallet failure retained uncommitted ledger' );
};

$tests['ledger reconciliation detects and repairs cached drift'] = static function () {
	$db     = new Arvan_Test_Database();
	$wallet = new Arvan_Reseller_Wallet( $db );
	$wallet->increase_balance( 10, '3.0000', 'payment', 'pay-3' );
	$db->wallets[10]['balance_minor'] = 1;

	$diagnostic = $wallet->reconcile( 10 );
	arvan_test_assert_true( ! $diagnostic['matches'], 'reconciliation missed drift' );

	$GLOBALS['arvan_test_can_manage'] = true;
	$repair = $wallet->reconcile( 10, true );
	$GLOBALS['arvan_test_can_manage'] = false;
	arvan_test_assert_true( $repair['matches'] && $repair['repaired'], 'admin repair failed' );
	arvan_test_assert_same( 30000, $db->wallets[10]['balance_minor'], 'repair did not use ledger value' );
};

$tests['usage greater than balance charges to zero and records uncovered amount'] = static function () {
	$db      = new Arvan_Test_Database();
	$wallet  = new Arvan_Reseller_Wallet( $db );
	$billing = new Arvan_Reseller_Billing( $db, $wallet, new Arvan_Reseller_API_Client() );
	$wallet->increase_balance( 11, '5.0000', 'payment', 'pay-4' );
	$resource = array( 'id' => 101, 'customer_id' => 11, 'resource_id' => 'server-1' );
	$usage = array( 'start' => '2026-01-01 00:00:00', 'end' => '2026-01-01 01:00:00', 'usage_amount' => '2.0000', 'unit_price' => '3.0000', 'unit' => 'hour' );
	$result = $billing->process_resource_usage( $resource, $usage );

	arvan_test_assert_true( ! is_wp_error( $result ), 'partial prepaid billing failed' );
	arvan_test_assert_same( 0, $db->wallets[11]['balance_minor'], 'partial debit did not reach zero' );
	arvan_test_assert_same( 50000, $result['charged_minor'], 'charged amount mismatch' );
	arvan_test_assert_same( 10000, $result['uncovered_minor'], 'uncovered amount mismatch' );
	arvan_test_assert_same( 1, count( $db->usage_records ), 'usage row missing' );

	$duplicate = $billing->process_resource_usage( $resource, $usage );
	arvan_test_assert_same( 'arvan_reseller_duplicate_billing', $duplicate->get_error_code(), 'duplicate usage was not blocked' );
	arvan_test_assert_same( 0, $db->wallets[11]['balance_minor'], 'duplicate usage changed balance' );
};

$tests['billing uses exact integer rounding at zero and twenty percent share'] = static function () {
	$db      = new Arvan_Test_Database();
	$wallet  = new Arvan_Reseller_Wallet( $db );
	$billing = new Arvan_Reseller_Billing( $db, $wallet, new Arvan_Reseller_API_Client() );

	$zero = $billing->calculate_customer_charge( '0.3333', '0.1000', '0' );
	arvan_test_assert_same( 333, $zero['base_cost_minor'], 'base cost rounding mismatch' );
	arvan_test_assert_same( 0, $zero['reseller_share_minor'], 'zero-percent share mismatch' );
	arvan_test_assert_same( 333, $zero['total_charge_minor'], 'zero-percent total mismatch' );

	$twenty = $billing->calculate_customer_charge( '0.3333', '0.1000', '20' );
	arvan_test_assert_same( 67, $twenty['reseller_share_minor'], 'twenty-percent rounding mismatch' );
	arvan_test_assert_same( 400, $twenty['total_charge_minor'], 'twenty-percent total mismatch' );

	$capped = $billing->calculate_customer_charge( '1', '1', '999' );
	arvan_test_assert_same( '20.00', $capped['share_percent'], 'share was not capped at twenty percent' );
};

$tests['billing write failure rolls wallet and ledger back'] = static function () {
	$db      = new Arvan_Test_Database();
	$wallet  = new Arvan_Reseller_Wallet( $db );
	$billing = new Arvan_Reseller_Billing( $db, $wallet, new Arvan_Reseller_API_Client() );
	$wallet->increase_balance( 12, '10.0000', 'payment', 'pay-5' );
	$ledger_count = count( $db->transactions );
	$db->fail_usage = true;
	$result = $billing->process_resource_usage(
		array( 'id' => 102, 'customer_id' => 12, 'resource_id' => 'server-2' ),
		array( 'start' => '2026-01-01 00:00:00', 'end' => '2026-01-01 01:00:00', 'usage_amount' => '1.0000', 'unit_price' => '2.0000', 'unit' => 'hour' )
	);

	arvan_test_assert_true( is_wp_error( $result ), 'billing failure should return an error' );
	arvan_test_assert_same( 100000, $db->wallets[12]['balance_minor'], 'billing failure did not restore balance' );
	arvan_test_assert_same( $ledger_count, count( $db->transactions ), 'billing failure retained ledger debit' );
};

$tests['configured currency stays consistent across wallet billing and history'] = static function () {
	$GLOBALS['arvan_test_options']['arvan_reseller_settings'] = array( 'currency' => 'USD' );
	$db      = new Arvan_Test_Database();
	$wallet  = new Arvan_Reseller_Wallet( $db );
	$billing = new Arvan_Reseller_Billing( $db, $wallet, new Arvan_Reseller_API_Client() );
	$wallet->increase_balance( 13, '5.0000', 'payment', 'usd-credit', '', 'USD' );
	$wallet->increase_balance( 13, '7.0000', 'payment', 'irr-credit', '', 'IRR' );
	$result = $billing->process_resource_usage(
		array( 'id' => 103, 'customer_id' => 13, 'resource_id' => 'server-usd', 'currency' => 'USD' ),
		array( 'start' => '2026-01-01 00:00:00', 'end' => '2026-01-01 01:00:00', 'usage_amount' => '1.0000', 'unit_price' => '2.0000', 'unit' => 'hour' )
	);

	arvan_test_assert_true( ! is_wp_error( $result ), 'USD billing failed' );
	arvan_test_assert_same( 30000, $db->wallets['13:USD']['balance_minor'], 'USD wallet was not debited' );
	arvan_test_assert_same( 70000, $db->wallets[13]['balance_minor'], 'IRR wallet was changed by USD billing' );
	arvan_test_assert_same( 'USD', $db->usage_records[0]['currency'], 'usage currency did not follow resource snapshot' );
	arvan_test_assert_same( 3, count( $wallet->get_balance_history( 13, 50 ) ), 'unfiltered history lost a currency' );
	arvan_test_assert_same( 2, count( $wallet->get_balance_history( 13, 50, 'USD' ) ), 'USD history filter was not applied' );
};

$tests['schema contains all domain tables and no decimal money columns'] = static function () {
	$schema = file_get_contents( dirname( __DIR__ ) . '/arvan-reseller/database/schema.php' );
	foreach ( array( 'wallets', 'wallet_transactions', 'payments', 'orders', 'resources', 'usage_records', 'invoices', 'settlements', 'notifications', 'audit_logs' ) as $table ) {
		arvan_test_assert_true( false !== strpos( $schema, "{$table} (" ), "missing schema table {$table}" );
	}

	arvan_test_assert_true( false === stripos( $schema, 'decimal(' ), 'schema still contains DECIMAL money columns' );
	arvan_test_assert_true( false !== strpos( $schema, 'UNIQUE KEY idempotency_key' ), 'idempotency unique key missing' );
	arvan_test_assert_true( false !== strpos( $schema, 'UNIQUE KEY resource_window' ), 'billing window unique key missing' );
	arvan_test_assert_true( false !== strpos( $schema, 'read_at datetime NULL' ), 'notification read state column missing' );
};

$tests['versioned migration creates the complete critical schema'] = static function () {
	$GLOBALS['arvan_test_options'] = array();
	$GLOBALS['wpdb'] = new class() {
		public $prefix = 'wp_';
		public $tables = array();
		public $indexes = array();

		public function get_charset_collate() {
			return 'DEFAULT CHARACTER SET utf8mb4';
		}

		public function esc_like( $value ) {
			return $value;
		}

		public function prepare( $query, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}

			foreach ( $args as $arg ) {
				$replacement = is_int( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
				$query = preg_replace( '/%[sd]/', $replacement, $query, 1 );
			}

			return $query;
		}

		public function get_var( $query ) {
			if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $query, $match ) ) {
				return isset( $this->tables[ $match[1] ] ) ? $match[1] : null;
			}

			if ( preg_match( "/SHOW COLUMNS FROM ([A-Za-z0-9_]+) LIKE '([^']+)'/", $query, $match ) ) {
				return isset( $this->tables[ $match[1] ][ $match[2] ] ) ? $match[2] : null;
			}

			if ( preg_match( "/SHOW INDEX FROM ([A-Za-z0-9_]+) WHERE Key_name = '([^']+)'/", $query, $match ) ) {
				return isset( $this->indexes[ $match[1] ][ $match[2] ] ) ? $match[1] : null;
			}

			return null;
		}

		public function get_row( $query ) {
			if ( preg_match( "/SHOW INDEX FROM ([A-Za-z0-9_]+) WHERE Key_name = '([^']+)'/", $query, $match ) && isset( $this->indexes[ $match[1] ][ $match[2] ] ) ) {
				return array( 'Non_unique' => $this->indexes[ $match[1] ][ $match[2] ] ? 0 : 1 );
			}

			return null;
		}

		public function query() {
			return 0;
		}

		public function record_schema_query( $query ) {
			if ( ! preg_match( '/CREATE TABLE ([A-Za-z0-9_]+) \(/', $query, $table_match ) ) {
				return;
			}

			$table = $table_match[1];
			$this->tables[ $table ] = array();
			$this->indexes[ $table ] = array();

			foreach ( preg_split( '/\R/', $query ) as $line ) {
				$line = trim( $line );

				if ( preg_match( '/^([a-z_][a-z0-9_]*)\s+[a-z]/i', $line, $column_match ) && ! in_array( strtoupper( $column_match[1] ), array( 'PRIMARY', 'UNIQUE', 'KEY' ), true ) ) {
					$this->tables[ $table ][ $column_match[1] ] = true;
				}

				if ( preg_match( '/^UNIQUE KEY ([a-z_][a-z0-9_]*)/i', $line, $index_match ) ) {
					$this->indexes[ $table ][ $index_match[1] ] = true;
				} elseif ( preg_match( '/^KEY ([a-z_][a-z0-9_]*)/i', $line, $index_match ) ) {
					$this->indexes[ $table ][ $index_match[1] ] = false;
				}
			}
		}
	};

	require_once dirname( __DIR__ ) . '/arvan-reseller/database/schema.php';
	require_once dirname( __DIR__ ) . '/arvan-reseller/database/migrations.php';

	$result = arvan_reseller_run_migrations();
	arvan_test_assert_true( $result, 'migration did not verify the resulting schema' );
	arvan_test_assert_same( '1.4.0', get_option( 'arvan_reseller_db_version' ), 'schema version was not advanced' );
	arvan_test_assert_same( 10, count( $GLOBALS['wpdb']->tables ), 'migration did not create all domain tables' );
	arvan_test_assert_true( isset( $GLOBALS['wpdb']->tables['wp_arvan_reseller_wallets']['balance_minor'] ), 'wallet integer balance column missing' );
};

$tests['persisted domain statuses are allowlisted'] = static function () {
	arvan_test_assert_true( Arvan_Reseller_Status::is_valid( 'wallets', 'active' ), 'valid wallet status rejected' );
	arvan_test_assert_true( ! Arvan_Reseller_Status::is_valid( 'wallets', 'hacked' ), 'invalid wallet status accepted' );
	arvan_test_assert_true( Arvan_Reseller_Status::is_valid( 'payments', 'refunded' ), 'required payment status missing' );
	arvan_test_assert_true( Arvan_Reseller_Status::is_valid( 'resources', 'suspended' ), 'required resource status missing' );
};

$tests['authenticated secret encryption uses random nonces and rejects tampering'] = static function () {
	if ( ! function_exists( 'sodium_crypto_secretbox' ) && ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', array_map( 'strtolower', openssl_get_cipher_methods() ), true ) ) ) {
		throw new Arvan_Test_Skip( 'No authenticated-encryption backend is enabled in this PHP runtime.' );
	}
	$one = Arvan_Reseller_Security::encrypt( 'machine-user-key-123456' );
	$two = Arvan_Reseller_Security::encrypt( 'machine-user-key-123456' );
	arvan_test_assert_true( ! is_wp_error( $one ) && ! is_wp_error( $two ), 'secret encryption failed' );
	arvan_test_assert_true( $one !== $two, 'encryption nonce was deterministic' );
	arvan_test_assert_same( 'machine-user-key-123456', Arvan_Reseller_Security::decrypt( $one ), 'secret did not decrypt' );
	$tampered = substr( $one, 0, -2 ) . 'aa';
	arvan_test_assert_true( is_wp_error( Arvan_Reseller_Security::decrypt( $tampered ) ), 'tampered secret was accepted' );
	arvan_test_assert_same( '[REDACTED]', Arvan_Reseller_Security::redact( array( 'api_key' => 'secret' ) )['api_key'], 'audit redaction failed' );
};

$tests['mock payment confirmation is atomic idempotent and isolated'] = static function () {
	$db = new Arvan_Test_Database(); $wallet = new Arvan_Reseller_Wallet( $db ); $payments = new Arvan_Reseller_Payment( $wallet, $db );
	$created = $payments->create_payment( 7, '25.0000', 'client-payment-1' );
	arvan_test_assert_true( ! is_wp_error( $created ) && 'pending' === $created['status'], 'payment intent failed' );
	$foreign = $payments->confirm_payment( 8, $created['payment_reference'] );
	arvan_test_assert_same( 'arvan_reseller_payment_not_found', $foreign->get_error_code(), 'payment IDOR was not blocked' );
	$confirmed = $payments->confirm_payment( 7, $created['payment_reference'] );
	arvan_test_assert_same( 'completed', $confirmed['status'], 'payment confirmation failed' );
	arvan_test_assert_same( 250000, $db->wallets[7]['balance_minor'], 'payment did not credit wallet' );
	$again = $payments->confirm_payment( 7, $created['payment_reference'] );
	arvan_test_assert_true( $again['idempotent'], 'repeat confirmation was not idempotent' );
	arvan_test_assert_same( 1, count( $db->transactions ), 'repeat confirmation duplicated ledger credit' );
	arvan_test_assert_same( 1, count( $db->notifications ), 'payment event notification was missing or duplicated' );
};

$tests['admin mock refund is atomic and idempotent'] = static function () {
	$db = new Arvan_Test_Database(); $wallet = new Arvan_Reseller_Wallet( $db ); $payments = new Arvan_Reseller_Payment( $wallet, $db );
	$created = $payments->create_payment( 7, '5.0000', 'refund-payment-1' ); $payments->confirm_payment( 7, $created['payment_reference'] );
	$GLOBALS['arvan_test_can_manage'] = true; $refunded = $payments->refund_payment( $created['payment_reference'] ); $again = $payments->refund_payment( $created['payment_reference'] ); $GLOBALS['arvan_test_can_manage'] = false;
	arvan_test_assert_same( 'refunded', $refunded['status'], 'refund status mismatch' );
	arvan_test_assert_same( 0, $db->wallets[7]['balance_minor'], 'refund did not reverse wallet credit' );
	arvan_test_assert_true( $again['idempotent'], 'repeat refund was not idempotent' );
	arvan_test_assert_same( 2, count( $db->transactions ), 'repeat refund changed immutable ledger' );
};

$tests['mock adapter is deterministic and never invokes HTTP'] = static function () {
	$GLOBALS['arvan_test_http_count'] = 0; $GLOBALS['arvan_test_options']['arvan_reseller_settings'] = array( 'mode' => 'mock', 'region' => 'ir-thr-mock' );
	$api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() );
	$payload = array( 'availabilityZone' => 'mock-zone-1', 'flavorId' => 'mock-g2-1-2', 'imageId' => '00000000-0000-4000-8000-000000000101', 'name' => 'test-server', 'rootVolumeSizeGigaBytes' => 25 );
	$first = $api->create_server( 'ir-thr-mock', $payload, 'same-key' ); $second = $api->create_server( 'ir-thr-mock', $payload, 'same-key' );
	arvan_test_assert_same( $first['body']['data']['id'], $second['body']['data']['id'], 'mock ID was not deterministic' );
	arvan_test_assert_same( 0, $GLOBALS['arvan_test_http_count'], 'Mock Mode made a network request' );
};

$tests['live adapter blocks invalid regions before network access'] = static function () {
	$GLOBALS['arvan_test_http_count'] = 0;
	$result = ( new Arvan_Reseller_Live_Cloud_Adapter() )->test_connection( 'https://127.0.0.1/admin' );
	arvan_test_assert_same( 'arvan_reseller_invalid_region', $result->get_error_code(), 'invalid live region was not blocked' );
	arvan_test_assert_same( 0, $GLOBALS['arvan_test_http_count'], 'SSRF input reached HTTP transport' );
};

$tests['provisioning creates local order then maps one remote resource'] = static function () {
	$GLOBALS['arvan_test_options']['arvan_reseller_mock_resources'] = array();
	$GLOBALS['arvan_test_options']['arvan_reseller_settings'] = array( 'mode' => 'mock', 'currency' => 'IRR', 'reseller_share_percent' => '10' );
	$db = new Arvan_Test_Database(); $api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() ); $service = new Arvan_Reseller_Provisioning( $db, $api );
	$config = array( 'region' => 'ir-thr-mock', 'availabilityZone' => 'mock-zone-1', 'flavorId' => 'mock-g2-1-2', 'imageId' => '00000000-0000-4000-8000-000000000101', 'name' => 'ordered-server', 'rootVolumeSizeGigaBytes' => 25 );
	$result = $service->create_server_order( 7, $config, 'order-key-1' );
	arvan_test_assert_true( ! is_wp_error( $result ), 'safe provisioning failed' );
	arvan_test_assert_same( 'provisioned', $result['status'], 'order was not provisioned' );
	arvan_test_assert_same( 'ordered-server', $result['configuration']['name'], 'order configuration contract is incomplete' );
	arvan_test_assert_same( '26400.0000', $result['quote']['total_charge'], 'authoritative 24-hour quote mismatch' );
	arvan_test_assert_same( 'not_charged_at_order', $result['payment']['status'], 'order payment timing is ambiguous' );
	arvan_test_assert_true( $result['resource_record_id'] > 0, 'local resource mapping is absent from order response' );
	arvan_test_assert_same( 1, count( $db->orders ), 'local order count mismatch' );
	arvan_test_assert_same( 1, count( $db->resources ), 'resource mapping count mismatch' );
	$duplicate = $service->create_server_order( 7, $config, 'order-key-1' );
	arvan_test_assert_true( $duplicate['idempotent'], 'duplicate order was not idempotent' );
	arvan_test_assert_same( 1, count( get_option( 'arvan_reseller_mock_resources', array() ) ), 'duplicate order created another server' );
};

$tests['failed provisioning records one safe customer event'] = static function () {
	$GLOBALS['arvan_test_options']['arvan_reseller_settings'] = array( 'mode'=>'mock','currency'=>'IRR','notification_enabled'=>1 );
	$db = new Arvan_Test_Database(); $service = new Arvan_Reseller_Provisioning( $db, new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() ) );
	$result = $service->create_server_order( 7, array( 'region'=>'ir-thr-mock','availabilityZone'=>'mock-zone-1','flavorId'=>'missing-flavor','imageId'=>'image','name'=>'failed-server','rootVolumeSizeGigaBytes'=>25 ), 'failed-order-key' );
	arvan_test_assert_same( 'arvan_reseller_flavor_not_found', $result->get_error_code(), 'provisioning failure code was not stable' );
	arvan_test_assert_same( 1, count( $db->notifications ), 'provisioning failure notification missing' );
	$notification = array_values( $db->notifications )[0];
	arvan_test_assert_same( 'provisioning_failed', $notification['notification_type'], 'wrong provisioning notification type' );
	arvan_test_assert_true( false === strpos( (string) $notification['payload'], 'api_key' ), 'provisioning notification leaked sensitive data' );
};

$tests['resource contract allowlists useful details without leaking remote payload'] = static function () {
	$db = new Arvan_Test_Database(); $api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() ); $wallet = new Arvan_Reseller_Wallet( $db );
	$provisioning = new Arvan_Reseller_Provisioning( $db, $api ); $billing = new Arvan_Reseller_Billing( $db, $wallet, $api ); $settlement = new Arvan_Reseller_Settlement( $db );
	$rest = new Arvan_Reseller_REST_API( $db, $wallet, new Arvan_Reseller_Payment( $wallet, $db ), $provisioning, $billing, $api, new Arvan_Reseller_Cron( $billing, $db, $api, new Arvan_Reseller_Notifications( $db ), $provisioning, $settlement ), new Arvan_Reseller_Settings() );
	$safe = $rest->safe_resource( array( 'id'=>4,'customer_id'=>7,'order_id'=>3,'resource_id'=>'srv-safe','product_type'=>'cloud_server','region'=>'ir-thr','status'=>'active','remote_status'=>'ACTIVE','hourly_price_minor'=>10000,'currency'=>'USD','remote_payload'=>wp_json_encode(array('name'=>'prod-1','availabilityZone'=>'az-1','publicIpAddress'=>'192.0.2.10','api_key'=>'must-not-leak','image'=>array('id'=>'ubuntu','name'=>'Ubuntu 22.04'),'flavor'=>array('id'=>'g2','cpuCount'=>2,'secret'=>'no'))),'last_synced_at'=>'','last_billed_at'=>'','created_at'=>'','updated_at'=>'' ) );
	arvan_test_assert_same( 7, $safe['customer_id'], 'admin resource ownership field missing' );
	arvan_test_assert_same( 'prod-1', $safe['name'], 'resource name missing' );
	arvan_test_assert_same( array( '192.0.2.10' ), $safe['ip_addresses'], 'validated resource IP missing' );
	arvan_test_assert_true( ! isset( $safe['remote_payload'], $safe['api_key'] ) && ! isset( $safe['flavor']['secret'] ), 'remote secrets leaked through resource contract' );
};

$tests['notification read transition is idempotent and customer isolated'] = static function () {
	$db = new Arvan_Test_Database(); $id = $db->create_notification( array( 'customer_id'=>7,'event_key'=>'evt-read','type'=>'billing','subject'=>'Notice','message'=>'Message' ) );
	arvan_test_assert_same( null, $db->mark_notification_read( $id, 8 ), 'foreign customer changed notification read state' );
	$read = $db->mark_notification_read( $id, 7 ); $again = $db->mark_notification_read( $id, 7 );
	arvan_test_assert_same( '2026-01-01 00:00:00', $read['read_at'], 'owned notification was not marked read' );
	arvan_test_assert_same( $read['read_at'], $again['read_at'], 'repeat notification read was not idempotent' );
};

$tests['settlement period is aggregated and idempotent'] = static function () {
	$db = new Arvan_Test_Database(); $db->usage_records[] = array( 'base_cost_minor' => 100, 'total_charge_minor' => 120, 'reseller_share_minor' => 20 );
	$settlement = new Arvan_Reseller_Settlement( $db );
	$first = $settlement->settle_period( '2026-01-01 00:00:00', '2026-01-02 00:00:00', 'IRR' );
	$second = $settlement->settle_period( '2026-01-01 00:00:00', '2026-01-02 00:00:00', 'IRR' );
	arvan_test_assert_same( 120, $first['customer_charge_minor'], 'settlement total mismatch' );
	arvan_test_assert_true( $second['idempotent'], 'settlement retry was not idempotent' );
	arvan_test_assert_same( 1, count( $db->settlements ), 'settlement duplicated' );
};

$tests['failed low-balance email retries once and then deduplicates'] = static function () {
	$db = new Arvan_Test_Database(); $db->ensure_wallet( 7 ); $db->wallets[7]['threshold_minor'] = 20000; $db->wallets[7]['balance_minor'] = 10000;
	$notifications = new Arvan_Reseller_Notifications( $db ); $GLOBALS['arvan_test_mail_count'] = 0; $GLOBALS['arvan_test_mail_success'] = false;
	$failed = $notifications->maybe_send_low_balance( 7 );
	$GLOBALS['arvan_test_mail_success'] = true; $sent = $notifications->maybe_send_low_balance( 7 ); $skipped = $notifications->maybe_send_low_balance( 7 );
	arvan_test_assert_same( 'failed', $failed['status'], 'mail failure was not recorded' );
	arvan_test_assert_same( 'sent', $sent['status'], 'failed mail was not retried' );
	arvan_test_assert_true( $skipped['skipped'], 'sent threshold warning was not deduplicated' );
	arvan_test_assert_same( 2, $GLOBALS['arvan_test_mail_count'], 'unexpected notification attempt count' );
};

$tests['expired Cron lock is replaced with token-safe release'] = static function () {
	$GLOBALS['wpdb'] = new Arvan_Test_Option_Wpdb(); $GLOBALS['arvan_test_options']['arvan_reseller_usage_sync_lock'] = array( 'token' => 'stale', 'expires' => 0 );
	$db = new Arvan_Test_Database(); $api = new Arvan_Reseller_API_Client( new Arvan_Reseller_Mock_Cloud_Adapter() ); $wallet = new Arvan_Reseller_Wallet( $db );
	$provisioning = new Arvan_Reseller_Provisioning( $db, $api ); $settlement = new Arvan_Reseller_Settlement( $db );
	$cron = new Arvan_Reseller_Cron( new Arvan_Reseller_Billing( $db, $wallet, $api ), $db, $api, new Arvan_Reseller_Notifications( $db ), $provisioning, $settlement );
	$result = $cron->run_hourly_usage_sync();
	arvan_test_assert_same( 'healthy', $result['status'], 'stale Cron lock was not recovered' );
	arvan_test_assert_true( ! array_key_exists( 'arvan_reseller_usage_sync_lock', $GLOBALS['arvan_test_options'] ), 'Cron lock was not token-safely released' );
};

$tests['REST and lifecycle contracts contain required security boundaries'] = static function () {
	$rest = file_get_contents( dirname( __DIR__ ) . '/arvan-reseller/includes/class-rest-api.php' );
	$live = file_get_contents( dirname( __DIR__ ) . '/arvan-reseller/includes/class-live-cloud-adapter.php' );
	arvan_test_assert_true( false !== strpos( $rest, 'get_current_user_id()' ) && false !== strpos( $rest, 'customer_id' ), 'REST customer identity boundary missing' );
	arvan_test_assert_true( false !== strpos( $rest, 'verify_rest_nonce' ) && false !== strpos( $rest, 'can_manage_plugin' ), 'REST nonce/capability boundary missing' );
	arvan_test_assert_true( false !== strpos( $live, "'redirection'         => 0" ), 'redirect blocking missing' );
	arvan_test_assert_true( false !== strpos( $live, "OFFICIAL_HOST_SUFFIX = '.arvanapis.ir'" ), 'official host allowlist missing' );
	arvan_test_assert_true( false === strpos( $live, 'api_base_url' ), 'live adapter permits arbitrary base URL' );
	arvan_test_assert_true( false !== strpos( $rest, "'X-Arvan-Has-More'" ) && false !== strpos( $rest, "'page'  => array(" ), 'bounded REST pagination metadata missing' );
};

$tests['settings preserve hidden values and enforce backend policy allowlists'] = static function () {
	$GLOBALS['arvan_test_can_manage'] = true;
	$GLOBALS['arvan_test_options']['arvan_reseller_settings'] = array( 'hidden_future_setting' => 'preserve-me', 'api_timeout' => 19 );
	$settings = new Arvan_Reseller_Settings();
	$result = $settings->sanitize_settings( array( 'mode'=>'live','region'=>'ir-thr-at1','availability_zone'=>'zone-a','currency'=>'usd','reseller_share_percent'=>'99','default_wallet_threshold'=>'10','minimum_topup'=>'2','maximum_topup'=>'1','termination_policy'=>'grace','termination_grace_hours'=>'48','suspend_policy'=>'zero_balance','notification_enabled'=>1 ) );
	$GLOBALS['arvan_test_can_manage'] = false;
	arvan_test_assert_same( 'preserve-me', $result['hidden_future_setting'], 'hidden setting was lost' );
	arvan_test_assert_same( '20.0000', $result['reseller_share_percent'], 'reseller share was not capped' );
	arvan_test_assert_same( '2.0000', $result['maximum_topup'], 'maximum top-up was not constrained to minimum' );
	arvan_test_assert_same( 'USD', $result['currency'], 'currency was not normalized' );
	arvan_test_assert_same( 'cloud_server', $result['product_type'], 'product selection was not locked' );
};

$tests['credential validation fails closed and uninstall never retains secrets'] = static function () {
	$GLOBALS['arvan_test_can_manage'] = true; $GLOBALS['arvan_test_settings_errors'] = array();
	$settings = new Arvan_Reseller_Settings(); $settings->sanitize_settings( array( 'api_key'=>'short-secret' ) );
	$GLOBALS['arvan_test_can_manage'] = false;
	arvan_test_assert_same( 'arvan_reseller_invalid_api_key', $GLOBALS['arvan_test_settings_errors'][0]['code'], 'invalid credential storage failed silently' );
	$uninstall = file_get_contents( dirname( __DIR__ ) . '/arvan-reseller/uninstall.php' );
	$secret_delete = strpos( $uninstall, "delete_option( 'arvan_reseller_api_key' )" ); $retention_return = strpos( $uninstall, "if ( empty( \$settings['delete_data_on_uninstall'] ) )" );
	arvan_test_assert_true( false !== $secret_delete && false !== $retention_return && $secret_delete < $retention_return, 'uninstall retention path can retain the encrypted credential' );
};

$passed = 0;
$failed = 0;
$skipped = 0;

foreach ( $tests as $name => $test ) {
	try {
		$test();
		++$passed;
		echo "PASS: {$name}\n";
	} catch ( Arvan_Test_Skip $skip ) {
		++$skipped;
		echo "SKIP: {$name}\n{$skip->getMessage()}\n";
	} catch ( Throwable $error ) {
		++$failed;
		echo "FAIL: {$name}\n{$error->getMessage()}\n";
	}
}

echo "\n{$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit( $failed > 0 ? 1 : 0 );
