<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Mcp_Keys_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'api_key',
				'plural'   => 'api_keys',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'label'      => 'Label',
			'key'        => 'Key',
			'user'       => 'Acts As',
			'status'     => 'Status',
			'created_at' => 'Created',
			'last_used'  => 'Last Used',
		);
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = Api_Key_Manager::list_all();
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'label':
				return esc_html( $item->label );
			case 'created_at':
				return esc_html( $item->created_at );
			case 'last_used':
				return $item->last_used_at ? esc_html( $item->last_used_at ) : '—';
			default:
				return '';
		}
	}

	public function column_key( $item ) {
		return '<code>mcp_' . esc_html( $item->key_prefix ) . '_' . str_repeat( '•', 8 ) . '</code>';
	}

	public function column_user( $item ) {
		$user = get_userdata( $item->user_id );
		if ( ! $user ) {
			return '<span class="mcp-badge-warning">user missing</span>';
		}
		return esc_html( $user->display_name ) . ' (' . esc_html( $user->user_login ) . ')';
	}

	public function column_status( $item ) {
		if ( 'active' === $item->status ) {
			return '<span class="mcp-badge-active">Active</span>';
		}
		return '<span class="mcp-badge-revoked">Revoked</span>';
	}

	public function column_label( $item ) {
		$actions = array();

		if ( 'active' === $item->status ) {
			$actions['revoke'] = sprintf(
				'<a href="#" class="mcp-revoke-key" data-id="%d">Revoke</a>',
				(int) $item->id
			);
		} else {
			$actions['delete'] = sprintf(
				'<a href="#" class="mcp-delete-key" data-id="%d">Delete permanently</a>',
				(int) $item->id
			);
		}

		return esc_html( $item->label ) . $this->row_actions( $actions );
	}

	public function no_items() {
		echo 'No API keys yet. Generate one above to connect Claude to this site.';
	}
}
