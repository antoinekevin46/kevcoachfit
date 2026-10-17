<?php
// Local WordPress only. Google and e-mail are intercepted; no external message is sent.
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('127.0.0.1','localhost'),true)){WP_CLI::error('Local tests only.');}
global $wpdb,$count;$count=0;$sessions=array();$book_ids=array();$request_ids=array();$google_calls=array();$mail_count=0;
function kcfr_assert_test($v,$message){global $count;$count++;if(!$v){throw new Exception($message);}}
$cfg=get_option('kcfr_settings');$old_token=get_option('kcfr_google_tokens');$old_secret=get_option('kcfr_google_client_secret');
$old_outbox=$wpdb->get_col("SELECT id FROM {$wpdb->prefix}kcfr_outbox");
$now=time();$error=null;
$make=function($starts,$capacity=15,$series='test-reservations')use(&$sessions,$wpdb){$wpdb->insert($wpdb->prefix.'kcfr_sessions',array('series'=>$series.'-'.bin2hex(random_bytes(3)),'title'=>'Cours test','venue'=>'Calès · test local','starts_at'=>kcfr_utc($starts),'duration'=>45,'capacity'=>$capacity));$sessions[]=$wpdb->insert_id;return (int)$wpdb->insert_id;};
$customer=fn($n)=>array('name'=>'Test '.$n,'email'=>'client'.$n.'@example.invalid');
$key=fn($b)=>explode('.',(string)wp_parse_url($b['management_url'],PHP_URL_FRAGMENT),2)[1];
$mail_filter=function($value,$args)use(&$mail_count){$mail_count++;return true;};add_filter('pre_wp_mail',$mail_filter,10,2);
$busy=false;$busy_error=false;$token_refresh=0;$events=array();
$http=function($pre,$args,$url)use(&$google_calls,&$busy,&$busy_error,&$token_refresh,&$events){
 if(!str_starts_with($url,'https://oauth2.googleapis.com/')&&!str_starts_with($url,'https://www.googleapis.com/calendar/')){return $pre;}
 $google_calls[]=array('method'=>$args['method']??'GET','url'=>$url);$code=200;$body=array();
 if(str_contains($url,'oauth2.googleapis.com')){$token_refresh++;$body=array('access_token'=>'local-test-access','expires_in'=>3600);}
 elseif(str_ends_with($url,'freeBusy')){$body=array('calendars'=>array('primary'=>$busy_error?array('errors'=>array(array('reason'=>'notFound'))):array('busy'=>$busy?array(array('start'=>gmdate('c',time()),'end'=>gmdate('c',time()+60))):array())));}
 else{
  $method=$args['method']??'GET';$path=wp_parse_url($url,PHP_URL_PATH);$id=basename($path);
  if($method==='GET'){$code=isset($events[$id])?200:404;$body=$events[$id]??array('error'=>array('code'=>404));}
  elseif($method==='POST'){$data=json_decode($args['body'],true);$events[$data['id']]=$data;$body=$data;}
  elseif($method==='PATCH'){$data=json_decode($args['body'],true);$events[$id]=array_merge($events[$id],$data);$body=$events[$id];}
 }
 return array('response'=>array('code'=>$code),'headers'=>array(),'body'=>wp_json_encode($body));
};add_filter('pre_http_request',$http,10,3);
try{
 wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
 $enc=kcfr_encrypt('local token é');kcfr_assert_test(kcfr_decrypt($enc)==='local token é','encrypted token round trip');kcfr_assert_test(kcfr_decrypt(substr($enc,0,-8).'AAAAAAAA')===false,'tampered ciphertext accepted');
 $s=$cfg;$s['live']=false;$s['google_sync']=false;update_option('kcfr_settings',$s,false);
 wp_set_current_user(0);$r=rest_do_request(new WP_REST_Request('GET','/kcfr/v1/sessions'));kcfr_assert_test($r->get_status()===401||$r->get_status()===403,'private sessions exposed');wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
 $session=$make($now+5*HOUR_IN_SECONDS);$bookings=array();
 for($i=1;$i<=17;$i++){$b=kcfr_book_session($session,$customer($i),$now);kcfr_assert_test(!is_wp_error($b),'booking failed');$bookings[]=$b;$book_ids[]=$b['id'];kcfr_assert_test($b['status']===($i<=15?'reserved':'waiting'),'capacity exceeded or waitlist missing');}
 kcfr_assert_test(kcfr_occupancy($session)===15,'capacity not 15');
 $dup=kcfr_book_session($session,$customer(1),$now);kcfr_assert_test(is_wp_error($dup)&&$dup->get_error_code()==='duplicate','duplicate accepted');
 kcfr_assert_test(is_wp_error(kcfr_manage_booking($bookings[0]['id'],str_repeat('0',64),'status',$now)),'invalid management key accepted');
 $cancel=kcfr_manage_booking($bookings[0]['id'],$key($bookings[0]),'cancel',$now+3600);kcfr_assert_test($cancel['status']==='cancelled','cancellation failed');
 $offer=kcfr_manage_booking($bookings[15]['id'],$key($bookings[15]),'status',$now+3600);kcfr_assert_test($offer['status']==='offered','FIFO first not offered');kcfr_assert_test(strtotime($offer['offered_until'])===$now+10800,'two-hour window wrong');
 $next=kcfr_manage_booking($bookings[16]['id'],$key($bookings[16]),'status',$now+10801);kcfr_assert_test($next['status']==='offered','next waiting participant not promoted');kcfr_assert_test(strtotime($next['offered_until'])===$now+18000,'offer extends after course starts');
 $confirm=kcfr_manage_booking($bookings[16]['id'],$key($bookings[16]),'confirm',$now+10802);kcfr_assert_test($confirm['status']==='reserved','offer confirmation failed');kcfr_assert_test(kcfr_occupancy($session)===15,'offer confirmation changed capacity');
 kcfr_assert_test(is_wp_error(kcfr_manage_booking($bookings[16]['id'],$key($bookings[16]),'cancel',$now+10802)),'late cancellation of reserved seat accepted');
 $extra=kcfr_book_session($session,$customer(18),$now+10802);$book_ids[]=$extra['id'];kcfr_assert_test($extra['status']==='waiting','full class accepted extra seat');
 $withdraw=kcfr_manage_booking($extra['id'],$key($extra),'cancel',$now+10803);kcfr_assert_test($withdraw['status']==='cancelled','waitlist withdrawal incorrectly blocked by reserved-seat cutoff');
 kcfr_assert_test(is_wp_error(kcfr_book_session($session,$customer(19),$now+18000)),'booking at or after started course accepted');
 $change=kcfr_change_capacity($session,14);kcfr_assert_test(is_wp_error($change),'capacity reduced below occupied');kcfr_assert_test(!is_wp_error(kcfr_change_capacity($session,16)),'capacity increase failed');
 kcfr_assert_test(kcfr_collective_closed('2027-07-05 15:00:00')&&kcfr_collective_closed('2027-08-05 15:00:00'),'summer not closed');
 $s['closed_dates']=array('2026-11-02');update_option('kcfr_settings',$s,false);kcfr_assert_test(kcfr_collective_closed('2026-11-02 16:00:00'),'custom closure ignored');
 $friday=(new DateTimeImmutable('next friday',kcfr_timezone()))->format('Y-m-d');$data=$customer(99)+array('kind'=>'coaching','date'=>$friday,'time'=>'23:30','address'=>'Adresse fictive de test local');
 $req=kcfr_save_request($data);kcfr_assert_test(is_wp_error($req)&&$req->get_error_code()==='hours','midnight rollover bypasses Friday closing');
 $data['time']='18:00';$req=kcfr_save_request($data);kcfr_assert_test(!is_wp_error($req),'valid 60-minute coaching request rejected');$request_ids[]=(int)substr($req['reference'],8);
 $data['time']='18:01';kcfr_assert_test(is_wp_error(kcfr_save_request($data)),'coaching beyond Friday closing accepted');
 kcfr_assert_test((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox")===count($old_outbox),'test mode queued real notifications');
 $s['live']=true;update_option('kcfr_settings',$s,false);$email_session=$make($now+4*HOUR_IN_SECONDS,1);$email=kcfr_book_session($email_session,$customer(100),$now);$book_ids[]=$email['id'];
 kcfr_assert_test(!is_wp_error($email),'live booking failed');$row=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}kcfr_outbox ORDER BY id DESC LIMIT 1");$payload=json_decode(kcfr_decrypt($row->payload),true);
 kcfr_assert_test(str_contains($payload['body'],'06 74 80 67 07')&&str_contains($payload['body'],'tapis'),'first visit advice missing');kcfr_assert_test(!str_contains($row->payload,$key($email)),'private management token not encrypted in outbox');
 kcfr_notice($email['id'],'reserved');kcfr_assert_test((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox WHERE message_key='booking-".$email['id']."-reserved'")===1,'duplicate confirmation queued');
 kcfr_send_outbox();kcfr_assert_test($mail_count===1,'mail worker did not send once');kcfr_send_outbox();kcfr_assert_test($mail_count===1,'mail worker duplicated mail');
 $google_session=$make((new DateTimeImmutable('2026-10-26 17:00',kcfr_timezone()))->getTimestamp(),15,'google-local-fixture');
 update_option('kcfr_google_client_secret',kcfr_encrypt('local-test-secret'),false);update_option('kcfr_google_tokens',kcfr_encrypt(wp_json_encode(array('access_token'=>'local-old','refresh_token'=>'local-refresh','expires_at'=>0))),false);
 kcfr_assert_test(kcfr_google_token()==='local-test-access'&&$token_refresh===1,'OAuth refresh failed');kcfr_assert_test(kcfr_google_token()==='local-test-access'&&$token_refresh===1,'unnecessary OAuth refresh');
 kcfr_assert_test(kcfr_google_busy($now,$now+3600)===false,'free Google slot not recognized');$busy=true;kcfr_assert_test(kcfr_google_busy($now,$now+3600)===true,'busy Google slot not recognized');$busy_error=true;kcfr_assert_test(is_wp_error(kcfr_google_busy($now,$now+3600)),'Google calendar error treated as free');$busy_error=false;
 kcfr_assert_test(!is_wp_error(kcfr_google_upsert_session($google_session)),'Google event creation failed');kcfr_assert_test(count($events)===1,'wrong Google event count');$event=reset($events);kcfr_assert_test(str_starts_with($event['start']['dateTime'],'2026-10-26T16:00:00'),'winter timezone off by one hour');kcfr_assert_test(!isset($event['attendees'])&&!str_contains(wp_json_encode($event),'example.invalid'),'participant contacts sent to Google');
 kcfr_assert_test(!is_wp_error(kcfr_google_upsert_session($google_session))&&count($events)===1,'repeat Google sync duplicates event');
 $state=bin2hex(random_bytes(32));set_transient('kcfr_oauth_'.hash('sha256',$state),get_current_user_id(),600);kcfr_assert_test(kcfr_oauth_state($state),'valid OAuth state rejected');kcfr_assert_test(!kcfr_oauth_state($state),'OAuth state replay accepted');kcfr_assert_test(!kcfr_oauth_state('invalid'),'malformed OAuth state accepted');
 $s['live']=false;update_option('kcfr_settings',$s,false);$h=kcfr_health();kcfr_assert_test(!str_contains(wp_json_encode($h),'local-refresh')&&!isset($h['google_client_secret']),'Google secret exposed by health');
 $guard=kcfr_update_settings(array('live'=>true,'mail_verified'=>false));kcfr_assert_test(is_wp_error($guard)&&!kcfr_live(),'opening before mail verification allowed');
} catch(Throwable $e){$error=$e->getMessage();}
finally{
 $wpdb->query('ROLLBACK');remove_filter('pre_http_request',$http,10);remove_filter('pre_wp_mail',$mail_filter,10);
 foreach($sessions as $id){$wpdb->delete($wpdb->prefix.'kcfr_bookings',array('session_id'=>$id));$wpdb->delete($wpdb->prefix.'kcfr_sessions',array('id'=>$id));}
 foreach($request_ids as $id){$wpdb->delete($wpdb->prefix.'kcfr_requests',array('id'=>$id));}
 $keep=$old_outbox?implode(',',array_map('intval',$old_outbox)):'0';$wpdb->query("DELETE FROM {$wpdb->prefix}kcfr_outbox WHERE id NOT IN ($keep)");
 update_option('kcfr_settings',$cfg,false);if($old_token===false){delete_option('kcfr_google_tokens');}else{update_option('kcfr_google_tokens',$old_token,false);}if($old_secret===false){delete_option('kcfr_google_client_secret');}else{update_option('kcfr_google_client_secret',$old_secret,false);}
}
if($error){WP_CLI::error($error);}if($count<50){WP_CLI::error('Test count is incomplete.');}WP_CLI::success($count.' reservation, waitlist, privacy, notification, OAuth and Google Calendar checks; all e-mails and Google HTTP calls intercepted.');
