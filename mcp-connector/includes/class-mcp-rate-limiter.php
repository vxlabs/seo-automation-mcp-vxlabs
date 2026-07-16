<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Rate_Limiter {

	const MAX_ATTEMPTS = 20;
	const WINDOW_SECONDS = 300; // 5 minutes.

	/**
	 * Max failed auth attempts allowed per IP within the window. Filterable so site
	 * owners with unusual traffic patterns (e.g. many legitimate keys behind one
	 * shared/proxy IP) can loosen or tighten the default.
	 */
	private static function max_attempts() {
		return (int) apply_filters( 'mcp_connector_rate_limit_max_attempts', self::MAX_ATTEMPTS );
	}

	private static function window_seconds() {
		return (int) apply_filters( 'mcp_connector_rate_limit_window_seconds', self::WINDOW_SECONDS );
	}

	private static function transient_key( $ip ) {
		return 'mcp_rl_' . md5( $ip );
	}

	/**
	 * Returns true if the given IP has exceeded the failed-auth-attempt budget.
	 */
	public static function is_limited( $ip ) {
		$count = get_transient( self::transient_key( $ip ) );
		return $count !== false && (int) $count >= self::max_attempts();
	}

	public static function record_failure( $ip ) {
		$key   = self::transient_key( $ip );
		$count = get_transient( $key );

		if ( false === $count ) {
			set_transient( $key, 1, self::window_seconds() );
		} else {
			set_transient( $key, (int) $count + 1, self::window_seconds() );
		}
	}

	public static function reset( $ip ) {
		delete_transient( self::transient_key( $ip ) );
	}

	public static function retry_after( $ip ) {
		// WP transients don't expose remaining TTL directly; return the fixed window
		// as a conservative, always-correct upper bound for the Retry-After header.
		return self::window_seconds();
	}
}
