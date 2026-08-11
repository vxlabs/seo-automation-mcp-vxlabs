<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Rest_Controller {

	public static function register_routes() {
		register_rest_route(
			'mcp-connector/v1',
			'/mcp',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE, // POST
					'callback'            => array( 'Mcp_Jsonrpc_Server', 'handle' ),
					'permission_callback' => array( 'Mcp_Auth', 'check_request' ),
					'args'                => array(
						'api-key' => array(
							'required' => false,
							'type'     => 'string',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE, // GET
					'callback'            => array( __CLASS__, 'method_not_allowed' ),
					'permission_callback' => array( 'Mcp_Auth', 'check_origin' ),
				),
			)
		);
	}

	public static function method_not_allowed() {
		return new WP_Error(
			'mcp_method_not_allowed',
			'This endpoint only accepts POST requests with a JSON-RPC body.',
			array( 'status' => 405 )
		);
	}

	/**
	 * Clean /mcp path once permalinks are non-Plain, otherwise the ?rest_route=
	 * fallback that works regardless of permalink structure.
	 */
	public static function get_endpoint_url() {
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/mcp' );
		}
		return home_url( '/?rest_route=/mcp-connector/v1/mcp' );
	}
}
