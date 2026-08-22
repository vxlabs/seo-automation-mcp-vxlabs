<?php
/**
 * Run inside an installed WordPress instance with:
 * wp eval-file wp-content/plugins/smartforms/tests/integration-smoke.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This smoke test must be run through WP-CLI.' );
}

wp_set_current_user( 1 );

$tools = Mcp_Tool_Registry::get_definitions();
$names = wp_list_pluck( $tools, 'name' );
if ( ! in_array( 'smartforms_list_entries', $names, true ) ) {
	throw new RuntimeException( 'SmartForms MCP tools were not registered.' );
}
foreach ( array( 'smartforms_claim_spam_reviews', 'smartforms_set_spam_verdict', 'smartforms_list_deliveries', 'smartforms_retry_delivery' ) as $tool_name ) {
	if ( ! in_array( $tool_name, $names, true ) ) throw new RuntimeException( $tool_name . ' was not registered.' );
}

$secret = 'smoke-secret-' . wp_generate_password( 12, false );
if ( SmartForms_Secrets::decrypt( SmartForms_Secrets::encrypt( $secret ) ) !== $secret ) throw new RuntimeException( 'Secret encryption round-trip failed.' );

$destination = SmartForms_Integration_Repository::save( array( 'name'=>'Smoke API', 'type'=>'api', 'enabled'=>true, 'config'=>array( 'url'=>'https://example.invalid/smartforms', 'auth_type'=>'hmac', 'secret'=>'test-secret', 'timeout'=>3 ) ) );
if ( is_wp_error( $destination ) ) throw new RuntimeException( $destination->get_error_message() );
$destination = SmartForms_Integration_Repository::save( array( 'name'=>'Smoke API', 'type'=>'api', 'enabled'=>true, 'config'=>array( 'url'=>'https://example.invalid/smartforms', 'auth_type'=>'hmac', 'secret'=>'', 'timeout'=>3 ) ), $destination['id'] );
$destination_secret = SmartForms_Integration_Repository::get( $destination['id'], true );
if ( is_wp_error( $destination_secret ) || 'test-secret' !== $destination_secret['config']['secret'] ) throw new RuntimeException( 'Blank integration edit did not preserve the encrypted secret.' );
$email_destination = SmartForms_Integration_Repository::save( array( 'name'=>'Smoke SMTP', 'type'=>'email', 'enabled'=>true, 'config'=>array( 'mailer'=>'smtp', 'recipients'=>'forms@example.com', 'host'=>'smtp.example.com', 'port'=>587, 'encryption'=>'tls', 'username'=>'forms@example.com', 'password'=>'smtp-secret' ) ) );
if ( is_wp_error( $email_destination ) ) throw new RuntimeException( $email_destination->get_error_message() );

$blank_schema = SmartForms_Form_Repository::default_schema();
if ( ! empty( $blank_schema['fields'] ) ) {
	throw new RuntimeException( 'New forms should start with an empty field canvas.' );
}

$test_schema = array(
	'version' => 1,
	'fields'  => array(
		SmartForms_Field_Registry::default_field( 'text' ),
		SmartForms_Field_Registry::default_field( 'email' ),
		SmartForms_Field_Registry::default_field( 'textarea' ),
		SmartForms_Field_Registry::default_field( 'button' ),
	),
);
$created = SmartForms_Form_Repository::create( 'SmartForms Smoke Test', $test_schema, array( 'integration_ids'=>array( $destination['id'], $email_destination['id'] ) ), 'publish' );
if ( is_wp_error( $created ) ) {
	throw new RuntimeException( $created->get_error_message() );
}

$fields = array();
foreach ( $created['schema']['fields'] as $field ) {
	if ( 'text' === $field['type'] ) {
		$fields[ $field['id'] ] = 'Test Person';
	} elseif ( 'email' === $field['type'] ) {
		$fields[ $field['id'] ] = 'test@example.com';
	} elseif ( 'textarea' === $field['type'] ) {
		$fields[ $field['id'] ] = 'This is an integration smoke test.';
	}
}

$request = new WP_REST_Request( 'POST', '/smartforms/v1/forms/' . $created['id'] . '/submit' );
$request->set_body_params(
	array(
		'form_token'     => SmartForms_Renderer::token( $created ),
		'form_started'   => time() - 3,
		'company_website'=> '',
		'source_url'     => home_url( '/smoke-test' ),
		'fields'         => $fields,
	)
);

$submitted = SmartForms_Submission_Service::submit( $created['id'], $request );
if ( is_wp_error( $submitted ) || empty( $submitted['entry_id'] ) ) {
	$message = is_wp_error( $submitted ) ? $submitted->get_error_message() : 'Entry was not created.';
	throw new RuntimeException( $message );
}

$stored = SmartForms_Entry_Repository::get( $submitted['entry_id'] );
if ( is_wp_error( $stored ) || 'pending' !== $stored['spam_status'] ) throw new RuntimeException( 'New entry was not held for spam review.' );
$classified = SmartForms_Spam_Service::classify( $submitted['entry_id'], 'clean', 2, 0.99, 'Smoke-test clean verdict.', 'integration-smoke' );
if ( is_wp_error( $classified ) || 'clean' !== $classified['spam_status'] ) throw new RuntimeException( 'Clean verdict failed.' );
$deliveries = SmartForms_Delivery_Repository::list_deliveries( array( 'entry_id'=>$submitted['entry_id'] ) );
if ( 2 !== count( $deliveries['items'] ) || 'queued' !== $deliveries['items'][0]['status'] || 'queued' !== $deliveries['items'][1]['status'] ) throw new RuntimeException( 'Clean entry did not create both queued API and Email deliveries.' );
$mock_api = function( $pre, $args, $url ) {
	if ( 'https://example.invalid/smartforms' === $url ) return array( 'headers'=>array(), 'body'=>'', 'response'=>array( 'code'=>202, 'message'=>'Accepted' ), 'cookies'=>array(), 'filename'=>null );
	return $pre;
};
add_filter( 'pre_http_request', $mock_api, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
SmartForms_Delivery_Worker::process_due();
remove_filter( 'pre_http_request', $mock_api, 10 );
remove_filter( 'pre_wp_mail', '__return_true' );
foreach ( $deliveries['items'] as $delivery ) {
	$delivered = SmartForms_Delivery_Repository::get( $delivery['id'] );
	if ( is_wp_error( $delivered ) || 'delivered' !== $delivered['status'] || 1 !== $delivered['attempt_count'] ) throw new RuntimeException( 'Mock Email/API delivery was not recorded as delivered.' );
}

$html = SmartForms_Renderer::render( $created['id'] );
if ( false === strpos( $html, 'smartforms-form' ) ) {
	throw new RuntimeException( 'Published form did not render.' );
}

global $wpdb;
$wpdb->delete( SmartForms_Delivery_Repository::table(), array( 'entry_id' => $submitted['entry_id'] ), array( '%d' ) );
$wpdb->delete( SmartForms_Entry_Repository::events_table(), array( 'entry_id' => $submitted['entry_id'] ), array( '%d' ) );
$wpdb->delete( SmartForms_Entry_Repository::table(), array( 'id' => $submitted['entry_id'] ), array( '%d' ) );
wp_delete_post( $created['id'], true );
SmartForms_Integration_Repository::delete( $destination['id'] );
SmartForms_Integration_Repository::delete( $email_destination['id'] );

WP_CLI::success( 'SmartForms form creation, gated spam review, Email/API delivery, secret storage, rendering, and MCP registration passed.' );
