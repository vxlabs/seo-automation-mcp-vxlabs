<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Form_Repository {

	const POST_TYPE     = 'smartform';
	const SCHEMA_META   = '_smartforms_schema';
	const SETTINGS_META = '_smartforms_settings';

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array( 'name' => 'SmartForms', 'singular_name' => 'SmartForm' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
			)
		);
	}

	public static function default_schema() {
		return array(
			'version' => 1,
			'fields'  => array(),
		);
	}

	public static function default_form_settings() {
		return array(
			'show_title'           => true,
			'store_entries'        => true,
			'confirmation_type'    => 'message',
			'confirmation_message' => 'Thanks! Your submission has been received.',
			'redirect_url'         => '',
			'notification_email'   => get_option( 'admin_email' ),
			'notification_subject' => 'New submission: {form_title}',
			'enable_recaptcha'     => false,
			'integration_ids'      => array(),
		);
	}

	public static function default_messages() {
		return array(
			'required_text'     => '{label} is required.',
			'required_email'    => 'Email is required.',
			'required_url'      => 'URL is required.',
			'required_textarea' => '{label} is required.',
			'required_choice'   => 'Please choose an option for {label}.',
			'required_checkbox' => 'Please select at least one option for {label}.',
			'required_gdpr'     => 'You must accept {label}.',
			'required_number'   => 'Number is required.',
			'required_phone'    => 'Phone number is required.',
			'required_dropdown' => 'Please choose an option for {label}.',
			'required_address'  => 'Address is required.',
			'invalid_email'     => 'Enter a valid email address.',
			'invalid_url'       => 'Enter a valid URL.',
			'invalid_number'    => 'Enter a valid number.',
			'invalid_phone'     => 'Enter a valid phone number.',
			'below_minimum'     => '{label} must be at least {min}.',
			'above_maximum'     => '{label} must be no more than {max}.',
			'too_long'          => '{label} must be no longer than {max} characters.',
			'captcha_failed'    => 'We could not verify that submission. Please try again.',
			'rate_limited'      => 'Too many submissions. Please wait and try again.',
			'submission_failed' => 'The form could not be submitted. Please try again.',
		);
	}

	public static function default_global_settings() {
		return array(
			'messages'         => self::default_messages(),
			'recaptcha_mode'   => 'none',
			'recaptcha_site'   => '',
			'recaptcha_secret' => '',
			'recaptcha_score'  => 0.5,
			'retain_days'      => 0,
			'ip_logging'       => false,
			'delete_on_uninstall' => false,
		);
	}

	public static function create( $title, $schema = null, $settings = null, $status = 'draft' ) {
		if ( ! current_user_can( 'smartforms_manage_forms' ) ) {
			return new WP_Error( 'smartforms_forbidden', 'You do not have permission to create forms.' );
		}

		$schema = null === $schema ? self::default_schema() : SmartForms_Field_Registry::sanitize_schema( $schema );
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => sanitize_text_field( $title ),
				'post_status' => in_array( $status, array( 'draft', 'publish' ), true ) ? $status : 'draft',
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::SCHEMA_META, $schema );
		update_post_meta( $post_id, self::SETTINGS_META, self::sanitize_form_settings( $settings ) );
		SmartForms_Integration_Repository::set_form_integrations( $post_id, is_array( $settings ) && isset( $settings['integration_ids'] ) ? $settings['integration_ids'] : array() );
		return self::get( $post_id );
	}

	public static function update( $id, $data ) {
		$form = get_post( $id );
		if ( ! $form || self::POST_TYPE !== $form->post_type ) {
			return new WP_Error( 'smartforms_not_found', 'Form not found.' );
		}
		if ( ! current_user_can( 'smartforms_manage_forms' ) ) {
			return new WP_Error( 'smartforms_forbidden', 'You do not have permission to update forms.' );
		}

		$post_data = array( 'ID' => $id );
		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'draft', 'publish', 'trash' ), true ) ) {
			$post_data['post_status'] = $data['status'];
		}
		$result = wp_update_post( $post_data, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( isset( $data['schema'] ) ) {
			$schema = SmartForms_Field_Registry::sanitize_schema( $data['schema'] );
			if ( is_wp_error( $schema ) ) {
				return $schema;
			}
			$current_schema    = get_post_meta( $id, self::SCHEMA_META, true );
			$schema['version'] = is_array( $current_schema ) && isset( $current_schema['version'] ) ? absint( $current_schema['version'] ) + 1 : 1;
			update_post_meta( $id, self::SCHEMA_META, $schema );
		}
		if ( isset( $data['settings'] ) ) {
			$current_settings = get_post_meta( $id, self::SETTINGS_META, true );
			$merged_settings  = array_merge( is_array( $current_settings ) ? $current_settings : self::default_form_settings(), is_array( $data['settings'] ) ? $data['settings'] : array() );
			update_post_meta( $id, self::SETTINGS_META, self::sanitize_form_settings( $merged_settings ) );
			if ( array_key_exists( 'integration_ids', $data['settings'] ) ) SmartForms_Integration_Repository::set_form_integrations( $id, $data['settings']['integration_ids'] );
		}
		wp_save_post_revision( $id );

		return self::get( $id );
	}

	public static function duplicate( $id ) {
		$form = self::get( $id );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		return self::create( $form['title'] . ' Copy', $form['schema'], $form['settings'], 'draft' );
	}

	public static function get( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'smartforms_not_found', 'Form not found.' );
		}
		$schema   = get_post_meta( $post->ID, self::SCHEMA_META, true );
		$settings = get_post_meta( $post->ID, self::SETTINGS_META, true );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_form_settings() );
		$settings['integration_ids'] = SmartForms_Integration_Repository::form_ids( $post->ID );
		return array(
			'id'           => (int) $post->ID,
			'title'        => $post->post_title,
			'status'       => $post->post_status,
			'schema'       => is_array( $schema ) ? $schema : self::default_schema(),
			'settings'     => $settings,
			'modified_gmt' => $post->post_modified_gmt,
			'created_gmt'  => $post->post_date_gmt,
			'shortcode'    => '[smartforms id="' . (int) $post->ID . '"]',
		);
	}

	public static function list_forms( $args = array() ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => isset( $args['status'] ) ? $args['status'] : array( 'publish', 'draft' ),
				'posts_per_page' => isset( $args['per_page'] ) ? min( 100, absint( $args['per_page'] ) ) : 100,
				's'              => isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		return array_map( array( __CLASS__, 'summary' ), $posts );
	}

	public static function summary( $post ) {
		return array(
			'id'           => (int) $post->ID,
			'title'        => $post->post_title,
			'status'       => $post->post_status,
			'modified_gmt' => $post->post_modified_gmt,
			'entry_count'  => SmartForms_Entry_Repository::count( array( 'form_id' => $post->ID ) ),
			'shortcode'    => '[smartforms id="' . (int) $post->ID . '"]',
		);
	}

	public static function sanitize_form_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$defaults = self::default_form_settings();
		return array(
			'show_title'           => isset( $settings['show_title'] ) ? ! empty( $settings['show_title'] ) : $defaults['show_title'],
			'store_entries'        => isset( $settings['store_entries'] ) ? ! empty( $settings['store_entries'] ) : $defaults['store_entries'],
			'confirmation_type'    => isset( $settings['confirmation_type'] ) && 'redirect' === $settings['confirmation_type'] ? 'redirect' : 'message',
			'confirmation_message' => isset( $settings['confirmation_message'] ) ? sanitize_text_field( $settings['confirmation_message'] ) : $defaults['confirmation_message'],
			'redirect_url'         => isset( $settings['redirect_url'] ) ? esc_url_raw( $settings['redirect_url'] ) : '',
			'notification_email'   => isset( $settings['notification_email'] ) ? sanitize_email( $settings['notification_email'] ) : $defaults['notification_email'],
			'notification_subject' => isset( $settings['notification_subject'] ) ? sanitize_text_field( $settings['notification_subject'] ) : $defaults['notification_subject'],
			'enable_recaptcha'     => ! empty( $settings['enable_recaptcha'] ),
			'integration_ids'      => isset( $settings['integration_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $settings['integration_ids'] ) ) ) ) : array(),
		);
	}
}
