<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Tool_Posts {

	const ALLOWED_STATUSES = array( 'draft', 'pending', 'publish', 'private' );

	public static function list_posts( $args ) {
		list( $per_page, $page, $offset ) = Mcp_Tool_Registry::pagination_args( $args );

		$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any';
		if ( 'any' !== $status && ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			$status = 'any';
		}
		if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
			$status = 'publish';
		}

		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		$query = new WP_Query( $query_args );

		$items = array_map( array( __CLASS__, 'format_post' ), $query->posts );

		return Mcp_Tool_Registry::envelope( $items, $query->found_posts, $per_page, $page );
	}

	public static function get_post( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}

		$post = get_post( absint( $args['id'] ) );

		if ( ! $post || 'post' !== $post->post_type ) {
			return new WP_Error( 'mcp_not_found', 'Post not found.' );
		}

		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to read this post.' );
		}

		return self::format_post( $post, true );
	}

	public static function create_post( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to create posts.' );
		}

		if ( empty( $args['title'] ) || ! isset( $args['content'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'title and content are required.' );
		}

		$status = self::sanitize_status( $args['status'] ?? 'draft' );

		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to publish posts.' );
		}

		$postarr = array(
			'post_title'   => sanitize_text_field( $args['title'] ),
			'post_content' => wp_kses_post( $args['content'] ),
			'post_status'  => $status,
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		);

		if ( isset( $args['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_text_field( $args['excerpt'] );
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $args['categories'] ) && is_array( $args['categories'] ) ) {
			wp_set_post_categories( $post_id, self::resolve_terms( $args['categories'], 'category' ) );
		}

		if ( ! empty( $args['tags'] ) && is_array( $args['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $args['tags'] ) );
		}

		return self::format_post( get_post( $post_id ), true );
	}

	public static function update_post( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}

		$post_id = absint( $args['id'] );
		$post    = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return new WP_Error( 'mcp_not_found', 'Post not found.' );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to edit this post.' );
		}

		$postarr = array( 'ID' => $post_id );

		if ( isset( $args['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$postarr['post_content'] = wp_kses_post( $args['content'] );
		}
		if ( isset( $args['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_text_field( $args['excerpt'] );
		}
		if ( isset( $args['status'] ) ) {
			$status = self::sanitize_status( $args['status'] );
			if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
				return new WP_Error( 'mcp_forbidden', 'You do not have permission to publish posts.' );
			}
			$postarr['post_status'] = $status;
		}

		$result = wp_update_post( $postarr, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::format_post( get_post( $post_id ), true );
	}

	private static function sanitize_status( $status ) {
		$status = sanitize_key( $status );
		return in_array( $status, self::ALLOWED_STATUSES, true ) ? $status : 'draft';
	}

	private static function resolve_terms( $names, $taxonomy ) {
		$ids = array();
		foreach ( $names as $name ) {
			$name = sanitize_text_field( $name );
			$term = term_exists( $name, $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term( $name, $taxonomy );
			}
			if ( ! is_wp_error( $term ) ) {
				$ids[] = (int) $term['term_id'];
			}
		}
		return $ids;
	}

	private static function format_post( $post, $with_content = false ) {
		$data = array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'status'    => $post->post_status,
			'author_id' => (int) $post->post_author,
			'date'      => $post->post_date_gmt,
			'link'      => get_permalink( $post ),
			'excerpt'   => get_the_excerpt( $post ),
		);

		if ( $with_content ) {
			$data['content'] = $post->post_content;
		}

		return $data;
	}
}
