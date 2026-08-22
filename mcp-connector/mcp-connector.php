<?php
/**
 * Plugin Name: Automator Agent
 * Description: Exposes business context, content, SEO metadata, JSON-LD, media, comments, and users to MCP clients such as Claude through capability-checked tool calls.
 * Version: 1.3.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Automator Agent
 * License: GPL-2.0-or-later
 * Text Domain: mcp-connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCP_CONNECTOR_VERSION', '1.3.0' );
define( 'MCP_CONNECTOR_FILE', __FILE__ );
define( 'MCP_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCP_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );

require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-logger.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-api-key-manager.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-rate-limiter.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-auth.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-business-context.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-seo-service.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-technical-seo.php';
require_once MCP_CONNECTOR_DIR . 'includes/class-mcp-tool-registry.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-posts.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-pages.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-comments.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-users.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-seo.php';
require_once MCP_CONNECTOR_DIR . 'includes/tools/class-tool-media.php';
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
