<?php
defined( 'ABSPATH' ) || exit;
function kcf_allowed_sellers(){
    $allowed=array();
    foreach(array('kevin','yohan') as $seller){if(current_user_can('kcf_view_'.$seller)){$allowed[]=$seller;}}
    return $allowed;
}
add_action('admin_menu',function(){
    add_menu_page('KevCoachFit','KevCoachFit','kcf_access','kcf-requests','kcf_admin_requests','dashicons-calendar-alt',25);
    add_submenu_page('kcf-requests','Demandes','Demandes','kcf_access','kcf-requests','kcf_admin_requests');
    add_submenu_page('kcf-requests','Produits et tarifs','Produits et tarifs','kcf_access','kcf-catalogue','kcf_admin_catalogue');
    add_submenu_page('kcf-requests','Cours et places','Cours et places','manage_options','kcf-sessions','kcf_admin_sessions');
    add_submenu_page('kcf-requests','Inscriptions','Inscriptions','manage_options','kcf-bookings','kcf_admin_bookings');
});
function kcf_admin_requests(){
    global $wpdb;$allowed=kcf_allowed_sellers();if(!$allowed){wp_die('Accès refusé.');}
    $marks=implode(',',array_fill(0,count($allowed),'%s'));
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcf_requests WHERE seller IN ($marks) ORDER BY id DESC LIMIT 100",...$allowed));
    echo '<div class="wrap"><h1>Demandes reçues — démonstration</h1><p>Les demandes d’essai restent dans cet atelier. Les e-mails, SMS et paiements ne sont pas connectés.</p><table class="widefat striped"><thead><tr><th>Référence</th><th>Vendeur / type</th><th>Contact</th><th>Demande</th></tr></thead><tbody>';
    foreach($rows as $r){$details=json_decode($r->details,true);echo '<tr><td>ESSAI-'.(int)$r->id.'<br>'.esc_html(kcf_when($r->created_at)).'</td><td>'.esc_html(ucfirst($r->seller).' · '.('order'===$r->kind?'Produits':'Coaching privé')).'</td><td>'.esc_html($r->name).'<br>'.esc_html($r->email).'<br>'.esc_html($r->phone).'</td><td>';
        if('order'===$r->kind){foreach($details['lines']??array() as $line){echo esc_html($line['quantity'].' × '.$line['name'].' — '.kcf_money($line['unit_cents'])).'<br>'; }echo '<strong>'.esc_html('Total indicatif : '.kcf_money($details['total_cents'])).'</strong>';}
        else{echo esc_html(($details['preferred_date']??'').' '.$details['preferred_time']).'<br>'.esc_html($details['address']??'').'<br>Agenda et trajet à vérifier.';}
        echo '<p>'.nl2br(esc_html($details['note']??'')).'</p></td></tr>';
    }
    if(!$rows){echo '<tr><td colspan="4">Aucune demande pour le moment.</td></tr>';}
    echo '</tbody></table></div>';
}
function kcf_admin_catalogue(){
    $allowed=kcf_allowed_sellers();if(!$allowed){wp_die('Accès refusé.');}
    echo '<div class="wrap"><h1>Produits et tarifs</h1><p>Les prix sont partagés entre les fiches produits et les formulaires. Chaque producteur voit uniquement ses produits. Les ventes restent fermées dans la démonstration.</p>';
    if(isset($_GET['saved'])){echo '<div class="notice notice-success"><p>Catalogue enregistré.</p></div>';}
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kcf_catalogue">';wp_nonce_field('kcf_catalogue');
    echo '<table class="widefat striped"><thead><tr><th>Produit</th><th>Prix en euros</th><th>Prix provisoire</th></tr></thead><tbody>';
    foreach(kcf_products() as $key=>$p){if(!in_array($p['seller'],$allowed,true)){continue;}
        echo '<tr><td><label for="name-'.esc_attr($key).'">'.esc_html(ucfirst($p['seller'])).'</label><br><input class="regular-text" id="name-'.esc_attr($key).'" name="products['.esc_attr($key).'][name]" value="'.esc_attr($p['name']).'" maxlength="160" required></td><td><input aria-label="'.esc_attr('Prix '.$p['name']).'" type="number" min="0.01" max="9999.99" step="0.01" name="products['.esc_attr($key).'][price]" value="'.esc_attr(number_format($p['price']/100,2,'.','')).'" required></td><td><label><input type="checkbox" name="products['.esc_attr($key).'][example]" value="1" '.checked($p['example'],true,false).'> Exemple à confirmer</label></td></tr>';
    }
    echo '</tbody></table>';submit_button('Enregistrer mes produits');echo '</form></div>';
}
add_action('admin_post_kcf_catalogue',function(){
    if(!current_user_can('kcf_access')){wp_die('Accès refusé.',403);}check_admin_referer('kcf_catalogue');
    $result=kcf_update_products_for_user(wp_unslash($_POST['products']??array()));
    if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()),403);}
    wp_safe_redirect(admin_url('admin.php?page=kcf-catalogue&saved=1'));exit;
});
function kcf_update_products_for_user($input){
    global $wpdb;
    if(!current_user_can('kcf_access')){return kcf_error('access','Accès refusé.',403);}
    $allowed=kcf_allowed_sellers();$wpdb->query('START TRANSACTION');
    $raw=$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='kcf_products' FOR UPDATE");
    $products=maybe_unserialize($raw);
    if(!is_array($products)){$wpdb->query('ROLLBACK');return kcf_error('storage','Catalogue indisponible.',500);}
    foreach((array)$input as $key=>$value){
        if(!isset($products[$key])||!in_array($products[$key]['seller'],$allowed,true)){$wpdb->query('ROLLBACK');return kcf_error('access','Produit non autorisé.',403);}
        $name=sanitize_text_field($value['name']??'');$price=(string)($value['price']??'');
        if(!$name||strlen($name)>160||!preg_match('/^\d{1,4}(\.\d{1,2})?$/',$price)||(float)$price<=0){$wpdb->query('ROLLBACK');return kcf_error('price','Nom ou prix invalide.');}
        $products[$key]['name']=$name;$products[$key]['price']=(int)round((float)$price*100);$products[$key]['example']=!empty($value['example']);
    }
    $ok=$wpdb->update($wpdb->options,array('option_value'=>maybe_serialize($products)),array('option_name'=>'kcf_products'));
    if(false===$ok){$wpdb->query('ROLLBACK');return kcf_error('storage','Enregistrement impossible.',500);}
    $wpdb->query('COMMIT');wp_cache_delete('kcf_products','options');return true;
}
function kcf_admin_sessions(){
    global $wpdb;if(!current_user_can('manage_options')){wp_die('Accès refusé.');}
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcf_sessions WHERE starts_at>%s AND series NOT LIKE 'test-%%' ORDER BY starts_at LIMIT 50",kcf_utc(time())));
    echo '<div class="wrap"><h1>Cours et places — démonstration</h1><p>Dates d’essai des six prochaines semaines, à valider selon la saison et les vacances avant ouverture publique. La capacité peut être modifiée sans réduire les places déjà réservées ou proposées.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kcf_capacity">';wp_nonce_field('kcf_capacity');
    echo '<table class="widefat striped"><thead><tr><th>Cours</th><th>Date et lieu</th><th>Places occupées / proposées</th><th>Capacité</th></tr></thead><tbody>';
    foreach($rows as $s){echo '<tr><td>'.esc_html($s->title).'</td><td>'.esc_html(kcf_when($s->starts_at).' · '.$s->venue).'</td><td>'.kcf_occupancy($s->id).'</td><td><input type="number" min="1" max="100" aria-label="'.esc_attr('Capacité '.$s->title.' '.kcf_when($s->starts_at)).'" name="capacity['.(int)$s->id.']" value="'.(int)$s->capacity.'"></td></tr>';}
    echo '</tbody></table>';submit_button('Enregistrer les capacités');echo '</form></div>';
}
add_action('admin_post_kcf_capacity',function(){
    global $wpdb;if(!current_user_can('manage_options')){wp_die('Accès refusé.',403);}check_admin_referer('kcf_capacity');
    // Validate every requested change before applying any, under row locks.
    $wpdb->query('START TRANSACTION');$changes=array();
    $posted=(array)($_POST['capacity']??array());ksort($posted,SORT_NUMERIC);
    foreach($posted as $id=>$raw){
        if(!ctype_digit((string)$raw)||(int)$raw<1||(int)$raw>100){$wpdb->query('ROLLBACK');wp_die('Capacité invalide.');}
        $s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcf_sessions WHERE id=%d FOR UPDATE",absint($id)));
        if(!$s||(int)$raw<kcf_occupancy($s->id)){$wpdb->query('ROLLBACK');wp_die('La capacité ne peut pas être inférieure aux places déjà prises.');}
        $changes[$s->id]=(int)$raw;
    }
    foreach($changes as $id=>$capacity){$wpdb->update($wpdb->prefix.'kcf_sessions',array('capacity'=>$capacity),array('id'=>$id));$s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcf_sessions WHERE id=%d",$id));kcf_refresh_locked($s,time());}
    $wpdb->query('COMMIT');wp_safe_redirect(admin_url('admin.php?page=kcf-sessions'));exit;
});
function kcf_admin_bookings(){
    global $wpdb;if(!current_user_can('manage_options')){wp_die('Accès refusé.');}
    $rows=$wpdb->get_results("SELECT b.name,b.email,b.status,b.offered_until,s.title,s.starts_at,s.venue FROM {$wpdb->prefix}kcf_bookings b JOIN {$wpdb->prefix}kcf_sessions s ON s.id=b.session_id WHERE s.series NOT LIKE 'test-%' ORDER BY b.id DESC LIMIT 100");
    echo '<div class="wrap"><h1>Inscriptions — démonstration</h1><p>Réservations, liste d’attente et places proposées. Les notifications ne sont pas connectées.</p><table class="widefat striped"><thead><tr><th>Personne</th><th>Cours</th><th>Statut</th><th>Fin de proposition</th></tr></thead><tbody>';
    $labels=array('reserved'=>'Réservé','waiting'=>'Liste d’attente','offered'=>'Place proposée','expired'=>'Délai expiré','cancelled'=>'Annulé');
    foreach($rows as $r){echo '<tr><td>'.esc_html($r->name).'<br>'.esc_html($r->email).'</td><td>'.esc_html($r->title.' · '.kcf_when($r->starts_at).' · '.$r->venue).'</td><td>'.esc_html($labels[$r->status]??$r->status).'</td><td>'.esc_html($r->offered_until?kcf_when($r->offered_until):'').'</td></tr>';}
    if(!$rows){echo '<tr><td colspan="4">Aucune inscription pour le moment.</td></tr>';}
    echo '</tbody></table></div>';
}
