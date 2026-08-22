<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Jsonrpc_Server {

	const PROTOCOL_VERSION = '2025-06-18';
	const LEGACY_PROTOCOL_VERSION = '2025-03-26';

	public static function handle( WP_REST_Request $request ) {
		$accept = $request->get_header( 'accept' );
		if ( $accept && false === stripos( $accept, 'application/json' ) && false === stripos( $accept, '*/*' ) ) {
			return self::http_error_response( 'This server returns application/json responses.', 406 );
		}

		$body = json_decode( $request->get_body(), true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $body ) ) {
			return self::error_response( null, -32700, 'Parse error' );
		}

		if ( self::is_list( $body ) ) {
			// The current MCP spec revision (2025-06-18) dropped JSON-RPC batching.
			return self::error_response( null, -32600, 'Batch requests are not supported' );
		}

		$is_notification = ! array_key_exists( 'id', $body );
		$id              = $is_notification ? null : $body['id'];

		if ( ! isset( $body['jsonrpc'] ) || '2.0' !== $body['jsonrpc'] || empty( $body['method'] ) || ! is_string( $body['method'] ) ) {
			return $is_notification ? self::empty_response() : self::error_response( $id, -32600, 'Invalid Request' );
		}

		$method  = $body['method'];
		$params  = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();
		$key_row = $request->get_param( '_mcp_key_row' );
		$protocol_header = $request->get_header( 'mcp-protocol-version' );

		if ( 'initialize' !== $method && $protocol_header && ! in_array( $protocol_header, array( self::PROTOCOL_VERSION, self::LEGACY_PROTOCOL_VERSION ), true ) ) {
			return self::http_error_response( 'Unsupported MCP-Protocol-Version header.', 400 );
		}

		try {
			switch ( $method ) {
				case 'initialize':
					$result = self::handle_initialize( $params );
					break;

				case 'notifications/initialized':
					return self::empty_response();

				case 'tools/list':
					$result = array( 'tools' => Mcp_Tool_Registry::get_definitions() );
					break;

				case 'tools/call':
					$tool_result = self::handle_tools_call( $params, $key_row );
					if ( is_wp_error( $tool_result ) ) {
						return self::error_response( $id, -32602, $tool_result->get_error_message() );
					}
					$result = $tool_result;
					break;

				default:
					return $is_notification ? self::empty_response() : self::error_response( $id, -32601, 'Method not found' );
			}
		} catch ( Throwable $e ) {
			error_log( '[mcp-connector] jsonrpc error: ' . $e->getMessage() );
			return $is_notification ? self::empty_response() : self::error_response( $id, -32603, 'Internal error' );
		}

		if ( $is_notification ) {
			return self::empty_response();
		}

		return self::success_response( $id, $result );
	}

	private static function handle_initialize( $params ) {
		$requested = isset( $params['protocolVersion'] ) && is_string( $params['protocolVersion'] ) ? $params['protocolVersion'] : '';
		$negotiated = in_array( $requested, array( self::PROTOCOL_VERSION, self::LEGACY_PROTOCOL_VERSION ), true ) ? $requested : self::PROTOCOL_VERSION;

		return array(
			'protocolVersion' => $negotiated,
			'serverInfo'      => array(
				'name'    => 'Automator Agent',
				'version' => MCP_CONNECTOR_VERSION,
			),
			'capabilities'    => array(
				'tools' => array( 'listChanged' => false ),
			),
		);
	}

	/**
	 * @return array|WP_Error Tool result envelope on success (including tool-level
	 *                         isError:true failures), WP_Error only for protocol-level
	 *                         problems (missing/unknown tool name).
	 */
	private static function handle_tools_call( $params, $key_row ) {
		$name      = isset( $params['name'] ) && is_string( $params['name'] ) ? $params['name'] : null;
		$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		if ( ! $name ) {
			return new WP_Error( 'mcp_invalid_params', 'params.name is required' );
		}

		if ( ! Mcp_Tool_Registry::has_tool( $name ) ) {
			return new WP_Error( 'mcp_unknown_tool', "Unknown tool: {$name}" );
		}

		$key_id  = $key_row ? (int) $key_row->id : null;
		$user_id = get_current_user_id();

		$tool_result = Mcp_Tool_Registry::call( $name, $arguments );

		if ( is_wp_error( $tool_result ) ) {
			Mcp_Logger::log( $key_id, $user_id, 'tools/call', $name, $arguments, false, $tool_result->get_error_message() );

			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Error: ' . $tool_result->get_error_message(),
					),
				),
				'isError' => true,
			);
		}

		Mcp_Logger::log( $key_id, $user_id, 'tools/call', $name, $arguments, true );

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => wp_json_encode( $tool_result ),
				),
			),
			'structuredContent' => is_array( $tool_result ) ? $tool_result : array( 'value' => $tool_result ),
			'isError' => false,
		);
	}

	private static function is_list( array $arr ) {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	private static function success_response( $id, $result ) {
		return self::no_store(
			new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'result'  => $result,
				),
				200
			)
		);
	}

	private static function error_response( $id, $code, $message ) {
		return self::no_store(
			new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'error'   => array(
						'code'    => $code,
						'message' => $message,
					),
				),
				200
			)
		);
	}

	private static function http_error_response( $message, $status ) {
		return self::no_store(
			new WP_REST_Response(
				array( 'code' => 'mcp_bad_protocol_version', 'message' => $message ),
				$status
			)
		);
	}

	private static function empty_response() {
		return self::no_store( new WP_REST_Response( null, 202 ) );
	}

	private static function no_store( WP_REST_Response $response ) {
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
