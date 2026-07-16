<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Tool_Users {

	public static function list_users( $args ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to list users.' );
		}

		list( $per_page, $page, $offset ) = Mcp_Tool_Registry::pagination_args( $args );

		$query_args = array(
			'number' => $per_page,
			'offset' => $offset,
			'fields' => 'all',
		);

		if ( ! empty( $args['role'] ) ) {
			$query_args['role'] = sanitize_key( $args['role'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$query_args['search'] = '*' . sanitize_text_field( $args['search'] ) . '*';
		}

		$user_query = new WP_User_Query( $query_args );
		$users      = $user_query->get_results();
		$total      = $user_query->get_total();

		$items = array_map( array( __CLASS__, 'format_user' ), $users );

		return Mcp_Tool_Registry::envelope( $items, $total, $per_page, $page );
	}

	public static function get_user( $args ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to view users.' );
		}

		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}

		$user = get_userdata( absint( $args['id'] ) );

		if ( ! $user ) {
			return new WP_Error( 'mcp_not_found', 'User not found.' );
		}

		return self::format_user( $user );
	}

	/**
	 * Deliberately omits email address and any other sensitive account fields —
	 * only username/display name/role/registration date are exposed to MCP clients.
	 */
	private static function format_user( $user ) {
		return array(
			'id'           => $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'roles'        => $user->roles,
			'registered'   => $user->user_registered,
		);
	}
}
