<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SmartForms_Delivery_Worker {
	public static function schedules( $schedules ) { $schedules['smartforms_minute']=array( 'interval'=>60, 'display'=>'Every minute (SmartForms)' ); return $schedules; }

	public static function process_due( $limit = 20 ) {
		global $wpdb; $now=current_time( 'mysql', true );
		$ids=$wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . SmartForms_Delivery_Repository::table() . " WHERE status IN ('queued','retry') AND next_attempt_at<=%s ORDER BY next_attempt_at ASC LIMIT %d", $now, min( 100, max( 1, absint( $limit ) ) ) ) );
		foreach ( $ids as $id ) self::process_one( $id );
		return count( $ids );
	}

	private static function process_one( $id ) {
		global $wpdb; $table=SmartForms_Delivery_Repository::table();
		$claimed=$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='processing',attempt_count=attempt_count+1,updated_at=%s WHERE id=%d AND status IN ('queued','retry')", current_time( 'mysql', true ), $id ) );
		if ( ! $claimed ) return;
		$delivery=SmartForms_Delivery_Repository::get( $id ); $entry=SmartForms_Entry_Repository::get( $delivery['entry_id'] ); $integration=SmartForms_Integration_Repository::get( $delivery['integration_id'], true );
		if ( is_wp_error( $entry ) || is_wp_error( $integration ) || 'clean' !== $entry['spam_status'] || ! $integration['enabled'] ) { self::finish( $id, false, 0, 'Entry or destination is unavailable.', false ); return; }
		$result='email'===$integration['type'] ? self::email( $entry, $integration ) : self::api( $entry, $integration, $delivery );
		self::finish( $id, $result['success'], $result['code'], $result['error'], $result['retry'] );
	}

	private static function email( $entry, $integration ) {
		$c=$integration['config']; $to=isset($c['recipients'])?$c['recipients']:'';
		if ( '' === $to ) return array( 'success'=>false,'code'=>0,'error'=>'No recipient configured.','retry'=>false );
		$form=SmartForms_Form_Repository::get( $entry['form_id'] ); $subject=self::tokens( isset($c['subject'])?$c['subject']:'New submission: {form_title}', $entry, $form ); $body=self::tokens( isset($c['body'])?$c['body']:'{fields}', $entry, $form ); $headers=array();
		foreach(array('cc'=>'Cc','bcc'=>'Bcc') as $key=>$label) if(!empty($c[$key])) $headers[]=$label.': '.$c[$key];
		if(!empty($c['from_email'])) $headers[]='From: '.(!empty($c['from_name'])?$c['from_name'].' ':'').'<'.$c['from_email'].'>';
		if(!empty($c['reply_to'])) $headers[]='Reply-To: '.$c['reply_to']; if(!empty($c['html'])) $headers[]='Content-Type: text/html; charset=UTF-8';
		$smtp=null;
		if('smtp'===$c['mailer']) { $smtp=function($phpmailer) use($c){ $phpmailer->isSMTP(); $phpmailer->Host=$c['host']; $phpmailer->Port=$c['port']; $phpmailer->SMTPAuth=''!==$c['username']; $phpmailer->Username=$c['username']; $phpmailer->Password=$c['password']; $phpmailer->SMTPSecure='none'===$c['encryption']?'':$c['encryption']; $phpmailer->SMTPAutoTLS='none'!==$c['encryption']; }; add_action('phpmailer_init',$smtp); }
		$error=''; $capture=function($wp_error) use(&$error){$error=$wp_error->get_error_message();}; add_action('wp_mail_failed',$capture); $ok=wp_mail($to,$subject,$body,$headers); remove_action('wp_mail_failed',$capture); if($smtp) remove_action('phpmailer_init',$smtp);
		return array('success'=>(bool)$ok,'code'=>0,'error'=>$ok?'':($error?:'WordPress mail transport rejected the message.'),'retry'=>!$ok);
	}

	private static function api( $entry, $integration, $delivery ) {
		$c=$integration['config']; $url=isset($c['url'])?$c['url']:''; $scheme=wp_parse_url($url,PHP_URL_SCHEME);
		if('https'!==$scheme && !(defined('SMARTFORMS_ALLOW_INSECURE_SYNC')&&SMARTFORMS_ALLOW_INSECURE_SYNC)) return array('success'=>false,'code'=>0,'error'=>'API destinations must use HTTPS.','retry'=>false);
		$body=wp_json_encode(array('event'=>'smartforms.entry.clean','event_id'=>$delivery['event_id'],'entry'=>$entry)); $headers=array('Content-Type'=>'application/json','Idempotency-Key'=>hash('sha256','smartforms:'.$delivery['id']),'X-SmartForms-Event-ID'=>$delivery['event_id']);
		$auth=isset($c['auth_type'])?$c['auth_type']:'none'; if('bearer'===$auth)$headers['Authorization']='Bearer '.$c['secret']; elseif('api_key'===$auth)$headers[$c['header_name']]=$c['secret']; elseif('basic'===$auth)$headers['Authorization']='Basic '.base64_encode($c['username'].':'.$c['secret']); elseif('hmac'===$auth){$ts=(string)time();$headers['X-SmartForms-Timestamp']=$ts;$headers['X-SmartForms-Signature']='sha256='.hash_hmac('sha256',$ts.'.'.$body,$c['secret']);}
		$response=wp_safe_remote_post($url,array('timeout'=>isset($c['timeout'])?$c['timeout']:10,'redirection'=>0,'headers'=>$headers,'body'=>$body)); if(is_wp_error($response))return array('success'=>false,'code'=>0,'error'=>$response->get_error_message(),'retry'=>true); $code=wp_remote_retrieve_response_code($response); return array('success'=>$code>=200&&$code<300,'code'=>$code,'error'=>$code>=200&&$code<300?'':'API returned HTTP '.$code.'.','retry'=>in_array($code,array(408,429),true)||$code>=500);
	}

	private static function tokens( $template, $entry, $form ) { $lines=array(); foreach($entry['fields'] as $field_id=>$field){$v=is_array($field['value'])?implode(', ',$field['value']):(is_bool($field['value'])?($field['value']?'Yes':'No'):$field['value']);$lines[]=$field['label'].': '.$v;$template=str_replace('{field:'.$field_id.'}',$v,$template);} return strtr($template,array('{form_title}'=>is_wp_error($form)?$entry['form_title']:$form['title'],'{entry_id}'=>$entry['id'],'{fields}'=>implode("\n",$lines))); }
	private static function finish( $id, $success, $code, $error, $retryable ) { global $wpdb; $delivery=SmartForms_Delivery_Repository::get($id); $attempt=$delivery['attempt_count']; $delays=array(60,300,1800,7200,43200); $retry=$retryable&&$attempt<=count($delays); $status=$success?'delivered':($retry?'retry':'dead'); $next=$retry?gmdate('Y-m-d H:i:s',time()+$delays[$attempt-1]):null; $wpdb->update(SmartForms_Delivery_Repository::table(),array('status'=>$status,'response_code'=>$code?:null,'last_error'=>$success?null:substr($error,0,2000),'next_attempt_at'=>$next,'updated_at'=>current_time('mysql',true),'delivered_at'=>$success?current_time('mysql',true):null),array('id'=>$id),array('%s','%d','%s','%s','%s','%s'),array('%d')); }
}
