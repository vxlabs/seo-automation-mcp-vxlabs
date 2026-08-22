<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Activator {

	public static function activate() {
		self::create_tables();
		self::add_capabilities();
		update_option( 'smartforms_db_version', SMARTFORMS_DB_VERSION, false );
		if ( false === get_option( 'smartforms_settings', false ) ) {
			add_option( 'smartforms_settings', SmartForms_Form_Repository::default_global_settings(), '', false );
		}
		self::schedule_events();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'smartforms_daily_cleanup' );
		wp_clear_scheduled_hook( 'smartforms_process_deliveries' );
		// Entries and forms are deliberately retained on deactivation.
	}

	public static function maybe_upgrade() {
		if ( SMARTFORMS_DB_VERSION !== get_option( 'smartforms_db_version' ) ) {
			self::create_tables();
			self::add_capabilities();
			update_option( 'smartforms_db_version', SMARTFORMS_DB_VERSION, false );
		}
		self::schedule_events();
	}

	private static function schedule_events() {
		if ( ! wp_next_scheduled( 'smartforms_daily_cleanup' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'smartforms_daily_cleanup' );
		if ( ! wp_next_scheduled( 'smartforms_process_deliveries' ) ) {
			add_filter( 'cron_schedules', array( 'SmartForms_Delivery_Worker', 'schedules' ) );
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'smartforms_minute', 'smartforms_process_deliveries' );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();
		$entries = $wpdb->prefix . 'smartforms_entries';
		$events  = $wpdb->prefix . 'smartforms_entry_events';
		$integrations = $wpdb->prefix . 'smartforms_integrations';
		$form_integrations = $wpdb->prefix . 'smartforms_form_integrations';
		$deliveries = $wpdb->prefix . 'smartforms_deliveries';

		$sql_entries = "CREATE TABLE {$entries} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			form_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			spam_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			spam_score DECIMAL(5,2) NULL,
			spam_confidence DECIMAL(5,2) NULL,
			spam_reason TEXT NULL,
			classifier VARCHAR(100) NULL,
			classified_at DATETIME NULL,
			classification_lease_until DATETIME NULL,
			classification_lease_owner VARCHAR(100) NULL,
			payload LONGTEXT NOT NULL,
			schema_snapshot LONGTEXT NOT NULL,
			source_url TEXT NULL,
			user_id BIGINT UNSIGNED NULL,
			ip_hash CHAR(64) NULL,
			user_agent VARCHAR(255) NULL,
			processed_by BIGINT UNSIGNED NULL,
			submitted_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY form_status (form_id, status),
			KEY spam_queue (spam_status, classification_lease_until),
			KEY submitted_at (submitted_at)
		) {$collate};";

		$sql_events = "CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(20) NOT NULL,
			old_status VARCHAR(20) NULL,
			new_status VARCHAR(20) NULL,
			note TEXT NULL,
			user_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id),
			KEY created_at (created_at)
		) {$collate};";

		$sql_integrations = "CREATE TABLE {$integrations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			type VARCHAR(20) NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			config LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY type_enabled (type, enabled)
		) {$collate};";

		$sql_form_integrations = "CREATE TABLE {$form_integrations} (
			form_id BIGINT UNSIGNED NOT NULL,
			integration_id BIGINT UNSIGNED NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (form_id, integration_id),
			KEY integration_id (integration_id)
		) {$collate};";

		$sql_deliveries = "CREATE TABLE {$deliveries} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id CHAR(36) NOT NULL,
			entry_id BIGINT UNSIGNED NOT NULL,
			integration_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			next_attempt_at DATETIME NULL,
			idempotency_key CHAR(64) NOT NULL,
			response_code SMALLINT UNSIGNED NULL,
			last_error TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			delivered_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY due_queue (status, next_attempt_at),
			KEY entry_id (entry_id)
		) {$collate};";

		dbDelta( $sql_entries );
		dbDelta( $sql_events );
		dbDelta( $sql_integrations );
		dbDelta( $sql_form_integrations );
		dbDelta( $sql_deliveries );
	}

	private static function add_capabilities() {
		$caps = array(
			'smartforms_manage_forms',
			'smartforms_view_entries',
			'smartforms_manage_entries',
			'smartforms_manage_settings',
			'smartforms_delete_entries',
			'smartforms_manage_integrations',
			'smartforms_classify_entries',
			'smartforms_view_delivery_logs',
			'smartforms_retry_deliveries',
		);

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$manager = get_role( 'smartforms_manager' );
		if ( ! $manager ) {
			$manager = add_role( 'smartforms_manager', 'SmartForms Manager', array( 'read' => true ) );
		}
		if ( $manager ) {
			foreach ( array( 'smartforms_manage_forms', 'smartforms_view_entries', 'smartforms_manage_entries', 'smartforms_classify_entries', 'smartforms_view_delivery_logs', 'smartforms_retry_deliveries' ) as $cap ) {
				$manager->add_cap( $cap );
			}
		}
	}
}
