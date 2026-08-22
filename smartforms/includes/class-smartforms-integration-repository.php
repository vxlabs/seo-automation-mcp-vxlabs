<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Integration_Repository {
	const TYPES = array( 'email', 'api' );

	public static function table() { global $wpdb; return $wpdb->prefix . 'smartforms_integrations'; }
	public static function links_table() { global $wpdb; return $wpdb->prefix . 'smartforms_form_integrations'; }

	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$type = isset( $data['type'] ) && in_array( $data['type'], self::TYPES, true ) ? $data['type'] : 'email';
		$old_config = array();
		if ( $id ) {
			$raw_config = $wpdb->get_var( $wpdb->prepare( 'SELECT config FROM ' . self::table() . ' WHERE id=%d', absint( $id ) ) );
			$old_config = $raw_config ? ( json_decode( $raw_config, true ) ?: array() ) : array();
		}
		$config = self::sanitize_config( $type, isset( $data['config'] ) ? $data['config'] : array(), $old_config );
		$row = array(
			'name' => sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' ),
			'type' => $type,
			'enabled' => empty( $data['enabled'] ) ? 0 : 1,
			'config' => wp_json_encode( $config ),
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( '' === $row['name'] ) return new WP_Error( 'smartforms_integration_name', 'A destination name is required.' );
		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => absint( $id ) ), array( '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
		} else {
			$row['created_at'] = $row['updated_at'];
			$wpdb->insert( self::table(), $row, array( '%s', '%s', '%d', '%s', '%s', '%s' ) );
			$id = $wpdb->insert_id;
		}
		return self::get( $id );
	}

	public static function get( $id, $include_secrets = false ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d', absint( $id ) ) );
		if ( ! $row ) return new WP_Error( 'smartforms_integration_not_found', 'Destination not found.' );
		return self::format( $row, $include_secrets );
	}

	public static function all( $enabled_only = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table() . ( $enabled_only ? ' WHERE enabled=1' : '' ) . ' ORDER BY name';
		return array_map( function( $row ) { return SmartForms_Integration_Repository::format( $row, false ); }, $wpdb->get_results( $sql ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::links_table(), array( 'integration_id' => absint( $id ) ), array( '%d' ) );
		return false !== $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function set_form_integrations( $form_id, $ids ) {
		global $wpdb;
		$wpdb->delete( self::links_table(), array( 'form_id' => absint( $form_id ) ), array( '%d' ) );
		foreach ( array_unique( array_map( 'absint', (array) $ids ) ) as $id ) {
			if ( $id ) $wpdb->insert( self::links_table(), array( 'form_id' => absint( $form_id ), 'integration_id' => $id, 'enabled' => 1 ), array( '%d', '%d', '%d' ) );
		}
	}

	public static function form_ids( $form_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT integration_id FROM ' . self::links_table() . ' WHERE form_id=%d AND enabled=1', absint( $form_id ) ) ) );
	}

	public static function for_form( $form_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT i.* FROM ' . self::table() . ' i INNER JOIN ' . self::links_table() . ' l ON i.id=l.integration_id WHERE l.form_id=%d AND l.enabled=1 AND i.enabled=1', absint( $form_id ) ) );
		return array_map( function( $row ) { return SmartForms_Integration_Repository::format( $row, true ); }, $rows );
	}

	private static function sanitize_config( $type, $config, $old ) {
		$config = is_array( $config ) ? $config : array();
		$out = array();
		if ( 'email' === $type ) {
			$out['mailer'] = isset( $config['mailer'] ) && 'smtp' === $config['mailer'] ? 'smtp' : 'site';
			foreach ( array( 'recipients', 'cc', 'bcc' ) as $key ) $out[ $key ] = implode( ', ', array_filter( array_map( 'sanitize_email', preg_split( '/[;,\s]+/', isset( $config[ $key ] ) ? $config[ $key ] : '' ) ) ) );
			$out['from_email'] = sanitize_email( isset( $config['from_email'] ) ? $config['from_email'] : '' );
			$out['from_name'] = sanitize_text_field( isset( $config['from_name'] ) ? $config['from_name'] : '' );
			$out['reply_to'] = sanitize_email( isset( $config['reply_to'] ) ? $config['reply_to'] : '' );
			$out['subject'] = sanitize_text_field( isset( $config['subject'] ) ? $config['subject'] : 'New submission: {form_title}' );
			$out['body'] = sanitize_textarea_field( isset( $config['body'] ) ? $config['body'] : "Entry #{entry_id}\n\n{fields}" );
			$out['html'] = ! empty( $config['html'] );
			$out['host'] = sanitize_text_field( isset( $config['host'] ) ? $config['host'] : '' );
			$out['port'] = min( 65535, max( 1, absint( isset( $config['port'] ) ? $config['port'] : 587 ) ) );
			$out['encryption'] = isset( $config['encryption'] ) && in_array( $config['encryption'], array( 'none', 'tls', 'ssl' ), true ) ? $config['encryption'] : 'tls';
			$out['username'] = sanitize_text_field( isset( $config['username'] ) ? $config['username'] : '' );
			$out['password'] = self::secret( isset( $config['password'] ) ? $config['password'] : '', isset( $old['password'] ) ? $old['password'] : '' );
		} else {
			$out['url'] = esc_url_raw( isset( $config['url'] ) ? $config['url'] : '' );
			$out['auth_type'] = isset( $config['auth_type'] ) && in_array( $config['auth_type'], array( 'none', 'bearer', 'api_key', 'basic', 'hmac' ), true ) ? $config['auth_type'] : 'none';
			$out['header_name'] = preg_replace( '/[^A-Za-z0-9-]/', '', isset( $config['header_name'] ) ? $config['header_name'] : 'X-API-Key' );
			$out['username'] = sanitize_text_field( isset( $config['username'] ) ? $config['username'] : '' );
			$out['secret'] = self::secret( isset( $config['secret'] ) ? $config['secret'] : '', isset( $old['secret'] ) ? $old['secret'] : '' );
			$out['timeout'] = min( 30, max( 3, absint( isset( $config['timeout'] ) ? $config['timeout'] : 10 ) ) );
		}
		return $out;
	}

	private static function secret( $new, $old ) { return '' !== trim( (string) $new ) ? SmartForms_Secrets::encrypt( trim( (string) $new ) ) : $old; }
	private static function format( $row, $include_secrets ) {
		$config = json_decode( $row->config, true ) ?: array();
		foreach ( array( 'password', 'secret' ) as $key ) {
			if ( isset( $config[ $key ] ) ) {
				if ( $include_secrets ) $config[ $key ] = SmartForms_Secrets::decrypt( $config[ $key ] );
				else { $config[ $key . '_configured' ] = '' !== $config[ $key ]; unset( $config[ $key ] ); }
			}
		}
		return array( 'id' => (int) $row->id, 'name' => $row->name, 'type' => $row->type, 'enabled' => (bool) $row->enabled, 'config' => $config, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at );
	}
}
