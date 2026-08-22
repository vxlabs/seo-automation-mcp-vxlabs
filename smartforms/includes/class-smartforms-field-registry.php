<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Field_Registry {

	public static function definitions() {
		return array(
			'text'        => array( 'label' => 'Text', 'category' => 'input', 'icon' => 'editor-textcolor' ),
			'email'       => array( 'label' => 'Email', 'category' => 'input', 'icon' => 'email' ),
			'url'         => array( 'label' => 'URL', 'category' => 'input', 'icon' => 'admin-links' ),
			'textarea'    => array( 'label' => 'Textarea', 'category' => 'input', 'icon' => 'editor-paragraph' ),
			'radio'       => array( 'label' => 'Multiple Choice', 'category' => 'input', 'icon' => 'list-view' ),
			'checkbox'    => array( 'label' => 'Checkbox', 'category' => 'input', 'icon' => 'yes' ),
			'gdpr'        => array( 'label' => 'GDPR Agreement', 'category' => 'input', 'icon' => 'privacy' ),
			'number'      => array( 'label' => 'Number', 'category' => 'input', 'icon' => 'editor-ol' ),
			'phone'       => array( 'label' => 'Phone Number', 'category' => 'input', 'icon' => 'phone' ),
			'select'      => array( 'label' => 'Dropdown', 'category' => 'input', 'icon' => 'arrow-down-alt2' ),
			'address'     => array( 'label' => 'Address', 'category' => 'input', 'icon' => 'location' ),
			'button'      => array( 'label' => 'Custom Button', 'category' => 'action', 'icon' => 'button' ),
			'separator'   => array( 'label' => 'Separator', 'category' => 'layout', 'icon' => 'minus' ),
			'heading'     => array( 'label' => 'Heading', 'category' => 'layout', 'icon' => 'heading' ),
			'image'       => array( 'label' => 'Image', 'category' => 'layout', 'icon' => 'format-image' ),
			'icon'        => array( 'label' => 'Icon', 'category' => 'layout', 'icon' => 'star-filled' ),
		);
	}

	public static function default_field( $type ) {
		$definitions = self::definitions();
		if ( ! isset( $definitions[ $type ] ) ) {
			$type = 'text';
		}

		$field = array(
			'id'            => 'fld_' . wp_generate_password( 8, false, false ),
			'type'          => $type,
			'label'         => $definitions[ $type ]['label'],
			'placeholder'   => '',
			'help'          => '',
			'default'       => '',
			'required'      => false,
			'width'         => 100,
			'css_class'     => '',
			'error_message' => '',
			'options'       => array( 'Option 1', 'Option 2' ),
			'min'           => '',
			'max'           => '',
			'max_length'    => '',
			'content'       => '',
			'image_url'     => '',
		);

		if ( 'button' === $type ) {
			$field['label'] = 'Submit';
		}
		if ( 'gdpr' === $type ) {
			$field['label']    = 'I agree to the privacy policy.';
			$field['required'] = true;
		}
		if ( 'heading' === $type ) {
			$field['content'] = 'Form section';
		}

		return $field;
	}

	public static function sanitize_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'smartforms_invalid_schema', 'The form schema must be an object.' );
		}

		$definitions = self::definitions();
		$fields      = isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : array();
		$clean       = array();
		$ids         = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : '';
			$id   = isset( $field['id'] ) ? sanitize_key( $field['id'] ) : '';
			if ( ! isset( $definitions[ $type ] ) || ! preg_match( '/^fld_[a-z0-9_-]{3,40}$/', $id ) || isset( $ids[ $id ] ) ) {
				continue;
			}
			$ids[ $id ] = true;

			$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
			$options = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( $options, 0, 100 ) ), 'strlen' ) );

			$clean[] = array(
				'id'            => $id,
				'type'          => $type,
				'label'         => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : $definitions[ $type ]['label'],
				'placeholder'   => isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '',
				'help'          => isset( $field['help'] ) ? sanitize_text_field( $field['help'] ) : '',
				'default'       => isset( $field['default'] ) ? sanitize_text_field( $field['default'] ) : '',
				'required'      => ! empty( $field['required'] ),
				'width'         => isset( $field['width'] ) ? max( 25, min( 100, absint( $field['width'] ) ) ) : 100,
				'css_class'     => isset( $field['css_class'] ) ? sanitize_html_class( $field['css_class'] ) : '',
				'error_message' => isset( $field['error_message'] ) ? sanitize_text_field( $field['error_message'] ) : '',
				'options'       => $options,
				'min'           => isset( $field['min'] ) && is_numeric( $field['min'] ) ? (float) $field['min'] : '',
				'max'           => isset( $field['max'] ) && is_numeric( $field['max'] ) ? (float) $field['max'] : '',
				'max_length'    => isset( $field['max_length'] ) ? min( 100000, absint( $field['max_length'] ) ) : '',
				'content'       => isset( $field['content'] ) ? wp_kses_post( $field['content'] ) : '',
				'image_url'     => isset( $field['image_url'] ) ? esc_url_raw( $field['image_url'] ) : '',
			);
		}

		if ( count( $clean ) > 200 ) {
			return new WP_Error( 'smartforms_too_many_fields', 'A form cannot contain more than 200 fields.' );
		}

		return array( 'version' => 1, 'fields' => $clean );
	}

	public static function is_input( $type ) {
		$definitions = self::definitions();
		return isset( $definitions[ $type ] ) && 'input' === $definitions[ $type ]['category'];
	}
}

