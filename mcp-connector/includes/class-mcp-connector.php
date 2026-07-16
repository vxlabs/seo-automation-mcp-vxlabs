<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Connector {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_rewrite_rule' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 20 );
		add_action( 'rest_api_init', array( 'Mcp_Rest_Controller', 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'maybe_add_retry_after_header' ), 10, 3 );
	}

	/**
	 * Maps the clean /mcp URL onto the REST route. Rewrite rules are inert while
	 * permalinks are set to "Plain" — the ?rest_route= fallback still works regardless.
	 */
	public function register_rewrite_rule() {
		add_rewrite_rule( '^mcp/?$', 'index.php?rest_route=/mcp-connector/v1/mcp', 'top' );
	}

	public function maybe_flush_rewrites() {
		if ( get_option( 'mcp_connector_flush_rewrites' ) ) {
			flush_rewrite_rules();
			delete_option( 'mcp_connector_flush_rewrites' );
		}
	}

	/**
	 * By the time rest_post_dispatch fires, a permission_callback's WP_Error has
	 * already been converted into a WP_REST_Response — this adds the Retry-After
	 * header for our rate-limit error onto that response.
	 */
	public function maybe_add_retry_after_header( $response, $server, $request ) {
		if ( '/mcp-connector/v1/mcp' !== $request->get_route() ) {
			return $response;
		}

		if ( ! ( $response instanceof WP_REST_Response ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( is_array( $data ) && isset( $data['code'] ) && 'mcp_rate_limited' === $data['code'] ) {
			$response->header( 'Retry-After', (string) Mcp_Rate_Limiter::retry_after( Mcp_Logger::get_client_ip() ) );
		}

		return $response;
	}
}
