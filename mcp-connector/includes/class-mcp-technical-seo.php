<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide technical SEO settings: robots.txt override, llms.txt, and a
 * site-wide JSON-LD block. Backs both the "Technical SEO" admin tab (Settings
 * API) and the get_technical_seo/update_technical_seo MCP tools — both paths
 * share the same option keys and sanitize/validate logic below.
 */
class Mcp_Technical_Seo {

	const OPTION_GROUP  = 'mcp_technical_seo';
	const OPT_ROBOTS    = 'mcp_seo_robots_txt';
	const OPT_LLMS      = 'mcp_seo_llms_txt';
	const OPT_JSONLD    = 'mcp_seo_jsonld';
	const QUERY_VAR     = 'mcp_llms_txt';

	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'init', array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_llms_txt' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'bypass_canonical_redirect' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots_txt' ), 10, 2 );
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPT_ROBOTS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_robots_txt' ),
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::OPT_LLMS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_llms_txt' ),
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::OPT_JSONLD,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_jsonld' ),
				'default'           => '',
			)
		);
	}

	/**
	 * @return array{robots_txt:string,llms_txt:string,jsonld:string,llms_txt_url:string,robots_txt_warnings:string[]}
	 */
	public static function get_all() {
		return array(
			'robots_txt'          => (string) get_option( self::OPT_ROBOTS, '' ),
			'llms_txt'            => (string) get_option( self::OPT_LLMS, '' ),
			'jsonld'              => (string) get_option( self::OPT_JSONLD, '' ),
			'llms_txt_url'        => home_url( '/llms.txt' ),
			'robots_txt_warnings' => self::robots_txt_warnings(),
		);
	}

	/**
	 * Updates any subset of the three fields. Omitted keys are preserved.
	 * Shared by the Settings API form and the update_technical_seo MCP tool
	 * so both paths run identical validation.
	 *
	 * @return array|WP_Error
	 */
	public static function update( $values ) {
		if ( ! is_array( $values ) ) {
			return new WP_Error( 'mcp_invalid_param', 'Technical SEO settings must be an object.' );
		}

		if ( array_key_exists( 'robots_txt', $values ) ) {
			update_option( self::OPT_ROBOTS, self::sanitize_robots_txt( (string) $values['robots_txt'] ) );
		}

		if ( array_key_exists( 'llms_txt', $values ) ) {
			update_option( self::OPT_LLMS, self::sanitize_llms_txt( (string) $values['llms_txt'] ) );
		}

		if ( array_key_exists( 'jsonld', $values ) ) {
			$jsonld = $values['jsonld'];
			$input  = is_string( $jsonld ) ? $jsonld : wp_json_encode( $jsonld );
			$result = self::validate_jsonld( $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			update_option( self::OPT_JSONLD, $result );
		}

		return self::get_all();
	}

	public static function sanitize_robots_txt( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
		return trim( wp_strip_all_tags( $value ) );
	}

	/**
	 * llms.txt is Markdown written by a manage_options user and served as
	 * text/plain — running sanitize_textarea_field() over it would mangle
	 * legitimate Markdown (backticks, angle brackets in code fences) for no
	 * security benefit, so only normalize encoding/line endings.
	 */
	public static function sanitize_llms_txt( $value ) {
		$value = wp_check_invalid_utf8( (string) $value );
		return str_replace( array( "\r\n", "\r" ), "\n", $value );
	}

	/**
	 * register_setting() sanitize callback: on invalid JSON, records a
	 * settings error and returns the previously stored value so a typo can't
	 * blank out a live schema.
	 */
	public static function sanitize_jsonld( $value ) {
		$result = self::validate_jsonld( (string) $value );
		if ( is_wp_error( $result ) ) {
			add_settings_error( self::OPTION_GROUP, 'mcp_invalid_jsonld', 'JSON-LD was not saved: ' . $result->get_error_message() );
			return get_option( self::OPT_JSONLD, '' );
		}
		return $result;
	}

	/**
	 * @return string|WP_Error Normalized JSON string, or WP_Error on invalid input.
	 */
	private static function validate_jsonld( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$normalized = Mcp_Seo_Service::normalize_schema( $value );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		return wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES );
	}

	/**
	 * @return array|null Decoded JSON-LD, or null when unset.
	 */
	public static function get_jsonld_decoded() {
		$raw = get_option( self::OPT_JSONLD, '' );
		if ( '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	public static function filter_robots_txt( $output, $public ) {
		$custom = get_option( self::OPT_ROBOTS, '' );
		return '' !== $custom ? $custom : $output;
	}

	public static function register_rewrite() {
		add_rewrite_rule( '^llms?\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Without this, "mcp_llms_txt" isn't in WP::$public_query_vars and
	 * parse_request() silently drops it — the request then falls through to
	 * whatever an empty query resolves to (the site's front page).
	 */
	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Core's redirect_canonical() doesn't know about this route and would
	 * 301 /llms.txt to /llms.txt/ (trailing-slashed permalinks) before
	 * maybe_serve_llms_txt() ever runs — the same reason core explicitly
	 * exempts is_robots()/is_favicon() from canonical redirection.
	 */
	public static function bypass_canonical_redirect( $redirect_url ) {
		return get_query_var( self::QUERY_VAR ) ? false : $redirect_url;
	}

	public static function maybe_serve_llms_txt() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$content = get_option( self::OPT_LLMS, '' );
		if ( '' === $content ) {
			return; // Fall through to a normal 404 rather than serving a stub.
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text response, not HTML.
		exit;
	}

	/**
	 * Conditions under which a saved robots.txt override silently does
	 * nothing. Surfaced in the admin tab and in the get_technical_seo MCP tool.
	 *
	 * @return string[]
	 */
	public static function robots_txt_warnings() {
		$warnings = array();

		if ( file_exists( ABSPATH . 'robots.txt' ) ) {
			$warnings[] = 'A physical robots.txt file exists at the site root and takes precedence over this setting — delete it for the saved content to take effect.';
		}

		if ( ! get_option( 'blog_public' ) ) {
			$warnings[] = 'Settings → Reading has "Discourage search engines from indexing this site" enabled, so WordPress serves "Disallow: /" regardless of this setting.';
		}

		return $warnings;
	}
}
