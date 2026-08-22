<?php
/**
 * Plugin Name: SmartForms
 * Description: Build WordPress forms, manage entries, embed forms in Elementor, and process submissions through MCP.
 * Version: 0.3.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: SmartForms
 * License: GPL-2.0-or-later
 * Text Domain: smartforms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMARTFORMS_VERSION', '0.3.0' );
define( 'SMARTFORMS_DB_VERSION', '2' );
define( 'SMARTFORMS_FILE', __FILE__ );
define( 'SMARTFORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMARTFORMS_URL', plugin_dir_url( __FILE__ ) );

require_once SMARTFORMS_DIR . 'includes/class-smartforms-field-registry.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-form-repository.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-entry-repository.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-secrets.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-integration-repository.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-delivery-repository.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-spam-service.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-delivery-worker.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-recaptcha.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-validator.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-renderer.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-submission-service.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-rest-controller.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-mcp-tools.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms-activator.php';
require_once SMARTFORMS_DIR . 'includes/class-smartforms.php';

if ( is_admin() ) {
	require_once SMARTFORMS_DIR . 'admin/class-smartforms-admin.php';
}

register_activation_hook( __FILE__, array( 'SmartForms_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SmartForms_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'SmartForms', 'instance' ) );
