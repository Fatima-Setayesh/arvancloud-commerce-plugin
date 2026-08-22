<?php
/** Cloud Server adapter contract. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

interface Arvan_Reseller_Cloud_Adapter_Interface {
	public function test_connection( $region );
	public function list_regions( $region );
	public function list_images( $region, array $query = array() );
	public function list_flavors( $region, array $query = array() );
	public function create_server( $region, array $payload, $idempotency_key );
	public function get_server( $region, $resource_id );
	public function power_off_server( $region, $resource_id, $availability_zone );
	public function terminate_server( $region, $resource_id, $availability_zone );
	public function get_usage( $region, $resource_id, array $window, array $resource = array() );
	public function get_mode();
}
