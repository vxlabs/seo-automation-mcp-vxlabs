<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Logger {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'mcp_request_log';
	}

	/**
	 * @param int|null    $key_id
	 * @param int|null    $user_id
	 * @param string      $method       JSON-RPC method, e.g. "tools/call".
	 * @param string|null $tool_name
	 * @param array       $args         Tool arguments; only the argument *names* are persisted.
	 * @param bool        $success
	 * @param string|null $error_message
	 */
	public static function log( $key_id, $user_id, $method, $tool_name, $args, $success, $error_message = null ) {
		global $wpdb;

		$args_summary = is_array( $args ) ? wp_json_encode( array_keys( $args ) ) : null;

		$wpdb->insert(
			self::table_name(),
			array(
				'key_id'        => $key_id,
				'user_id'       => $user_id,
				'method'        => $method,
				'tool_name'     => $tool_name,
				'args_summary'  => $args_summary,
				'success'       => $success ? 1 : 0,
				'error_message' => $error_message ? substr( $error_message, 0, 255 ) : null,
				'ip'            => self::get_client_ip(),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	public static function get_client_ip() {
		// REMOTE_ADDR is the connection's actual peer address; deliberately not trusting
		// X-Forwarded-For here since it's attacker-controllable unless a trusted proxy is configured.
		// Sites behind a trusted reverse proxy/CDN can hook 'mcp_connector_client_ip' to resolve
		// the real client IP (e.g. from a specific trusted X-Forwarded-For/CF-Connecting-IP header)
		// instead — rate limiting and audit logs are otherwise keyed to the proxy's own IP for everyone.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = substr( $ip, 0, 45 );

		return apply_filters( 'mcp_connector_client_ip', $ip );
	}
}
