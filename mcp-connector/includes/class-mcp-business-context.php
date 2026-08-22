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
	 * Labels for the pre-1.2.0 structured field set. Used only to render a
	 * readable fallback the first time a site with old JSON-shaped context is
	 * read after upgrading — the plugin no longer collects or stores context
	 * this way.
	 */
	private static function legacy_field_labels() {
		return array(
			'business_name'        => 'Public business name',
			'legal_name'           => 'Legal name',
			'website_url'          => 'Canonical website URL',
			'business_description' => 'Business description',
			'organization_type'    => 'Schema.org organization type',
			'founding_date'        => 'Founding date',
			'logo_url'             => 'Logo URL',
			'email'                => 'Public contact email',
			'telephone'            => 'Public telephone',
			'contact_type'         => 'Contact type',
			'address'              => 'Public postal address',
			'address_locality'     => 'City / locality',
			'address_region'       => 'State / region',
			'postal_code'          => 'Postal code',
			'address_country'      => 'Country code',
			'opening_hours'        => 'Opening hours',
			'price_range'          => 'Public price range',
			'service_areas'        => 'Locations / service areas',
			'languages'            => 'Languages',
			'social_profiles'      => 'Official profile URLs',
			'products_services'    => 'Products and services',
			'target_audiences'     => 'Target audiences',
			'differentiators'      => 'Differentiators and proof points',
			'competitors'          => 'Competitors / comparison set',
			'brand_voice'          => 'Brand voice and writing style',
			'author_guidelines'    => 'Author and editorial guidelines',
			'preferred_ctas'       => 'Preferred calls to action',
			'approved_claims'      => 'Approved claims',
			'prohibited_claims'    => 'Claims and language to avoid',
			'compliance_notes'     => 'Compliance notes / required disclaimers',
			'primary_topics'       => 'Priority topics and search themes',
			'priority_urls'        => 'Priority internal URLs',
		);
	}

	/**
	 * Flattens the old 29-key JSON shape into readable "Label: value" lines.
	 * This does not write anything back — the flattened text only persists
	 * once the owner saves the context again through the new textarea.
	 */
	private static function flatten_legacy_context( $decoded ) {
		$labels = self::legacy_field_labels();
		$lines  = array();

		foreach ( $labels as $key => $label ) {
			if ( ! array_key_exists( $key, $decoded ) ) {
				continue;
			}
			$value = $decoded[ $key ];
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( $value ) );
			}
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$lines[] = $label . ': ' . $value;
		}

		return implode( "\n", $lines );
	}

	public static function get() {
		$post = self::get_context_post();
		$text = '';

		if ( $post && '' !== trim( (string) $post->post_content ) ) {
			$decoded = json_decode( $post->post_content, true );
			if ( is_array( $decoded ) && JSON_ERROR_NONE === json_last_error() ) {
				// Pre-1.2.0 structured context. Present it as readable text
				// without rewriting the stored post.
				$text = self::flatten_legacy_context( $decoded );
			} else {
				$text = $post->post_content;
			}
		}

		$revisions = $post ? wp_get_post_revisions( $post->ID, array( 'posts_per_page' => 1 ) ) : array();
		$revision  = $revisions ? reset( $revisions ) : null;

		return array(
			'context_text'       => $text,
			'configured'         => '' !== trim( $text ),
			'context_id'         => $post ? (int) $post->ID : null,
			'latest_revision_id' => $revision ? (int) $revision->ID : null,
			'last_updated'       => $post ? get_post_modified_time( 'c', true, $post ) : null,
		);
	}

	/**
	 * @return array|WP_Error
	 */
	public static function update( $text, $user_id ) {
		if ( ! is_string( $text ) ) {
			return new WP_Error( 'mcp_invalid_context', 'Business context must be a string.' );
		}

		$sanitized = sanitize_textarea_field( $text );

		$post    = self::get_context_post();
		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'private',
			'post_title'   => 'Site Business Context',
			'post_content' => $sanitized,
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
}
