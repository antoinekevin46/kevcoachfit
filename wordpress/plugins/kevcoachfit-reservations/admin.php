<?php
defined('ABSPATH')||exit;
function kcfr_health(){
 global $wpdb;$s=kcfr_settings();return array('version'=>'1.0.0','live'=>kcfr_live(),'mail_verified'=>(bool)$s['mail_verified'],'cron_verified'=>(bool)$s['cron_verified'],'last_worker_run'=>(int)get_option('kcfr_last_worker_run',0),'google_connected'=>(bool)get_option('kcfr_google_tokens'),'google_sync'=>(bool)$s['google_sync'],'google_secret_configured'=>(bool)get_option('kcfr_google_client_secret'),'google_redirect_uri'=>admin_url('admin-post.php?action=kcfr_google_callback'),'next_worker'=>wp_next_scheduled('kcfr_tick'),'validated_until'=>$s['validated_until'],'sessions'=>count(kcfr_sessions()),'outbox_failed'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox WHERE status='failed'"),'booking_page_id'=>(int)$s['booking_page_id'],'manage_page_id'=>(int)$s['manage_page_id'],'sms_connected'=>false,'automatic_travel'=>false,'coaching_confirmation'=>'manual');
}
function kcfr_update_settings($input){
 $s=kcfr_settings();$allowed=array('live','mail_verified','cron_verified','google_sync','booking_page_id','manage_page_id','google_client_id','google_calendar_id','google_busy_calendars','closed_dates','validated_until');
 foreach($allowed as $key){if(!array_key_exists($key,$input)){continue;}
  if(in_array($key,array('booking_page_id','manage_page_id'),true)){
   $id=absint($input[$key]);if(get_post_type($id)!=='page'){return kcfr_error('settings','Page WordPress introuvable.');}$s[$key]=$id;
  }elseif(in_array($key,array('live','mail_verified','cron_verified','google_sync'),true)){$s[$key]=(bool)$input[$key];}
  elseif(in_array($key,array('closed_dates','google_busy_calendars'),true)){
   if(!is_array($input[$key])){return kcfr_error('settings','Liste de valeurs attendue.');}
   $s[$key]=array_values(array_unique(array_filter(array_map('sanitize_text_field',$input[$key]))));
  }else{$s[$key]=sanitize_text_field($input[$key]);}
 }
 foreach(array_merge($s['closed_dates'],array($s['validated_until'])) as $date){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date){return kcfr_error('settings','Date de calendrier invalide.');}}
 if(!$s['google_calendar_id']||!$s['google_busy_calendars']){return kcfr_error('settings','Indiquez les agendas à utiliser.');}
 if(!empty($s['live'])){
  if(!$s['mail_verified']||!$s['cron_verified']||get_post_status($s['booking_page_id'])!=='publish'||get_post_status($s['manage_page_id'])!=='publish'){return kcfr_error('not_ready','Avant ouverture : vérifier les e-mails, la tâche planifiée et publier les deux pages fonctionnelles.',409);}
 }
 update_option('kcfr_settings',$s,false);
 if(!empty($input['google_client_secret'])){update_option('kcfr_google_client_secret',kcfr_encrypt((string)$input['google_client_secret']),false);}
 return kcfr_health();
}
add_action('admin_menu',function(){add_menu_page('Réservations Vitali’Van','Réservations Vitali’Van','manage_options','kcfr','kcfr_admin','dashicons-calendar-alt',31);});
function kcfr_admin(){
 if(!current_user_can('manage_options')){return;}
 $s=kcfr_settings();$h=kcfr_health();global $wpdb;
 echo '<div class="wrap"><h1>Vitali’Van — réservations et agenda</h1><p><strong>'.(kcfr_live()?'Réservations ouvertes':'Mode test privé : aucune réservation publique ni notification client').'</strong></p>';
 echo '<p>15 places par cours. Réservation jusqu’au début, annulation d’une place réservée jusqu’à 2 h avant. Les places libérées sont proposées dans l’ordre de la liste d’attente, pour 2 h au maximum et jamais après le début du cours. Les demandes de coaching nécessitent une confirmation et la vérification du trajet.</p>';
 foreach(array('booking_page_id'=>'Page de réservation','manage_page_id'=>'Page de gestion des inscriptions') as $key=>$label){echo '<p>'.esc_html($label).' : <a href="'.esc_url(get_edit_post_link($s[$key])).'">Modifier</a> · <a href="'.esc_url(get_preview_post_link($s[$key])).'" target="_blank">Voir l’aperçu</a></p>';}
 echo '<h2>1. Vérifier les notifications</h2><p>Les e-mails utilisent l’envoi WordPress. « Accepté par WordPress » ne garantit pas la livraison : vérifiez la réception et les indésirables. Aucun SMS n’est connecté.</p>';
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kcfr_admin_action"><input type="hidden" name="operation" value="mail_test">';wp_nonce_field('kcfr_admin_action');submit_button('M’envoyer un e-mail de test','secondary');echo '</form>';
 echo '<h2>2. Connecter Google Agenda</h2><p>Créez un client OAuth « Application Web » dans votre propre projet Google Cloud, activez Google Calendar API et autorisez votre compte Google. N’envoyez pas le secret dans un chat.</p><p>URI de redirection exacte : <code>'.esc_html($h['google_redirect_uri']).'</code></p><p>En mode « Test » Google, une autorisation externe peut expirer après sept jours. Prévoir une configuration de consentement adaptée avant l’usage quotidien.</p>';
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kcfr_admin_action"><input type="hidden" name="operation" value="settings">';wp_nonce_field('kcfr_admin_action');
 foreach(array('google_client_id'=>'Client ID Google','google_calendar_id'=>'Agenda où inscrire les cours (primary ou identifiant Google)') as $key=>$label){echo '<p><label>'.esc_html($label).'<br><input class="large-text" name="'.esc_attr($key).'" value="'.esc_attr($s[$key]).'"></label></p>';}
 echo '<p><label>Client secret Google (laisser vide pour conserver celui enregistré)<br><input class="large-text" type="password" name="google_client_secret" autocomplete="new-password" value=""></label></p>';
 echo '<p><label>Agendas dont vérifier les occupations (un identifiant par ligne)<br><textarea class="large-text" rows="3" name="google_busy_calendars">'.esc_textarea(implode("\n",$s['google_busy_calendars'])).'</textarea></label></p>';
 echo '<h2>3. Calendrier et ouverture</h2><p>Les cinq cours habituels sont préparés sur six semaines, dans la limite de la date validée ci-dessous. Saint-Projet reste exclu tant que les modalités ne sont pas confirmées. Juillet et août sont fermés pour les cours collectifs. Saisir les dates réelles de la pause de fin d’année avant d’étendre le calendrier au-delà du 20 décembre.</p>';
 echo '<p><label>Calendrier validé jusqu’au <input type="date" name="validated_until" value="'.esc_attr($s['validated_until']).'" required></label></p><p><label>Dates sans cours (une date AAAA-MM-JJ par ligne)<br><textarea name="closed_dates" rows="4" class="large-text">'.esc_textarea(implode("\n",$s['closed_dates'])).'</textarea></label></p>';
 foreach(array('mail_verified'=>'J’ai reçu et vérifié l’e-mail de test.','cron_verified'=>'L’hébergement exécute WP-Cron régulièrement (idéalement chaque minute).','google_sync'=>'Activer la synchronisation des cours vers mon agenda après connexion.','live'=>'Ouvrir les réservations après publication des pages fonctionnelles et vérification complète.') as $key=>$label){echo '<p><label><input type="checkbox" name="'.$key.'" value="1" '.checked($s[$key],true,false).'> '.esc_html($label).'</label></p>';}
 submit_button('Enregistrer les réglages');echo '</form>';
 foreach(array('google_connect'=>'Autoriser Google Agenda','google_test'=>'Vérifier la connexion Google','google_sync'=>'Synchroniser les cours','seed'=>'Préparer les six prochaines semaines','tick'=>'Traiter les attentes et notifications') as $op=>$label){
  $action=$op==='google_connect'?'kcfr_google_connect':'kcfr_admin_action';
  echo '<form style="display:inline-block;margin:0 12px 12px 0" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="operation" value="'.$op.'">';wp_nonce_field($action);submit_button($label,'secondary','submit',false);echo '</form>';
 }
 echo '<p>Prochain traitement planifié : '.esc_html($h['next_worker']?wp_date('d/m/Y H:i',$h['next_worker']):'non planifié').'. Envois en échec : '.(int)$h['outbox_failed'].'. La ponctualité nécessite que WP-Cron soit exécuté régulièrement par l’hébergement.</p>';
 echo '<p>À configurer chez l’hébergeur pour un traitement régulier : <code>'.esc_html(site_url('/wp-cron.php?doing_wp_cron')).'</code>. Sans tâche serveur, WP-Cron dépend des visites.</p>';
 echo '<h2>Places et inscriptions</h2><table class="widefat striped"><thead><tr><th>Cours</th><th>Date / lieu</th><th>Places libres / capacité</th></tr></thead><tbody>';
 foreach(kcfr_sessions() as $row){echo '<tr><td>'.esc_html($row['title']).'</td><td>'.esc_html(wp_date('d/m H:i',strtotime($row['starts_at']),kcfr_timezone()).' · '.$row['venue']).'</td><td>'.(int)$row['free'].' / '.(int)$row['capacity'].'<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kcfr_admin_action"><input type="hidden" name="operation" value="capacity"><input type="hidden" name="id" value="'.(int)$row['id'].'">'.wp_nonce_field('kcfr_admin_action','_wpnonce',true,false).'<label>Capacité <input name="capacity" type="number" min="1" max="100" value="'.(int)$row['capacity'].'" style="width:75px" required></label> <button class="button" type="submit">Enregistrer</button></form></td></tr>';}
 echo '</tbody></table><p>Une capacité ne peut pas passer sous le nombre de places réservées ou proposées.</p>';
 $rows=$wpdb->get_results("SELECT b.name,b.email,b.status,s.title,s.starts_at,s.venue FROM {$wpdb->prefix}kcfr_bookings b JOIN {$wpdb->prefix}kcfr_sessions s ON s.id=b.session_id WHERE s.series NOT LIKE 'test-%' ORDER BY b.id DESC LIMIT 100");
 echo '<table class="widefat striped"><thead><tr><th>Personne</th><th>Cours</th><th>Statut</th></tr></thead><tbody>';
 foreach($rows as $r){echo '<tr><td>'.esc_html($r->name).' · '.esc_html($r->email).'</td><td>'.esc_html($r->title.' · '.kcfr_when($r->starts_at)).'</td><td>'.esc_html($r->status).'</td></tr>';}
 echo '</tbody></table><h2>Demandes de coaching à confirmer</h2>';
 foreach($wpdb->get_results("SELECT * FROM {$wpdb->prefix}kcfr_requests ORDER BY id DESC LIMIT 50") as $r){$d=json_decode($r->details,true);echo '<p><strong>'.esc_html($r->name).'</strong> · '.esc_html($r->email.' · '.$r->phone).' — '.esc_html(($d['preferred_date']??'').' '.($d['preferred_time']??'').' · '.($d['address']??'')).'<br>'.esc_html($d['note']??'').'</p>';}
 echo '</div>';
}
add_action('admin_post_kcfr_admin_action',function(){
 if(!current_user_can('manage_options')){wp_die('Accès refusé.',403);}check_admin_referer('kcfr_admin_action');$op=sanitize_key($_POST['operation']??'');$result=true;
 if($op==='settings'){
  $in=wp_unslash($_POST);foreach(array('live','mail_verified','cron_verified','google_sync') as $k){$in[$k]=!empty($in[$k]);}foreach(array('closed_dates','google_busy_calendars') as $k){$in[$k]=preg_split('/\R/',trim($in[$k]??''),-1,PREG_SPLIT_NO_EMPTY);}$result=kcfr_update_settings($in);
 }elseif($op==='mail_test'){$result=wp_mail(get_option('admin_email'),'KevCoachFit — test des notifications','Ceci est le test d’envoi de vos réservations. Vérifiez sa réception avant de cocher la confirmation dans WordPress.');if(!$result){$result=kcfr_error('mail','WordPress n’a pas accepté l’envoi. Configurez l’envoi e-mail chez votre hébergeur.');}}
 elseif($op==='google_test'){$result=kcfr_google_busy(time(),time()+3600);if(!is_wp_error($result)){$result=true;}}
 elseif($op==='capacity'){$result=kcfr_change_capacity(absint($_POST['id']??0),absint($_POST['capacity']??0));}
 elseif($op==='seed'){kcfr_seed_sessions();}
 elseif($op==='tick'){kcfr_tick();}
 elseif($op==='google_sync'){if(!get_option('kcfr_google_tokens')||!kcfr_settings()['google_sync']){$result=kcfr_error('google','Connectez Google et activez la synchronisation dans les réglages.');}else{foreach(kcfr_sessions() as $s){kcfr_queue_google_session($s['id']);}}}
 else{$result=kcfr_error('operation','Action inconnue.');}
 if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}wp_safe_redirect(admin_url('admin.php?page=kcfr&saved=1'));exit;
});
