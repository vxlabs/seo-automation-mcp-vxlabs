<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcp_api_keys" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcp_request_log" );

delete_option( 'mcp_connector_flush_rewrites' );
