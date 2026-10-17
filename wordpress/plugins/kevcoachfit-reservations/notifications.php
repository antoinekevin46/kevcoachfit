<?php
defined('ABSPATH')||exit;
function kcfr_install_outbox(){
 global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();
 dbDelta("CREATE TABLE {$wpdb->prefix}kcfr_outbox (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 message_key varchar(190) NOT NULL,
 payload longtext NOT NULL,
 status varchar(20) NOT NULL DEFAULT 'pending',
 attempts int unsigned NOT NULL DEFAULT 0,
 available_at datetime NOT NULL,
 locked_until datetime NULL,
 PRIMARY KEY (id),
 UNIQUE KEY message_key (message_key)
 ) ENGINE=InnoDB $c;");
}
function kcfr_enqueue($key,$payload){
 if(!kcfr_live()&&($payload['type']??'')!=='google'){return;}
 global $wpdb;
 $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}kcfr_outbox (message_key,payload,available_at) VALUES (%s,%s,%s)",$key,kcfr_encrypt(wp_json_encode($payload)),kcfr_utc(time())));
}
function kcfr_notice($id,$status){
 global $wpdb;if(!kcfr_live()){return;}
 $b=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d",$id));
 $s=$b?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcfr_sessions WHERE id=%d",$b->session_id)):null;
 if(!$b||!$s){return;}
 $token=kcfr_decrypt($b->key_ciphertext);if($token===false){return;}
 $url=kcfr_manage_url().'#'.$id.'.'.$token;
 $labels=array('reserved'=>'Votre place est réservée','waiting'=>'Votre inscription sur liste d’attente','offered'=>'Une place vous est proposée','cancelled'=>'Votre inscription est annulée');
 $body="Bonjour {$b->name},\n\n".$labels[$status]." pour {$s->title}, le ".kcfr_when($s->starts_at).".\nLieu : {$s->venue}.\n";
 if($status==='offered'){$body.="Confirmez avant le ".kcfr_when($b->offered_until).". La place reste proposée au maximum 2 heures et jamais après le début du cours.\n";}
 if($status==='reserved'){$body.="Prévoyez un tapis, de l’eau, une tenue et des chaussures adaptées. Pour votre première séance ou toute question, appelez Kévin au 06 74 80 67 07. Paiement sur place ou selon votre formule ; les cours Vitali’Seniors actuels sont gratuits.\nAnnulation en ligne jusqu’à 2 heures avant le cours.\n";}
 if($status!=='cancelled'){$body.="\nVotre lien privé pour suivre ou gérer cette inscription :\n".$url."\nNe partagez pas ce lien.\n";}
 $key='booking-'.$id.'-'.$status.($status==='offered'?'-'.strtotime($b->offered_until):'');
 kcfr_enqueue($key,array('type'=>'booking','booking_id'=>$id,'expected_status'=>$status,'to'=>$b->email,'subject'=>'Vitali’Van — '.$labels[$status],'body'=>$body));
}
function kcfr_request_notice($id){
 global $wpdb;$r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcfr_requests WHERE id=%d",$id));if(!$r){return;}
 kcfr_enqueue('request-'.$id,array('type'=>'request','to'=>get_option('admin_email'),'subject'=>'KevCoachFit — nouvelle demande de coaching','body'=>"Une demande de coaching est à vérifier dans WordPress :\n".admin_url('admin.php?page=kcfr')."\nAucun créneau n’est confirmé automatiquement."));
}
function kcfr_send_outbox(){
 global $wpdb;
 $lock='kcfr_outbox_'.md5(home_url('/'));
 if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1){return;}
 try{
 $scope=kcfr_live()?'':" AND message_key LIKE 'google-%'";
 // Row locks prevent two cron workers from sending the same queued record concurrently.
 for($i=0;$i<10;$i++){
  $wpdb->query('START TRANSACTION');
  $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcfr_outbox WHERE status='pending' $scope AND attempts<5 AND available_at<=%s AND (locked_until IS NULL OR locked_until<%s) ORDER BY id LIMIT 1 FOR UPDATE",kcfr_utc(time()),kcfr_utc(time())));
  if(!$row){$wpdb->query('COMMIT');break;}
  $wpdb->update($wpdb->prefix.'kcfr_outbox',array('locked_until'=>kcfr_utc(time()+120),'attempts'=>$row->attempts+1),array('id'=>$row->id));$wpdb->query('COMMIT');
  $raw=kcfr_decrypt($row->payload);$data=$raw===false?null:json_decode($raw,true);
  if(!is_array($data)){$wpdb->update($wpdb->prefix.'kcfr_outbox',array('status'=>'failed','locked_until'=>null),array('id'=>$row->id));continue;}
  if(($data['type']??'')==='booking'){
   $b=$wpdb->get_row($wpdb->prepare("SELECT status,offered_until FROM {$wpdb->prefix}kcfr_bookings WHERE id=%d",$data['booking_id']));
   if(!$b||$b->status!==$data['expected_status']||($b->status==='offered'&&kcfr_timestamp($b->offered_until)<=time())){$wpdb->update($wpdb->prefix.'kcfr_outbox',array('status'=>'discarded','locked_until'=>null),array('id'=>$row->id));continue;}
  }
  $ok=($data['type']??'')==='google'?!is_wp_error(kcfr_google_upsert_session((int)$data['session_id'])):wp_mail($data['to'],$data['subject'],$data['body'],array('Content-Type: text/plain; charset=UTF-8'));
  $wpdb->update($wpdb->prefix.'kcfr_outbox',array('status'=>$ok?'sent':($row->attempts+1>=5?'failed':'pending'),'locked_until'=>null,'available_at'=>kcfr_utc(time()+min(3600,60*2**$row->attempts))),array('id'=>$row->id));
 }
 }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
}
