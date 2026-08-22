<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( 'SmartForms_Form_Repository', 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'rest_api_init', array( 'SmartForms_REST_Controller', 'register_routes' ) );
		add_shortcode( 'smartforms', array( 'SmartForms_Renderer', 'shortcode' ) );
		add_filter( 'wp_post_revision_meta_keys', array( $this, 'revision_meta_keys' ) );
		add_filter( 'mcp_connector_tools', array( 'SmartForms_MCP_Tools', 'register_tools' ) );
		add_action( 'plugins_loaded', array( 'SmartForms_Activator', 'maybe_upgrade' ), 20 );
		add_action( 'smartforms_daily_cleanup', array( 'SmartForms_Entry_Repository', 'purge_expired' ) );
		add_filter( 'cron_schedules', array( 'SmartForms_Delivery_Worker', 'schedules' ) );
		add_action( 'smartforms_process_deliveries', array( 'SmartForms_Delivery_Worker', 'process_due' ) );

		if ( is_admin() ) {
			SmartForms_Admin::instance();
		}

		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
	}

	public function register_assets() {
		wp_register_style( 'smartforms-frontend', SMARTFORMS_URL . 'assets/css/frontend.css', array(), SMARTFORMS_VERSION );
		wp_register_script( 'smartforms-frontend', SMARTFORMS_URL . 'assets/js/frontend.js', array(), SMARTFORMS_VERSION, true );
	}

	public function revision_meta_keys( $keys ) {
		$keys[] = SmartForms_Form_Repository::SCHEMA_META;
		$keys[] = SmartForms_Form_Repository::SETTINGS_META;
		return array_values( array_unique( $keys ) );
	}

	public function register_elementor_widget( $widgets_manager ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
			return;
		}

		require_once SMARTFORMS_DIR . 'integrations/class-smartforms-elementor-widget.php';
		$widgets_manager->register( new SmartForms_Elementor_Widget() );
	}
}
