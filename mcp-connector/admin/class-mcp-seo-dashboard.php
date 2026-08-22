<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only admin surface over Mcp_Seo_Service: per-post-type tab list,
 * grid badge statuses, and the ajax-loaded content-audit detail panel.
 */
class Mcp_Seo_Dashboard {

	const TITLE_MAX_LEN       = 60;
	const DESCRIPTION_MAX_LEN = 155;

	/**
	 * Ordered list of post-type tabs for the dashboard. 'post' and 'page'
	 * always come first (as "Blog Posts"/"Pages"), followed by any other
	 * public, non-attachment post type Mcp_Seo_Service manages.
	 *
	 * @return array post_type slug => tab label
	 */
	public static function get_tabs() {
		$types   = Mcp_Seo_Service::managed_post_types();
		$ordered = array();

		if ( isset( $types['post'] ) ) {
			$ordered['post'] = 'Blog Posts';
			unset( $types['post'] );
		}
		if ( isset( $types['page'] ) ) {
			$ordered['page'] = 'Pages';
			unset( $types['page'] );
		}
		foreach ( $types as $slug => $post_type_object ) {
			$ordered[ $slug ] = $post_type_object->labels->name;
		}

		return $ordered;
	}

	/**
	 * Turns a Mcp_Seo_Service::get_for_post() array into per-column badge
	 * statuses for the grid. Statuses: 'set', 'missing', 'too_long' for
	 * text fields; 'yes'/'no' for the robots-derived flags.
	 *
	 * @param array $seo Mcp_Seo_Service::get_for_post() result.
	 * @return array column key => status string
	 */
	public static function compute_badges( $seo ) {
		return array(
			'meta_title' => self::text_status( $seo['title'], self::TITLE_MAX_LEN ),
			'meta_desc'  => self::text_status( $seo['description'], self::DESCRIPTION_MAX_LEN ),
			'keywords'   => '' === $seo['keywords'] ? 'missing' : 'set',
			'canonical'  => '' === $seo['canonical_url'] ? 'missing' : 'set',
			'noindex'    => in_array( 'noindex', $seo['robots'], true ) ? 'yes' : 'no',
			'nofollow'   => in_array( 'nofollow', $seo['robots'], true ) ? 'yes' : 'no',
		);
	}

	private static function text_status( $value, $max_len ) {
		if ( '' === $value ) {
			return 'missing';
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		return $length > $max_len ? 'too_long' : 'set';
	}

	/**
	 * Renders the ajax-loaded detail panel for a single post.
	 *
	 * @param int $post_id
	 * @return string HTML
	 */
	public static function render_detail_panel( $post_id ) {
		$seo   = Mcp_Seo_Service::get_for_post( $post_id );
		$audit = Mcp_Seo_Service::audit_post( $post_id );

		if ( is_wp_error( $seo ) || is_wp_error( $audit ) ) {
			return '<p>' . esc_html( is_wp_error( $seo ) ? $seo->get_error_message() : $audit->get_error_message() ) . '</p>';
		}

		ob_start();
		require MCP_CONNECTOR_DIR . 'admin/views/partial-seo-dashboard-detail.php';
		return ob_get_clean();
	}
}
