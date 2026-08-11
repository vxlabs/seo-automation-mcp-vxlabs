<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Tool_Media {

	public static function list_media( $args ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to access the media library.' );
		}
		list( $per_page, $page, $offset ) = Mcp_Tool_Registry::pagination_args( $args );
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}
		if ( ! empty( $args['mime_type'] ) ) {
			$query_args['post_mime_type'] = sanitize_mime_type( $args['mime_type'] );
		}

		$query = new WP_Query( $query_args );
		$items = array_map( array( __CLASS__, 'format_media' ), $query->posts );
		return Mcp_Tool_Registry::envelope( $items, $query->found_posts, $per_page, $page );
	}

	public static function get_media( $args ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to access the media library.' );
		}
		if ( empty( $args['id'] ) ) {
			return new WP_Error( 'mcp_missing_param', 'id is required.' );
		}
		$attachment = get_post( absint( $args['id'] ) );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'mcp_not_found', 'Media item not found.' );
		}
		if ( ! current_user_can( 'read_post', $attachment->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to read this media item.' );
		}
		return self::format_media( $attachment );
	}

	public static function update_media_alt_text( $args ) {
		if ( empty( $args['id'] ) || ! array_key_exists( 'alt_text', $args ) ) {
			return new WP_Error( 'mcp_missing_param', 'id and alt_text are required.' );
		}
		$attachment = get_post( absint( $args['id'] ) );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'mcp_not_found', 'Media item not found.' );
		}
		if ( ! wp_attachment_is_image( $attachment->ID ) ) {
			return new WP_Error( 'mcp_invalid_media', 'Alt text can only be set on an image attachment.' );
		}
		if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to edit this media item.' );
		}

		update_post_meta( $attachment->ID, '_wp_attachment_image_alt', sanitize_text_field( $args['alt_text'] ) );
		return self::format_media( get_post( $attachment->ID ) );
	}

	private static function format_media( $attachment ) {
		$metadata = wp_get_attachment_metadata( $attachment->ID );
		return array(
			'id'           => (int) $attachment->ID,
			'title'        => get_the_title( $attachment ),
			'alt_text'     => (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'caption'      => $attachment->post_excerpt,
			'description'  => $attachment->post_content,
			'url'          => wp_get_attachment_url( $attachment->ID ),
			'mime_type'    => $attachment->post_mime_type,
			'width'        => is_array( $metadata ) && isset( $metadata['width'] ) ? (int) $metadata['width'] : null,
			'height'       => is_array( $metadata ) && isset( $metadata['height'] ) ? (int) $metadata['height'] : null,
			'parent_id'    => (int) $attachment->post_parent,
			'date'         => $attachment->post_date_gmt,
			'modified_gmt' => $attachment->post_modified_gmt,
		);
	}
}
