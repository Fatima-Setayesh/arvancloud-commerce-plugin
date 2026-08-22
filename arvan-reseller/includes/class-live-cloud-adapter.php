<?php
/** Official ArvanCloud IaaS v3 adapter. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Arvan_Reseller_Live_Cloud_Adapter implements Arvan_Reseller_Cloud_Adapter_Interface {

	const OFFICIAL_HOST_SUFFIX = '.arvanapis.ir';

	public function get_mode() {
		return 'live'; }

	public function test_connection( $region ) {
		return $this->request( 'GET', $region, '/availability-zones' ); }

	public function list_regions( $region ) {
		return $this->request( 'GET', $region, '/availability-zones' ); }

	public function list_images( $region, array $query = array() ) {
		return $this->request( 'GET', $region, '/images', array(), $this->pagination_query( $query ) ); }

	public function list_flavors( $region, array $query = array() ) {
		return $this->request( 'GET', $region, '/flavors', array(), $this->pagination_query( $query ) ); }

	public function create_server( $region, array $payload, $idempotency_key ) {
		$payload = $this->validate_create_payload( $payload );
		/* The official v3 specification documents no idempotency header. */
		return is_wp_error( $payload ) ? $payload : $this->request( 'POST', $region, '/servers', $payload );
	}

	public function get_server( $region, $resource_id ) {
		$id = $this->validate_resource_id( $resource_id );
		return is_wp_error( $id ) ? $id : $this->request( 'GET', $region, '/servers/' . rawurlencode( $id ) );
	}

	public function power_off_server( $region, $resource_id, $availability_zone ) {
		$id   = $this->validate_resource_id( $resource_id );
		$zone = $this->validate_identifier( $availability_zone, 'availability_zone' );
		return is_wp_error( $id ) || is_wp_error( $zone ) ? new WP_Error( 'arvan_reseller_invalid_server_operation', __( 'Server operation parameters are invalid.', 'arvan-reseller' ) ) : $this->request( 'POST', $region, '/servers/' . rawurlencode( $id ) . '/power-off', array( 'availabilityZone' => $zone ) );
	}

	public function terminate_server( $region, $resource_id, $availability_zone ) {
		$id   = $this->validate_resource_id( $resource_id );
		$zone = $this->validate_identifier( $availability_zone, 'availability_zone' );
		return is_wp_error( $id ) || is_wp_error( $zone ) ? new WP_Error( 'arvan_reseller_invalid_server_operation', __( 'Server operation parameters are invalid.', 'arvan-reseller' ) ) : $this->request( 'POST', $region, '/servers/' . rawurlencode( $id ) . '/terminate', array( 'availabilityZone' => $zone ) );
	}

	public function get_usage( $region, $resource_id, array $window, array $resource = array() ) {
		return new WP_Error( 'arvan_reseller_usage_api_unavailable', __( 'The published IaaS v3 specification has no server usage endpoint; local hourly pricing must be used.', 'arvan-reseller' ), array( 'type' => 'unsupported' ) );
	}

	/** @return array|WP_Error */
	private function request( $method, $region, $path, array $body = array(), array $query = array(), array $headers = array() ) {
		$region     = $this->validate_region( $region );
		$stored_key = get_option( 'arvan_reseller_api_key', '' );
		$key        = Arvan_Reseller_Security::get_decrypted_option( 'arvan_reseller_api_key' );

		if ( is_wp_error( $region ) ) {
			return $region; }
		if ( ( is_string( $stored_key ) && '' !== $stored_key && '' === $key ) || ( ! is_string( $stored_key ) && ! empty( $stored_key ) ) ) {
			return new WP_Error( 'arvan_reseller_api_key_unavailable', __( 'Stored Machine User credentials could not be opened safely.', 'arvan-reseller' ) ); }
		if ( '' === $key ) {
			return new WP_Error( 'arvan_reseller_missing_api_key', __( 'Machine User API key is not configured.', 'arvan-reseller' ) ); }
		if ( ! preg_match( '#^/[a-z0-9/\-]+$#', $path ) ) {
			return new WP_Error( 'arvan_reseller_invalid_api_path', __( 'API path is invalid.', 'arvan-reseller' ) ); }

		$host = 'ecc.' . $region . self::OFFICIAL_HOST_SUFFIX;
		$url  = 'https://' . $host . '/v3' . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url ); }

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || ( $parts['host'] ?? '' ) !== $host || ! str_ends_with( $host, self::OFFICIAL_HOST_SUFFIX ) ) {
			return new WP_Error( 'arvan_reseller_ssrf_blocked', __( 'API destination was blocked.', 'arvan-reseller' ) );
		}

		$args = array(
			'method'              => $method,
			'timeout'             => $this->timeout(),
			'redirection'         => 0,
			'reject_unsafe_urls'  => true,
			'limit_response_size' => 1048576,
			'headers'             => array_merge(
				array(
					'Accept'        => 'application/json',
					'Authorization' => 'apikey ' . $key,
				),
				$headers
			),
		);
		if ( ! empty( $body ) ) {
			$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
			$args['body']                    = wp_json_encode( $body ); }

		$attempts = 'GET' === $method ? 3 : 1;
		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			$response = wp_safe_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				if ( $attempt < $attempts ) {
					usleep( 100000 * $attempt );
					continue; }
				return new WP_Error( 'arvan_reseller_api_transport_error', __( 'ArvanCloud request could not be completed.', 'arvan-reseller' ), array( 'retryable' => true ) );
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( ( 429 === $status || $status >= 500 ) && $attempt < $attempts ) {
				usleep( 100000 * $attempt );
				continue; }
			return $this->normalize_response( $response, $status, $path );
		}

		return new WP_Error( 'arvan_reseller_api_transport_error', __( 'ArvanCloud request could not be completed.', 'arvan-reseller' ) );
	}

	/** @return array|WP_Error */
	private function normalize_response( array $response, $status, $path ) {
		$raw  = wp_remote_retrieve_body( $response );
		$body = '' === $raw ? array() : json_decode( $raw, true );
		if ( '' !== $raw && ! is_array( $body ) ) {
			return new WP_Error( 'arvan_reseller_invalid_api_response', __( 'ArvanCloud returned an invalid response.', 'arvan-reseller' ) ); }
		if ( $status >= 200 && $status < 300 ) {
			$requires_collection = in_array( $path, array( '/availability-zones', '/images', '/flavors' ), true );
			$requires_server     = '/servers' === $path || 1 === preg_match( '#^/servers/[A-Fa-f0-9-]+$#', $path );
			if ( ( $requires_collection && ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) ) || ( $requires_server && ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) || empty( $body['data']['id'] ) ) ) ) {
				return new WP_Error( 'arvan_reseller_invalid_api_response', __( 'ArvanCloud returned an unexpected response schema.', 'arvan-reseller' ) );
			}
			return array(
				'status_code' => $status,
				'body'        => $body,
			); }

		$map  = array(
			401 => 'unauthorized',
			403 => 'forbidden',
			404 => 'not_found',
			409 => 'conflict',
			415 => 'conflict',
			422 => 'validation',
			429 => 'rate_limited',
		);
		$kind = $map[ $status ] ?? ( $status >= 500 ? 'server_error' : 'request_failed' );
		return new WP_Error(
			'arvan_reseller_api_' . $kind,
			__( 'ArvanCloud rejected the request.', 'arvan-reseller' ),
			array(
				'status_code' => $status,
				'retryable'   => 429 === $status || $status >= 500,
			)
		);
	}

	/** @return array|WP_Error */
	private function validate_create_payload( array $payload ) {
		$required = array( 'availabilityZone', 'flavorId', 'imageId', 'name', 'rootVolumeSizeGigaBytes' );
		foreach ( $required as $field ) {
			if ( ! isset( $payload[ $field ] ) || '' === (string) $payload[ $field ] ) {
				return new WP_Error( 'arvan_reseller_invalid_server_payload', __( 'Required server configuration is missing.', 'arvan-reseller' ) ); }
		}

		$output = array(
			'availabilityZone'        => $this->validate_identifier( $payload['availabilityZone'], 'availability_zone' ),
			'flavorId'                => $this->validate_identifier( $payload['flavorId'], 'flavor' ),
			'imageId'                 => $this->validate_identifier( $payload['imageId'], 'image' ),
			'name'                    => sanitize_text_field( (string) $payload['name'] ),
			'rootVolumeSizeGigaBytes' => absint( $payload['rootVolumeSizeGigaBytes'] ),
		);
		foreach ( $output as $value ) {
			if ( is_wp_error( $value ) ) {
				return $value; }
		}
		if ( '' === $output['name'] || strlen( $output['name'] ) > 100 || $output['rootVolumeSizeGigaBytes'] < 1 || $output['rootVolumeSizeGigaBytes'] > 10000 ) {
			return new WP_Error( 'arvan_reseller_invalid_server_payload', __( 'Server configuration is invalid.', 'arvan-reseller' ) ); }
		foreach ( array( 'enableBackup', 'enableFailOver', 'enableIpv4', 'enableIpv6' ) as $boolean ) {
			if ( isset( $payload[ $boolean ] ) ) {
				$output[ $boolean ] = (bool) $payload[ $boolean ]; }
		}
		if ( ! empty( $payload['sshKeyName'] ) ) {
			$output['sshKeyName'] = sanitize_text_field( (string) $payload['sshKeyName'] ); }
		return $output;
	}

	/** @return string|WP_Error */
	private function validate_region( $region ) {
		$region = strtolower( (string) $region );
		return preg_match( '/^[a-z0-9][a-z0-9-]{1,39}$/', $region ) && false === strpos( $region, '--' ) ? $region : new WP_Error( 'arvan_reseller_invalid_region', __( 'Cloud region is invalid.', 'arvan-reseller' ) );
	}

	/** @return string|WP_Error */
	private function validate_resource_id( $id ) {
		$id = (string) $id;
		return preg_match( '/^[A-Fa-f0-9-]{16,64}$/', $id ) ? $id : new WP_Error( 'arvan_reseller_invalid_resource_id', __( 'Cloud Server ID is invalid.', 'arvan-reseller' ) );
	}

	/** @return string|WP_Error */
	private function validate_identifier( $value, $type ) {
		$value = (string) $value;
		return preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/', $value ) ? $value : new WP_Error( 'arvan_reseller_invalid_' . $type, __( 'Cloud identifier is invalid.', 'arvan-reseller' ) );
	}

	private function pagination_query( array $query ) {
		return array(
			'page'    => max( 1, absint( $query['page'] ?? 1 ) ),
			'perPage' => min( 100, max( 1, absint( $query['perPage'] ?? 50 ) ) ),
		); }
	private function timeout() {
		$s = get_option( 'arvan_reseller_settings', array() );
		return min( 30, max( 5, absint( $s['api_timeout'] ?? 15 ) ) ); }
}
