<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Key_Manager {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'mcp_api_keys';
	}

	/**
	 * Creates a new API key. Returns the full plaintext key exactly once — callers
	 * must surface it to the admin immediately; it is not recoverable afterward.
	 *
	 * @return array{id:int,full_key:string,key_prefix:string,label:string,created_at:string}|WP_Error
	 */
	public static function generate( $user_id, $label, $created_by ) {
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'mcp_invalid_user', 'The selected user does not exist.' );
		}

		global $wpdb;

		$key_prefix = bin2hex( random_bytes( 4 ) );
		$secret     = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$full_key   = "mcp_{$key_prefix}_{$secret}";
		$key_hash   = hash( 'sha256', $secret );
		$now        = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'key_prefix' => $key_prefix,
				'key_hash'   => $key_hash,
				'label'      => sanitize_text_field( $label ),
				'user_id'    => $user_id,
				'scopes'     => 'inherit',
				'created_at' => $now,
				'created_by' => $created_by,
				'status'     => 'active',
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'mcp_db_error', 'Could not create the API key.' );
		}

		return array(
			'id'         => (int) $wpdb->insert_id,
			'full_key'   => $full_key,
			'key_prefix' => $key_prefix,
			'label'      => $label,
			'created_at' => $now,
		);
	}

	/**
	 * Validates a presented full key string. Returns the DB row on success, or null.
	 */
	public static function verify( $presented_key ) {
		if ( ! preg_match( '/^mcp_([a-f0-9]{8})_(.+)$/', $presented_key, $matches ) ) {
			return null;
		}

		list( , $key_prefix, $secret ) = $matches;

		global $wpdb;
		$table = self::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE key_prefix = %s AND status = 'active'",
				$key_prefix
			)
		);

		if ( ! $row ) {
			return null;
		}

		if ( ! hash_equals( $row->key_hash, hash( 'sha256', $secret ) ) ) {
			return null;
		}

		return $row;
	}

	public static function touch_last_used( $key_id, $ip ) {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array(
				'last_used_at' => current_time( 'mysql', true ),
				'last_used_ip' => substr( $ip, 0, 45 ),
			),
			array( 'id' => $key_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function revoke( $key_id ) {
		global $wpdb;
		return $wpdb->update(
			self::table_name(),
			array(
				'status'     => 'revoked',
				'revoked_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $key_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function delete( $key_id ) {
		global $wpdb;
		return $wpdb->delete( self::table_name(), array( 'id' => $key_id ), array( '%d' ) );
	}

	public static function list_all() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}
}
