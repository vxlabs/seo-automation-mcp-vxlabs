<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Mcp_Seo_Dashboard_List_Table extends WP_List_Table {

	private $post_type;
	private $seo_cache = array();

	public function __construct( $post_type ) {
		$this->post_type = $post_type;

		parent::__construct(
			array(
				'singular' => 'seo_row',
				'plural'   => 'seo_rows',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'title'      => 'Title',
			'meta_title' => 'Meta Title',
			'meta_desc'  => 'Meta Desc',
			'keywords'   => 'Keywords',
			'canonical'  => 'Canonical',
			'noindex'    => 'Noindex',
			'nofollow'   => 'Nofollow',
			'actions'    => 'Actions',
		);
	}

	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$query = new WP_Query(
			array(
				'post_type'      => $this->post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'orderby'        => 'title',
				'order'          => 'ASC',
				's'              => $search,
			)
		);

		$this->items           = $query->posts;
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
			)
		);
	}

	public function column_title( $item ) {
		return '<a href="' . esc_url( get_edit_post_link( $item->ID ) ) . '">' . esc_html( get_the_title( $item ) ) . '</a>';
	}

	public function column_actions( $item ) {
		$actions = '<a href="' . esc_url( get_edit_post_link( $item->ID ) ) . '">Edit Post</a> | ';

		if ( Mcp_Seo_Service::native_output_enabled() ) {
			$actions .= '<a href="#" class="mcp-seo-edit-open" data-post-id="' . (int) $item->ID . '">Edit SEO</a> | ';
		}

		$actions .= '<a href="#" class="mcp-seo-toggle-detail" data-post-id="' . (int) $item->ID . '">Details</a>';

		return $actions;
	}

	public function column_default( $item, $column_name ) {
		$seo = $this->get_seo_for_post( $item->ID );
		if ( is_wp_error( $seo ) ) {
			return '';
		}

		$badges = Mcp_Seo_Dashboard::compute_badges( $seo );

		if ( ! array_key_exists( $column_name, $badges ) ) {
			return '';
		}

		return self::render_badge( $badges[ $column_name ] );
	}

	/**
	 * column_default() is called once per column per row, but Mcp_Seo_Service::get_for_post()
	 * only needs to run once per row — memoize it for the life of this list table instance.
	 */
	private function get_seo_for_post( $post_id ) {
		if ( ! array_key_exists( $post_id, $this->seo_cache ) ) {
			$this->seo_cache[ $post_id ] = Mcp_Seo_Service::get_for_post( $post_id );
		}
		return $this->seo_cache[ $post_id ];
	}

	private static function render_badge( $status ) {
		$labels = array(
			'set'      => 'Set',
			'missing'  => 'Missing',
			'too_long' => 'Too Long',
			'yes'      => 'Yes',
			'no'       => 'No',
		);
		$css_class = array(
			'set'      => 'mcp-seo-badge-set',
			'missing'  => 'mcp-seo-badge-missing',
			'too_long' => 'mcp-seo-badge-too-long',
			'yes'      => 'mcp-seo-badge-too-long',
			'no'       => 'mcp-seo-badge-set',
		);

		return '<span class="mcp-seo-badge ' . esc_attr( $css_class[ $status ] ) . '">' . esc_html( $labels[ $status ] ) . '</span>';
	}

	public function no_items() {
		echo 'No content found for this content type.';
	}
}
