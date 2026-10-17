<?php
defined( 'ABSPATH' ) || exit;
function kcf_money( $cents ) { return number_format( $cents / 100, 2, ',', ' ' ) . ' €'; }
function kcf_when( $utc ) {
    $d=(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(kcf_timezone());
    $days=array(1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche');
    return $days[(int)$d->format('N')].' '.$d->format('d/m').' à '.$d->format('H').' h '.$d->format('i');
}
function kcf_demo_notice() { return '<p class="kcf-demo"><strong>Atelier de démonstration.</strong> Les essais restent dans ce site de travail. Aucun paiement, e-mail ou SMS n’est envoyé.</p>'; }
function kcf_identity_fields( $id ) {
    return '<div class="kcf-form-grid"><div><label for="'.$id.'-name">Votre nom</label><input id="'.$id.'-name" name="name" autocomplete="name" maxlength="160" required></div><div><label for="'.$id.'-email">Votre e-mail</label><input id="'.$id.'-email" name="email" type="email" autocomplete="email" maxlength="254" required></div></div><div class="kcf-honeypot" aria-hidden="true"><label>Ne pas remplir<input name="website" tabindex="-1" autocomplete="off"></label></div>';
}
function kcf_result_area() { return '<div class="kcf-form-result" role="status" aria-live="polite" tabindex="-1"></div>'; }
add_shortcode('kcf_booking',function(){
    global $wpdb;
    $sessions=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcf_sessions WHERE starts_at>%s AND series NOT LIKE 'test-%%' ORDER BY starts_at LIMIT 40",kcf_utc(time())));
    $sessions=array_values(array_filter($sessions,function($s){return !kcf_collective_closed($s->starts_at);}));
    ob_start(); echo kcf_demo_notice();
    if(!$sessions){echo '<p>Les prochaines dates seront ajoutées prochainement. Vous pouvez contacter Kévin au 06 74 80 67 07.</p>';return ob_get_clean();}
    ?>
    <form class="kcf-form" data-kcf-form="book">
    <label for="kcf-session">Choisir un cours et une date</label><select id="kcf-session" name="session" aria-describedby="kcf-session-preview" data-kcf-session-choices required><option value="">Sélectionner une séance</option>
    <?php foreach($sessions as $s){
        $locked=kcf_session_lock($s->id); if($locked){kcf_refresh_locked($locked,time());} $wpdb->query('COMMIT');
        $free=max(0,$s->capacity-kcf_occupancy($s->id));
        $label=$s->title.' · '.kcf_when($s->starts_at).' · '.$s->venue.' · '.($free?$free.' place(s) restante(s)':'complet — liste d’attente');
        echo '<option value="'.(int)$s->id.'">'.esc_html($label).'</option>';
    } ?></select><p id="kcf-session-preview" class="kcf-session-preview" aria-live="polite"></p>
    <?php echo kcf_identity_fields('kcf-book'); ?>
    <p class="kcf-field-note">15 places par cours. Réservation à chaque séance, même avec une formule par période. Paiement en une fois par période, sur place. Si le cours est complet, la demande rejoint la liste d’attente.</p>
    <label class="kcf-check"><input type="checkbox" required> J’ai compris qu’il s’agit d’un essai et que je dois conserver mon lien privé pour suivre ou annuler mon inscription.</label>
    <button type="submit">Essayer la réservation</button><?php echo kcf_result_area(); ?>
    </form><noscript><p>Pour utiliser le formulaire, activez JavaScript ou contactez Kévin par téléphone.</p></noscript>
    <?php return ob_get_clean();
});
add_shortcode('kcf_manage',function(){return kcf_demo_notice().'<section class="kcf-management" data-kcf-management><p>Ouverture de votre inscription…</p></section>';});
add_shortcode('kcf_coaching',function(){ob_start();echo kcf_demo_notice();?>
    <form class="kcf-form" data-kcf-form="request"><input type="hidden" name="kind" value="coaching">
    <?php echo kcf_identity_fields('kcf-coach'); ?>
    <div class="kcf-form-grid"><div><label for="kcf-coach-date">Date souhaitée</label><input id="kcf-coach-date" name="date" type="date" min="<?php echo esc_attr(wp_date('Y-m-d',time(),kcf_timezone())); ?>" required></div><div><label for="kcf-coach-time">Heure souhaitée</label><input id="kcf-coach-time" name="time" type="time" min="07:00" required></div></div>
    <label for="kcf-coach-address">Adresse du rendez-vous</label><textarea id="kcf-coach-address" name="address" rows="2" autocomplete="street-address" maxlength="500" required></textarea>
    <label for="kcf-coach-phone">Téléphone, si vous souhaitez être rappelé</label><input id="kcf-coach-phone" name="phone" type="tel" autocomplete="tel" maxlength="40">
    <label for="kcf-coach-note">Votre objectif ou votre question (facultatif)</label><textarea id="kcf-coach-note" name="note" rows="3" maxlength="2000"></textarea>
    <p class="kcf-field-note">45 minutes de séance + 15 minutes d’échange, déplacements en plus. Zone : 15 minutes autour de Loupiac. Ce formulaire propose une date ; Kévin devra vérifier son agenda et le trajet avant toute confirmation.</p>
    <button type="submit">Essayer une demande de coaching</button><?php echo kcf_result_area(); ?></form>
    <?php return ob_get_clean();});
add_shortcode('kcf_order',function($attributes){
    $a=shortcode_atts(array('seller'=>''),$attributes);$seller=$a['seller'];
    if(!in_array($seller,array('kevin','yohan'),true)){return '';}
    $products=array_filter(kcf_products(),function($p)use($seller){return $p['seller']===$seller;});
    $name='kevin'===$seller?'Kévin':'Yohan'; $id='kcf-'.$seller;
    ob_start();echo kcf_demo_notice();?>
    <form class="kcf-form" data-kcf-form="request"><input type="hidden" name="kind" value="order"><input type="hidden" name="seller" value="<?php echo esc_attr($seller); ?>">
    <fieldset><legend>Choisir les produits de <?php echo esc_html($name); ?></legend>
    <?php foreach($products as $key=>$p){?>
    <div class="kcf-order-line"><label for="<?php echo esc_attr($id.'-'.$key); ?>"><?php echo esc_html($p['name']); ?><small><?php echo esc_html(kcf_money($p['price']).($p['example']?' · prix d’exemple':'')); ?></small></label><input type="number" id="<?php echo esc_attr($id.'-'.$key); ?>" name="items[<?php echo esc_attr($key); ?>]" value="0" min="0" max="24" step="1" inputmode="numeric" data-unit-cents="<?php echo (int)$p['price']; ?>" aria-label="<?php echo esc_attr('Quantité : '.$p['name']); ?>"></div>
    <?php } ?></fieldset>
    <p class="kcf-order-total">Total indicatif : <strong data-order-total>0,00 €</strong></p>
    <?php echo kcf_identity_fields($id); ?>
    <label for="<?php echo esc_attr($id); ?>-phone">Téléphone (facultatif)</label><input id="<?php echo esc_attr($id); ?>-phone" name="phone" type="tel" autocomplete="tel" maxlength="40">
    <label for="<?php echo esc_attr($id); ?>-note">Votre message ou préférence de retrait (facultatif)</label><textarea id="<?php echo esc_attr($id); ?>-note" name="note" rows="3" maxlength="2000"></textarea>
    <?php if('yohan'===$seller){?><label class="kcf-check"><input type="checkbox" name="adult" value="1" required> Je certifie avoir 18 ans ou plus.</label><?php } ?>
    <p class="kcf-field-note">Demande séparée auprès de <?php echo esc_html($name); ?>. Confirmation par le vendeur et paiement au retrait. Les prix provisoires sont des exemples de maquette ; aucune vente n’est ouverte.</p>
    <button type="submit">Essayer la demande à <?php echo esc_html($name); ?></button><?php echo kcf_result_area(); ?></form>
    <?php return ob_get_clean();
});
add_shortcode('kcf_price',function($attributes){
    $a=shortcode_atts(array('product'=>'','per_kg'=>''),$attributes);$all=kcf_products();$p=$all[$a['product']]??null;if(!$p){return '';}
    $price=$p['price'];
    if($a['per_kg']){$grams=str_ends_with($a['product'],'-500')?500:1000;return esc_html(kcf_money($price*1000/$grams).'/kg');}
    return esc_html(kcf_money($price));
});
