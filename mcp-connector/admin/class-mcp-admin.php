<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MCP_CONNECTOR_DIR . 'admin/class-mcp-keys-list-table.php';
require_once MCP_CONNECTOR_DIR . 'admin/class-mcp-seo-dashboard.php';
require_once MCP_CONNECTOR_DIR . 'admin/class-mcp-seo-dashboard-list-table.php';

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
		add_action( 'admin_init', array( $this, 'maybe_redirect_legacy_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_mcp_save_business_context', array( $this, 'save_business_context' ) );
		add_action( 'wp_ajax_mcp_generate_key', array( $this, 'ajax_generate_key' ) );
		add_action( 'wp_ajax_mcp_revoke_key', array( $this, 'ajax_revoke_key' ) );
		add_action( 'wp_ajax_mcp_delete_key', array( $this, 'ajax_delete_key' ) );
		add_action( 'wp_ajax_mcp_seo_dashboard_detail', array( $this, 'ajax_seo_dashboard_detail' ) );
		add_action( 'wp_ajax_mcp_seo_dashboard_get_meta', array( $this, 'ajax_seo_dashboard_get_meta' ) );
		add_action( 'wp_ajax_mcp_seo_dashboard_save_meta', array( $this, 'ajax_seo_dashboard_save_meta' ) );
	}

	public function register_menu() {
		add_menu_page(
			'Automator Agent',
			'Automator Agent',
			self::CAPABILITY,
			'mcp-connector',
			array( $this, 'render_page' ),
			'dashicons-rest-api',
			80
		);

		add_submenu_page(
			'mcp-connector',
			'Automator Agent',
			'Settings',
			self::CAPABILITY,
			'mcp-connector',
			array( $this, 'render_page' )
		);
	}

	/**
	 * The old "Business Context" page was its own submenu (page=mcp-business-context).
	 * It's now the "context" tab on the single settings page — redirect bookmarks.
	 */
	public function maybe_redirect_legacy_page() {
		if ( ! isset( $_GET['page'] ) || 'mcp-business-context' !== $_GET['page'] ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=mcp-connector&tab=context' ) );
		exit;
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_mcp-connector' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mcp-connector-admin',
			MCP_CONNECTOR_URL . 'admin/css/admin.css',
			array(),
			MCP_CONNECTOR_VERSION
		);

		wp_enqueue_script(
			'mcp-connector-admin',
			MCP_CONNECTOR_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			MCP_CONNECTOR_VERSION,
			true
		);

		wp_localize_script(
			'mcp-connector-admin',
			'mcpConnectorAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( self::NONCE_ACTION ),
				'seoTitleMaxLen' => Mcp_Seo_Dashboard::TITLE_MAX_LEN,
				'seoDescMaxLen'  => Mcp_Seo_Dashboard::DESCRIPTION_MAX_LEN,
			)
		);

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'keys';
		if ( 'technical' === $active_tab ) {
			$text_settings = wp_enqueue_code_editor( array( 'type' => 'text/plain' ) );
			$json_settings = wp_enqueue_code_editor( array( 'type' => 'application/json' ) );

			// wp_enqueue_code_editor() returns false when the user has disabled
			// syntax highlighting in their profile — the plain textarea stands in that case.
			if ( false !== $text_settings || false !== $json_settings ) {
				wp_add_inline_script(
					'mcp-connector-admin',
					'window.mcpTechnicalSeoEditors = ' . wp_json_encode(
						array(
							'text' => $text_settings,
							'json' => $json_settings,
						)
					) . ';',
					'before'
				);
			}
		}
	}

	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to access this page.' );
		}

		$endpoint_url         = Mcp_Rest_Controller::get_endpoint_url();
		$permalinks_are_plain = ! get_option( 'permalink_structure' );
		$site_is_https        = is_ssl();
		$stored               = Mcp_Business_Context::get();
		$context_text         = $stored['context_text'];
		$seo_provider         = Mcp_Seo_Service::detect_provider();

		require MCP_CONNECTOR_DIR . 'admin/views/admin-page.php';
	}

	public function save_business_context() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to update business context.', 403 );
		}

		check_admin_referer( 'mcp_save_business_context' );
		$text   = isset( $_POST['context_text'] ) ? wp_unslash( $_POST['context_text'] ) : '';
		$result = Mcp_Business_Context::update( $text, get_current_user_id() );
		$status = is_wp_error( $result ) ? 'error' : 'saved';

		wp_safe_redirect( add_query_arg( 'mcp-context-status', $status, admin_url( 'admin.php?page=mcp-connector&tab=context' ) ) );
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

	public function ajax_seo_dashboard_detail() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || ! in_array( $post->post_type, array_keys( Mcp_Seo_Service::managed_post_types() ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post.' ), 400 );
		}

		wp_send_json_success( array( 'html' => Mcp_Seo_Dashboard::render_detail_panel( $post_id ) ) );
	}

	public function ajax_seo_dashboard_get_meta() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || ! in_array( $post->post_type, array_keys( Mcp_Seo_Service::managed_post_types() ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post.' ), 400 );
		}

		$seo = Mcp_Seo_Service::get_for_post( $post_id );

		if ( is_wp_error( $seo ) ) {
			wp_send_json_error( array( 'message' => $seo->get_error_message() ), 400 );
		}

		wp_send_json_success( $seo );
	}

	public function ajax_seo_dashboard_save_meta() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		$values = array(
			'title'         => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
			'description'   => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
			'canonical_url' => isset( $_POST['canonical_url'] ) ? wp_unslash( $_POST['canonical_url'] ) : '',
			'focus_topic'   => isset( $_POST['focus_topic'] ) ? wp_unslash( $_POST['focus_topic'] ) : '',
			'keywords'      => isset( $_POST['keywords'] ) ? wp_unslash( $_POST['keywords'] ) : '',
			'robots'        => isset( $_POST['robots'] ) ? wp_unslash( $_POST['robots'] ) : '',
		);

		$result = Mcp_Seo_Service::update_for_post( $post_id, $values );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}
}
