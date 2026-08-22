<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_REST_Controller {

	public static function register_routes() {
		register_rest_route(
			'smartforms/v1',
			'/forms/(?P<id>\d+)/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			)
		);
	}

	public static function submit( WP_REST_Request $request ) {
		$result = SmartForms_Submission_Service::submit( absint( $request['id'] ), $request );
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 400;
			$body   = array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() );
			if ( is_array( $data ) && ! empty( $data['fields'] ) ) {
				$body['fields'] = $data['fields'];
			}
			return new WP_REST_Response( $body, $status, array( 'Cache-Control' => 'no-store' ) );
		}
		return new WP_REST_Response( $result, 200, array( 'Cache-Control' => 'no-store' ) );
	}
}

