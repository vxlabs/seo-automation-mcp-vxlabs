<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcp_api_keys" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcp_request_log" );

$context_ids = $wpdb->get_col(
	$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'mcp_business_ctx' )
);
foreach ( $context_ids as $context_id ) {
	wp_delete_post( (int) $context_id, true );
}

$seo_meta_keys = array( '_mcp_seo_title', '_mcp_seo_description', '_mcp_seo_canonical', '_mcp_seo_robots', '_mcp_seo_schema', '_mcp_seo_focus_topic' );
foreach ( $seo_meta_keys as $meta_key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ), array( '%s' ) );
}

delete_option( 'mcp_connector_flush_rewrites' );
delete_option( 'mcp_connector_rewrite_version' );
delete_option( 'mcp_business_context_post_id' );
delete_option( 'mcp_seo_robots_txt' );
delete_option( 'mcp_seo_llms_txt' );
delete_option( 'mcp_seo_jsonld' );
