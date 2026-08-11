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
		if ( 'publish' !== $status && ! current_user_can( 'edit_others_posts' ) ) {
			$query_args['author'] = get_current_user_id();
		}

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
		if ( ! $status ) {
			return new WP_Error( 'mcp_invalid_param', 'status must be one of: ' . implode( ', ', self::ALLOWED_STATUSES ) );
		}

		if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to publish posts.' );
		}

		$category_ids = array();
		$tag_ids      = array();
		if ( ! empty( $args['categories'] ) ) {
			$category_ids = self::resolve_terms( $args['categories'], 'category' );
			if ( is_wp_error( $category_ids ) ) {
				return $category_ids;
			}
		}
		if ( ! empty( $args['tags'] ) ) {
			$tag_ids = self::resolve_terms( $args['tags'], 'post_tag' );
			if ( is_wp_error( $tag_ids ) ) {
				return $tag_ids;
			}
		}

		$postarr = array(
			'post_title'   => sanitize_text_field( $args['title'] ),
			'post_content' => wp_kses_post( $args['content'] ),
			'post_status'  => $status,
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		);
		if ( isset( $args['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $args['slug'] );
		}

		if ( isset( $args['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_text_field( $args['excerpt'] );
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( $category_ids ) {
			wp_set_post_categories( $post_id, $category_ids );
		}

		if ( $tag_ids ) {
			wp_set_object_terms( $post_id, $tag_ids, 'post_tag', false );
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
		if ( isset( $args['expected_modified_gmt'] ) && $args['expected_modified_gmt'] !== $post->post_modified_gmt ) {
			return new WP_Error( 'mcp_edit_conflict', 'The post changed after it was read. Fetch it again before updating.' );
		}

		$postarr = array( 'ID' => $post_id );
		$resolved_terms = array();
		foreach ( array( 'categories' => 'category', 'tags' => 'post_tag' ) as $arg_key => $taxonomy ) {
			if ( array_key_exists( $arg_key, $args ) ) {
				$resolved_terms[ $taxonomy ] = self::resolve_terms( is_array( $args[ $arg_key ] ) ? $args[ $arg_key ] : array(), $taxonomy );
				if ( is_wp_error( $resolved_terms[ $taxonomy ] ) ) {
					return $resolved_terms[ $taxonomy ];
				}
			}
		}

		if ( isset( $args['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$postarr['post_content'] = wp_kses_post( $args['content'] );
		}
		if ( isset( $args['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_text_field( $args['excerpt'] );
		}
		if ( isset( $args['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $args['slug'] );
		}
		if ( isset( $args['status'] ) ) {
			$status = self::sanitize_status( $args['status'] );
			if ( ! $status ) {
				return new WP_Error( 'mcp_invalid_param', 'status must be one of: ' . implode( ', ', self::ALLOWED_STATUSES ) );
			}
			if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( 'publish_posts' ) ) {
				return new WP_Error( 'mcp_forbidden', 'You do not have permission to publish posts.' );
			}
			$postarr['post_status'] = $status;
		}

		$result = wp_update_post( $postarr, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		foreach ( $resolved_terms as $taxonomy => $term_ids ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
		}

		return self::format_post( get_post( $post_id ), true );
	}

	private static function sanitize_status( $status ) {
		$status = sanitize_key( $status );
		return in_array( $status, self::ALLOWED_STATUSES, true ) ? $status : null;
	}

	private static function resolve_terms( $names, $taxonomy ) {
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object || ! current_user_can( $taxonomy_object->cap->assign_terms ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to assign these terms.' );
		}
		$ids = array();
		foreach ( $names as $name ) {
			$name = sanitize_text_field( $name );
			if ( '' === $name ) {
				continue;
			}
			$term = term_exists( $name, $taxonomy );
			if ( ! $term ) {
				if ( ! current_user_can( $taxonomy_object->cap->manage_terms ) ) {
					return new WP_Error( 'mcp_forbidden', 'A requested term does not exist and you do not have permission to create it: ' . $name );
				}
				$term = wp_insert_term( $name, $taxonomy );
			}
			if ( ! is_wp_error( $term ) ) {
				$ids[] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			}
		}
		return $ids;
	}

	private static function format_post( $post, $with_content = false ) {
		$data = array(
			'id'                => $post->ID,
			'title'             => get_the_title( $post ),
			'slug'              => $post->post_name,
			'status'            => $post->post_status,
			'author_id'         => (int) $post->post_author,
			'date'              => $post->post_date_gmt,
			'modified_gmt'      => $post->post_modified_gmt,
			'link'              => get_permalink( $post ),
			'excerpt'           => get_the_excerpt( $post ),
			'featured_media_id' => (int) get_post_thumbnail_id( $post ),
			'categories'        => wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) ),
			'tags'              => wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) ),
		);

		if ( $with_content ) {
			$data['content'] = $post->post_content;
		}

		return $data;
	}
}
