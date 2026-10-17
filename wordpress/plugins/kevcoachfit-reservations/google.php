<?php
defined('ABSPATH')||exit;
function kcfr_google_token(){
 $raw=kcfr_decrypt(get_option('kcfr_google_tokens',''));$token=$raw===false?null:json_decode($raw,true);
 if(!$token||empty($token['refresh_token'])){return kcfr_error('google','Google Agenda n’est pas encore connecté.',409);}
 if(!empty($token['access_token'])&&($token['expires_at']??0)>time()+60){return $token['access_token'];}
 $secret=kcfr_decrypt(get_option('kcfr_google_client_secret',''));
 if($secret===false){return kcfr_error('google','La configuration Google doit être enregistrée à nouveau.',409);}
 $response=wp_remote_post('https://oauth2.googleapis.com/token',array('timeout'=>20,'body'=>array('client_id'=>kcfr_settings()['google_client_id'],'client_secret'=>$secret,'refresh_token'=>$token['refresh_token'],'grant_type'=>'refresh_token')));
 if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200){return kcfr_error('google','La connexion Google doit être vérifiée ou renouvelée.',502);}
 $data=json_decode(wp_remote_retrieve_body($response),true);
 if(empty($data['access_token'])){return kcfr_error('google','Réponse Google incomplète.',502);}
 $token['access_token']=$data['access_token'];$token['expires_at']=time()+(int)($data['expires_in']??3600);
 update_option('kcfr_google_tokens',kcfr_encrypt(wp_json_encode($token)),false);return $token['access_token'];
}
function kcfr_google_request($method,$path,$payload=null){
 $token=kcfr_google_token();if(is_wp_error($token)){return $token;}
 $args=array('method'=>$method,'timeout'=>20,'headers'=>array('Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'));
 if($payload!==null){$args['body']=wp_json_encode($payload);}
 $response=wp_remote_request('https://www.googleapis.com/calendar/v3/'.$path,$args);
 if(is_wp_error($response)){return kcfr_error('google_network','Google Agenda est temporairement inaccessible.',502);}
 $status=wp_remote_retrieve_response_code($response);$data=json_decode(wp_remote_retrieve_body($response),true);
 if($status<200||$status>=300){return new WP_Error('google_http','Google Agenda a refusé la requête.',array('status'=>502,'upstream_status'=>$status));}
 return is_array($data)?$data:array();
}
function kcfr_google_busy($start,$end){
 $ids=kcfr_settings()['google_busy_calendars'];
 $result=kcfr_google_request('POST','freeBusy',array('timeMin'=>gmdate('c',$start),'timeMax'=>gmdate('c',$end),'timeZone'=>'Europe/Paris','items'=>array_map(fn($id)=>array('id'=>$id),$ids)));
 if(is_wp_error($result)){return $result;}
 foreach($ids as $id){
  if(!isset($result['calendars'][$id])||!empty($result['calendars'][$id]['errors'])){return kcfr_error('calendar','Impossible de vérifier tous les agendas sélectionnés.',502);}
  if(!empty($result['calendars'][$id]['busy'])){return true;}
 }
 return false;
}
function kcfr_queue_google_session($id){
 if(empty(kcfr_settings()['google_sync'])){return;}
 global $wpdb;$s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcfr_sessions WHERE id=%d",$id));
 if(!$s||str_starts_with($s->series,'test-')){return;}
 $version=hash('sha256',$s->starts_at.'|'.$s->duration.'|'.$s->venue.'|'.$s->title.'|'.microtime(true).bin2hex(random_bytes(4)));
 kcfr_enqueue('google-'.$id.'-'.substr($version,0,32),array('type'=>'google','session_id'=>$id));
}
function kcfr_google_upsert_session($id){
 global $wpdb;$s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcfr_sessions WHERE id=%d",$id));
 if(!$s||str_starts_with($s->series,'test-')){return kcfr_error('session','Cours introuvable.');}
 $calendar=rawurlencode(kcfr_settings()['google_calendar_id']);
 $event='kcfr'.md5(home_url('/').'|'.$id);
 $data=array('id'=>$event,'summary'=>$s->title.' — '.$s->venue,'location'=>$s->venue,'description'=>'Cours Vitali’Van. Les inscriptions se gèrent dans WordPress. Aucun nom de participant n’est transmis.','start'=>array('dateTime'=>gmdate('c',kcfr_timestamp($s->starts_at)),'timeZone'=>'Europe/Paris'),'end'=>array('dateTime'=>gmdate('c',kcfr_timestamp($s->starts_at)+60*$s->duration),'timeZone'=>'Europe/Paris'),'extendedProperties'=>array('private'=>array('kcfr_session'=>(string)$id,'kcfr_site'=>hash('sha256',home_url('/')))));
 $existing=kcfr_google_request('GET','calendars/'.$calendar.'/events/'.$event);
 if(is_wp_error($existing)){
  if(($existing->get_error_data()['upstream_status']??0)!==404){return $existing;}
  $result=kcfr_google_request('POST','calendars/'.$calendar.'/events?sendUpdates=none',$data);
  // Concurrent first writes share a deterministic Google event ID.
  if(is_wp_error($result)&&($result->get_error_data()['upstream_status']??0)===409){unset($data['id']);return kcfr_google_request('PATCH','calendars/'.$calendar.'/events/'.$event.'?sendUpdates=none',$data);}
  return $result;
 }
 if(($existing['extendedProperties']['private']['kcfr_site']??'')!==$data['extendedProperties']['private']['kcfr_site']){return kcfr_error('calendar','Cet événement ne correspond pas au site.');}
 unset($data['id']);return kcfr_google_request('PATCH','calendars/'.$calendar.'/events/'.$event.'?sendUpdates=none',$data);
}
add_action('admin_post_kcfr_google_connect',function(){
 if(!current_user_can('manage_options')){wp_die('Accès refusé.',403);}check_admin_referer('kcfr_google_connect');
 $cfg=kcfr_settings();if(!$cfg['google_client_id']||!get_option('kcfr_google_client_secret')){wp_die('Enregistrez d’abord le client OAuth Google.');}
 $state=bin2hex(random_bytes(32));set_transient('kcfr_oauth_'.hash('sha256',$state),get_current_user_id(),600);
 $url=add_query_arg(array('client_id'=>$cfg['google_client_id'],'redirect_uri'=>admin_url('admin-post.php?action=kcfr_google_callback'),'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.freebusy','access_type'=>'offline','prompt'=>'consent','state'=>$state),'https://accounts.google.com/o/oauth2/v2/auth');
 wp_redirect($url);exit;
});
function kcfr_oauth_state($state){
 if(!is_string($state)||!preg_match('/^[a-f0-9]{64}$/',$state)){return false;}
 $key='kcfr_oauth_'.hash('sha256',$state);$id=get_transient($key);delete_transient($key);return $id&&(int)$id===get_current_user_id();
}
add_action('admin_post_kcfr_google_callback',function(){
 if(!current_user_can('manage_options')||!kcfr_oauth_state($_GET['state']??'')){wp_die('Autorisation expirée. Recommencez depuis les réglages.',403);}
 if(isset($_GET['error'])||empty($_GET['code'])){wp_die('Google Agenda n’a pas été autorisé. Aucun agenda n’a été modifié.');}
 $secret=kcfr_decrypt(get_option('kcfr_google_client_secret',''));if($secret===false){wp_die('Configuration Google à vérifier.');}
 $response=wp_remote_post('https://oauth2.googleapis.com/token',array('timeout'=>20,'body'=>array('client_id'=>kcfr_settings()['google_client_id'],'client_secret'=>$secret,'code'=>sanitize_text_field(wp_unslash($_GET['code'])),'redirect_uri'=>admin_url('admin-post.php?action=kcfr_google_callback'),'grant_type'=>'authorization_code')));
 $data=is_wp_error($response)?null:json_decode(wp_remote_retrieve_body($response),true);
 if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200||empty($data['access_token'])||empty($data['refresh_token'])){wp_die('Google n’a pas fourni de connexion durable. Recommencez avec l’autorisation hors connexion.');}
 $data['expires_at']=time()+(int)($data['expires_in']??3600);update_option('kcfr_google_tokens',kcfr_encrypt(wp_json_encode($data)),false);
 wp_safe_redirect(admin_url('admin.php?page=kcfr&google=connected'));exit;
});
