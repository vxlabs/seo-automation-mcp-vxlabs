<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'smartforms_daily_cleanup' );
wp_clear_scheduled_hook( 'smartforms_process_deliveries' );

$settings = get_option( 'smartforms_settings', array() );
$delete   = ! empty( $settings['delete_on_uninstall'] );

if ( $delete ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smartforms_entry_events" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smartforms_deliveries" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smartforms_form_integrations" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smartforms_integrations" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smartforms_entries" );
	$form_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'smartform' ) );
	foreach ( $form_ids as $form_id ) {
		wp_delete_post( (int) $form_id, true );
	}
	delete_option( 'smartforms_settings' );
	delete_option( 'smartforms_db_version' );
}

foreach ( array( 'administrator', 'smartforms_manager' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		foreach ( array( 'smartforms_manage_forms', 'smartforms_view_entries', 'smartforms_manage_entries', 'smartforms_manage_settings', 'smartforms_delete_entries', 'smartforms_manage_integrations', 'smartforms_classify_entries', 'smartforms_view_delivery_logs', 'smartforms_retry_deliveries' ) as $capability ) {
			$role->remove_cap( $capability );
		}
	}
}
