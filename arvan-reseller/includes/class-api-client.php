<?php
/**
 * Arvan Cloud API client.
 *
 * @package Arvan_Reseller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arvan_Reseller_API_Client {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	private $settings_option = 'arvan_reseller_settings';

	/**
	 * API key option name.
	 *
	 * @var string
	 */
	private $api_key_option = 'arvan_reseller_api_key';

	/**
	 * Perform an authenticated API request.
	 *
	 * @param string       $method HTTP method.
	 * @param string       $path Endpoint path or URL.
	 * @param array|string $body Optional request body.
	 * @param array        $query Optional query args.
	 * @param array        $headers Optional headers.
	 * @return array|WP_Error
	 */
	public function request( $method, $path, $body = array(), array $query = array(), array $headers = array() ) {
		$base_url = $this->get_base_url();
		$api_key  = $this->get_api_key();

		if ( '' === $base_url ) {
			return new WP_Error( 'arvan_reseller_missing_base_url', __( 'Arvan API base URL is not configured.', 'arvan-reseller' ) );
		}

		if ( '' === $api_key ) {
			return new WP_Error( 'arvan_reseller_missing_api_key', __( 'Arvan API key is not configured.', 'arvan-reseller' ) );
		}

		$url = $this->build_url( $base_url, $path, $query );

		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$default_headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Apikey ' . $api_key,
		);

		$args = array(
			'method'      => strtoupper( $method ),
			'timeout'     => $this->get_timeout(),
			'headers'     => array_merge( $default_headers, $headers ),
			'data_format' => 'body',
		);

		if ( ! empty( $body ) ) {
			$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->validate_response( $response );
	}

	/**
	 * Create a remote resource.
	 *
	 * @param array  $payload Resource payload.
	 * @param string $endpoint Optional endpoint override.
	 * @return array|WP_Error
	 */
	public function create_resource( array $payload, $endpoint = '' ) {
		$path = $endpoint ? $endpoint : $this->get_endpoint( 'resource_create' );

		return $this->request( 'POST', $path, $payload );
	}

	/**
	 * Fetch a remote resource.
	 *
	 * @param string $resource_id Resource ID.
	 * @param string $endpoint Optional endpoint override.
	 * @return array|WP_Error
	 */
	public function get_resource( $resource_id, $endpoint = '' ) {
		$path = $endpoint ? $endpoint : $this->replace_resource_id_placeholder( $this->get_endpoint( 'resource_get' ), $resource_id );

		return $this->request( 'GET', $path );
	}

	/**
	 * Fetch usage for a remote resource.
	 *
	 * @param string $resource_id Resource ID.
	 * @param array  $query Query arguments.
	 * @param string $endpoint Optional endpoint override.
	 * @return array|WP_Error
	 */
	public function get_usage( $resource_id, array $query = array(), $endpoint = '' ) {
		$path = $endpoint ? $endpoint : $this->replace_resource_id_placeholder( $this->get_endpoint( 'usage_get' ), $resource_id );

		return $this->request( 'GET', $path, array(), $query );
	}

	/**
	 * Suspend a remote resource.
	 *
	 * @param string $resource_id Resource ID.
	 * @param string $endpoint Optional endpoint override.
	 * @return array|WP_Error
	 */
	public function suspend_resource( $resource_id, $endpoint = '' ) {
		$path = $endpoint ? $endpoint : $this->replace_resource_id_placeholder( $this->get_endpoint( 'resource_suspend' ), $resource_id );

		return $this->request( 'POST', $path );
	}

	/**
	 * Terminate a remote resource.
	 *
	 * @param string $resource_id Resource ID.
	 * @param string $endpoint Optional endpoint override.
	 * @return array|WP_Error
	 */
	public function terminate_resource( $resource_id, $endpoint = '' ) {
		$path = $endpoint ? $endpoint : $this->replace_resource_id_placeholder( $this->get_endpoint( 'resource_terminate' ), $resource_id );

		return $this->request( 'DELETE', $path );
	}

	/**
	 * Build a URL from base path and optional query args.
	 *
	 * @param string $base_url Base URL.
	 * @param string $path Endpoint path or URL.
	 * @param array  $query Query arguments.
	 * @return string|WP_Error
	 */
	private function build_url( $base_url, $path, array $query ) {
		$path = (string) $path;

		if ( '' === $path ) {
			return new WP_Error( 'arvan_reseller_missing_endpoint', __( 'Arvan API endpoint is not configured.', 'arvan-reseller' ) );
		}

		$url = wp_http_validate_url( $path ) ? $path : trailingslashit( untrailingslashit( $base_url ) ) . ltrim( $path, '/' );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( array_map( 'rawurlencode', $query ), $url );
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'arvan_reseller_invalid_url', __( 'Arvan API URL is invalid.', 'arvan-reseller' ) );
		}

		return $url;
	}

	/**
	 * Validate an HTTP response.
	 *
	 * @param array $response HTTP response.
	 * @return array|WP_Error
	 */
	private function validate_response( array $response ) {
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = '' !== $body ? json_decode( $body, true ) : array();

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'arvan_reseller_api_error',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Arvan API request failed with status %d.', 'arvan-reseller' ),
					$status_code
				),
				array(
					'status_code' => $status_code,
					'response'    => $decoded,
					'body'        => $body,
				)
			);
		}

		if ( '' !== $body && null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'arvan_reseller_invalid_json', __( 'Arvan API returned invalid JSON.', 'arvan-reseller' ) );
		}

		return array(
			'status_code' => $status_code,
			'headers'     => wp_remote_retrieve_headers( $response ),
			'body'        => is_array( $decoded ) ? $decoded : array(),
			'raw_body'    => $body,
		);
	}

	/**
	 * Resolve the configured API base URL.
	 *
	 * @return string
	 */
	private function get_base_url() {
		$settings = get_option( $this->settings_option, array() );
		$base_url = isset( $settings['api_base_url'] ) ? esc_url_raw( (string) $settings['api_base_url'] ) : '';

		return (string) apply_filters( 'arvan_reseller_api_base_url', $base_url, $settings );
	}

	/**
	 * Resolve the API timeout.
	 *
	 * @return int
	 */
	private function get_timeout() {
		$settings = get_option( $this->settings_option, array() );
		$timeout  = isset( $settings['api_timeout'] ) ? absint( $settings['api_timeout'] ) : 20;

		return max( 5, min( 120, $timeout ) );
	}

	/**
	 * Fetch the decrypted API key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		return Arvan_Reseller_Security::get_decrypted_option( $this->api_key_option );
	}

	/**
	 * Get an API endpoint from settings or filters.
	 *
	 * @param string $key Endpoint key.
	 * @return string
	 */
	private function get_endpoint( $key ) {
		$settings  = get_option( $this->settings_option, array() );
		$endpoints = array(
			'resource_create'    => isset( $settings['resource_create_endpoint'] ) ? (string) $settings['resource_create_endpoint'] : '',
			'resource_get'       => isset( $settings['resource_get_endpoint'] ) ? (string) $settings['resource_get_endpoint'] : '',
			'usage_get'          => isset( $settings['usage_endpoint'] ) ? (string) $settings['usage_endpoint'] : '',
			'resource_suspend'   => isset( $settings['resource_suspend_endpoint'] ) ? (string) $settings['resource_suspend_endpoint'] : '',
			'resource_terminate' => isset( $settings['resource_terminate_endpoint'] ) ? (string) $settings['resource_terminate_endpoint'] : '',
		);

		return (string) apply_filters( 'arvan_reseller_api_endpoint', isset( $endpoints[ $key ] ) ? $endpoints[ $key ] : '', $key, $settings );
	}

	/**
	 * Replace resource identifier placeholders.
	 *
	 * @param string $path Endpoint path.
	 * @param string $resource_id Resource ID.
	 * @return string
	 */
	private function replace_resource_id_placeholder( $path, $resource_id ) {
		return str_replace( '{resource_id}', rawurlencode( (string) $resource_id ), (string) $path );
	}
}
