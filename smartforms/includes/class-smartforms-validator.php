<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Validator {

	public static function validate( $schema, $submitted ) {
		$submitted = is_array( $submitted ) ? $submitted : array();
		$settings  = wp_parse_args( get_option( 'smartforms_settings', array() ), SmartForms_Form_Repository::default_global_settings() );
		$messages  = wp_parse_args( isset( $settings['messages'] ) ? $settings['messages'] : array(), SmartForms_Form_Repository::default_messages() );
		$values    = array();
		$errors    = array();

		foreach ( $schema['fields'] as $field ) {
			if ( ! SmartForms_Field_Registry::is_input( $field['type'] ) ) {
				continue;
			}
			$raw = isset( $submitted[ $field['id'] ] ) ? $submitted[ $field['id'] ] : '';
			if ( is_array( $raw ) ) {
				$raw = array_values( array_map( 'sanitize_text_field', array_slice( $raw, 0, 100 ) ) );
			} else {
				$raw = wp_unslash( (string) $raw );
			}

			$is_empty = is_array( $raw ) ? 0 === count( array_filter( $raw, 'strlen' ) ) : '' === trim( $raw );
			if ( ! empty( $field['required'] ) && $is_empty ) {
				$errors[ $field['id'] ] = self::required_message( $field, $messages );
				continue;
			}
			if ( $is_empty ) {
				$values[ $field['id'] ] = array( 'label' => $field['label'], 'type' => $field['type'], 'value' => is_array( $raw ) ? array() : '' );
				continue;
			}

			$value = self::sanitize_value( $field, $raw );
			$error = self::validate_value( $field, $value, $messages );
			if ( $error ) {
				$errors[ $field['id'] ] = $error;
				continue;
			}
			$values[ $field['id'] ] = array( 'label' => $field['label'], 'type' => $field['type'], 'value' => $value );
		}

		return array( 'values' => $values, 'errors' => $errors );
	}

	private static function sanitize_value( $field, $raw ) {
		switch ( $field['type'] ) {
			case 'email':
				return sanitize_email( $raw );
			case 'url':
				return esc_url_raw( $raw );
			case 'textarea':
			case 'address':
				return sanitize_textarea_field( $raw );
			case 'number':
				return is_numeric( $raw ) ? (float) $raw : sanitize_text_field( $raw );
			case 'checkbox':
				return is_array( $raw ) ? array_values( array_intersect( $raw, $field['options'] ) ) : array();
			case 'radio':
			case 'select':
				return in_array( sanitize_text_field( $raw ), $field['options'], true ) ? sanitize_text_field( $raw ) : '';
			case 'gdpr':
				return '1' === (string) $raw ? true : false;
			default:
				return sanitize_text_field( $raw );
		}
	}

	private static function validate_value( $field, $value, $messages ) {
		$key = '';
		if ( 'email' === $field['type'] && ! is_email( $value ) ) {
			$key = 'invalid_email';
		} elseif ( 'url' === $field['type'] && ! wp_http_validate_url( $value ) ) {
			$key = 'invalid_url';
		} elseif ( 'number' === $field['type'] && ! is_numeric( $value ) ) {
			$key = 'invalid_number';
		} elseif ( 'phone' === $field['type'] && ! preg_match( '/^[0-9+().\-\s]{7,30}$/', $value ) ) {
			$key = 'invalid_phone';
		} elseif ( 'number' === $field['type'] && '' !== $field['min'] && $value < $field['min'] ) {
			$key = 'below_minimum';
		} elseif ( 'number' === $field['type'] && '' !== $field['max'] && $value > $field['max'] ) {
			$key = 'above_maximum';
		} elseif ( ! empty( $field['max_length'] ) && is_string( $value ) && mb_strlen( $value ) > $field['max_length'] ) {
			$key = 'too_long';
		} elseif ( in_array( $field['type'], array( 'radio', 'select' ), true ) && '' === $value ) {
			$key = 'required_choice';
		} elseif ( 'gdpr' === $field['type'] && ! $value ) {
			$key = 'required_gdpr';
		}

		if ( ! $key ) {
			return '';
		}
		return self::replace_tokens( isset( $messages[ $key ] ) ? $messages[ $key ] : 'Invalid value.', $field );
	}

	private static function required_message( $field, $messages ) {
		$map = array(
			'text' => 'required_text', 'email' => 'required_email', 'url' => 'required_url', 'textarea' => 'required_textarea',
			'radio' => 'required_choice', 'checkbox' => 'required_checkbox', 'gdpr' => 'required_gdpr', 'number' => 'required_number',
			'phone' => 'required_phone', 'select' => 'required_dropdown', 'address' => 'required_address',
		);
		$message = ! empty( $field['error_message'] ) ? $field['error_message'] : $messages[ $map[ $field['type'] ] ];
		return self::replace_tokens( $message, $field );
	}

	private static function replace_tokens( $message, $field ) {
		return strtr(
			$message,
			array( '{label}' => $field['label'], '{min}' => (string) $field['min'], '{max}' => (string) ( '' !== $field['max'] ? $field['max'] : $field['max_length'] ) )
		);
	}
}

