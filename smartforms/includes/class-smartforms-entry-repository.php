<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Entry_Repository {

	const STATUSES = array( 'new', 'read', 'in_progress', 'processed', 'spam', 'trash' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'smartforms_entries';
	}

	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'smartforms_entry_events';
	}

	public static function create( $form, $payload, $request ) {
		global $wpdb;
		$global  = wp_parse_args( get_option( 'smartforms_settings', array() ), SmartForms_Form_Repository::default_global_settings() );
		$ip_hash = null;
		if ( ! empty( $global['ip_logging'] ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_hash = hash_hmac( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), wp_salt( 'auth' ) );
		}
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			self::table(),
			array(
				'form_id'         => $form['id'],
				'form_version'    => isset( $form['schema']['version'] ) ? absint( $form['schema']['version'] ) : 1,
				'status'          => 'new',
				'spam_status'     => 'pending',
				'payload'         => wp_json_encode( $payload ),
				'schema_snapshot' => wp_json_encode( $form['schema'] ),
				'source_url'      => esc_url_raw( $request->get_param( 'source_url' ) ),
				'user_id'         => get_current_user_id() ?: null,
				'ip_hash'         => $ip_hash,
				'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
				'submitted_at'    => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'smartforms_db_error', 'The entry could not be stored.' );
		}
		return self::get( $wpdb->insert_id );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ) );
		if ( ! $row ) {
			return new WP_Error( 'smartforms_entry_not_found', 'Entry not found.' );
		}
		return self::format( $row );
	}

	public static function list_entries( $args = array() ) {
		global $wpdb;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, absint( $args['per_page'] ) ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$where    = array( '1=1' );
		$params   = array();

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$params[] = absint( $args['form_id'] );
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['spam_status'] ) && in_array( $args['spam_status'], SmartForms_Spam_Service::STATUSES, true ) ) {
			$where[] = 'spam_status = %s';
			$params[] = $args['spam_status'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'submitted_at >= %s';
			$params[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'submitted_at <= %s';
			$params[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . $where_sql;
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );
		$sql       = 'SELECT * FROM ' . self::table() . ' WHERE ' . $where_sql . ' ORDER BY submitted_at DESC LIMIT %d OFFSET %d';
		$query_params   = array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) );
		$rows      = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );

		return array(
			'items'     => array_map( array( __CLASS__, 'format' ), $rows ),
			'total'     => $total,
			'has_more'  => $page * $per_page < $total,
			'next_page' => $page * $per_page < $total ? $page + 1 : null,
		);
	}

	public static function count( $args = array() ) {
		$result = self::list_entries( array_merge( $args, array( 'per_page' => 1 ) ) );
		return $result['total'];
	}

	public static function update_status( $id, $status, $user_id = null ) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new WP_Error( 'smartforms_invalid_status', 'Invalid entry status.' );
		}
		$entry = self::get( $id );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array( 'status' => $status, 'processed_by' => $user_id ?: get_current_user_id(), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $id ) ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		self::add_event( $id, 'status', $entry['status'], $status, '', $user_id );
		return self::get( $id );
	}

	public static function add_note( $id, $note, $user_id = null ) {
		$entry = self::get( $id );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		self::add_event( $id, 'note', null, null, sanitize_textarea_field( $note ), $user_id );
		return self::events( $id );
	}

	private static function add_event( $id, $type, $old, $new, $note, $user_id ) {
		global $wpdb;
		$wpdb->insert(
			self::events_table(),
			array( 'entry_id' => absint( $id ), 'event_type' => $type, 'old_status' => $old, 'new_status' => $new, 'note' => $note, 'user_id' => $user_id ?: get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	public static function add_system_event( $id, $type, $old, $new, $note = '' ) {
		self::add_event( $id, $type, $old, $new, sanitize_textarea_field( $note ), get_current_user_id() );
	}

	public static function events( $id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::events_table() . ' WHERE entry_id = %d ORDER BY created_at DESC', absint( $id ) ), ARRAY_A );
		return array_map(
			function ( $row ) {
				$row['id']       = (int) $row['id'];
				$row['entry_id'] = (int) $row['entry_id'];
				$row['user_id']  = $row['user_id'] ? (int) $row['user_id'] : null;
				return $row;
			},
			$rows
		);
	}

	public static function stats( $form_id = 0 ) {
		global $wpdb;
		$where = $form_id ? $wpdb->prepare( ' WHERE form_id = %d', absint( $form_id ) ) : '';
		$rows  = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . self::table() . $where . ' GROUP BY status', ARRAY_A );
		$out   = array_fill_keys( self::STATUSES, 0 );
		foreach ( $rows as $row ) {
			$out[ $row['status'] ] = (int) $row['total'];
		}
		$out['total'] = array_sum( $out );
		return $out;
	}

	public static function purge_expired() {
		$settings = wp_parse_args( get_option( 'smartforms_settings', array() ), SmartForms_Form_Repository::default_global_settings() );
		$days     = isset( $settings['retain_days'] ) ? absint( $settings['retain_days'] ) : 0;
		if ( 0 === $days ) {
			return 0;
		}
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days );
		$ids    = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE submitted_at < %s LIMIT 1000', $cutoff ) );
		if ( ! $ids ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::events_table() . " WHERE entry_id IN ({$placeholders})", $ids ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . SmartForms_Delivery_Repository::table() . " WHERE entry_id IN ({$placeholders})", $ids ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . " WHERE id IN ({$placeholders})", $ids ) );
		return count( $ids );
	}

	private static function format( $row ) {
		$form = get_post( $row->form_id );
		return array(
			'id'              => (int) $row->id,
			'form_id'         => (int) $row->form_id,
			'form_title'      => $form ? $form->post_title : '(deleted form)',
			'form_version'    => (int) $row->form_version,
			'status'          => $row->status,
			'spam_status'     => isset( $row->spam_status ) ? $row->spam_status : 'pending',
			'spam_score'      => isset( $row->spam_score ) && null !== $row->spam_score ? (float) $row->spam_score : null,
			'spam_confidence' => isset( $row->spam_confidence ) && null !== $row->spam_confidence ? (float) $row->spam_confidence : null,
			'spam_reason'     => isset( $row->spam_reason ) ? $row->spam_reason : '',
			'classifier'      => isset( $row->classifier ) ? $row->classifier : '',
			'classified_at'   => isset( $row->classified_at ) ? $row->classified_at : null,
			'classification_lease_until' => isset( $row->classification_lease_until ) ? $row->classification_lease_until : null,
			'fields'          => json_decode( $row->payload, true ) ?: array(),
			'schema_snapshot' => json_decode( $row->schema_snapshot, true ) ?: array(),
			'source_url'      => $row->source_url,
			'user_id'         => $row->user_id ? (int) $row->user_id : null,
			'submitted_at'    => $row->submitted_at,
			'updated_at'      => $row->updated_at,
		);
	}
}
