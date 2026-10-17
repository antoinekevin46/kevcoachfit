<?php
defined( 'ABSPATH' ) || exit;
function kcfr_money( $cents ) { return number_format( $cents / 100, 2, ',', ' ' ) . ' €'; }
function kcfr_when( $utc ) {
    $d=(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(kcfr_timezone());
    $days=array(1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche');
    return $days[(int)$d->format('N')].' '.$d->format('d/m').' à '.$d->format('H').' h '.$d->format('i');
}
function kcfr_demo_notice() { if(kcfr_live()){return '<p class="kcfr-demo">Paiement sur place. Aucun paiement en ligne n’est demandé.</p>';} return '<p class="kcfr-demo"><strong>Atelier de démonstration.</strong> Les essais restent dans ce site de travail. Aucun paiement, e-mail ou SMS n’est envoyé.</p>'; }
function kcfr_identity_fields( $id ) {
    return '<div class="kcfr-form-grid"><div><label for="'.$id.'-name">Votre nom</label><input id="'.$id.'-name" name="name" autocomplete="name" maxlength="160" required></div><div><label for="'.$id.'-email">Votre e-mail</label><input id="'.$id.'-email" name="email" type="email" autocomplete="email" maxlength="254" required></div></div><div class="kcfr-honeypot" aria-hidden="true"><label>Ne pas remplir<input name="website" tabindex="-1" autocomplete="off"></label></div>';
}
function kcfr_result_area() { return '<div class="kcfr-form-result" role="status" aria-live="polite" tabindex="-1"></div>'; }
add_shortcode('kcfr_booking',function(){
    global $wpdb;
    $sessions=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcfr_sessions WHERE starts_at>%s AND series NOT LIKE 'test-%%' ORDER BY starts_at LIMIT 40",kcfr_utc(time())));
    $sessions=array_values(array_filter($sessions,function($s){return !kcfr_collective_closed($s->starts_at);}));
    ob_start(); echo kcfr_demo_notice();
    if(!$sessions){echo '<p>Les prochaines dates seront ajoutées prochainement. Vous pouvez contacter Kévin au 06 74 80 67 07.</p>';return ob_get_clean();}
    ?>
    <form class="kcfr-form" data-kcfr-form="book">
    <label for="kcfr-session">Choisir un cours et une date</label><select id="kcfr-session" name="session" aria-describedby="kcfr-session-preview" data-kcfr-session-choices required><option value="">Sélectionner une séance</option>
    <?php foreach($sessions as $s){
        $locked=kcfr_session_lock($s->id); if($locked){kcfr_refresh_locked($locked,time());} $wpdb->query('COMMIT');
        $free=max(0,$s->capacity-kcfr_occupancy($s->id));
        $label=$s->title.' · '.kcfr_when($s->starts_at).' · '.$s->venue.' · '.($free?$free.' place(s) restante(s)':'complet — liste d’attente');
        echo '<option value="'.(int)$s->id.'">'.esc_html($label).'</option>';
    } ?></select><p id="kcfr-session-preview" class="kcfr-session-preview" aria-live="polite"></p>
    <?php echo kcfr_identity_fields('kcfr-book'); ?>
    <p class="kcfr-field-note">15 places par cours. Réservation à chaque séance, même avec une formule par période. Paiement en une fois par période, sur place. Si le cours est complet, la demande rejoint la liste d’attente.</p>
    <label class="kcfr-check"><input type="checkbox" required> <?php echo kcfr_live()?'Je conserve mon lien privé pour suivre ou annuler mon inscription.':'Je comprends qu’il s’agit d’un test, sans inscription réelle.'; ?></label>
    <button type="submit"><?php echo kcfr_live()?'Réserver ma place':'Tester la réservation'; ?></button><?php echo kcfr_result_area(); ?>
    </form><noscript><p>Pour utiliser le formulaire, activez JavaScript ou contactez Kévin par téléphone.</p></noscript>
    <?php return ob_get_clean();
});
add_shortcode('kcfr_manage',function(){return kcfr_demo_notice().'<section class="kcfr-management" data-kcfr-management><p>Ouverture de votre inscription…</p></section>';});
add_shortcode('kcfr_coaching',function(){ob_start();echo kcfr_demo_notice();?>
    <form class="kcfr-form" data-kcfr-form="request"><input type="hidden" name="kind" value="coaching">
    <?php echo kcfr_identity_fields('kcfr-coach'); ?>
    <div class="kcfr-form-grid"><div><label for="kcfr-coach-date">Date souhaitée</label><input id="kcfr-coach-date" name="date" type="date" min="<?php echo esc_attr(wp_date('Y-m-d',time(),kcfr_timezone())); ?>" required></div><div><label for="kcfr-coach-time">Heure souhaitée</label><input id="kcfr-coach-time" name="time" type="time" min="07:00" required></div></div>
    <label for="kcfr-coach-address">Adresse du rendez-vous</label><textarea id="kcfr-coach-address" name="address" rows="2" autocomplete="street-address" maxlength="500" required></textarea>
    <label for="kcfr-coach-phone">Téléphone, si vous souhaitez être rappelé</label><input id="kcfr-coach-phone" name="phone" type="tel" autocomplete="tel" maxlength="40">
    <label for="kcfr-coach-note">Votre objectif ou votre question (facultatif)</label><textarea id="kcfr-coach-note" name="note" rows="3" maxlength="2000"></textarea>
    <p class="kcfr-field-note">45 minutes de séance + 15 minutes d’échange, déplacements en plus. Zone : 15 minutes autour de Loupiac. Ce formulaire propose une date ; Kévin devra vérifier son agenda et le trajet avant toute confirmation.</p>
    <button type="submit"><?php echo kcfr_live()?'Proposer ce rendez-vous':'Tester une demande de coaching'; ?></button><?php echo kcfr_result_area(); ?></form>
    <?php return ob_get_clean();});
