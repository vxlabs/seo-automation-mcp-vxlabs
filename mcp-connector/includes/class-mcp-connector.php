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
		add_action( 'init', array( 'Mcp_Business_Context', 'register_post_type' ), 5 );
		add_action( 'init', array( 'Mcp_Seo_Service', 'register' ), 20 );
		add_action( 'init', array( $this, 'register_rewrite_rule' ) );
		add_action( 'init', array( $this, 'maybe_flag_rewrite_upgrade' ), 8 );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 20 );
		add_action( 'rest_api_init', array( 'Mcp_Rest_Controller', 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'maybe_add_retry_after_header' ), 10, 3 );

		// Mcp_Technical_Seo::register() only wires up add_action()/add_filter()
		// calls (admin_init, init, template_redirect, robots_txt) — safe to run now.
		Mcp_Technical_Seo::register();
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
	 * Rewrite rules (e.g. Mcp_Technical_Seo's llms.txt rule) added in an
	 * upgrade aren't present in an existing site's rewrite cache — activation
	 * alone can't flush for a site that's already active. Runs after rules are
	 * (re-)registered above but before maybe_flush_rewrites() consumes the flag.
	 */
	public function maybe_flag_rewrite_upgrade() {
		if ( get_option( 'mcp_connector_rewrite_version' ) !== MCP_CONNECTOR_VERSION ) {
			update_option( 'mcp_connector_flush_rewrites', 1 );
			update_option( 'mcp_connector_rewrite_version', MCP_CONNECTOR_VERSION );
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
