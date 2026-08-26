<?php
require_once __DIR__ . '/bootstrap.php';

class Arvan_Test_Activation_Wpdb {
	public $prefix = 'wp_'; public $tables = array(); public $indexes = array(); public $insert_id = 0; public $last_error = ''; public $options = 'wp_options';
	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
	public function esc_like( $v ) { return $v; }
	public function prepare( $query, ...$args ) { if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; } foreach ( $args as $arg ) { $replacement = is_int( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'"; $query = preg_replace( '/%[sd]/', $replacement, $query, 1 ); } return $query; }
	public function get_var( $query ) { if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $query, $m ) ) { return isset( $this->tables[$m[1]] ) ? $m[1] : null; } if ( preg_match( "/SHOW COLUMNS FROM ([A-Za-z0-9_]+) LIKE '([^']+)'/", $query, $m ) ) { return isset( $this->tables[$m[1]][$m[2]] ) ? $m[2] : null; } if ( preg_match( "/SHOW INDEX FROM ([A-Za-z0-9_]+) WHERE Key_name = '([^']+)'/", $query, $m ) ) { return isset( $this->indexes[$m[1]][$m[2]] ) ? $m[1] : null; } return null; }
	public function get_row( $query ) { if ( preg_match( "/SHOW INDEX FROM ([A-Za-z0-9_]+) WHERE Key_name = '([^']+)'/", $query, $m ) && isset( $this->indexes[$m[1]][$m[2]] ) ) { return array( 'Non_unique' => $this->indexes[$m[1]][$m[2]] ? 0 : 1 ); } return null; }
	public function query() { return 0; }
	public function record_schema_query( $query ) { if ( ! preg_match( '/CREATE TABLE ([A-Za-z0-9_]+) \(/', $query, $tm ) ) { return; } $table=$tm[1];$this->tables[$table]=array();$this->indexes[$table]=array(); foreach(preg_split('/\R/',$query) as $line){$line=trim($line);if(preg_match('/^([a-z_][a-z0-9_]*)\s+[a-z]/i',$line,$cm)&&!in_array(strtoupper($cm[1]),array('PRIMARY','UNIQUE','KEY'),true)){$this->tables[$table][$cm[1]]=true;}if(preg_match('/^UNIQUE KEY ([a-z_][a-z0-9_]*)/i',$line,$im)){$this->indexes[$table][$im[1]]=true;}elseif(preg_match('/^KEY ([a-z_][a-z0-9_]*)/i',$line,$im)){$this->indexes[$table][$im[1]]=false;}} }
}

$GLOBALS['wpdb'] = new Arvan_Test_Activation_Wpdb(); $GLOBALS['arvan_test_options'] = array(); $GLOBALS['arvan_test_schedules'] = array();
require_once dirname( __DIR__ ) . '/arvan-reseller/includes/class-activator.php';
Arvan_Reseller_Activator::activate();
arvan_test_assert_same( '1.4.0', get_option( 'arvan_reseller_db_version' ), 'clean activation migration version mismatch' );
arvan_test_assert_same( 10, count( $GLOBALS['wpdb']->tables ), 'clean activation did not install ten domain tables' );
foreach ( array( 'arvan_reseller_usage_sync', 'arvan_reseller_reconciliation', 'arvan_reseller_settlement' ) as $hook ) { arvan_test_assert_true( isset( $GLOBALS['arvan_test_schedules'][$hook] ), 'activation schedule missing: ' . $hook ); }
arvan_test_assert_same( 'mock', get_option( 'arvan_reseller_settings' )['mode'], 'safe default mode is not Mock' );
echo "PASS: clean WordPress activation and versioned migration harness\n";
