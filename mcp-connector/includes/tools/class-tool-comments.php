<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Tool_Comments {

	const ALLOWED_ACTIONS = array( 'approve', 'spam', 'trash', 'unapprove' );

	public static function list_comments( $args ) {
		list( $per_page, $page, $offset ) = Mcp_Tool_Registry::pagination_args( $args );

		$can_moderate = current_user_can( 'moderate_comments' );

		$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'all';
		if ( ! $can_moderate ) {
			$status = 'approve';
		}

		$query_args = array(
			'status' => $status,
			'number' => $per_page,
			'offset' => $offset,
			'order'  => 'DESC',
		);

		if ( ! empty( $args['post_id'] ) ) {
			$query_args['post_id'] = absint( $args['post_id'] );
		}

		$comments = get_comments( $query_args );

		$count_args           = $query_args;
		$count_args['count']  = true;
		unset( $count_args['number'], $count_args['offset'] );
		$total = get_comments( $count_args );

		$items = array_map( array( __CLASS__, 'format_comment' ), $comments );

		return Mcp_Tool_Registry::envelope( $items, $total, $per_page, $page );
	}

	public static function get_comment( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}

		$comment = get_comment( absint( $args['id'] ) );

		if ( ! $comment ) {
			return new WP_Error( 'mcp_not_found', 'Comment not found.' );
		}

		if ( '1' !== $comment->comment_approved && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to view this comment.' );
		}

		return self::format_comment( $comment );
	}

	public static function moderate_comment( $args ) {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to moderate comments.' );
		}

		if ( empty( $args['id'] ) || empty( $args['action'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id and action are required.' );
		}

		$action = sanitize_key( $args['action'] );

		if ( ! in_array( $action, self::ALLOWED_ACTIONS, true ) ) {
			return new WP_Error( 'mcp_invalid_param', 'action must be one of: ' . implode( ', ', self::ALLOWED_ACTIONS ) );
		}

		$comment_id = absint( $args['id'] );

		if ( ! get_comment( $comment_id ) ) {
			return new WP_Error( 'mcp_not_found', 'Comment not found.' );
		}

		$status_map = array(
			'approve'   => 'approve',
			'unapprove' => 'hold',
			'spam'      => 'spam',
			'trash'     => 'trash',
		);

		$result = wp_set_comment_status( $comment_id, $status_map[ $action ], true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::format_comment( get_comment( $comment_id ) );
	}

	private static function format_comment( $comment ) {
		return array(
			'id'       => (int) $comment->comment_ID,
			'post_id'  => (int) $comment->comment_post_ID,
			'author'   => $comment->comment_author,
			'content'  => $comment->comment_content,
			'status'   => wp_get_comment_status( $comment ),
			'date'     => $comment->comment_date_gmt,
			'parent_id' => (int) $comment->comment_parent,
		);
	}
}
