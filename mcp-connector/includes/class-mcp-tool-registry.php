<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Tool_Registry {

	const DEFAULT_PER_PAGE = 20;
	const MAX_PER_PAGE     = 100;

	private static $tools = null;

	/**
	 * name => [class, method, description, inputSchema, annotations]
	 */
	private static function definitions() {
		if ( null !== self::$tools ) {
			return self::$tools;
		}

		self::$tools = array(
			'get_business_context' => array(
				'handler'     => array( 'Mcp_Tool_Seo', 'get_business_context' ),
				'description' => 'Read the site owner\'s business facts, audiences, positioning, editorial guardrails, and SEO priorities before writing or optimizing content.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'update_business_context' => array(
				'handler'     => array( 'Mcp_Tool_Seo', 'update_business_context' ),
				'description' => 'Update selected business-context fields. Requires a key bound to an administrator. Omitted fields are preserved.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'context' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
					'required'             => array( 'context' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_seo_metadata' => array(
				'handler'     => array( 'Mcp_Tool_Seo', 'get_seo_metadata' ),
				'description' => 'Get connector-managed SEO metadata, robots directives, provider status, and JSON-LD for a post or page.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'update_seo_metadata' => array(
				'handler'     => array( 'Mcp_Tool_Seo', 'update_seo_metadata' ),
				'description' => 'Update SEO title, meta description, canonical URL, robots directives, focus topic, or Schema.org JSON-LD for a post or page. Omitted fields are preserved.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'            => array( 'type' => 'integer', 'minimum' => 1 ),
						'title'         => array( 'type' => 'string', 'maxLength' => 255 ),
						'description'   => array( 'type' => 'string', 'maxLength' => 500 ),
						'canonical_url' => array( 'type' => 'string', 'format' => 'uri' ),
						'focus_topic'   => array( 'type' => 'string', 'maxLength' => 255, 'description' => 'Internal editorial target; this is not emitted as a meta-keywords tag.' ),
						'robots'        => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' ) ), 'uniqueItems' => true ),
						'schema'        => array( 'type' => array( 'object', 'null' ), 'description' => 'A Schema.org object containing @type, or an @graph. Pass null to remove it.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'audit_content_seo' => array(
				'handler'     => array( 'Mcp_Tool_Seo', 'audit_content_seo' ),
				'description' => 'Run deterministic on-page checks for a post or page. Results are diagnostics, not a search ranking score.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'list_content_revisions' => array(
				'handler'     => array( 'Mcp_Tool_Seo', 'list_content_revisions' ),
				'description' => 'List saved WordPress revisions for a post or page before or after making an optimization.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE ),
						'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_content_revision' => array(
				'handler'     => array( 'Mcp_Tool_Seo', 'get_content_revision' ),
				'description' => 'Read a specific saved revision of a post or page for comparison or recovery planning.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer', 'minimum' => 1 ),
						'revision_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required'             => array( 'id', 'revision_id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'list_media' => array(
				'handler'     => array( 'Mcp_Tool_Media', 'list_media' ),
				'description' => 'List media-library items and their alt text, dimensions, type, and attachment URL.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'    => array( 'type' => 'string' ),
						'mime_type' => array( 'type' => 'string', 'description' => 'For example image or image/jpeg.' ),
						'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE ),
						'page'      => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_media' => array(
				'handler'     => array( 'Mcp_Tool_Media', 'get_media' ),
				'description' => 'Get one media-library item including its alt text and dimensions.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'update_media_alt_text' => array(
				'handler'     => array( 'Mcp_Tool_Media', 'update_media_alt_text' ),
				'description' => 'Set accurate, concise alt text on an image attachment. Use an empty string only when the image is decorative.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
						'alt_text' => array( 'type' => 'string', 'maxLength' => 500 ),
					),
					'required'             => array( 'id', 'alt_text' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'list_posts'      => array(
				'handler'      => array( 'Mcp_Tool_Posts', 'list_posts' ),
				'description'  => 'List blog posts, optionally filtered by status or search term.',
				'inputSchema'  => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private', 'any' ) ),
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE ),
						'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'additionalProperties' => false,
				),
				'annotations'  => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_post'        => array(
				'handler'     => array( 'Mcp_Tool_Posts', 'get_post' ),
				'description' => 'Get a single blog post by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'required'   => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'create_post'     => array(
				'handler'     => array( 'Mcp_Tool_Posts', 'create_post' ),
				'description' => 'Create a new blog post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ), 'description' => 'Defaults to draft.' ),
						'slug'       => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
					'required'   => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false ),
			),
			'update_post'     => array(
				'handler'     => array( 'Mcp_Tool_Posts', 'update_post' ),
				'description' => 'Update fields on an existing blog post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer', 'minimum' => 1 ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ) ),
						'excerpt' => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'expected_modified_gmt' => array( 'type' => 'string', 'description' => 'Use the modified_gmt value returned by get_post to prevent overwriting a newer edit.' ),
					),
					'required'   => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'list_pages'      => array(
				'handler'     => array( 'Mcp_Tool_Pages', 'list_pages' ),
				'description' => 'List pages, optionally filtered by status or search term.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private', 'any' ) ),
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE ),
						'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_page'        => array(
				'handler'     => array( 'Mcp_Tool_Pages', 'get_page' ),
				'description' => 'Get a single page by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'required'   => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'create_page'     => array(
				'handler'     => array( 'Mcp_Tool_Pages', 'create_page' ),
				'description' => 'Create a new page.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ) ),
						'parent_id'  => array( 'type' => 'integer', 'minimum' => 0 ),
						'slug'       => array( 'type' => 'string' ),
					),
					'required'   => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false ),
			),
			'update_page'     => array(
				'handler'     => array( 'Mcp_Tool_Pages', 'update_page' ),
				'description' => 'Update fields on an existing page.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer', 'minimum' => 1 ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ) ),
						'parent_id' => array( 'type' => 'integer', 'minimum' => 0 ),
						'slug'      => array( 'type' => 'string' ),
						'expected_modified_gmt' => array( 'type' => 'string', 'description' => 'Use the modified_gmt value returned by get_page to prevent overwriting a newer edit.' ),
					),
					'required'   => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'list_comments'   => array(
				'handler'     => array( 'Mcp_Tool_Comments', 'list_comments' ),
				'description' => 'List comments, optionally filtered by post or status.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
						'status'   => array( 'type' => 'string', 'enum' => array( 'all', 'approve', 'hold', 'spam', 'trash' ) ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE ),
						'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_comment'     => array(
				'handler'     => array( 'Mcp_Tool_Comments', 'get_comment' ),
				'description' => 'Get a single comment by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'required'   => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'moderate_comment' => array(
				'handler'     => array( 'Mcp_Tool_Comments', 'moderate_comment' ),
				'description' => 'Approve, spam, trash, or unapprove a comment.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer', 'minimum' => 1 ),
						'action' => array( 'type' => 'string', 'enum' => array( 'approve', 'spam', 'trash', 'unapprove' ) ),
					),
					'required'   => array( 'id', 'action' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true ),
			),
			'list_users'      => array(
				'handler'     => array( 'Mcp_Tool_Users', 'list_users' ),
				'description' => 'List site users (username, display name, role, registration date — no email addresses).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'role'     => array( 'type' => 'string' ),
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE ),
						'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_user'        => array(
				'handler'     => array( 'Mcp_Tool_Users', 'get_user' ),
				'description' => 'Get a single user by ID (no email address).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'required'   => array( 'id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
		);

		return self::$tools;
	}

	public static function get_definitions() {
		$out = array();
		foreach ( self::definitions() as $name => $tool ) {
			$out[] = array(
				'name'        => $name,
				'description' => $tool['description'],
				'inputSchema' => $tool['inputSchema'],
				'annotations' => $tool['annotations'],
			);
		}
		return $out;
	}

	public static function has_tool( $name ) {
		$defs = self::definitions();
		return isset( $defs[ $name ] );
	}

	/**
	 * Invokes a tool handler. Returns an array result or a WP_Error.
	 */
	public static function call( $name, $args ) {
		$defs = self::definitions();

		if ( ! isset( $defs[ $name ] ) ) {
			return new WP_Error( 'mcp_unknown_tool', "Unknown tool: {$name}" );
		}

		$args = is_array( $args ) ? $args : array();
		$validation = rest_validate_value_from_schema( $args, $defs[ $name ]['inputSchema'], 'arguments' );
		if ( is_wp_error( $validation ) ) {
			return new WP_Error( 'mcp_invalid_params', $validation->get_error_message() );
		}

		try {
			return call_user_func( $defs[ $name ]['handler'], $args );
		} catch ( Throwable $e ) {
			error_log( '[mcp-connector] tool ' . $name . ' threw: ' . $e->getMessage() );
			return new WP_Error( 'mcp_internal_error', 'Internal error while executing tool.' );
		}
	}

	/**
	 * Normalizes pagination args and returns [per_page, page, offset].
	 */
	public static function pagination_args( $args ) {
		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;

		return array( $per_page, $page, ( $page - 1 ) * $per_page );
	}

	/**
	 * Builds the uniform {items, total, has_more, next_page} envelope.
	 */
	public static function envelope( $items, $total, $per_page, $page ) {
		$has_more = ( $page * $per_page ) < $total;

		return array(
			'items'     => $items,
			'total'     => (int) $total,
			'has_more'  => $has_more,
			'next_page' => $has_more ? $page + 1 : null,
		);
	}
}
