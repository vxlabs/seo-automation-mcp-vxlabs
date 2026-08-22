<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SmartForms_Delivery_Repository {
	const STATUSES = array( 'queued', 'processing', 'retry', 'delivered', 'dead', 'cancelled' );
	public static function table() { global $wpdb; return $wpdb->prefix . 'smartforms_deliveries'; }

	public static function enqueue_entry( $entry_id ) {
		$entry = SmartForms_Entry_Repository::get( $entry_id );
		if ( is_wp_error( $entry ) || 'clean' !== $entry['spam_status'] ) return array();
		global $wpdb;
		$ids = array();
		foreach ( SmartForms_Integration_Repository::for_form( $entry['form_id'] ) as $integration ) {
			$key = hash( 'sha256', 'entry:' . $entry_id . ':integration:' . $integration['id'] );
			$event_id = wp_generate_uuid4();
			$now = current_time( 'mysql', true );
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::table() . ' (event_id,entry_id,integration_id,status,attempt_count,next_attempt_at,idempotency_key,created_at,updated_at) VALUES (%s,%d,%d,%s,0,%s,%s,%s,%s)', $event_id, $entry_id, $integration['id'], 'queued', $now, $key, $now, $now ) );
			$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE idempotency_key=%s', $key ) );
			if ( $id ) $ids[] = $id;
		}
		if ( $ids && ! wp_next_scheduled( 'smartforms_process_deliveries' ) ) wp_schedule_single_event( time() + 5, 'smartforms_process_deliveries' );
		return $ids;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT d.*,i.name integration_name,i.type integration_type FROM ' . self::table() . ' d LEFT JOIN ' . SmartForms_Integration_Repository::table() . ' i ON d.integration_id=i.id WHERE d.id=%d', absint( $id ) ) );
		return $row ? self::format( $row ) : new WP_Error( 'smartforms_delivery_not_found', 'Delivery not found.' );
	}

	public static function list_deliveries( $args = array() ) {
		global $wpdb;
		$where = array( '1=1' ); $params = array();
		if ( ! empty( $args['entry_id'] ) ) { $where[]='d.entry_id=%d'; $params[]=absint( $args['entry_id'] ); }
		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) { $where[]='d.status=%s'; $params[]=$args['status']; }
		$limit = isset( $args['per_page'] ) ? min( 100, max( 1, absint( $args['per_page'] ) ) ) : 50;
		$sql = 'SELECT d.*,i.name integration_name,i.type integration_type FROM ' . self::table() . ' d LEFT JOIN ' . SmartForms_Integration_Repository::table() . ' i ON d.integration_id=i.id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY d.created_at DESC LIMIT %d';
		$params[]=$limit;
		$rows=$wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		return array( 'items' => array_map( array( __CLASS__, 'format' ), $rows ) );
	}

	public static function retry( $id ) {
		global $wpdb;
		$delivery = self::get( $id );
		if ( is_wp_error( $delivery ) ) return $delivery;
		$entry = SmartForms_Entry_Repository::get( $delivery['entry_id'] );
		if ( is_wp_error( $entry ) || 'clean' !== $entry['spam_status'] ) return new WP_Error( 'smartforms_delivery_blocked', 'Only clean entries can be delivered.' );
		$wpdb->update( self::table(), array( 'status'=>'queued', 'next_attempt_at'=>current_time( 'mysql', true ), 'last_error'=>null, 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>absint( $id ) ), array( '%s','%s','%s','%s' ), array( '%d' ) );
		wp_schedule_single_event( time() + 1, 'smartforms_process_deliveries' );
		return self::get( $id );
	}

	public static function cancel_entry( $entry_id ) {
		global $wpdb;
		return $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET status='cancelled',updated_at=%s WHERE entry_id=%d AND status IN ('queued','retry')", current_time( 'mysql', true ), absint( $entry_id ) ) );
	}

	public static function stats() {
		global $wpdb; $out=array_fill_keys( self::STATUSES, 0 );
		foreach ( $wpdb->get_results( 'SELECT status,COUNT(*) total FROM ' . self::table() . ' GROUP BY status', ARRAY_A ) as $row ) if ( isset( $out[$row['status']] ) ) $out[$row['status']] = (int) $row['total'];
		return $out;
	}

	public static function format( $row ) {
		return array( 'id'=>(int)$row->id, 'event_id'=>$row->event_id, 'entry_id'=>(int)$row->entry_id, 'integration_id'=>(int)$row->integration_id, 'integration_name'=>$row->integration_name, 'integration_type'=>$row->integration_type, 'status'=>$row->status, 'attempt_count'=>(int)$row->attempt_count, 'next_attempt_at'=>$row->next_attempt_at, 'response_code'=>$row->response_code ? (int)$row->response_code : null, 'last_error'=>$row->last_error, 'created_at'=>$row->created_at, 'updated_at'=>$row->updated_at, 'delivered_at'=>$row->delivered_at );
	}
}
