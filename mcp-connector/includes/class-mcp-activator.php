<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Activator {

	public static function activate() {
		self::create_tables();
		// The /mcp rewrite rule is registered on `init`, which hasn't run yet during
		// activation, so flush_rewrite_rules() here would flush without the rule present.
		// Defer the flush to the next `init` instead (consumed once in Mcp_Connector).
		update_option( 'mcp_connector_flush_rewrites', 1 );
	}

	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$keys_table = $wpdb->prefix . 'mcp_api_keys';
		$sql_keys   = "CREATE TABLE {$keys_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			key_prefix VARCHAR(12) NOT NULL,
			key_hash CHAR(64) NOT NULL,
			label VARCHAR(191) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			scopes VARCHAR(255) NOT NULL DEFAULT 'inherit',
			created_at DATETIME NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			last_used_at DATETIME NULL,
			last_used_ip VARCHAR(45) NULL,
			revoked_at DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			PRIMARY KEY  (id),
			KEY key_prefix (key_prefix),
			KEY user_id (user_id)
		) {$charset_collate};";

		$log_table = $wpdb->prefix . 'mcp_request_log';
		$sql_log   = "CREATE TABLE {$log_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			key_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NULL,
			method VARCHAR(64) NOT NULL,
			tool_name VARCHAR(64) NULL,
			args_summary TEXT NULL,
			success TINYINT(1) NOT NULL DEFAULT 0,
			error_message VARCHAR(255) NULL,
			ip VARCHAR(45) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY key_id (key_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_keys );
		dbDelta( $sql_log );
	}
}
