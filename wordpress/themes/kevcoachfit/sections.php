<?php
/** Activity-specific navigation. One WordPress, three independent visitor journeys. */
defined('ABSPATH') || exit;
function kcf_section_key($id = 0) {
    $id = $id ?: get_queried_object_id();
    $page = kcf_search_key($id);
    if (in_array($page, array('vitalivan', 'reserver', 'mes-reservations'), true)) { return 'sport'; }
    if ($page === 'ak-anti-nuisible') { return 'protection'; }
    if ($page === 'terroir-loupiac') { return 'terroir'; }
    if(in_array($page,array('accueil','contact'),true)){return kcf_search_key((int)get_option('page_on_front'))==='vitalivan'?'sport':'portail';}
    return '';
}
function kcf_section_url($slug, $anchor = '') {
    // An explicit preview URL, including an installation in a subdirectory.
    if (!empty($_GET['kcf_atelier']) && current_user_can('manage_options') && function_exists('kcf_workshop_url')) {
        $preview = kcf_workshop_url($slug);
        if ($preview) { return $preview . ($anchor ? '#' . $anchor : ''); }
    }
    $front_key=kcf_search_key((int)get_option('page_on_front'));
    $url = home_url($slug === 'accueil' || $slug===$front_key ? '/' : '/' . $slug . '/');
    return $url . ($anchor ? '#' . $anchor : '');
}
function kcf_section_info($section) {
    $sections = array(
        'sport' => array('brand'=>'Vitali’Van', 'tag'=>'SPORT & BIEN-ÊTRE · LOUPIAC, LOT', 'home'=>'vitalivan', 'phone'=>'+33674806707', 'display_phone'=>'06 74 80 67 07', 'email'=>'kevcoachfit@gmail.com', 'links'=>array(array('Programmes','vitalivan','programmes'), array('Planning','vitalivan','planning'), array('Tarifs','vitalivan','tarifs'), array('Réserver','reserver','cours'))),
        'protection' => array('brand'=>'A.K Nuisibles', 'tag'=>'PAYRAC & ALENTOURS · ACTIVITÉ EN PRÉPARATION', 'home'=>'ak-anti-nuisible', 'phone'=>'+33674806707', 'display_phone'=>'06 74 80 67 07', 'email'=>'aknuisible@gmail.com', 'links'=>array(array('Prestations','ak-anti-nuisible','prestations-ak'), array('Essaims d’abeilles','ak-anti-nuisible','essaims-ak'), array('Secteur & contact','ak-anti-nuisible','contact-ak'), array('Appeler Kévin','tel:+33674806707',''))),
        'terroir' => array('brand'=>'Terroir Antoine', 'tag'=>'KEV’MIEL & MAISON LOU’PIAC · LOUPIAC, LOT', 'home'=>'terroir-loupiac', 'phone'=>'+33674806707', 'display_phone'=>'06 74 80 67 07', 'email'=>'', 'links'=>array(array('Les miels','terroir-loupiac','miels-de-kevin'), array('Vins & spiritueux','terroir-loupiac','maison-loupiac'), array('Retrait sur rendez-vous','terroir-loupiac','retrait-terroir'))),
        'portail' => array('brand'=>'KevCoachFit', 'tag'=>'KÉVIN ANTOINE · LOUPIAC 46350', 'home'=>'accueil', 'phone'=>'+33674806707', 'display_phone'=>'06 74 80 67 07', 'email'=>'kevcoachfit@gmail.com', 'links'=>array(array('Vitali’Van','vitalivan',''), array('A.K Nuisibles','ak-anti-nuisible',''), array('Terroir','terroir-loupiac',''), array('Contact','contact',''))),
    );
    return $sections[$section] ?? null;
}
function kcf_section_link($item) {
    $href = str_starts_with($item[1], 'tel:') ? $item[1] : kcf_section_url($item[1], $item[2]);
    return '<a class="wp-block-navigation-item__content" href="'.esc_url($href).'">'.esc_html($item[0]).'</a>';
}
function kcf_section_header($section) {
    $info=kcf_section_info($section); if (!$info) { return ''; }
    $has_rdv=in_array($section,array('sport','portail'),true);
    $rdv=$has_rdv?'<a class="kcf-header-rdv" href="'.esc_url(kcf_section_url('reserver')).'" aria-label="Rendez-vous et réservation des cours"><span class="kcf-rdv-desktop">Réserver</span><span class="kcf-rdv-mobile">RDV</span></a>':'';
    $header_class=$has_rdv?' kcf-header-has-rdv':'';
    $html='<div class="wp-block-group kcf-site-header kcf-section-header'.$header_class.' kcf-section-'.$section.'"><div class="wp-block-group kcf-brand"><p class="wp-block-site-title"><a href="'.esc_url(kcf_section_url($info['home'])).'">'.esc_html($info['brand']).'</a></p><p class="kcf-brand-location">'.esc_html($info['tag']).'</p></div>'.$rdv.'<nav class="wp-block-navigation" aria-label="'.esc_attr('Navigation '.$info['brand']).'"><ul class="wp-block-navigation__container">';
    $page_links=$info['links'];
    foreach($page_links as $item) { $html.='<li class="wp-block-navigation-item">'.kcf_section_link($item).'</li>'; }
    return '<header class="wp-block-template-part">'.$html.'</ul></nav></div></header>';
}
function kcf_section_footer($section) {
    $info=kcf_section_info($section); if (!$info) { return ''; }
    $html='<div class="wp-block-group kcf-site-footer kcf-section-footer kcf-section-'.$section.'"><div class="kcf-section-footer-grid"><div><h2 class="kcf-footer-signature">'.esc_html($info['brand']).'</h2><p>'.esc_html($info['tag']).'</p></div><div><h2>Dans cet espace</h2><ul>';
    foreach($info['links'] as $item) { $html.='<li>'.kcf_section_link($item).'</li>'; }
    $html.='</ul></div><div><h2>'.($section==='terroir'?'Vos producteurs':'Votre contact').'</h2><p><a href="tel:'.esc_attr($info['phone']).'">'.($section==='terroir'?'Kévin · ':'').esc_html($info['display_phone']).'</a></p>';
    if ($info['email']) { $html.='<p><a href="mailto:'.esc_attr($info['email']).'">'.esc_html($info['email']).'</a></p>'; }
    if ($section==='terroir') { $html.='<p><a href="tel:+33633176328">Yohan · 06 33 17 63 28</a></p><p>Deux vendeurs, confirmation séparée et paiement au retrait sur rendez-vous.</p>'; }
    if ($section==='sport') { $html.='<p class="kcf-social-links"><a href="https://www.instagram.com/kev_coach_fit/">Instagram Vitali’Van</a><a href="https://www.facebook.com/share/14sJ6TNTfyN/">Facebook de Kévin</a></p>'; }
    if ($section==='protection') { $html.='<p>Devis gratuit · prestations et ouverture à confirmer avec Kévin.</p>'; }
    $html.='</div></div>';
    if($section==='portail'){$html.='<details class="kcf-other-spaces"><summary>Les autres univers de Kévin</summary><p><a href="'.esc_url(kcf_section_url('accueil')).'">Le portail KevCoachFit</a> · <a href="'.esc_url(kcf_section_url('vitalivan')).'">Vitali’Van</a> · <a href="'.esc_url(kcf_section_url('ak-anti-nuisible')).'">A.K Nuisibles</a> · <a href="'.esc_url(kcf_section_url('terroir-loupiac')).'">Terroir Antoine</a></p></details>';}
    $html.='<nav class="kcf-activity-switch" aria-label="Découvrir les activités de Kévin"><p>Les activités de Kévin et de sa famille</p><ul><li><a href="'.esc_url(kcf_section_url('vitalivan')).'">Vitali’Van · sport et coaching</a></li><li><a href="'.esc_url(kcf_section_url('ak-anti-nuisible')).'">A.K Nuisibles</a></li><li><a href="'.esc_url(kcf_section_url('terroir-loupiac')).'">Miel et vin · Terroir Antoine</a></li></ul></nav>';
    $html.='</div>';
    return '<footer class="wp-block-template-part">'.$html.'</footer>';
}
add_filter('render_block_core/template-part',function($html,$block){
    if(!is_singular('page')) { return $html; }
    $section=kcf_section_key(); if(!$section) { return $html; }
    $slug=$block['attrs']['slug']??'';
    if($slug==='header') { return kcf_section_header($section); }
    if($slug==='footer') { return kcf_section_footer($section); }
    return $html;
}, 10, 2);
add_filter('body_class',function($classes){$section=kcf_section_key();if($section){$classes[]='kcf-universe-'.$section;}return $classes;});
