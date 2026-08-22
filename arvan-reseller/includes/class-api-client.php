<?php
/**
 * Mode-selecting Cloud Server API facade.
 *
 * Live Mode delegates only to documented IaaS v3 paths. Mock Mode delegates
 * to a deterministic in-process adapter and never calls WordPress HTTP APIs.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Arvan_Reseller_API_Client {

	/** @var Arvan_Reseller_Cloud_Adapter_Interface|null */
	private $adapter;

	/** @param Arvan_Reseller_Cloud_Adapter_Interface|null $adapter Test override. */
	public function __construct( $adapter = null ) {
		$this->adapter = $adapter instanceof Arvan_Reseller_Cloud_Adapter_Interface ? $adapter : null;
	}

	public function get_mode() {
		return $this->adapter()->get_mode(); }
	public function test_connection( $region = '' ) {
		return $this->adapter()->test_connection( $this->region( $region ) ); }
	public function list_regions( $region = '' ) {
		return $this->adapter()->list_regions( $this->region( $region ) ); }
	public function list_images( $region = '', array $query = array() ) {
		return $this->adapter()->list_images( $this->region( $region ), $query ); }
	public function list_flavors( $region = '', array $query = array() ) {
		return $this->adapter()->list_flavors( $this->region( $region ), $query ); }
	public function create_server( $region, array $payload, $idempotency_key ) {
		return $this->adapter()->create_server( $this->region( $region ), $payload, $idempotency_key ); }
	public function get_server( $region, $resource_id ) {
		return $this->adapter()->get_server( $this->region( $region ), $resource_id ); }
	public function power_off_server( $region, $resource_id, $availability_zone ) {
		return $this->adapter()->power_off_server( $this->region( $region ), $resource_id, $availability_zone ); }
	public function terminate_server( $region, $resource_id, $availability_zone ) {
		return $this->adapter()->terminate_server( $this->region( $region ), $resource_id, $availability_zone ); }
	public function get_resource_status( $region, $resource_id ) {
		$result = $this->get_server( $region, $resource_id );
		return is_wp_error( $result ) ? $result : (string) ( $result['body']['data']['status'] ?? 'unknown' ); }

	/** @return array|WP_Error */
	public function get_usage_for_resource( array $resource, array $window ) {
		$result = $this->adapter()->get_usage( (string) ( $resource['region'] ?? $this->region( '' ) ), (string) $resource['resource_id'], $window, $resource );
		if ( ! is_wp_error( $result ) || 'arvan_reseller_usage_api_unavailable' !== $result->get_error_code() ) {
			return $result; }

		$start = strtotime( (string) ( $window['start'] ?? '' ) . ' UTC' );
		$end   = strtotime( (string) ( $window['end'] ?? '' ) . ' UTC' );
		$price = (int) ( $resource['hourly_price_minor'] ?? 0 );
		if ( false === $start || false === $end || $start >= $end || $price <= 0 ) {
			return $result; }

		$quantity = intdiv( ( $end - $start ) * Arvan_Reseller_Money::scale() + 1800, 3600 );
		return array(
			'status_code' => 200,
			'body'        => array(
				'data' => array(
					'start'        => gmdate( 'Y-m-d H:i:s', $start ),
					'end'          => gmdate( 'Y-m-d H:i:s', $end ),
					'usage_amount' => Arvan_Reseller_Money::format( $quantity ),
					'unit_price'   => Arvan_Reseller_Money::format( $price ),
					'unit'         => 'hour',
					'source'       => 'documented_flavor_price_fallback',
				),
			),
		);
	}

	/* Compatibility wrappers intentionally reject endpoint overrides. */
	public function create_resource( array $payload, $endpoint = '' ) {
		if ( '' !== $endpoint ) {
			return $this->endpoint_override_error();
		} return $this->create_server( $this->region( '' ), $payload, hash( 'sha256', wp_json_encode( $payload ) ) ); }
	public function get_resource( $resource_id, $endpoint = '' ) {
		return '' !== $endpoint ? $this->endpoint_override_error() : $this->get_server( $this->region( '' ), $resource_id ); }
	public function suspend_resource( $resource_id, $endpoint = '' ) {
		$settings = $this->settings();
		return '' !== $endpoint ? $this->endpoint_override_error() : $this->power_off_server( $this->region( '' ), $resource_id, (string) ( $settings['availability_zone'] ?? '' ) ); }
	public function terminate_resource( $resource_id, $endpoint = '' ) {
		$settings = $this->settings();
		return '' !== $endpoint ? $this->endpoint_override_error() : $this->terminate_server( $this->region( '' ), $resource_id, (string) ( $settings['availability_zone'] ?? '' ) ); }
	public function get_usage( $resource_id, array $query = array(), $endpoint = '' ) {
		if ( '' !== $endpoint ) {
			return $this->endpoint_override_error();
		} return $this->adapter()->get_usage( $this->region( '' ), $resource_id, $query ); }

	/** @return Arvan_Reseller_Cloud_Adapter_Interface */
	private function adapter() {
		if ( null === $this->adapter ) {
			$this->adapter = 'live' === (string) ( $this->settings()['mode'] ?? 'mock' ) ? new Arvan_Reseller_Live_Cloud_Adapter() : new Arvan_Reseller_Mock_Cloud_Adapter(); }
		return $this->adapter;
	}

	private function settings() {
		$value = get_option( 'arvan_reseller_settings', array() );
		return is_array( $value ) ? $value : array(); }
	private function region( $region ) {
		if ( '' !== (string) $region ) {
			return sanitize_key( $region );
		} $settings = $this->settings();
		return sanitize_key( (string) ( $settings['region'] ?? 'ir-thr-mock' ) ); }
	private function endpoint_override_error() {
		return new WP_Error( 'arvan_reseller_endpoint_override_blocked', __( 'Endpoint overrides are not allowed.', 'arvan-reseller' ) ); }
}
