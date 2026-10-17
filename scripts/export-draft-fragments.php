<?php
// Local only: resolve dynamic demo fragments for a portable, non-submitting preview.
wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
$slugs=array('accueil'=>'portail','vitalivan'=>'sport','ak-anti-nuisible'=>'protection','terroir-loupiac'=>'terroir','contact'=>'portail','reserver'=>'sport','mes-reservations'=>'sport');
$out=array('pages'=>array(),'shortcodes'=>array());
foreach($slugs as $slug=>$section){
 $body=file_get_contents(WP_PLUGIN_DIR.'/kevcoachfit-workflows/starter-pages/'.$slug.'.html');
 preg_match_all('/\[kcf_[^\]]+\]/',$body,$matches);
 foreach($matches[0] as $shortcode){$out['shortcodes'][$shortcode]=do_shortcode($shortcode);}
 $out['pages'][$slug]=array('header'=>kcf_section_header($section),'footer'=>kcf_section_footer($section),'section'=>$section,'body'=>$body);
}
echo wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
