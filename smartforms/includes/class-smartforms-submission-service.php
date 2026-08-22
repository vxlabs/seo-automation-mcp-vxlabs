<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Submission_Service {

	public static function submit( $form_id, WP_REST_Request $request ) {
		$form = SmartForms_Form_Repository::get( $form_id );
		if ( is_wp_error( $form ) || 'publish' !== $form['status'] ) {
			return new WP_Error( 'smartforms_unavailable', 'This form is not available.', array( 'status' => 404 ) );
		}

		$origin = $request->get_header( 'origin' );
		if ( $origin && self::origin( $origin ) !== self::origin( home_url( '/' ) ) ) {
			return new WP_Error( 'smartforms_origin', 'This submission origin is not allowed.', array( 'status' => 403 ) );
		}

		if ( ! hash_equals( SmartForms_Renderer::token( $form ), (string) $request->get_param( 'form_token' ) ) ) {
			return new WP_Error( 'smartforms_token', 'The form changed or the submission token is invalid. Reload the page and try again.', array( 'status' => 400 ) );
		}

		if ( '' !== trim( (string) $request->get_param( 'company_website' ) ) ) {
			return new WP_Error( 'smartforms_spam', 'The submission was rejected.', array( 'status' => 400 ) );
		}
		$started = absint( $request->get_param( 'form_started' ) );
		if ( ! $started || time() - $started < 2 || time() - $started > DAY_IN_SECONDS ) {
			return new WP_Error( 'smartforms_timing', 'Reload the form and try again.', array( 'status' => 400 ) );
		}

		if ( self::rate_limited() ) {
			return new WP_Error( 'smartforms_rate_limited', self::message( 'rate_limited' ), array( 'status' => 429 ) );
		}

		if ( ! empty( $form['settings']['enable_recaptcha'] ) ) {
			$captcha = SmartForms_Recaptcha::verify( $request->get_param( 'recaptcha_token' ) );
			if ( is_wp_error( $captcha ) ) {
				return new WP_Error( 'smartforms_captcha', self::message( 'captcha_failed' ), array( 'status' => 400 ) );
			}
		}

		$fields     = $request->get_param( 'fields' );
		$validation = SmartForms_Validator::validate( $form['schema'], is_array( $fields ) ? $fields : array() );
		if ( $validation['errors'] ) {
			return new WP_Error( 'smartforms_validation', 'Please correct the highlighted fields.', array( 'status' => 422, 'fields' => $validation['errors'] ) );
		}

		$entry = null;
		if ( ! empty( $form['settings']['store_entries'] ) ) {
			$entry = SmartForms_Entry_Repository::create( $form, $validation['values'], $request );
			if ( is_wp_error( $entry ) ) {
				return new WP_Error( 'smartforms_storage', self::message( 'submission_failed' ), array( 'status' => 500 ) );
			}
			$entry = SmartForms_Spam_Service::initial_assessment( $entry['id'] );
		}

		return array(
			'success'  => true,
			'entry_id' => $entry ? $entry['id'] : null,
			'message'  => $form['settings']['confirmation_message'],
			'redirect' => 'redirect' === $form['settings']['confirmation_type'] ? $form['settings']['redirect_url'] : '',
		);
	}

	private static function rate_limited() {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key  = 'smartforms_rate_' . md5( $ip );
		$data = get_transient( $key );
		$data = is_array( $data ) ? $data : array( 'count' => 0 );
		$data['count']++;
		set_transient( $key, $data, MINUTE_IN_SECONDS );
		return $data['count'] > (int) apply_filters( 'smartforms_submission_rate_limit', 10 );
	}

	private static function origin( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! isset( $parts['scheme'], $parts['host'] ) ) {
			return '';
		}
		return strtolower( $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) );
	}

	private static function message( $key ) {
		$settings = wp_parse_args( get_option( 'smartforms_settings', array() ), SmartForms_Form_Repository::default_global_settings() );
		$messages = wp_parse_args( isset( $settings['messages'] ) ? $settings['messages'] : array(), SmartForms_Form_Repository::default_messages() );
		return isset( $messages[ $key ] ) ? $messages[ $key ] : 'The form could not be submitted.';
	}
}
