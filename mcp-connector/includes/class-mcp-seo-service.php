<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connector-owned SEO metadata and JSON-LD output.
 *
 * Native output is deliberately disabled when a known SEO plugin is active. This
 * prevents duplicate title, canonical, robots, and schema markup. Provider-specific
 * write adapters can be added later without changing the MCP tool contract.
 */
class Mcp_Seo_Service {

	const META_TITLE       = '_mcp_seo_title';
	const META_DESCRIPTION = '_mcp_seo_description';
	const META_CANONICAL   = '_mcp_seo_canonical';
	const META_ROBOTS      = '_mcp_seo_robots';
	const META_SCHEMA      = '_mcp_seo_schema';
	const META_FOCUS_TOPIC = '_mcp_seo_focus_topic';
	const META_KEYWORDS    = '_mcp_seo_keywords';

	/**
	 * Public, non-attachment post types the SEO service manages. Always
	 * includes 'post' and 'page'; also picks up any public CPT registered
	 * by a theme or plugin (e.g. Portfolio, Products).
	 *
	 * @return array post_type slug => WP_Post_Type
	 */
	public static function managed_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
	}

	public static function register() {
		foreach ( array_keys( self::managed_post_types() ) as $post_type ) {
			foreach ( self::meta_definitions() as $key => $definition ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => $definition['sanitize'],
						'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}

		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ), 20 );
		add_action( 'wp', array( __CLASS__, 'maybe_disable_core_canonical' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_head_metadata' ), 2 );
	}

	public static function detect_provider() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rank-math';
		}
		if ( defined( 'AIOSEO_VERSION' ) || defined( 'AIOSEO_PHP_VERSION_DIR' ) ) {
			return 'aioseo';
		}
		return 'connector-native';
	}

	public static function native_output_enabled() {
		$provider = self::detect_provider();
		$enabled  = 'connector-native' === $provider;
		return (bool) apply_filters( 'mcp_connector_native_seo_output_enabled', $enabled, $provider );
	}

	/** @return array|WP_Error */
	public static function get_for_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || ! in_array( $post->post_type, array_keys( self::managed_post_types() ), true ) ) {
			return new WP_Error( 'mcp_not_found', 'Post or page not found.' );
		}

		$schema_json = get_post_meta( $post->ID, self::META_SCHEMA, true );
		$schema      = $schema_json ? json_decode( $schema_json, true ) : null;

		return array(
			'post_id'               => (int) $post->ID,
			'post_type'             => $post->post_type,
			'permalink'             => get_permalink( $post ),
			'provider'              => self::detect_provider(),
			'native_output_enabled' => self::native_output_enabled(),
			'title'                 => (string) get_post_meta( $post->ID, self::META_TITLE, true ),
			'description'           => (string) get_post_meta( $post->ID, self::META_DESCRIPTION, true ),
			'canonical_url'         => (string) get_post_meta( $post->ID, self::META_CANONICAL, true ),
			'robots'                => self::decode_robots( get_post_meta( $post->ID, self::META_ROBOTS, true ) ),
			'focus_topic'           => (string) get_post_meta( $post->ID, self::META_FOCUS_TOPIC, true ),
			'keywords'              => (string) get_post_meta( $post->ID, self::META_KEYWORDS, true ),
			'schema'                => is_array( $schema ) ? $schema : null,
			'warning'               => self::native_output_enabled() ? null : 'A supported SEO plugin is active. Connector-native markup is suppressed to prevent duplicates.',
		);
	}

	/** @return array|WP_Error */
	public static function update_for_post( $post_id, $values ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || ! in_array( $post->post_type, array_keys( self::managed_post_types() ), true ) ) {
			return new WP_Error( 'mcp_not_found', 'Post or page not found.' );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to edit SEO metadata for this content.' );
		}
		if ( ! is_array( $values ) ) {
			return new WP_Error( 'mcp_invalid_param', 'SEO metadata must be an object.' );
		}
		if ( ! self::native_output_enabled() ) {
			return new WP_Error( 'mcp_seo_provider_conflict', 'A supported SEO plugin is active. Connector-native SEO writes are disabled to prevent duplicate or conflicting markup.' );
		}

		$map = array(
			'title'         => self::META_TITLE,
			'description'   => self::META_DESCRIPTION,
			'canonical_url' => self::META_CANONICAL,
			'focus_topic'   => self::META_FOCUS_TOPIC,
			'keywords'      => self::META_KEYWORDS,
		);

		foreach ( $map as $input_key => $meta_key ) {
			if ( ! array_key_exists( $input_key, $values ) ) {
				continue;
			}
			$value = 'canonical_url' === $input_key ? esc_url_raw( $values[ $input_key ] ) : sanitize_text_field( $values[ $input_key ] );
			self::set_or_delete_meta( $post->ID, $meta_key, $value );
		}

		if ( array_key_exists( 'robots', $values ) ) {
			$robots = self::sanitize_robots( $values['robots'] );
			self::set_or_delete_meta( $post->ID, self::META_ROBOTS, $robots ? wp_json_encode( $robots ) : '' );
		}

		if ( array_key_exists( 'schema', $values ) ) {
			$schema = self::normalize_schema( $values['schema'] );
			if ( is_wp_error( $schema ) ) {
				return $schema;
			}
			self::set_or_delete_meta( $post->ID, self::META_SCHEMA, $schema ? wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) : '' );
		}

		return self::get_for_post( $post->ID );
	}

	/** @return array|WP_Error */
	public static function audit_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || ! in_array( $post->post_type, array_keys( self::managed_post_types() ), true ) ) {
			return new WP_Error( 'mcp_not_found', 'Post or page not found.' );
		}
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'mcp_forbidden', 'You do not have permission to audit this content.' );
		}

		$seo         = self::get_for_post( $post->ID );
		$plain       = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
		$words       = '' === $plain ? array() : preg_split( '/\s+/u', $plain );
		$h1_count    = preg_match_all( '/<h1\b/i', $post->post_content );
		$links_count = preg_match_all( '/<a\b[^>]*\bhref=/i', $post->post_content );
		$image_count = preg_match_all( '/<img\b/i', $post->post_content );
		$missing_alt = preg_match_all( '/<img\b(?![^>]*\balt\s*=)[^>]*>/i', $post->post_content );
		$issues      = array();

		if ( self::native_output_enabled() && '' === $seo['title'] ) {
			$issues[] = array( 'code' => 'missing_seo_title', 'severity' => 'warning', 'message' => 'No connector-managed SEO title is set.' );
		}
		if ( self::native_output_enabled() && '' === $seo['description'] ) {
			$issues[] = array( 'code' => 'missing_meta_description', 'severity' => 'warning', 'message' => 'No connector-managed meta description is set.' );
		}
		if ( $h1_count > 1 ) {
			$issues[] = array( 'code' => 'multiple_h1', 'severity' => 'warning', 'message' => 'The content contains more than one H1 heading.' );
		}
		if ( $missing_alt > 0 ) {
			$issues[] = array( 'code' => 'missing_image_alt', 'severity' => 'warning', 'message' => 'One or more content images do not have an alt attribute.' );
		}

		return array(
			'post_id'                => (int) $post->ID,
			'url'                    => get_permalink( $post ),
			'word_count'             => count( $words ),
			'content_h1_count'       => (int) $h1_count,
			'internal_and_external_links' => (int) $links_count,
			'image_count'            => (int) $image_count,
			'images_missing_alt'     => (int) $missing_alt,
			'seo_title_length'       => self::text_length( $seo['title'] ),
			'meta_description_length'=> self::text_length( $seo['description'] ),
			'has_custom_schema'      => ! empty( $seo['schema'] ),
			'issues'                 => $issues,
			'note'                   => self::native_output_enabled() ? 'These are deterministic checks, not a ranking score. Review recommendations against search intent and visible page content.' : 'A supported SEO plugin is active, so its private metadata is not included in this audit. Content-level checks are still reported.',
		);
	}

	public static function filter_document_title( $title ) {
		if ( ! self::native_output_enabled() || ! is_singular( array_keys( self::managed_post_types() ) ) ) {
			return $title;
		}
		$custom = get_post_meta( get_queried_object_id(), self::META_TITLE, true );
		return $custom ? $custom : $title;
	}

	public static function filter_robots( $robots ) {
		if ( ! self::native_output_enabled() || ! is_singular( array_keys( self::managed_post_types() ) ) ) {
			return $robots;
		}
		$custom = self::decode_robots( get_post_meta( get_queried_object_id(), self::META_ROBOTS, true ) );
		foreach ( $custom as $directive ) {
			$robots[ $directive ] = true;
		}
		return $robots;
	}

	public static function maybe_disable_core_canonical() {
		if ( ! self::native_output_enabled() || ! is_singular( array_keys( self::managed_post_types() ) ) ) {
			return;
		}
		if ( get_post_meta( get_queried_object_id(), self::META_CANONICAL, true ) ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}
	}

	public static function render_head_metadata() {
		if ( ! self::native_output_enabled() ) {
			return;
		}

		$sitewide_jsonld = Mcp_Technical_Seo::get_jsonld_decoded();
		if ( $sitewide_jsonld ) {
			self::print_schema( $sitewide_jsonld );
		}

		if ( is_singular( array_keys( self::managed_post_types() ) ) ) {
			$post_id     = get_queried_object_id();
			$title       = get_post_meta( $post_id, self::META_TITLE, true );
			$description = get_post_meta( $post_id, self::META_DESCRIPTION, true );
			$canonical   = get_post_meta( $post_id, self::META_CANONICAL, true );
			$schema      = get_post_meta( $post_id, self::META_SCHEMA, true );
			$share_url   = $canonical ? $canonical : get_permalink( $post_id );

			if ( $description ) {
				echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
			}
			if ( $canonical ) {
				echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
			}
			if ( $schema ) {
				self::print_schema( json_decode( $schema, true ) );
			}

			echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '" />' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $title ? $title : get_the_title( $post_id ) ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $share_url ) . '" />' . "\n";
			if ( $description ) {
				echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
			}
			echo '<meta name="twitter:card" content="summary" />' . "\n";
		}
	}

	private static function meta_definitions() {
		return array(
			self::META_TITLE       => array( 'sanitize' => 'sanitize_text_field' ),
			self::META_DESCRIPTION => array( 'sanitize' => 'sanitize_text_field' ),
			self::META_CANONICAL   => array( 'sanitize' => 'esc_url_raw' ),
			self::META_ROBOTS      => array( 'sanitize' => 'sanitize_text_field' ),
			self::META_SCHEMA      => array( 'sanitize' => 'sanitize_text_field' ),
			self::META_FOCUS_TOPIC => array( 'sanitize' => 'sanitize_text_field' ),
			self::META_KEYWORDS    => array( 'sanitize' => 'sanitize_text_field' ),
		);
	}

	private static function sanitize_robots( $robots ) {
		$allowed = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
		$robots  = is_array( $robots ) ? $robots : preg_split( '/[\s,]+/', (string) $robots );
		return array_values( array_intersect( $allowed, array_map( 'sanitize_key', $robots ) ) );
	}

	private static function decode_robots( $value ) {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? self::sanitize_robots( $decoded ) : array();
	}

	/**
	 * Validates and deep-sanitizes a Schema.org JSON-LD payload. Shared by
	 * per-post schema (update_seo_metadata) and the site-wide JSON-LD field
	 * (Mcp_Technical_Seo) so both paths enforce the same rules.
	 *
	 * @return array|WP_Error
	 */
	public static function normalize_schema( $schema ) {
		if ( '' === $schema || null === $schema || array() === $schema ) {
			return null;
		}
		if ( is_string( $schema ) ) {
			$schema = json_decode( $schema, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'mcp_invalid_schema', 'schema must be valid JSON.' );
			}
		}
		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'mcp_invalid_schema', 'schema must be a JSON object.' );
		}
		if ( isset( $schema['@context'] ) && 'https://schema.org' !== $schema['@context'] ) {
			return new WP_Error( 'mcp_invalid_schema', 'schema @context must be https://schema.org.' );
		}
		if ( ! isset( $schema['@context'] ) ) {
			$schema['@context'] = 'https://schema.org';
		}
		if ( empty( $schema['@type'] ) && empty( $schema['@graph'] ) ) {
			return new WP_Error( 'mcp_invalid_schema', 'schema must contain @type or @graph.' );
		}
		return self::sanitize_schema_node( $schema );
	}

	private static function sanitize_schema_node( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $child ) {
				$clean[ is_int( $key ) ? $key : sanitize_text_field( $key ) ] = self::sanitize_schema_node( $child );
			}
			return $clean;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		return sanitize_text_field( (string) $value );
	}

	private static function set_or_delete_meta( $post_id, $key, $value ) {
		if ( '' === $value || null === $value || array() === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}

	public static function print_schema( $schema ) {
		if ( ! is_array( $schema ) || ! $schema ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
