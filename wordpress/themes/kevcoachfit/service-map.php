<?php
/** Self-contained geographic map. Administrative boundaries are not driving-time limits. */
defined('ABSPATH') || exit;
function kcf_map_data(){
    static $data=null;
    if($data===null){$data=json_decode(file_get_contents(__DIR__.'/data/communes-lot.json'),true);}
    return $data;
}
function kcf_map_point($lon,$lat){return array(($lon-1.46)*cos(deg2rad(44.82))*3000+500,(44.82-$lat)*3000+360);}
function kcf_map_description($code,$profile){
    $course=array('46047'=>'Calès · salle des fêtes. Vitali’Seniors lundi 17 h, Vitali’Renfort jeudi 18 h.','46215'=>'Payrac · salle polyvalente. Vitali’Seniors mardi 17 h 30, Vitali’Training mardi 18 h 30, Vitali’Renfort jeudi 19 h 05.','46290'=>'Saint-Projet · salle des fêtes. Cours seniors prévu le mercredi à 17 h 45 à partir de janvier 2027. Lancement et modalités à confirmer.');
    if($profile==='sport'&&isset($course[$code])){return $course[$code];}
    if($profile==='sport'&&$code==='46178'){return 'Loupiac 46350 · point de référence du coaching à domicile. Secteur annoncé : environ 15 minutes de trajet ; disponibilité et adresse à confirmer avec Kévin.';}
    if($profile==='protection'&&$code==='46215'){return 'Payrac · point de référence du secteur A.K Nuisibles : environ 20 minutes de trajet. Activité en préparation, intervention à confirmer avec Kévin.';}
    if($profile==='protection'&&in_array($code,array('46178','46309','46127'),true)){return 'Commune citée dans le secteur du flyer A.K Nuisibles. Vérifiez votre adresse, les conditions et la disponibilité directement avec Kévin. Activité en préparation.';}
    return 'Commune voisine pour vous repérer. Sa présence sur la carte ne confirme pas une intervention : contactez Kévin pour vérifier votre adresse.';
}
add_shortcode('kcf_service_map',function($attributes){
    $a=shortcode_atts(array('profile'=>'sport'),$attributes);$profile=$a['profile'];
    if(!in_array($profile,array('sport','protection'),true)){return '';}
    $data=kcf_map_data();if(empty($data['towns'])){return '<p>Pour vérifier votre secteur, appelez Kévin au 06 74 80 67 07.</p>';}
    $id=wp_unique_id('kcf-map-');$sport=$profile==='sport';
    $title=$sport?'Vos cours et votre coaching, près de chez vous':'Votre secteur autour de Payrac';
    $html='<section class="kcf-service-map kcf-map-'.$profile.'" data-kcf-map data-profile="'.$profile.'" aria-labelledby="'.$id.'-title"><div class="kcf-map-heading"><p class="kcf-eyebrow">LOUPIAC · PAYRAC · LOT</p><h3 id="'.$id.'-title">'.esc_html($title).'</h3><p>'.($sport?'Distinguez les lieux de cours de votre rendez-vous à domicile.':'Repérez les communes citées dans le flyer et leurs voisines.').'</p></div>';
    if($sport){$html.='<div class="kcf-map-modes" role="group" aria-label="Type de prestation"><button type="button" data-map-mode="courses" aria-pressed="true">Cours en salle</button><button type="button" data-map-mode="coaching" aria-pressed="false">Coaching à domicile</button></div>';}
    $html.='<div class="kcf-map-layout"><div class="kcf-map-canvas"><div class="kcf-map-tools" role="group" aria-label="Commandes de la carte"><button type="button" data-map-action="in" aria-label="Zoom avant">+</button><button type="button" data-map-action="out" aria-label="Zoom arrière">−</button><button type="button" data-map-action="reset">Vue d’ensemble</button><button type="button" data-map-action="drag" aria-pressed="false">Déplacer</button></div><svg class="kcf-map-svg" viewBox="0 0 1000 840" tabindex="0" role="group" aria-label="Carte des communes du secteur, zoom et déplacement disponibles" aria-describedby="'.$id.'-help"><g class="kcf-map-stage"><g class="kcf-map-boundaries" aria-hidden="true">';
    foreach($data['towns'] as $town){
        $path='';foreach($town['rings'] as $ring){foreach($ring as $i=>$point){$xy=kcf_map_point($point[0],$point[1]);$path.=($i===0?'M':'L').round($xy[0],2).','.round($xy[1],2);}$path.='Z';}
        $html.='<path d="'.esc_attr($path).'" data-boundary="'.esc_attr($town['code']).'" vector-effect="non-scaling-stroke"/>';
    }
    $html.='</g><g class="kcf-map-towns">';
    foreach($data['towns'] as $town){
        $xy=kcf_map_point($town['lon'],$town['lat']);$major=in_array($town['code'],array('46178','46215','46047','46290','46309','46127'),true);
        $html.='<g class="kcf-map-town'.($major?' is-major show-label':'').(($sport&&in_array($town['code'],array('46047','46215','46290'),true))||(!$sport&&$town['code']==='46215')?' is-active':'').($sport&&$town['code']==='46290'?' is-future':'').'" data-town="'.esc_attr($town['code']).'" data-x="'.round($xy[0],2).'" data-y="'.round($xy[1],2).'" transform="translate('.round($xy[0],2).','.round($xy[1],2).')"><title>'.esc_html($town['name']).'</title><circle class="kcf-map-halo" r="12"/><circle class="kcf-map-dot" r="5"/><text x="10" y="-10">'.esc_html($town['label']).'</text></g>';
    }
    $html.='</g></g></svg><span class="kcf-map-north" aria-hidden="true">↑ N</span><p id="'.$id.'-help" class="kcf-map-help">Utilisez + et − pour zoomer. Activez « Déplacer » pour glisser ou zoomer avec deux doigts. Au clavier : +, − et flèches sur la carte.</p></div><div class="kcf-map-info"><label for="'.$id.'-town">Repérer une commune</label><select id="'.$id.'-town" data-map-select><option value="">Choisir une commune</option>';
    foreach($data['towns'] as $town){$html.='<option value="'.esc_attr($town['code']).'" data-description="'.esc_attr(kcf_map_description($town['code'],$profile)).'">'.esc_html($town['name']).'</option>';}
    $html.='</select><div class="kcf-map-status" role="status" aria-live="polite"><h4 data-map-name>'.($sport?'Cours en salle':'A.K Nuisibles').'</h4><p data-map-description>'.($sport?'Calès et Payrac : cours actuels. Saint-Projet : cours à venir. Les points indiquent les communes, pas l’entrée exacte des salles.':'Secteur annoncé : environ 20 minutes autour de Payrac, notamment Loupiac, Souillac et Gourdon. Activité en préparation.').'</p></div><p class="kcf-map-legend"><span class="kcf-map-legend-dot"></span><span data-map-legend>'.($sport?'Lieu de cours · pointillé : à venir':'Point de référence du secteur').'</span></p><p class="kcf-map-scope" data-map-scope>'.($sport?'Coaching à domicile : environ 15 minutes autour de Loupiac. Choisissez cette vue pour distinguer les deux prestations.':'Le trajet et la prise en charge dépendent de votre adresse. Appelez pour confirmer.').'</p><a class="kcf-map-call" href="tel:+33674806707">Vérifier mon secteur avec Kévin</a></div></div><p class="kcf-map-source">Contours et centres communaux : <a href="https://geo.api.gouv.fr/decoupage-administratif" target="_blank" rel="noopener noreferrer">API Découpage administratif</a>. Carte simplifiée : les contours ne représentent pas des limites de temps de trajet. Communes voisines affichées à titre de repère.</p><noscript><p>La carte reste visible sans JavaScript. Pour vérifier une adresse et votre prestation, appelez Kévin : 06 74 80 67 07.</p></noscript></section>';
    return $html;
});
