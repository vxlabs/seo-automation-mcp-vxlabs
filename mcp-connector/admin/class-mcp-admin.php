<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MCP_CONNECTOR_DIR . 'admin/class-mcp-keys-list-table.php';

class Mcp_Admin {

	const NONCE_ACTION = 'mcp_connector_admin';
	const CAPABILITY   = 'manage_options';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_mcp_save_business_context', array( $this, 'save_business_context' ) );
		add_action( 'wp_ajax_mcp_generate_key', array( $this, 'ajax_generate_key' ) );
		add_action( 'wp_ajax_mcp_revoke_key', array( $this, 'ajax_revoke_key' ) );
		add_action( 'wp_ajax_mcp_delete_key', array( $this, 'ajax_delete_key' ) );
	}

	public function register_menu() {
		add_menu_page(
			'MCP Connector',
			'MCP Connector',
			self::CAPABILITY,
			'mcp-connector',
			array( $this, 'render_page' ),
			'dashicons-rest-api',
			80
		);

		add_submenu_page(
			'mcp-connector',
			'API Keys',
			'API Keys',
			self::CAPABILITY,
			'mcp-connector',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			'mcp-connector',
			'Business Context',
			'Business Context',
			self::CAPABILITY,
			'mcp-business-context',
			array( $this, 'render_business_context_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'toplevel_page_mcp-connector', 'mcp-connector_page_mcp-business-context' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'mcp-connector-admin',
			MCP_CONNECTOR_URL . 'admin/css/admin-api-keys.css',
			array(),
			MCP_CONNECTOR_VERSION
		);

		wp_enqueue_script(
			'mcp-connector-admin',
			MCP_CONNECTOR_URL . 'admin/js/admin-api-keys.js',
			array( 'jquery' ),
			MCP_CONNECTOR_VERSION,
			true
		);

		wp_localize_script(
			'mcp-connector-admin',
			'mcpConnectorAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to access this page.' );
		}

		$endpoint_url         = Mcp_Rest_Controller::get_endpoint_url();
		$permalinks_are_plain = ! get_option( 'permalink_structure' );
		$site_is_https        = is_ssl();

		require MCP_CONNECTOR_DIR . 'admin/views/page-api-keys.php';
	}

	public function render_business_context_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to access this page.' );
		}

		$stored      = Mcp_Business_Context::get();
		$context     = $stored['context'];
		$definitions = Mcp_Business_Context::field_definitions();
		$seo_provider = Mcp_Seo_Service::detect_provider();
		$sections    = array(
			'identity'    => 'Business identity',
			'contact'     => 'Public contact and locations',
			'positioning' => 'Audience and positioning',
			'editorial'   => 'Editorial guardrails',
			'seo'         => 'SEO strategy',
		);

		require MCP_CONNECTOR_DIR . 'admin/views/page-business-context.php';
	}

	public function save_business_context() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to update business context.', 403 );
		}

		check_admin_referer( 'mcp_save_business_context' );
		$input  = isset( $_POST['context'] ) && is_array( $_POST['context'] ) ? wp_unslash( $_POST['context'] ) : array();
		$result = Mcp_Business_Context::update( $input, get_current_user_id() );
		$status = is_wp_error( $result ) ? 'error' : 'saved';

		wp_safe_redirect( add_query_arg( 'mcp-context-status', $status, admin_url( 'admin.php?page=mcp-business-context' ) ) );
		exit;
	}

	public function ajax_generate_key() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( '' === $label ) {
			$label = 'Untitled key';
		}

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Please choose a valid WordPress user for this key to act as.' ), 400 );
		}

		$result = Api_Key_Manager::generate( $user_id, $label, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$endpoint_url = Mcp_Rest_Controller::get_endpoint_url();
		$separator    = ( false === strpos( $endpoint_url, '?' ) ) ? '?' : '&';

		wp_send_json_success(
			array(
					'full_key'      => $result['full_key'],
					'label'         => $result['label'],
					'endpoint_url'  => $endpoint_url,
					'connector_url' => $endpoint_url . $separator . 'api-key=' . rawurlencode( $result['full_key'] ),
			)
		);
	}

	public function ajax_revoke_key() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Invalid key id.' ), 400 );
		}

		Api_Key_Manager::revoke( $id );

		wp_send_json_success();
	}

	public function ajax_delete_key() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Invalid key id.' ), 400 );
		}

		Api_Key_Manager::delete( $id );

		wp_send_json_success();
	}
}
