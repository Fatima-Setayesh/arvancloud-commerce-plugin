<?php
/** Deterministic no-network Cloud Server adapter. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Arvan_Reseller_Mock_Cloud_Adapter implements Arvan_Reseller_Cloud_Adapter_Interface {
	private $option = 'arvan_reseller_mock_resources';
	public function get_mode() {
		return 'mock'; }
	public function test_connection( $region ) {
		return array(
			'status_code' => 200,
			'body'        => array(
				'message' => 'Mock Mode ready',
				'data'    => array(
					'mode'   => 'mock',
					'region' => $this->region( $region ),
				),
			),
		); }
	public function list_regions( $region ) {
		return array(
			'status_code' => 200,
			'body'        => array(
				'data' => array(
					array(
						'name'   => 'ir-thr-mock',
						'status' => 'AVAILABLE',
					),
				),
			),
		); }
	public function list_images( $region, array $query = array() ) {
		return array(
			'status_code' => 200,
			'body'        => array(
				'data' => array(
					array(
						'id'   => '00000000-0000-4000-8000-000000000101',
						'name' => 'Ubuntu 24.04 LTS',
						'os'   => 'linux',
					),
				),
			),
		); }
	public function list_flavors( $region, array $query = array() ) {
		return array(
			'status_code' => 200,
			'body'        => array(
				'data' => array(
					array(
						'id'              => 'mock-g2-1-2',
						'name'            => 'Mock G2',
						'cpuCount'        => 1,
						'memoryMegaBytes' => 2048,
						'diskGigaBytes'   => 25,
						'pricePerHour'    => '1000.0000',
					),
				),
			),
		); }

	public function create_server( $region, array $payload, $idempotency_key ) {
		$required = array( 'availabilityZone', 'flavorId', 'imageId', 'name', 'rootVolumeSizeGigaBytes' );
		foreach ( $required as $field ) {
			if ( empty( $payload[ $field ] ) ) {
				return new WP_Error( 'arvan_reseller_invalid_server_payload', __( 'Required server configuration is missing.', 'arvan-reseller' ) ); }
		}
		$id        = 'mock-' . substr( hash( 'sha256', (string) $idempotency_key ), 0, 24 );
		$resources = get_option( $this->option, array() );
		if ( ! isset( $resources[ $id ] ) ) {
			$resources[ $id ] = array(
				'id'               => $id,
				'name'             => sanitize_text_field( $payload['name'] ),
				'status'           => 'ACTIVE',
				'availabilityZone' => sanitize_text_field( $payload['availabilityZone'] ),
				'flavor'           => array(
					'id'              => sanitize_text_field( $payload['flavorId'] ),
					'name'            => 'Mock G2',
					'cpuCount'        => 1,
					'memoryMegaBytes' => 2048,
					'diskGigaBytes'   => 25,
					'pricePerHour'    => '1000.0000',
				),
				'image'                    => array( 'id' => sanitize_text_field( $payload['imageId'] ), 'name' => 'Ubuntu 24.04 LTS', 'os' => 'linux' ),
				'rootVolumeSizeGigaBytes' => absint( $payload['rootVolumeSizeGigaBytes'] ),
				'createDate'               => current_time( 'mysql', true ),
			);
			update_option( $this->option, $resources, false );
		}
		return array(
			'status_code' => 201,
			'body'        => array(
				'message' => 'Mock server created',
				'data'    => $resources[ $id ],
			),
		);
	}

	public function get_server( $region, $resource_id ) {
		$resources = get_option( $this->option, array() );
		return isset( $resources[ $resource_id ] ) ? array(
			'status_code' => 200,
			'body'        => array( 'data' => $resources[ $resource_id ] ),
		) : new WP_Error( 'arvan_reseller_api_not_found', __( 'Mock server was not found.', 'arvan-reseller' ), array( 'status_code' => 404 ) );
	}

	public function power_off_server( $region, $resource_id, $availability_zone ) {
		return $this->change_status( $resource_id, 'SHUTOFF', 202 ); }
	public function terminate_server( $region, $resource_id, $availability_zone ) {
		return $this->change_status( $resource_id, 'TERMINATED', 202 ); }

	public function get_usage( $region, $resource_id, array $window, array $resource = array() ) {
		$start = strtotime( (string) ( $window['start'] ?? '' ) . ' UTC' );
		$end   = strtotime( (string) ( $window['end'] ?? '' ) . ' UTC' );
		if ( false === $start || false === $end || $start >= $end ) {
			return new WP_Error( 'arvan_reseller_invalid_usage_window', __( 'Usage window is invalid.', 'arvan-reseller' ) ); }
		$quantity_scaled  = intdiv( ( $end - $start ) * Arvan_Reseller_Money::scale() + 1800, 3600 );
		$unit_price_minor = isset( $resource['hourly_price_minor'] ) && (int) $resource['hourly_price_minor'] > 0 ? (int) $resource['hourly_price_minor'] : 1000 * Arvan_Reseller_Money::scale();
		return array(
			'status_code' => 200,
			'body'        => array(
				'data' => array(
					'start'        => gmdate( 'Y-m-d H:i:s', $start ),
					'end'          => gmdate( 'Y-m-d H:i:s', $end ),
					'usage_amount' => Arvan_Reseller_Money::format( $quantity_scaled ),
					'unit_price'   => Arvan_Reseller_Money::format( $unit_price_minor ),
					'unit'         => 'hour',
					'source'       => 'deterministic_mock',
				),
			),
		);
	}

	private function change_status( $id, $status, $code ) {
		$r = get_option( $this->option, array() );
		if ( ! isset( $r[ $id ] ) ) {
			return new WP_Error( 'arvan_reseller_api_not_found', __( 'Mock server was not found.', 'arvan-reseller' ) );
		} $r[ $id ]['status'] = $status;
		update_option( $this->option, $r, false );
		return array(
			'status_code' => $code,
			'body'        => array( 'data' => $r[ $id ] ),
		); }
	private function region( $region ) {
		return preg_match( '/^[a-z0-9-]+$/', (string) $region ) ? (string) $region : 'ir-thr-mock'; }
}
