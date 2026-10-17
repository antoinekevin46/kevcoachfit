<?php
// Run against local WordPress only; all external effects are intercepted.
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('127.0.0.1','localhost'),true)){WP_CLI::error('Local tests only.');}
global $wpdb;
$original=kcfr_settings();$schema=get_option('kcfr_schema_version');$sessions=array();$requests=array();$checks=0;$error=null;$mail=0;
$old_outbox=$wpdb->get_col("SELECT id FROM {$wpdb->prefix}kcfr_outbox");
$markers=array();foreach(array('bookings','requests') as $t){$markers[$t]=$wpdb->get_results("SELECT id,is_test FROM {$wpdb->prefix}kcfr_$t",ARRAY_A);}
$assert=function($ok,$message)use(&$checks){$checks++;if(!$ok){throw new Exception($message);}};
$filter=function($pre,$args)use(&$mail){$mail++;return true;};add_filter('pre_wp_mail',$filter,10,2);
$network=function($pre){return new WP_Error('local_test_network','External calls blocked by local test.');};add_filter('pre_http_request',$network);
$now=time();$token=fn($b)=>explode('.',wp_parse_url($b['management_url'],PHP_URL_FRAGMENT),2)[1];
$mode=function($live)use($original){$s=$original;$s['live']=$live;$s['google_sync']=false;update_option('kcfr_settings',$s,false);};
$create=function($capacity=1)use(&$sessions,$wpdb,$now){$wpdb->insert($wpdb->prefix.'kcfr_sessions',array('series'=>'test-mode-'.bin2hex(random_bytes(6)),'title'=>'Essai séparé','venue'=>'Local uniquement','starts_at'=>kcfr_utc($now+8*HOUR_IN_SECONDS),'duration'=>45,'capacity'=>$capacity));$sessions[]=(int)$wpdb->insert_id;return (int)$wpdb->insert_id;};
try{
 $mode(false);$id=$create();$customer=array('name'=>'Essai local','email'=>'mode-test@example.invalid');
 $trial=kcfr_book_session($id,$customer,$now);$assert(!is_wp_error($trial)&&$trial['status']==='reserved','private trial not reserved');
 $assert((int)$wpdb->get_var($wpdb->prepare("SELECT is_test FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d",$trial['id']))===1,'private marker missing');
 // Simulate the version 1 database and verify preservation of its existing trial.
 $wpdb->update($wpdb->prefix.'kcfr_bookings',array('is_test'=>0),array('id'=>$trial['id']));update_option('kcfr_schema_version','1');kcfr_install_workflows();
 $assert(get_option('kcfr_schema_version')==='2','schema migration not saved');
 $assert(!$wpdb->last_error,'database error during migration');
 $index=$wpdb->get_col("SHOW INDEX FROM {$wpdb->prefix}kcfr_bookings WHERE Key_name='queue_scope'",4);$assert($index===array('session_id','is_test','status','id'),'scope index missing after migration');
 $assert((int)$wpdb->get_var($wpdb->prepare("SELECT is_test FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d",$trial['id']))===1,'legacy trial not migrated');
 $assert(kcfr_manage_booking($trial['id'],$token($trial),'status',$now)['status']==='reserved','migration invalidated original management link');
 $wait=kcfr_book_session($id,array('name'=>'Attente essai','email'=>'trial-wait@example.invalid'),$now);$assert($wait['status']==='waiting','private waitlist missing');
 $mode(true);$assert(kcfr_occupancy($id)===0,'trials consume real capacity');
 $real=kcfr_book_session($id,$customer,$now);$assert(!is_wp_error($real)&&$real['status']==='reserved','trial blocks real booking for same e-mail');
 $assert((int)$wpdb->get_var($wpdb->prepare("SELECT is_test FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d",$real['id']))===0,'real marker incorrect');
 $assert(is_wp_error(kcfr_manage_booking($trial['id'],$token($trial),'cancel',$now)),'old trial key accepted in production');
 $before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox");kcfr_notice($trial['id'],'reserved');$assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox")===$before,'trial queued notification after launch');
 $wait_real=kcfr_book_session($id,array('name'=>'Attente réelle','email'=>'real-wait@example.invalid'),$now);$assert($wait_real['status']==='waiting','real waitlist missing');
 $cancel=kcfr_manage_booking($real['id'],$token($real),'cancel',$now);$assert($cancel['status']==='cancelled','real cancellation failed');
 $assert(kcfr_manage_booking($wait_real['id'],$token($wait_real),'status',$now)['status']==='offered','real FIFO promotion failed');
 $assert($wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d",$wait['id']))==='waiting','real cancellation modified private waitlist');
 $mode(false);$assert(kcfr_occupancy($id)===1,'real offers consume private test capacity');
 $assert(is_wp_error(kcfr_manage_booking($wait_real['id'],$token($wait_real),'cancel',$now)),'real booking mutable through private mode');
 // A stale, incorrectly queued test notification must still never be delivered.
 $mode(true);kcfr_enqueue('mode-regression-test-'.bin2hex(random_bytes(6)),array('type'=>'booking','booking_id'=>$trial['id'],'expected_status'=>'reserved','to'=>'never-send@example.invalid','subject'=>'Never deliver','body'=>'Local intercepted trial.'));
 kcfr_send_outbox();$assert($mail===2,'test notification was delivered or real notification lost');
 $assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox WHERE status='discarded' AND message_key LIKE %s",'mode-regression-test-%'))===1,'stale trial notification not discarded');
 $mode(false);$friday=(new DateTimeImmutable('next friday',kcfr_timezone()))->format('Y-m-d');
 $coach=kcfr_save_request($customer+array('kind'=>'coaching','date'=>$friday,'time'=>'08:00','address'=>'Adresse fictive pour test local'));
 $assert(!is_wp_error($coach),'private coaching failed');$req=(int)substr($coach['reference'],8);$requests[]=$req;
 $assert((int)$wpdb->get_var($wpdb->prepare("SELECT is_test FROM {$wpdb->prefix}kcfr_requests WHERE id=%d",$req))===1,'coaching trial not marked');
 $mode(true);$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox");kcfr_request_notice($req);$assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox")===$before,'coaching trial queued owner email after launch');
} catch(Throwable $e){$error=$e->getMessage();}
finally{
 $wpdb->query('ROLLBACK');remove_filter('pre_wp_mail',$filter,10);remove_filter('pre_http_request',$network);
 foreach($sessions as $id){$wpdb->delete($wpdb->prefix.'kcfr_bookings',array('session_id'=>$id));$wpdb->delete($wpdb->prefix.'kcfr_sessions',array('id'=>$id));}
 foreach($requests as $id){$wpdb->delete($wpdb->prefix.'kcfr_requests',array('id'=>$id));}
 foreach($markers as $table=>$rows){foreach($rows as $r){$wpdb->update($wpdb->prefix.'kcfr_'.$table,array('is_test'=>$r['is_test']),array('id'=>$r['id']));}}
 $keep=$old_outbox?implode(',',array_map('intval',$old_outbox)):'0';$wpdb->query("DELETE FROM {$wpdb->prefix}kcfr_outbox WHERE id NOT IN ($keep)");
 update_option('kcfr_settings',$original,false);update_option('kcfr_schema_version',$schema,false);
}
if($error){WP_CLI::error($error);}WP_CLI::success($checks.' trial-to-live isolation checks; migration preserved trial links; all messages intercepted.');
