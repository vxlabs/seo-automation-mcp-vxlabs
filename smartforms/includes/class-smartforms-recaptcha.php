<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Recaptcha {

	public static function settings() {
		return wp_parse_args( get_option( 'smartforms_settings', array() ), SmartForms_Form_Repository::default_global_settings() );
	}

	public static function is_configured() {
		$settings = self::settings();
		return 'none' !== $settings['recaptcha_mode'] && ! empty( $settings['recaptcha_site'] ) && ! empty( $settings['recaptcha_secret'] );
	}

	public static function verify( $token ) {
		$settings = self::settings();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'smartforms_captcha_not_configured', 'reCAPTCHA is enabled for this form but has not been configured.' );
		}
		if ( ! is_string( $token ) || '' === trim( $token ) ) {
			return new WP_Error( 'smartforms_captcha_missing', 'The reCAPTCHA response is missing.' );
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $settings['recaptcha_secret'],
					'response' => $token,
					'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'smartforms_captcha_unavailable', 'reCAPTCHA verification is temporarily unavailable.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['success'] ) ) {
			return new WP_Error( 'smartforms_captcha_failed', 'reCAPTCHA verification failed.' );
		}

		$expected_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! empty( $data['hostname'] ) && $expected_host && strtolower( $data['hostname'] ) !== strtolower( $expected_host ) ) {
			return new WP_Error( 'smartforms_captcha_hostname', 'reCAPTCHA hostname verification failed.' );
		}

		if ( 'v3' === $settings['recaptcha_mode'] ) {
			$score = isset( $data['score'] ) ? (float) $data['score'] : 0;
			if ( $score < (float) $settings['recaptcha_score'] || ( ! empty( $data['action'] ) && 'smartforms_submit' !== $data['action'] ) ) {
				return new WP_Error( 'smartforms_captcha_score', 'reCAPTCHA did not accept this submission.' );
			}
		}

		return true;
	}
}

