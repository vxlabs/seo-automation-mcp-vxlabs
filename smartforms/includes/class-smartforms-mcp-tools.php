<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_MCP_Tools {

	public static function register_tools( $tools ) {
		$read = array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true );
		$write = array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true );
		$form_schema = array( 'type' => 'object', 'description' => 'SmartForms schema with version and a fields array.', 'additionalProperties' => true );

		$tools['smartforms_list_forms'] = self::definition( array( __CLASS__, 'list_forms' ), 'List SmartForms with statuses, entry counts, and shortcodes.', self::object_schema( array( 'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'trash', 'any' ) ), 'search' => array( 'type' => 'string' ), 'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ) ) ), $read );
		$tools['smartforms_get_form'] = self::definition( array( __CLASS__, 'get_form' ), 'Get a SmartForm definition, settings, fields, and embed shortcode.', self::id_schema(), $read );
		$tools['smartforms_create_form'] = self::definition( array( __CLASS__, 'create_form' ), 'Create a SmartForm. New forms default to draft.', self::object_schema( array( 'title' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191 ), 'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ), 'schema' => $form_schema, 'settings' => array( 'type' => 'object', 'additionalProperties' => true ) ), array( 'title' ) ), $write );
		$tools['smartforms_update_form'] = self::definition( array( __CLASS__, 'update_form' ), 'Update the title, status, schema, or settings of a SmartForm. Omitted values are preserved.', self::object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ), 'title' => array( 'type' => 'string', 'maxLength' => 191 ), 'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ), 'schema' => $form_schema, 'settings' => array( 'type' => 'object', 'additionalProperties' => true ), 'expected_modified_gmt' => array( 'type' => 'string', 'description' => 'Use modified_gmt from get_form to avoid overwriting a newer edit.' ) ), array( 'id' ) ), $write );
		$tools['smartforms_duplicate_form'] = self::definition( array( __CLASS__, 'duplicate_form' ), 'Duplicate a SmartForm into a new draft.', self::id_schema(), array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false ) );
		$tools['smartforms_get_form_stats'] = self::definition( array( __CLASS__, 'get_form_stats' ), 'Get entry counts grouped by workflow status for one form or all forms.', self::object_schema( array( 'form_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) ), $read );
		$tools['smartforms_list_entries'] = self::definition( array( __CLASS__, 'list_entries' ), 'List form entries, optionally filtered by form, workflow status, or submitted date.', self::entry_list_schema(), $read );
		$tools['smartforms_get_entry'] = self::definition( array( __CLASS__, 'get_entry' ), 'Get one form entry including submitted field values and internal activity.', self::id_schema(), $read );
		$tools['smartforms_update_entry_status'] = self::definition( array( __CLASS__, 'update_entry_status' ), 'Move an entry through the new, read, in-progress, processed, spam, or trash workflow.', self::object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ), 'status' => array( 'type' => 'string', 'enum' => SmartForms_Entry_Repository::STATUSES ) ), array( 'id', 'status' ) ), $write );
		$tools['smartforms_bulk_update_entries'] = self::definition( array( __CLASS__, 'bulk_update_entries' ), 'Update the workflow status of up to 100 entries.', self::object_schema( array( 'ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'minItems' => 1, 'maxItems' => 100, 'uniqueItems' => true ), 'status' => array( 'type' => 'string', 'enum' => SmartForms_Entry_Repository::STATUSES ) ), array( 'ids', 'status' ) ), $write );
		$tools['smartforms_add_entry_note'] = self::definition( array( __CLASS__, 'add_entry_note' ), 'Add an internal processing note to a form entry.', self::object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ), 'note' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 5000 ) ), array( 'id', 'note' ) ), array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false ) );
		$tools['smartforms_get_settings'] = self::definition( array( __CLASS__, 'get_settings' ), 'Get SmartForms global validation, privacy, and reCAPTCHA configuration. Secret values are never returned.', self::object_schema(), $read );
		$tools['smartforms_claim_spam_reviews'] = self::definition( array( __CLASS__, 'claim_spam_reviews' ), 'Claim pending entries for AI spam classification. Review the submitted fields and return a verdict with smartforms_set_spam_verdict.', self::object_schema( array( 'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>50), 'classifier'=>array('type'=>'string','maxLength'=>100), 'lease_seconds'=>array('type'=>'integer','minimum'=>60,'maximum'=>1800) ) ), $write );
		$tools['smartforms_get_spam_review'] = self::definition( array( __CLASS__, 'get_spam_review' ), 'Get one entry and its current spam classification state.', self::id_schema(), $read );
		$tools['smartforms_set_spam_verdict'] = self::definition( array( __CLASS__, 'set_spam_verdict' ), 'Classify an entry as clean or spam. Clean entries are queued to their configured Email and API destinations; spam entries are blocked.', self::object_schema( array( 'id'=>array('type'=>'integer','minimum'=>1), 'verdict'=>array('type'=>'string','enum'=>array('clean','spam')), 'score'=>array('type'=>'number','minimum'=>0,'maximum'=>100), 'confidence'=>array('type'=>'number','minimum'=>0,'maximum'=>1), 'reason'=>array('type'=>'string','maxLength'=>2000), 'classifier'=>array('type'=>'string','maxLength'=>100) ), array('id','verdict','reason') ), $write );
		$tools['smartforms_list_deliveries'] = self::definition( array( __CLASS__, 'list_deliveries' ), 'List Email and API delivery attempts without exposing destination secrets.', self::object_schema( array( 'entry_id'=>array('type'=>'integer','minimum'=>1), 'status'=>array('type'=>'string','enum'=>SmartForms_Delivery_Repository::STATUSES), 'per_page'=>array('type'=>'integer','minimum'=>1,'maximum'=>100) ) ), $read );
		$tools['smartforms_get_delivery'] = self::definition( array( __CLASS__, 'get_delivery' ), 'Get one delivery attempt and its safe response metadata.', self::id_schema(), $read );
		$tools['smartforms_retry_delivery'] = self::definition( array( __CLASS__, 'retry_delivery' ), 'Retry a failed delivery for an entry already classified clean.', self::id_schema(), $write );
		return $tools;
	}

	public static function list_forms( $args ) {
		if ( ! self::can( 'smartforms_manage_forms' ) ) return self::forbidden();
		$status = isset( $args['status'] ) && 'any' !== $args['status'] ? $args['status'] : array( 'publish', 'draft', 'trash' );
		return array( 'items' => SmartForms_Form_Repository::list_forms( array( 'status' => $status, 'search' => isset( $args['search'] ) ? $args['search'] : '', 'per_page' => isset( $args['per_page'] ) ? $args['per_page'] : 100 ) ) );
	}

	public static function get_form( $args ) {
		if ( ! self::can( 'smartforms_manage_forms' ) ) return self::forbidden();
		return SmartForms_Form_Repository::get( $args['id'] );
	}

	public static function create_form( $args ) {
		if ( ! self::can( 'smartforms_manage_forms' ) ) return self::forbidden();
		return SmartForms_Form_Repository::create( $args['title'], isset( $args['schema'] ) ? $args['schema'] : null, isset( $args['settings'] ) ? $args['settings'] : null, isset( $args['status'] ) ? $args['status'] : 'draft' );
	}

	public static function update_form( $args ) {
		if ( ! self::can( 'smartforms_manage_forms' ) ) return self::forbidden();
		$form = SmartForms_Form_Repository::get( $args['id'] );
		if ( is_wp_error( $form ) ) return $form;
		if ( ! empty( $args['expected_modified_gmt'] ) && $args['expected_modified_gmt'] !== $form['modified_gmt'] ) return new WP_Error( 'smartforms_conflict', 'The form has changed since it was read. Fetch it again before updating.' );
		$data = array_intersect_key( $args, array_flip( array( 'title', 'status', 'schema', 'settings' ) ) );
		return SmartForms_Form_Repository::update( $args['id'], $data );
	}

	public static function duplicate_form( $args ) { if ( ! self::can( 'smartforms_manage_forms' ) ) return self::forbidden(); return SmartForms_Form_Repository::duplicate( $args['id'] ); }
	public static function get_form_stats( $args ) { if ( ! self::can( 'smartforms_view_entries' ) ) return self::forbidden(); return SmartForms_Entry_Repository::stats( isset( $args['form_id'] ) ? $args['form_id'] : 0 ); }

	public static function list_entries( $args ) {
		if ( ! self::can( 'smartforms_view_entries' ) ) return self::forbidden();
		return SmartForms_Entry_Repository::list_entries( $args );
	}

	public static function get_entry( $args ) {
		if ( ! self::can( 'smartforms_view_entries' ) ) return self::forbidden();
		$entry = SmartForms_Entry_Repository::get( $args['id'] );
		if ( is_wp_error( $entry ) ) return $entry;
		$entry['activity'] = SmartForms_Entry_Repository::events( $args['id'] );
		return $entry;
	}

	public static function update_entry_status( $args ) { if ( ! self::can( 'smartforms_manage_entries' ) ) return self::forbidden(); return SmartForms_Entry_Repository::update_status( $args['id'], $args['status'] ); }

	public static function bulk_update_entries( $args ) {
		if ( ! self::can( 'smartforms_manage_entries' ) ) return self::forbidden();
		$items = array();
		foreach ( $args['ids'] as $id ) { $result = SmartForms_Entry_Repository::update_status( $id, $args['status'] ); $items[] = is_wp_error( $result ) ? array( 'id' => $id, 'success' => false, 'error' => $result->get_error_message() ) : array( 'id' => $id, 'success' => true, 'entry' => $result ); }
		return array( 'items' => $items );
	}

	public static function add_entry_note( $args ) { if ( ! self::can( 'smartforms_manage_entries' ) ) return self::forbidden(); $activity = SmartForms_Entry_Repository::add_note( $args['id'], $args['note'] ); if ( is_wp_error( $activity ) ) return $activity; return array( 'entry_id' => $args['id'], 'activity' => $activity ); }

	public static function get_settings() {
		if ( ! self::can( 'smartforms_manage_settings' ) ) return self::forbidden();
		$settings = wp_parse_args( get_option( 'smartforms_settings', array() ), SmartForms_Form_Repository::default_global_settings() );
		$settings['recaptcha_secret_configured'] = ! empty( $settings['recaptcha_secret'] );
		unset( $settings['recaptcha_secret'] );
		return $settings;
	}

	public static function claim_spam_reviews( $args ) { if ( ! self::can( 'smartforms_classify_entries' ) ) return self::forbidden(); return SmartForms_Spam_Service::claim( isset($args['limit'])?$args['limit']:10, isset($args['classifier'])?$args['classifier']:'mcp', isset($args['lease_seconds'])?$args['lease_seconds']:300 ); }
	public static function get_spam_review( $args ) { if ( ! self::can( 'smartforms_classify_entries' ) ) return self::forbidden(); return SmartForms_Entry_Repository::get( $args['id'] ); }
	public static function set_spam_verdict( $args ) { if ( ! self::can( 'smartforms_classify_entries' ) ) return self::forbidden(); return SmartForms_Spam_Service::classify( $args['id'], $args['verdict'], isset($args['score'])?$args['score']:null, isset($args['confidence'])?$args['confidence']:null, $args['reason'], isset($args['classifier'])?$args['classifier']:'mcp' ); }
	public static function list_deliveries( $args ) { if ( ! self::can( 'smartforms_view_delivery_logs' ) ) return self::forbidden(); return SmartForms_Delivery_Repository::list_deliveries( $args ); }
	public static function get_delivery( $args ) { if ( ! self::can( 'smartforms_view_delivery_logs' ) ) return self::forbidden(); return SmartForms_Delivery_Repository::get( $args['id'] ); }
	public static function retry_delivery( $args ) { if ( ! self::can( 'smartforms_retry_deliveries' ) ) return self::forbidden(); return SmartForms_Delivery_Repository::retry( $args['id'] ); }

	private static function definition( $handler, $description, $schema, $annotations ) { return array( 'handler' => $handler, 'description' => $description, 'inputSchema' => $schema, 'annotations' => $annotations ); }
	private static function object_schema( $properties = array(), $required = array() ) { $schema = array( 'type' => 'object', 'properties' => $properties, 'additionalProperties' => false ); if ( $required ) $schema['required'] = $required; return $schema; }
	private static function id_schema() { return self::object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ) ); }
	private static function entry_list_schema() { return self::object_schema( array( 'form_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'status' => array( 'type' => 'string', 'enum' => SmartForms_Entry_Repository::STATUSES ), 'spam_status'=>array('type'=>'string','enum'=>SmartForms_Spam_Service::STATUSES), 'date_from' => array( 'type' => 'string', 'format' => 'date' ), 'date_to' => array( 'type' => 'string', 'format' => 'date' ), 'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ), 'page' => array( 'type' => 'integer', 'minimum' => 1 ) ) ); }
	private static function can( $cap ) { return current_user_can( $cap ); }
	private static function forbidden() { return new WP_Error( 'smartforms_forbidden', 'The API-key user does not have the required SmartForms capability.' ); }
}
