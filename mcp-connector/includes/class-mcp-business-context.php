<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the site's editorial and business context in a private, revision-enabled
 * post. Using a post rather than a plain option gives site owners an audit trail
 * without exposing the context on the public site or through the core REST API.
 */
class Mcp_Business_Context {

	const POST_TYPE      = 'mcp_business_ctx';
	const POST_ID_OPTION = 'mcp_business_context_post_id';

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => 'Business Context',
					'singular_name' => 'Business Context',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor', 'revisions', 'author' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Field definitions are shared by the admin form, sanitization, and MCP output.
	 */
	public static function field_definitions() {
		return array(
			'business_name'        => array( 'label' => 'Public business name', 'type' => 'text', 'section' => 'identity' ),
			'legal_name'           => array( 'label' => 'Legal name', 'type' => 'text', 'section' => 'identity' ),
			'website_url'          => array( 'label' => 'Canonical website URL', 'type' => 'url', 'section' => 'identity' ),
			'business_description' => array( 'label' => 'Business description', 'type' => 'textarea', 'section' => 'identity' ),
			'organization_type'    => array( 'label' => 'Schema.org organization type', 'type' => 'text', 'section' => 'identity', 'placeholder' => 'Organization, LocalBusiness, ProfessionalService…' ),
			'founding_date'        => array( 'label' => 'Founding date', 'type' => 'text', 'section' => 'identity', 'placeholder' => 'YYYY or YYYY-MM-DD' ),
			'logo_url'             => array( 'label' => 'Logo URL', 'type' => 'url', 'section' => 'identity' ),
			'email'                => array( 'label' => 'Public contact email', 'type' => 'email', 'section' => 'contact' ),
			'telephone'            => array( 'label' => 'Public telephone', 'type' => 'text', 'section' => 'contact' ),
			'contact_type'         => array( 'label' => 'Contact type', 'type' => 'text', 'section' => 'contact', 'placeholder' => 'customer service, sales, reservations…' ),
			'address'              => array( 'label' => 'Public postal address', 'type' => 'textarea', 'section' => 'contact' ),
			'address_locality'     => array( 'label' => 'City / locality', 'type' => 'text', 'section' => 'contact' ),
			'address_region'       => array( 'label' => 'State / region', 'type' => 'text', 'section' => 'contact' ),
			'postal_code'          => array( 'label' => 'Postal code', 'type' => 'text', 'section' => 'contact' ),
			'address_country'      => array( 'label' => 'Country code', 'type' => 'text', 'section' => 'contact', 'placeholder' => 'IN, US, GB…' ),
			'opening_hours'        => array( 'label' => 'Opening hours', 'type' => 'lines', 'section' => 'contact', 'placeholder' => 'Mo-Fr 09:00-17:00' ),
			'price_range'          => array( 'label' => 'Public price range', 'type' => 'text', 'section' => 'contact', 'placeholder' => '$$, ₹₹, or a truthful short range' ),
			'service_areas'        => array( 'label' => 'Locations / service areas', 'type' => 'lines', 'section' => 'contact' ),
			'languages'            => array( 'label' => 'Languages', 'type' => 'lines', 'section' => 'contact' ),
			'social_profiles'      => array( 'label' => 'Official profile URLs', 'type' => 'url_lines', 'section' => 'contact' ),
			'products_services'    => array( 'label' => 'Products and services', 'type' => 'textarea', 'section' => 'positioning' ),
			'target_audiences'     => array( 'label' => 'Target audiences', 'type' => 'textarea', 'section' => 'positioning' ),
			'differentiators'      => array( 'label' => 'Differentiators and proof points', 'type' => 'textarea', 'section' => 'positioning' ),
			'competitors'          => array( 'label' => 'Competitors / comparison set', 'type' => 'lines', 'section' => 'positioning' ),
			'brand_voice'          => array( 'label' => 'Brand voice and writing style', 'type' => 'textarea', 'section' => 'editorial' ),
			'author_guidelines'    => array( 'label' => 'Author and editorial guidelines', 'type' => 'textarea', 'section' => 'editorial' ),
			'preferred_ctas'       => array( 'label' => 'Preferred calls to action', 'type' => 'lines', 'section' => 'editorial' ),
			'approved_claims'      => array( 'label' => 'Approved claims', 'type' => 'textarea', 'section' => 'editorial' ),
			'prohibited_claims'    => array( 'label' => 'Claims and language to avoid', 'type' => 'textarea', 'section' => 'editorial' ),
			'compliance_notes'     => array( 'label' => 'Compliance notes / required disclaimers', 'type' => 'textarea', 'section' => 'editorial' ),
			'primary_topics'       => array( 'label' => 'Priority topics and search themes', 'type' => 'lines', 'section' => 'seo' ),
			'priority_urls'        => array( 'label' => 'Priority internal URLs', 'type' => 'url_lines', 'section' => 'seo' ),
		);
	}

	public static function get() {
		$post = self::get_context_post();
		$data = array();

		if ( $post && $post->post_content ) {
			$decoded = json_decode( $post->post_content, true );
			if ( is_array( $decoded ) ) {
				$data = $decoded;
			}
		}

		foreach ( self::field_definitions() as $key => $definition ) {
			if ( ! array_key_exists( $key, $data ) ) {
				$data[ $key ] = in_array( $definition['type'], array( 'lines', 'url_lines' ), true ) ? array() : '';
			}
		}

		$revisions = $post ? wp_get_post_revisions( $post->ID, array( 'posts_per_page' => 1 ) ) : array();
		$revision  = $revisions ? reset( $revisions ) : null;

		return array(
			'context'      => $data,
			'configured'   => '' !== $data['business_name'],
			'context_id'   => $post ? (int) $post->ID : null,
			'latest_revision_id' => $revision ? (int) $revision->ID : null,
			'last_updated' => $post ? get_post_modified_time( 'c', true, $post ) : null,
		);
	}

	/**
	 * @return array|WP_Error
	 */
	public static function update( $input, $user_id ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'mcp_invalid_context', 'Business context must be an object.' );
		}

		$current   = self::get();
		$sanitized = $current['context'];

		foreach ( self::field_definitions() as $key => $definition ) {
			if ( array_key_exists( $key, $input ) ) {
				$sanitized[ $key ] = self::sanitize_value( $input[ $key ], $definition['type'] );
			}
		}

		$post    = self::get_context_post();
		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'private',
			'post_title'   => 'Site Business Context',
			'post_content' => wp_json_encode( $sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'post_author'  => absint( $user_id ),
		);

		if ( $post ) {
			$postarr['ID'] = $post->ID;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_option( self::POST_ID_OPTION, (int) $result, false );
		return self::get();
	}

	private static function get_context_post() {
		$post_id = absint( get_option( self::POST_ID_OPTION ) );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( $post && self::POST_TYPE === $post->post_type ) {
			return $post;
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		return $posts ? $posts[0] : null;
	}

	private static function sanitize_value( $value, $type ) {
		if ( in_array( $type, array( 'lines', 'url_lines' ), true ) ) {
			$values = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
			$values = array_filter( array_map( 'trim', $values ) );
			if ( 'url_lines' === $type ) {
				return array_values( array_filter( array_map( 'esc_url_raw', $values ) ) );
			}
			return array_values( array_map( 'sanitize_text_field', $values ) );
		}

		if ( 'url' === $type ) {
			return esc_url_raw( $value );
		}
		if ( 'email' === $type ) {
			return sanitize_email( $value );
		}
		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( $value );
		}

		return sanitize_text_field( $value );
	}
}
