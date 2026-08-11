<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Tool_Seo {

	public static function get_business_context( $args ) {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to read the site business context.' );
		}

		return Mcp_Business_Context::get();
	}

	public static function update_business_context( $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'mcp_forbidden', 'Only a site administrator can update business context through MCP.' );
		}

		$context = isset( $args['context'] ) && is_array( $args['context'] ) ? $args['context'] : array();
		return Mcp_Business_Context::update( $context, get_current_user_id() );
	}

	public static function get_seo_metadata( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}
		$post = get_post( absint( $args['id'] ) );
		if ( ! $post || ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to read SEO metadata for this content.' );
		}
		return Mcp_Seo_Service::get_for_post( $post->ID );
	}

	public static function update_seo_metadata( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}

		$values = $args;
		unset( $values['id'] );
		return Mcp_Seo_Service::update_for_post( absint( $args['id'] ), $values );
	}

	public static function audit_content_seo( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}
		return Mcp_Seo_Service::audit_post( absint( $args['id'] ) );
	}

	public static function list_content_revisions( $args ) {
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}
		$post = get_post( absint( $args['id'] ) );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'mcp_not_found', 'Post or page not found.' );
		}
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to read revisions for this content.' );
		}

		list( $per_page, $page, $offset ) = Mcp_Tool_Registry::pagination_args( $args );
		$all       = wp_get_post_revisions( $post->ID, array( 'posts_per_page' => -1 ) );
		$revisions = array_slice( array_values( $all ), $offset, $per_page );
		$items     = array_map(
			function ( $revision ) {
				return array(
					'id'        => (int) $revision->ID,
					'parent_id' => (int) $revision->post_parent,
					'author_id' => (int) $revision->post_author,
					'date'      => $revision->post_date_gmt,
					'title'     => $revision->post_title,
				);
			},
			$revisions
		);

		return Mcp_Tool_Registry::envelope( $items, count( $all ), $per_page, $page );
	}

	public static function get_content_revision( $args ) {
		if ( empty( $args['id'] ) || empty( $args['revision_id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id and revision_id are required.' );
		}
		$post     = get_post( absint( $args['id'] ) );
		$revision = wp_get_post_revision( absint( $args['revision_id'] ) );
		if ( ! $post || ! $revision || (int) $revision->post_parent !== (int) $post->ID ) {
			return new WP_Error( 'mcp_not_found', 'Revision not found for this content.' );
		}
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to read this revision.' );
		}

		return array(
			'id'        => (int) $revision->ID,
			'parent_id' => (int) $revision->post_parent,
			'author_id' => (int) $revision->post_author,
			'date'      => $revision->post_date_gmt,
			'title'     => $revision->post_title,
			'excerpt'   => $revision->post_excerpt,
			'content'   => $revision->post_content,
		);
	}
}
