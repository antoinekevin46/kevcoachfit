<?php
/**
 * Plugin Name: KevCoachFit — réservations et agenda
 * Description: Cours à 15 places, attente individuelle, demandes de coaching et connexion Google Agenda. Paiement sur place.
 * Version: 1.0.1
 * Requires PHP: 8.0
 * Requires at least: 6.6
 */
defined('ABSPATH')||exit;
function kcfr_settings(){return array_merge(array('live'=>false,'mail_verified'=>false,'cron_verified'=>false,'booking_page_id'=>0,'manage_page_id'=>0,'closed_dates'=>array(),'validated_until'=>'2026-12-20','google_client_id'=>'','google_calendar_id'=>'primary','google_busy_calendars'=>array('primary'),'google_sync'=>false),get_option('kcfr_settings',array()));}
function kcfr_live(){return !empty(kcfr_settings()['live']);}
function kcfr_manage_url(){ $id=kcfr_settings()['manage_page_id'];return $id?(kcfr_live()?get_permalink($id):add_query_arg(array('page_id'=>$id,'preview'=>'true'),home_url('/'))):home_url('/'); }
function kcfr_encrypt($text){
 if(!function_exists('openssl_encrypt')){throw new RuntimeException('OpenSSL est requis pour protéger les données de connexion.');}
 $iv=random_bytes(12);$tag='';$key=hash('sha256',wp_salt('auth').'kcfr-v1',true);
 $data=openssl_encrypt($text,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
 if($data===false){throw new RuntimeException('Chiffrement indisponible.');}
 return base64_encode($iv.$tag.$data);
}
function kcfr_decrypt($text){
 $raw=base64_decode($text,true);if($raw===false||strlen($raw)<28){return false;}
 return openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth').'kcfr-v1',true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
}
require_once __DIR__.'/engine.php';
require_once __DIR__.'/notifications.php';
require_once __DIR__.'/google.php';
require_once __DIR__.'/views.php';
require_once __DIR__.'/admin.php';
register_activation_hook(__FILE__,function(){
 kcfr_install_workflows();kcfr_install_outbox();
 if(!get_option('kcfr_settings')){
  $settings=kcfr_settings();
  foreach(array('booking_page_id'=>array('Réserver — test fonctionnel','<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Réserver avec Vitali’Van</h1><!-- /wp:heading -->\n[kcfr_booking]\n[kcfr_coaching]'),'manage_page_id'=>array('Mon inscription — test fonctionnel','<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Mon inscription Vitali’Van</h1><!-- /wp:heading -->\n[kcfr_manage]')) as $key=>$page){
   $id=wp_insert_post(array('post_type'=>'page','post_status'=>'draft','post_title'=>$page[0],'post_content'=>str_replace('\\n',"\n\n",$page[1]),'comment_status'=>'closed','meta_input'=>array('_kcfr_setup'=>'1')));
   if($id&&!is_wp_error($id)){$settings[$key]=$id;}
  }
  update_option('kcfr_settings',$settings,false);
 }
 kcfr_seed_sessions();
 if(!wp_next_scheduled('kcfr_tick')){wp_schedule_event(time()+60,'kcfr_minute','kcfr_tick');}
});
register_deactivation_hook(__FILE__,function(){wp_clear_scheduled_hook('kcfr_tick');});
add_action('plugins_loaded',function(){if(get_option('kcfr_schema_version')!=='2'){kcfr_install_workflows();}});
add_filter('cron_schedules',function($s){$s['kcfr_minute']=array('interval'=>60,'display'=>'KevCoachFit : chaque minute');return $s;});
add_action('kcfr_tick','kcfr_tick');
function kcfr_tick(){
 global $wpdb;update_option('kcfr_last_worker_run',time(),false);
 $today=wp_date('Y-m-d',time(),kcfr_timezone());
 if(get_option('kcfr_last_seed_day')!==$today){kcfr_seed_sessions();update_option('kcfr_last_seed_day',$today,false);}
 $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}kcfr_bookings b JOIN {$wpdb->prefix}kcfr_sessions s ON s.id=b.session_id SET b.status='expired' WHERE s.starts_at<=%s AND b.is_test=%d AND b.status IN ('waiting','offered')",kcfr_utc(time()),kcfr_test_scope()));
 $ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT s.id FROM {$wpdb->prefix}kcfr_sessions s JOIN {$wpdb->prefix}kcfr_bookings b ON b.session_id=s.id WHERE s.starts_at>%s AND b.is_test=%d AND b.status IN ('waiting','offered') ORDER BY s.starts_at LIMIT 100",kcfr_utc(time()),kcfr_test_scope()));
 foreach($ids as $id){$s=kcfr_session_lock($id);if($s){kcfr_refresh_locked($s,time());}$wpdb->query('COMMIT');}
 kcfr_send_outbox();
}
function kcfr_permission($r){
 if(!kcfr_live()&&!current_user_can('manage_options')){return new WP_Error('private','Les réservations ne sont pas encore ouvertes.',array('status'=>403));}
 if(!current_user_can('manage_options')&&!wp_verify_nonce($r->get_header('X-KCFR-Nonce'),'kcfr_forms')){return new WP_Error('nonce','Rechargez la page puis réessayez.',array('status'=>403));}
 if($r->get_param('website')){return new WP_Error('spam','Demande refusée.',array('status'=>400));}
 if(!current_user_can('manage_options')&&$r->get_method()!=='GET'){
  $key='kcfr_rate_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'',wp_salt());$n=(int)get_transient($key);
  if($n>=30){return new WP_Error('rate','Trop de demandes. Réessayez plus tard.',array('status'=>429));}set_transient($key,$n+1,HOUR_IN_SECONDS);
 }
 return true;
}
function kcfr_sessions(){
 global $wpdb;
 return array_map(function($s){return array('id'=>(int)$s->id,'title'=>$s->title,'venue'=>$s->venue,'starts_at'=>gmdate('c',kcfr_timestamp($s->starts_at)),'duration'=>(int)$s->duration,'capacity'=>(int)$s->capacity,'free'=>max(0,$s->capacity-kcfr_occupancy($s->id)));},array_filter($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcfr_sessions WHERE starts_at>%s ORDER BY starts_at LIMIT 100",kcfr_utc(time()))),fn($s)=>!kcfr_collective_closed($s->starts_at)));
}
add_action('rest_api_init',function(){
 register_rest_route('kcfr/v1','/sessions',array('methods'=>'GET','permission_callback'=>'kcfr_permission','callback'=>fn()=>kcfr_sessions()));
 foreach(array('book','manage','request') as $action){
  register_rest_route('kcfr/v1','/'.$action,array('methods'=>'POST','permission_callback'=>'kcfr_permission','args'=>array('session'=>array('type'=>'integer'),'id'=>array('type'=>'integer'),'name'=>array('type'=>'string'),'email'=>array('type'=>'string'),'key'=>array('type'=>'string'),'operation'=>array('type'=>'string'),'date'=>array('type'=>'string'),'time'=>array('type'=>'string'),'kind'=>array('type'=>'string'),'address'=>array('type'=>'string'),'phone'=>array('type'=>'string'),'note'=>array('type'=>'string'),'website'=>array('type'=>'string')),'callback'=>function($r)use($action){
   if($action==='book'){return kcfr_book_session(absint($r['session']),$r->get_params());}
   if($action==='manage'){return kcfr_manage_booking(absint($r['id']),(string)$r['key'],(string)$r['operation']);}
   return kcfr_save_request($r->get_params());
  }));
 }
 register_rest_route('kcfr/v1','/health',array('methods'=>'GET','permission_callback'=>fn()=>current_user_can('manage_options'),'callback'=>'kcfr_health'));
 register_rest_route('kcfr/v1','/settings',array('methods'=>'POST','permission_callback'=>fn()=>current_user_can('manage_options'),'callback'=>fn($r)=>kcfr_update_settings($r->get_json_params())));
 register_rest_route('kcfr/v1','/capacity',array('methods'=>'POST','permission_callback'=>fn()=>current_user_can('manage_options'),'callback'=>function($r){return kcfr_change_capacity(absint($r['id']),absint($r['capacity']));}));
});
add_action('wp_enqueue_scripts',function(){
 if(!is_singular()||!preg_match('/\[kcfr_(booking|manage|coaching)\b/',get_post_field('post_content',get_queried_object_id()))){return;}
 wp_enqueue_style('kcfr',plugins_url('ui.css',__FILE__),array(),filemtime(__DIR__.'/ui.css'));
 wp_enqueue_script('kcfr',plugins_url('app.js',__FILE__),array(),filemtime(__DIR__.'/app.js'),true);
 wp_localize_script('kcfr','kcfrApp',array('endpoint'=>rest_url('kcfr/v1/'),'nonce'=>wp_create_nonce('kcfr_forms'),'restNonce'=>wp_create_nonce('wp_rest'),'live'=>kcfr_live()));
});
add_action('template_redirect',function(){
 if(is_singular()&&preg_match('/\[kcfr_(booking|manage|coaching)\b/',get_post_field('post_content',get_queried_object_id()))){nocache_headers();header('Referrer-Policy: no-referrer');}
});
function kcfr_change_capacity($id,$capacity){
 global $wpdb;if($capacity<1||$capacity>100){return kcfr_error('capacity','Capacité invalide.');}
 $s=kcfr_session_lock($id);
 if(!$s||$capacity<kcfr_occupancy($id)){$wpdb->query('ROLLBACK');return kcfr_error('capacity','La capacité ne peut pas être inférieure aux places réservées ou proposées.',409);}
 $ok=$wpdb->update($wpdb->prefix.'kcfr_sessions',array('capacity'=>$capacity),array('id'=>$id));
 if($ok===false){$wpdb->query('ROLLBACK');return kcfr_error('storage','Modification non enregistrée.',500);}
 $s->capacity=$capacity;kcfr_refresh_locked($s,time());$wpdb->query('COMMIT');return array('id'=>$id,'capacity'=>$capacity);
}

// Functional pages must not appear as search results after publication either.
add_filter('wp_robots',function($robots){
 if(is_singular()&&preg_match('/\[kcfr_(booking|manage|coaching)\b/',get_post_field('post_content',get_queried_object_id()))){
  unset($robots['index']);$robots['noindex']=true;
 }
 return $robots;
});
