<?php
/**
 * Minimal WordPress-compatible bootstrap for isolated financial unit tests.
 */

define( 'ABSPATH', __DIR__ . '/fixtures/wordpress/' );
define( 'ARVAN_RESELLER_PATH', dirname( __DIR__ ) . '/arvan-reseller/' );
define( 'ARVAN_RESELLER_VERSION', '1.1.0' );
define( 'ARVAN_RESELLER_DB_VERSION', '1.4.0' );
define( 'ARVAN_RESELLER_MONEY_SCALE', 10000 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['arvan_test_options'] = array();
$GLOBALS['arvan_test_can_manage'] = false;
$GLOBALS['arvan_test_current_user'] = 7;
$GLOBALS['arvan_test_mail_count'] = 0;
$GLOBALS['arvan_test_mail_success'] = true;
$GLOBALS['arvan_test_http_count'] = 0;
$GLOBALS['arvan_test_schedules'] = array();
$GLOBALS['arvan_test_pages'] = array();
$GLOBALS['arvan_test_settings_errors'] = array();
class Arvan_Test_Option_Wpdb {
	public $options = 'wp_options';
	public function delete( $table, array $where ) {
		$key = $where['option_name'];
		if ( ! array_key_exists( $key, $GLOBALS['arvan_test_options'] ) || serialize( $GLOBALS['arvan_test_options'][ $key ] ) !== $where['option_value'] ) { return 0; }
		unset( $GLOBALS['arvan_test_options'][ $key ] ); return 1;
	}
}
$GLOBALS['wpdb'] = new Arvan_Test_Option_Wpdb();

class WP_Error {
	private $code;
	private $message;

	private $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
	public function get_error_messages() { return array( $this->message ); }
	public function get_error_data() { return $this->data; }
	public function add_data( $data ) { $this->data = $data; }
}

class Arvan_Test_Skip extends RuntimeException {}
class WP_Post {
	public $ID;
	public $post_content;
	public $post_name;
	public function __construct( $id, $content ) { $this->ID = $id; $this->post_content = $content; }
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function __( $message ) {
	return $message;
}

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_textarea_field( $value ) { return sanitize_text_field( $value ); }
function wp_unslash( $value ) { return $value; }
function esc_html( $value ) { return (string) $value; }
function esc_html__( $value ) { return (string) $value; }
function esc_url_raw( $value ) { return (string) $value; }
function wp_salt( $scheme = '' ) { return 'test-salt-' . $scheme; }

function current_time() {
	return '2026-01-01 00:00:00';
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['arvan_test_options'] ) ? $GLOBALS['arvan_test_options'][ $key ] : $default;
}

function update_option( $key, $value ) {
	$GLOBALS['arvan_test_options'][ $key ] = $value;

	return true;
}
function add_option( $key, $value ) { if ( array_key_exists( $key, $GLOBALS['arvan_test_options'] ) ) { return false; } $GLOBALS['arvan_test_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['arvan_test_options'][ $key ] ); return true; }
function set_transient( $key, $value ) { return update_option( '_transient_' . $key, $value ); }
function get_transient( $key ) { return get_option( '_transient_' . $key, false ); }
function is_user_logged_in() { return $GLOBALS['arvan_test_current_user'] > 0; }
function get_current_user_id() { return (int) $GLOBALS['arvan_test_current_user']; }
function wp_verify_nonce( $nonce, $action ) { return 'valid-' . $action === $nonce ? 1 : false; }
function get_userdata( $id ) { return (object) array( 'ID' => $id, 'user_email' => 'customer' . $id . '@example.test' ); }
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function wp_mail() { ++$GLOBALS['arvan_test_mail_count']; return (bool) $GLOBALS['arvan_test_mail_success']; }
function apply_filters( $tag, $value ) { return $value; }
function wp_safe_remote_request() { ++$GLOBALS['arvan_test_http_count']; return new WP_Error( 'network_forbidden', 'Network was called.' ); }
function maybe_serialize( $value ) { return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value; }
function wp_cache_delete() { return true; }
function wp_next_scheduled( $hook = '' ) { return $GLOBALS['arvan_test_schedules'][ $hook ] ?? false; }
function wp_schedule_event( $time, $recurrence, $hook ) { $GLOBALS['arvan_test_schedules'][ $hook ] = $time; return true; }
function flush_rewrite_rules() { return true; }
function get_role() { return new class() { public function add_cap() {} public function remove_cap() {} }; }
function get_post( $id ) { return $GLOBALS['arvan_test_pages'][(int) $id] ?? null; }
function get_post_status( $id ) { return isset( $GLOBALS['arvan_test_pages'][(int) $id] ) ? 'publish' : null; }
function get_page_by_path( $slug ) { foreach ( $GLOBALS['arvan_test_pages'] as $page ) { if ( $page->post_name === $slug ) { return $page; } } return null; }
function has_shortcode( $content, $shortcode ) { return false !== strpos( (string) $content, '[' . $shortcode . ']' ); }
function wp_insert_post( array $data, $return_error = false ) { unset( $return_error ); $id=count($GLOBALS['arvan_test_pages'])+1; $page=new WP_Post($id,$data['post_content']); $page->post_name=$data['post_name']; $GLOBALS['arvan_test_pages'][$id]=$page; return $id; }
function add_settings_error( $setting, $code, $message, $type = 'error' ) { $GLOBALS['arvan_test_settings_errors'][]=compact('setting','code','message','type'); }

function current_user_can() {
	return (bool) $GLOBALS['arvan_test_can_manage'];
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, $args );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function wp_generate_uuid4() {
	return '00000000-0000-4000-8000-000000000001';
}

require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-money.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-status.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-security.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-database.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-wallet.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-cloud-adapter-interface.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-live-cloud-adapter.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-mock-cloud-adapter.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-api-client.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-billing.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-payment.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-provisioning.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-notifications.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-settlement.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-cron.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/admin/class-settings.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-rate-limiter.php';
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-rest-api.php';

class Arvan_Test_Database extends Arvan_Reseller_Database {
	public $wallets = array();
	public $transactions = array();
	public $usage_records = array();
	public $resources = array();
	public $payments = array();
	public $orders = array();
	public $notifications = array();
	public $invoices = array();
	public $settlements = array();
	public $audits = array();
	public $fail_ledger = false;
	public $fail_wallet_update = false;
	public $fail_usage = false;
	public $fail_cursor = false;
	public $fail_resource_save = false;
	public $begins = 0;
	public $commits = 0;
	public $rollbacks = 0;
	private $active = false;
	private $snapshot = array();

	public function __construct() {}

	public function begin_transaction() {
		if ( ! $this->active ) {
			$this->snapshot = array( $this->wallets, $this->transactions, $this->usage_records, $this->resources, $this->payments, $this->orders, $this->notifications, $this->invoices, $this->settlements, $this->audits );
			$this->active   = true;
			++$this->begins;
		}

		return true;
	}

	public function commit() {
		$this->active = false;
		$this->snapshot = array();
		++$this->commits;

		return true;
	}

	public function rollback() {
		if ( $this->active ) {
			list( $this->wallets, $this->transactions, $this->usage_records, $this->resources, $this->payments, $this->orders, $this->notifications, $this->invoices, $this->settlements, $this->audits ) = $this->snapshot;
		}

		$this->active = false;
		++$this->rollbacks;

		return true;
	}

	public function in_transaction() {
		return $this->active;
	}

	public function ensure_wallet( $customer_id, $currency = 'IRR' ) {
		$customer_id = absint( $customer_id );
		$currency    = strtoupper( (string) $currency );
		$key         = 'IRR' === $currency ? $customer_id : $customer_id . ':' . $currency;

		if ( ! isset( $this->wallets[ $key ] ) ) {
			$this->wallets[ $key ] = array(
				'id'              => 'IRR' === $currency ? $customer_id : 1000000 + $customer_id,
				'customer_id'     => $customer_id,
				'currency'        => $currency,
				'balance_minor'   => 0,
				'threshold_minor' => 0,
				'low_balance_notified' => 0,
				'status'          => 'active',
				'updated_at'      => current_time( 'mysql', true ),
			);
		}

		return $this->wallets[ $key ];
	}

	public function get_wallet_by_customer_id( $customer_id, $currency = 'IRR' ) {
		return $this->ensure_wallet( $customer_id, $currency );
	}

	public function lock_wallet( $customer_id, $currency = 'IRR' ) {
		return $this->active ? $this->ensure_wallet( $customer_id, $currency ) : null;
	}

	public function update_locked_wallet_balance( $wallet_id, $before_minor, $after_minor ) {
		$key = null;
		foreach ( $this->wallets as $candidate_key => $wallet ) {
			if ( (int) $wallet['id'] === (int) $wallet_id ) { $key = $candidate_key; break; }
		}
		if ( $this->fail_wallet_update || null === $key || $this->wallets[ $key ]['balance_minor'] !== $before_minor ) {
			return false;
		}

		$this->wallets[ $key ]['balance_minor'] = $after_minor;
		if ( $after_minor > (int) $this->wallets[ $key ]['threshold_minor'] ) { $this->wallets[ $key ]['low_balance_notified'] = 0; }

		return true;
	}

	public function create_transaction( array $data ) {
		if ( $this->fail_ledger || null !== $this->get_transaction_by_idempotency_key( $data['idempotency_key'] ) ) {
			return false;
		}

		$data['id'] = count( $this->transactions ) + 1;
		$this->transactions[] = $data;

		return $data['id'];
	}

	public function get_transaction_by_idempotency_key( $idempotency_key ) {
		foreach ( $this->transactions as $transaction ) {
			if ( $transaction['idempotency_key'] === $idempotency_key ) {
				return $transaction;
			}
		}

		return null;
	}

	public function get_transactions_by_customer_id( $customer_id, $limit = 50, $currency = '', $offset = 0 ) {
		$rows = array_values( array_filter( $this->transactions, static function ( $row ) use ( $customer_id, $currency ) {
			return (int) $row['customer_id'] === (int) $customer_id && ( '' === (string) $currency || (string) $row['currency'] === (string) $currency );
		} ) );
		return array_slice( $rows, $offset, $limit );
	}

	public function get_ledger_balance_minor( $wallet_id ) {
		$total = 0;

		foreach ( $this->transactions as $row ) {
			if ( (int) $row['wallet_id'] === (int) $wallet_id ) {
				$total += 'credit' === $row['transaction_type'] ? (int) $row['amount_minor'] : -(int) $row['amount_minor'];
			}
		}

		return $total;
	}

	public function is_duplicate_error() {
		return false;
	}

	public function usage_log_exists( $billing_reference ) {
		foreach ( $this->usage_records as $row ) {
			if ( $row['billing_reference'] === $billing_reference ) {
				return true;
			}
		}

		return false;
	}

	public function create_usage_log( array $data ) {
		if ( $this->fail_usage || $this->usage_log_exists( $data['billing_reference'] ) ) {
			return false;
		}

		$data['id'] = count( $this->usage_records ) + 1;
		$this->usage_records[] = $data;

		return $data['id'];
	}

	public function update( $table, array $data, array $where, array $format = array(), array $where_format = array() ) {
		if ( $this->fail_cursor ) { return false; }
		$map = array( 'resources' => 'resources', 'orders' => 'orders', 'notifications' => 'notifications', 'settlements' => 'settlements' );
		if ( ! isset( $map[ $table ] ) ) { return false; }
		$property = $map[ $table ]; $id = (int) $where['id'];
		if ( ! isset( $this->{$property}[ $id ] ) ) { return 0; }
		foreach ( $where as $key => $value ) { if ( ! array_key_exists( $key, $this->{$property}[ $id ] ) || (string) $this->{$property}[ $id ][ $key ] !== (string) $value ) { return 0; } }
		$this->{$property}[ $id ] = array_merge( $this->{$property}[ $id ], $data ); return 1;
	}

	public function create_payment( array $data ) { $id = count( $this->payments ) + 1; $data['id'] = $id; $data['created_at'] = current_time( 'mysql', true ); $data['updated_at'] = current_time( 'mysql', true ); $data['completed_at'] = null; $this->payments[ $id ] = $data; return $id; }
	public function get_payment_by_idempotency_key( $key ) { foreach ( $this->payments as $row ) { if ( $row['idempotency_key'] === $key ) { return $row; } } return null; }
	public function get_payment_by_reference( $ref ) { foreach ( $this->payments as $row ) { if ( $row['payment_reference'] === $ref ) { return $row; } } return null; }
	public function lock_payment_by_reference( $ref ) { return $this->active ? $this->get_payment_by_reference( $ref ) : null; }
	public function transition_payment_status( $id, $from, $to, array $extra = array() ) { if ( ! isset( $this->payments[$id] ) || $this->payments[$id]['status'] !== $from ) { return false; } $this->payments[$id] = array_merge( $this->payments[$id], $extra, array( 'status' => $to ) ); return true; }
	public function get_payments_by_customer_id( $id, $limit = 50, $offset = 0 ) { return array_slice( array_values( array_filter( $this->payments, static function( $r ) use ( $id ) { return (int) $r['customer_id'] === (int) $id; } ) ), $offset, $limit ); }
	public function create_audit_log( $event_type, $object_type = '', $object_id = '', array $metadata = array(), $customer_id = 0, $actor_user_id = null ) { $this->audits[] = array( 'event_type' => $event_type, 'metadata' => Arvan_Reseller_Security::redact( $metadata ) ); return count( $this->audits ); }
	public function create_order( array $data ) { if ( null !== $this->get_order_by_idempotency_key( $data['idempotency_key'] ) ) { return false; } $id = count( $this->orders ) + 1; $data = wp_parse_args( $data, array( 'resource_id' => '', 'resource_record_id' => null, 'recovery_required' => 0, 'failure_code' => '', 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ) ); $data['id'] = $id; $this->orders[$id] = $data; return $id; }
	public function get_order_by_idempotency_key( $key ) { foreach ( $this->orders as $row ) { if ( $row['idempotency_key'] === $key ) { return $row; } } return null; }
	public function lock_order( $id ) { return $this->active && isset( $this->orders[$id] ) ? $this->orders[$id] : null; }
	public function transition_order_status( $id, $from, $to, array $extra = array() ) { if ( ! isset( $this->orders[$id] ) || $this->orders[$id]['status'] !== $from ) { return false; } $this->orders[$id] = array_merge( $this->orders[$id], $extra, array( 'status' => $to, 'updated_at' => current_time( 'mysql', true ) ) ); return true; }
	public function save_resource( array $data ) { if ( $this->fail_resource_save ) { return false; } $id = count( $this->resources ) + 1; $data = wp_parse_args( $data, array( 'id' => $id, 'currency' => 'IRR', 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ), 'last_billed_at' => null ) ); $data['id'] = $id; $this->resources[$id] = $data; return $id; }
	public function get_resource_by_arvan_id( $rid, $type = '', $region = '' ) { foreach ( $this->resources as $r ) { if ( $r['resource_id'] === $rid ) { return $r; } } return null; }
	public function get_resources_by_customer_id( $id, $limit = 100, $offset = 0 ) { return array_slice( array_values( array_filter( $this->resources, static function( $r ) use ( $id ) { return (int) $r['customer_id'] === (int) $id; } ) ), $offset, $limit ); }
	public function get_billable_resources( $after_id = 0, $limit = 50 ) { return array_values( array_slice( array_filter( $this->resources, static function( $r ) use ( $after_id ) { return (int) $r['id'] > (int) $after_id && in_array( (string) $r['status'], array( 'active', 'provisioned', 'suspended' ), true ); } ), 0, $limit ) ); }
	public function get_orders_requiring_recovery( $limit = 50 ) { return array_values( array_filter( $this->orders, static function( $r ) { return ! empty( $r['recovery_required'] ); } ) ); }
	public function get_row_by( $table, array $where ) { $map=array('orders'=>'orders','resources'=>'resources','notifications'=>'notifications'); if(isset($where['id'],$map[$table])){$property=$map[$table];$row=$this->{$property}[(int)$where['id']]??null;if(null===$row){return null;}foreach($where as $key=>$value){if(!array_key_exists($key,$row)||(string)$row[$key] !== (string)$value){return null;}}return $row;} if('invoices'===$table&&isset($where['invoice_reference'])){return $this->get_invoice_by_reference($where['invoice_reference']);} return null; }
	public function mark_wallet_low_balance_notified( $wallet_id ) { foreach ( $this->wallets as &$wallet ) { if ( (int) $wallet['id'] === (int) $wallet_id ) { $wallet['low_balance_notified'] = 1; return true; } } return false; }
	public function get_notification_by_event_key( $key ) { foreach ( $this->notifications as $r ) { if ( $r['event_key'] === $key ) { return $r; } } return null; }
	public function create_notification( array $data ) { $id=count($this->notifications)+1; $data=wp_parse_args($data,array('status'=>'pending','channel'=>'email','payload'=>'','error_code'=>'','read_at'=>null,'created_at'=>current_time('mysql',true),'sent_at'=>null)); $data['id']=$id; $this->notifications[$id]=$data; return $id; }
	public function aggregate_customer_usage_period( $start, $end, $currency = 'IRR' ) { unset($start,$end);$groups=array();foreach($this->usage_records as $r){if((string)($r['currency']??'IRR')!==$currency){continue;}$id=(int)($r['customer_id']??0);if(!isset($groups[$id])){$groups[$id]=array('customer_id'=>$id,'currency'=>$currency,'base_cost_minor'=>0,'reseller_share_minor'=>0,'total_minor'=>0,'charged_minor'=>0,'uncovered_minor'=>0,'usage_count'=>0);}foreach(array('base_cost_minor','reseller_share_minor','charged_minor','uncovered_minor') as $key){$groups[$id][$key]+=(int)($r[$key]??0);}$groups[$id]['total_minor']+=(int)($r['total_charge_minor']??0);++$groups[$id]['usage_count'];}return array_values($groups); }
	public function get_invoice_by_reference( $ref ) { foreach($this->invoices as $r){if($r['invoice_reference']===$ref){return $r;}}return null; }
	public function create_invoice( array $data ) { if(null!==$this->get_invoice_by_reference($data['invoice_reference'])){return false;}$id=count($this->invoices)+1;$data['id']=$id;$this->invoices[$id]=$data;return $id; }
	public function aggregate_usage_period( $start, $end, $currency = 'IRR' ) { $out=array('base_cost_minor'=>0,'customer_charge_minor'=>0,'reseller_share_minor'=>0,'usage_count'=>0); foreach($this->usage_records as $r){$out['base_cost_minor']+=(int)$r['base_cost_minor'];$out['customer_charge_minor']+=(int)$r['total_charge_minor'];$out['reseller_share_minor']+=(int)$r['reseller_share_minor'];++$out['usage_count'];} return $out; }
	public function get_settlement_by_reference( $ref ) { foreach($this->settlements as $r){if($r['settlement_reference']===$ref){return $r;}}return null; }
	public function create_settlement( array $data ) { $id=count($this->settlements)+1;$data['id']=$id;$this->settlements[$id]=$data;return $id; }
}

function arvan_test_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message . '\nExpected: ' . var_export( $expected, true ) . '\nActual: ' . var_export( $actual, true ) );
	}
}

function arvan_test_assert_true( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
