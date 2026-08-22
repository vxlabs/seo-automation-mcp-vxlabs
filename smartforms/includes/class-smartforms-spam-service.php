<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SmartForms_Spam_Service {
	const STATUSES = array( 'pending', 'clean', 'spam' );

	public static function initial_assessment( $entry_id ) {
		$entry = SmartForms_Entry_Repository::get( $entry_id );
		if ( is_wp_error( $entry ) ) return $entry;
		$text = '';
		foreach ( $entry['fields'] as $field ) $text .= ' ' . ( is_array( $field['value'] ) ? implode( ' ', $field['value'] ) : $field['value'] );
		$score = 0; $signals = array();
		$links = preg_match_all( '~https?://|www\.~i', $text );
		if ( $links > 2 ) { $score += min( 60, $links * 15 ); $signals[] = 'many links'; }
		if ( preg_match( '/\b(viagra|casino|crypto giveaway|seo service|loan offer)\b/i', $text ) ) { $score += 65; $signals[]='known spam phrase'; }
		if ( preg_match( '/(.)\1{12,}/', $text ) ) { $score += 20; $signals[]='repeated characters'; }
		$score = min( 100, $score );
		if ( $score >= 80 ) return self::classify( $entry_id, 'spam', $score, 0.95, implode( ', ', $signals ), 'rules' );
		global $wpdb;
		$wpdb->update( SmartForms_Entry_Repository::table(), array( 'spam_score'=>$score, 'spam_reason'=>implode( ', ', $signals ), 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>$entry_id ), array( '%f','%s','%s' ), array( '%d' ) );
		return SmartForms_Entry_Repository::get( $entry_id );
	}

	public static function classify( $entry_id, $verdict, $score = null, $confidence = null, $reason = '', $classifier = 'manual' ) {
		if ( ! in_array( $verdict, array( 'clean', 'spam' ), true ) ) return new WP_Error( 'smartforms_invalid_verdict', 'Verdict must be clean or spam.' );
		$entry = SmartForms_Entry_Repository::get( $entry_id );
		if ( is_wp_error( $entry ) ) return $entry;
		global $wpdb;
		$data = array( 'spam_status'=>$verdict, 'spam_score'=>null === $score ? null : max( 0, min( 100, (float)$score ) ), 'spam_confidence'=>null === $confidence ? null : max( 0, min( 1, (float)$confidence ) ), 'spam_reason'=>sanitize_textarea_field( $reason ), 'classifier'=>substr( sanitize_text_field( $classifier ), 0, 100 ), 'classified_at'=>current_time( 'mysql', true ), 'classification_lease_until'=>null, 'classification_lease_owner'=>null, 'updated_at'=>current_time( 'mysql', true ) );
		$wpdb->update( SmartForms_Entry_Repository::table(), $data, array( 'id'=>absint( $entry_id ) ), array( '%s','%f','%f','%s','%s','%s','%s','%s','%s' ), array( '%d' ) );
		SmartForms_Entry_Repository::add_system_event( $entry_id, 'spam', $entry['spam_status'], $verdict, trim( $classifier . ': ' . $reason ) );
		if ( 'clean' === $verdict ) SmartForms_Delivery_Repository::enqueue_entry( $entry_id );
		else SmartForms_Delivery_Repository::cancel_entry( $entry_id );
		return SmartForms_Entry_Repository::get( $entry_id );
	}

	public static function claim( $limit = 10, $owner = 'mcp', $lease_seconds = 300 ) {
		global $wpdb; $limit=min( 50, max( 1, absint( $limit ) ) ); $now=current_time( 'mysql', true ); $until=gmdate( 'Y-m-d H:i:s', time()+min( 1800, max( 60, absint( $lease_seconds ) ) ) );
		$ids=$wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . SmartForms_Entry_Repository::table() . " WHERE spam_status='pending' AND (classification_lease_until IS NULL OR classification_lease_until<%s) ORDER BY submitted_at ASC LIMIT %d", $now, $limit ) );
		$items=array();
		foreach ( $ids as $id ) {
			$updated=$wpdb->query( $wpdb->prepare( 'UPDATE ' . SmartForms_Entry_Repository::table() . " SET classification_lease_until=%s,classification_lease_owner=%s WHERE id=%d AND spam_status='pending' AND (classification_lease_until IS NULL OR classification_lease_until<%s)", $until, substr( sanitize_text_field( $owner ), 0, 100 ), $id, $now ) );
			if ( $updated ) { $entry=SmartForms_Entry_Repository::get( $id ); if ( ! is_wp_error( $entry ) ) $items[]=$entry; }
		}
		return array( 'items'=>$items, 'lease_until'=>$until );
	}
}
