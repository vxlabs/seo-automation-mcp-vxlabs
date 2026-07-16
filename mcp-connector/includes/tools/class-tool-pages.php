<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Tool_Pages {

	const ALLOWED_STATUSES = array( 'draft', 'pending', 'publish', 'private' );

	public static function list_pages( $args ) {
		list( $per_page, $page, $offset ) = Mcp_Tool_Registry::pagination_args( $args );

		$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any';
		if ( 'any' !== $status && ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			$status = 'any';
		}
		if ( 'publish' !== $status && ! current_user_can( 'edit_pages' ) ) {
			$status = 'publish';
		}

		$query_args = array(
			'post_type'      => 'page',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		$query = new WP_Query( $query_args );

		$items = array_map( array( __CLASS__, 'format_page' ), $query->posts );

		return Mcp_Tool_Registry::envelope( $items, $query->found_posts, $per_page, $page );
	}

	public static function get_page( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}

		$page = get_post( absint( $args['id'] ) );

		if ( ! $page || 'page' !== $page->post_type ) {
			return new WP_Error( 'mcp_not_found', 'Page not found.' );
		}

		if ( ! current_user_can( 'read_post', $page->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to read this page.' );
		}

		return self::format_page( $page, true );
	}

	public static function create_page( $args ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to create pages.' );
		}

		if ( empty( $args['title'] ) || ! isset( $args['content'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'title and content are required.' );
		}

		$status = self::sanitize_status( $args['status'] ?? 'draft' );

		if ( 'publish' === $status && ! current_user_can( 'publish_pages' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to publish pages.' );
		}

		$postarr = array(
			'post_title'   => sanitize_text_field( $args['title'] ),
			'post_content' => wp_kses_post( $args['content'] ),
			'post_status'  => $status,
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		);

		if ( ! empty( $args['parent_id'] ) ) {
			$postarr['post_parent'] = absint( $args['parent_id'] );
		}

		$page_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		return self::format_page( get_post( $page_id ), true );
	}

	public static function update_page( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}

		$page_id = absint( $args['id'] );
		$page    = get_post( $page_id );

		if ( ! $page || 'page' !== $page->post_type ) {
			return new WP_Error( 'mcp_not_found', 'Page not found.' );
		}

		if ( ! current_user_can( 'edit_page', $page_id ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to edit this page.' );
		}

		$postarr = array( 'ID' => $page_id );

		if ( isset( $args['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$postarr['post_content'] = wp_kses_post( $args['content'] );
		}
		if ( isset( $args['status'] ) ) {
			$status = self::sanitize_status( $args['status'] );
			if ( 'publish' === $status && ! current_user_can( 'publish_pages' ) ) {
				return new WP_Error( 'mcp_forbidden', 'You do not have permission to publish pages.' );
			}
			$postarr['post_status'] = $status;
		}

		$result = wp_update_post( $postarr, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::format_page( get_post( $page_id ), true );
	}

	private static function sanitize_status( $status ) {
		$status = sanitize_key( $status );
		return in_array( $status, self::ALLOWED_STATUSES, true ) ? $status : 'draft';
	}

	private static function format_page( $page, $with_content = false ) {
		$data = array(
			'id'        => $page->ID,
			'title'     => get_the_title( $page ),
			'status'    => $page->post_status,
			'author_id' => (int) $page->post_author,
			'parent_id' => (int) $page->post_parent,
			'date'      => $page->post_date_gmt,
			'link'      => get_permalink( $page ),
		);

		if ( $with_content ) {
			$data['content'] = $page->post_content;
		}

		return $data;
	}
}
