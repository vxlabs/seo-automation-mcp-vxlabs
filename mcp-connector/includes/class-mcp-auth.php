<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Auth {

	// WordPress core's rest_send_allow_header() re-invokes permission_callback a
	// second time per request (to compute the Allow: header), so this memoizes the
	// result to keep rate-limit counting and last-used tracking accurate per request.
	private static $result_cached = false;
	private static $cached_result = null;

	/**
	 * REST permission_callback for the /mcp route. On success, sets the resolved
	 * WP user as the request-scoped current user (no cookie/session persisted) so
	 * every tool handler's current_user_can() checks apply to the key's bound account.
	 *
	 * @return true|WP_Error
	 */
	public static function check_request( WP_REST_Request $request ) {
		if ( self::$result_cached ) {
			return self::$cached_result;
		}

		self::$result_cached = true;
		self::$cached_result = self::do_check_request( $request );

		return self::$cached_result;
	}

	private static function do_check_request( WP_REST_Request $request ) {
		$ip = Mcp_Logger::get_client_ip();

		if ( Mcp_Rate_Limiter::is_limited( $ip ) ) {
			$error = new WP_Error(
				'mcp_rate_limited',
				'Too many invalid API key attempts. Try again later.',
				array( 'status' => 429 )
			);
			return $error;
		}

		$presented_key = self::extract_key( $request );

		if ( empty( $presented_key ) ) {
			Mcp_Rate_Limiter::record_failure( $ip );
			return self::invalid_key_error();
		}

		$row = Api_Key_Manager::verify( $presented_key );

		if ( ! $row ) {
			Mcp_Rate_Limiter::record_failure( $ip );
			return self::invalid_key_error();
		}

		$user = get_userdata( $row->user_id );

		if ( ! $user ) {
			Mcp_Rate_Limiter::record_failure( $ip );
			return self::invalid_key_error();
		}

		Mcp_Rate_Limiter::reset( $ip );
		Api_Key_Manager::touch_last_used( $row->id, $ip );

		wp_set_current_user( $user->ID );

		$request->set_param( '_mcp_key_row', $row );

		return true;
	}

	private static function extract_key( WP_REST_Request $request ) {
		$from_query = $request->get_param( 'api-key' );
		if ( ! empty( $from_query ) ) {
			return $from_query;
		}

		$auth_header = $request->get_header( 'authorization' );
		if ( $auth_header && stripos( $auth_header, 'Bearer ' ) === 0 ) {
			return trim( substr( $auth_header, 7 ) );
		}

		return null;
	}

	private static function invalid_key_error() {
		// Deliberately generic — does not distinguish "missing", "unknown", or
		// "revoked" so a caller can't enumerate valid key prefixes by response shape.
		return new WP_Error( 'mcp_invalid_key', 'Invalid API key.', array( 'status' => 401 ) );
	}
}
