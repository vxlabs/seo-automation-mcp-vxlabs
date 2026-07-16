<?php
/**
 * Plugin Name: MCP Connector
 * Description: Exposes this WordPress site as a remote MCP (Model Context Protocol) server so AI clients like Claude can manage posts, pages, comments, and users via API-key-authenticated tool calls.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: MCP Connector
 * License: GPL-2.0-or-later
 * Text Domain: mcp-connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCP_CONNECTOR_VERSION', '1.0.0' );
define( 'MCP_CONNECTOR_FILE', __FILE__ );
define( 'MCP_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCP_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );

require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-logger.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-api-key-manager.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-rate-limiter.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-auth.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-tool-registry.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-posts.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-pages.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-comments.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-users.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-jsonrpc-server.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-rest-controller.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-connector.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-activator.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-deactivator.php';

if ( is_admin() ) {
	require_once MCP_CONNECTOR_DIR . 'admin/class-mcp-admin.php';
	add_action( 'plugins_loaded', array( 'Mcp_Admin', 'instance' ) );
}

register_activation_hook( __FILE__, array( 'Mcp_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Mcp_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Mcp_Connector', 'instance' ) );
