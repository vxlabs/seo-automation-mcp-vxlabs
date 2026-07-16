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
			'list_posts'      => array(
				'handler'      => array( 'Mcp_Tool_Posts', 'list_posts' ),
				'description'  => 'List blog posts, optionally filtered by status or search term.',
				'inputSchema'  => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string', 'description' => 'draft|pending|publish|private|any' ),
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'annotations'  => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_post'        => array(
				'handler'     => array( 'Mcp_Tool_Posts', 'get_post' ),
				'description' => 'Get a single blog post by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
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
						'status'     => array( 'type' => 'string', 'description' => 'draft|pending|publish|private, default draft' ),
						'excerpt'    => array( 'type' => 'string' ),
						'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
					'required'   => array( 'title', 'content' ),
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false ),
			),
			'update_post'     => array(
				'handler'     => array( 'Mcp_Tool_Posts', 'update_post' ),
				'description' => 'Update fields on an existing blog post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'list_pages'      => array(
				'handler'     => array( 'Mcp_Tool_Pages', 'list_pages' ),
				'description' => 'List pages, optionally filtered by status or search term.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string' ),
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_page'        => array(
				'handler'     => array( 'Mcp_Tool_Pages', 'get_page' ),
				'description' => 'Get a single page by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
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
						'status'     => array( 'type' => 'string' ),
						'parent_id'  => array( 'type' => 'integer' ),
					),
					'required'   => array( 'title', 'content' ),
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false ),
			),
			'update_page'     => array(
				'handler'     => array( 'Mcp_Tool_Pages', 'update_page' ),
				'description' => 'Update fields on an existing page.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'list_comments'   => array(
				'handler'     => array( 'Mcp_Tool_Comments', 'list_comments' ),
				'description' => 'List comments, optionally filtered by post or status.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer' ),
						'status'   => array( 'type' => 'string', 'description' => 'all|approve|hold|spam|trash' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_comment'     => array(
				'handler'     => array( 'Mcp_Tool_Comments', 'get_comment' ),
				'description' => 'Get a single comment by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'moderate_comment' => array(
				'handler'     => array( 'Mcp_Tool_Comments', 'moderate_comment' ),
				'description' => 'Approve, spam, trash, or unapprove a comment.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'action' => array( 'type' => 'string', 'description' => 'approve|spam|trash|unapprove' ),
					),
					'required'   => array( 'id', 'action' ),
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
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ),
			),
			'get_user'        => array(
				'handler'     => array( 'Mcp_Tool_Users', 'get_user' ),
				'description' => 'Get a single user by ID (no email address).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
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
