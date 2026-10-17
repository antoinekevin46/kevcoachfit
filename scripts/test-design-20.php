<?php
// Integration checks against the local WordPress only.
global $checks; $checks=0;
function kcf_assert20($condition,$message){global $checks;$checks++;if(!$condition){throw new Exception($message);}}
wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
$original_pages=get_option('kcf_workshop_pages');
$original_version=get_option('kcf_workshop_design');
$original_archive=get_option('kcf_workshop_archive_19',null);
$before=array(get_option('stylesheet'),get_option('template'),get_option('page_on_front'));
$created=array();$fixture=0;$error=null;
try {
    $fixture=wp_insert_post(array('post_type'=>'page','post_status'=>'draft','post_title'=>'Migration fixture 15','post_content'=>'Personal edited content, keep this.','meta_input'=>array('_kcf_workshop_source'=>'vitalivan','_kcf_workshop_design'=>'19')));
    update_option('kcf_workshop_pages',array('vitalivan'=>$fixture));update_option('kcf_workshop_design','19');
    $pages=kcf_prepare_workshop_pages(WP_PLUGIN_DIR.'/kevcoachfit-workflows/starter-pages',true);
    kcf_assert20(!is_wp_error($pages),'migration returns an error');$created=array_values($pages);
    kcf_assert20(count($pages)===7,'seven pages expected');
    kcf_assert20(get_option('kcf_workshop_design')==='20','wrong target version');
    kcf_assert20(get_post_field('post_content',$fixture)==='Personal edited content, keep this.','old edit lost');
    kcf_assert20(get_option('kcf_workshop_archive_19')['vitalivan']===$fixture,'old draft not archived');
    foreach($pages as $slug=>$id){
        kcf_assert20(get_post_status($id)==='draft','new page published');
        kcf_assert20(get_post_meta($id,'_kcf_workshop_design',true)==='20','wrong draft metadata');
        kcf_assert20(str_contains(get_post_field('post_name',$id),'atelier-20-'),'wrong draft URL');
        kcf_assert20(count(array_filter(parse_blocks(get_post_field('post_content',$id)),fn($b)=>!empty($b['blockName'])))>0,'missing Gutenberg blocks');
    }
    $body=get_post_field('post_content',$pages['vitalivan']);
    foreach(array('120 € / période','220 € / période','80 € / période','147 € / période','8 €','Vitali’Seniors','Vitali’Renfort','Vitali’Training','Vitali’Coach','CFPPA','Pass’Sport','Chèques-Vacances','Coupons Sport','Sport sur Ordonnance') as $text){kcf_assert20(str_contains($body,$text),'offer missing: '.$text);}
    kcf_assert20(!preg_match('/(?:60|110)€?\/trimestre/u',$body),'old fees still active');
    wp_update_post(array('ID'=>$pages['vitalivan'],'post_content'=>$body.'\n<!-- retained edit -->'));
    $again=kcf_prepare_workshop_pages(WP_PLUGIN_DIR.'/kevcoachfit-workflows/starter-pages',true);
    kcf_assert20($again===$pages,'repeat preparation created new drafts');
    kcf_assert20(str_contains(get_post_field('post_content',$pages['vitalivan']),'retained edit'),'new personal edit lost');
    kcf_assert20($before===array(get_option('stylesheet'),get_option('template'),get_option('page_on_front')),'public settings changed');
    foreach(array('sport','protection','terroir','portail') as $section){
        $head=kcf_section_header($section);$foot=kcf_section_footer($section);$info=kcf_section_info($section);
        kcf_assert20(str_contains($head,$info['brand']),'brand not shown');
        kcf_assert20(str_contains($head,'<nav'),'navigation missing');
        if($section==='protection'){kcf_assert20(!str_contains($head,'/reserver/'),'sport navigation leaked into pest header');kcf_assert20(str_contains($foot,'aknuisible@gmail.com'),'wrong pest email');}
        if($section==='terroir'){kcf_assert20(!str_contains($head,'/reserver/'),'sport navigation leaked into terroir');kcf_assert20(str_contains($foot,'06 33 17 63 28'),'Yohan contact missing');}
    }
    $_GET['kcf_atelier']='1';
    $url=kcf_section_url('vitalivan','tarifs');
    kcf_assert20(str_contains($url,'preview=true')&&str_ends_with($url,'#tarifs'),'private link not retained');unset($_GET['kcf_atelier']);
    kcf_assert20(str_ends_with(kcf_section_url('vitalivan','tarifs'),'/vitalivan/#tarifs'),'wrong normal link');
    foreach($pages as $slug=>$id){kcf_assert20(kcf_search_key($id)===$slug,'SEO identity wrong');}
    kcf_assert20(kcf_section_key($pages['reserver'])==='sport','booking not in sports');
    kcf_assert20(kcf_section_key($pages['terroir-loupiac'])==='terroir','terroir context wrong');
    kcf_assert20(kcf_section_key($pages['ak-anti-nuisible'])==='protection','pest context wrong');
    $old_public=get_option('blog_public');update_option('blog_public','1');
    foreach(array('reserver','mes-reservations') as $key){wp_update_post(array('ID'=>$pages[$key],'post_status'=>'publish'));kcf_assert20(!kcf_search_public($pages[$key]),'workflow page indexable');}
    update_option('blog_public',$old_public);
    kcf_assert20(str_contains(kcf_search_pages()['vitalivan']['description'],'janvier–juin'),'SEO fee period missing');
    $map_data=kcf_map_data();
    kcf_assert20(count($map_data['towns'])===20,'twenty verified communes expected');
    $by_name=array_column($map_data['towns'],null,'name');
    kcf_assert20($by_name['Calès']['code']==='46047','wrong Cales selected');
    kcf_assert20($by_name['Loupiac']['code']==='46178','wrong Loupiac selected');
    $sport_map=do_shortcode('[kcf_service_map profile="sport"]');$pest_map=do_shortcode('[kcf_service_map profile="protection"]');
    kcf_assert20(substr_count($sport_map,'data-town="')===20,'map markers missing');
    kcf_assert20(str_contains($sport_map,'data-map-mode="courses"')&&str_contains($sport_map,'data-map-mode="coaching"'),'two sport map modes missing');
    kcf_assert20(!str_contains($pest_map,'data-map-mode="courses"'),'sports mixed into pest map');
    kcf_assert20(!str_contains($sport_map,'Sarnels'),'private base address disclosed');
    kcf_assert20(str_contains($sport_map,'ne représentent pas des limites de temps de trajet'),'routing limitation missing');
    kcf_assert20(do_shortcode('[kcf_service_map profile="invalid"]')==='','unknown map profile rendered');
    kcf_assert20(str_contains(kcf_map_description('46290','sport'),'Lancement et modalités à confirmer'),'future course portrayed as active');


    foreach(array('Quel cours est fait pour vous ?','Vos questions, simplement','Kévin Antoine, à vos côtés','Trouver votre salle, simplement','Les retours des participants','vitalivan-planning-cours.png') as $text){kcf_assert20(str_contains($body,$text),'new section missing: '.$text);}
    kcf_assert20(substr_count($body,'kcf-faq-item')===16,'eight native FAQ details expected');
    kcf_assert20(substr_count($body,'Photo à venir')===5,'five honest photo placeholders expected');
    kcf_assert20(!str_contains($body,'★★★★★'),'fake review stars');
    $old_body=file_get_contents('/project/scripts/fixtures/vitalivan-17.html');
    $old_body=str_replace('/wp-content/themes/kevcoachfit/',trailingslashit(get_theme_root_uri()).'kevcoachfit/',$old_body);
    $find_programs=function($blocks)use(&$find_programs){$out=array();foreach($blocks as $block){if(str_contains($block['attrs']['className']??'','kcf-program-card')){$out[]=serialize_block($block);}else{$out=array_merge($out,$find_programs($block['innerBlocks']??array()));}}return $out;};
    $old_programs=$find_programs(parse_blocks($old_body));$new_programs=$find_programs(parse_blocks($body));
    kcf_assert20(count($new_programs)===4&&$old_programs===$new_programs,'original detailed program offers changed');
    $ak=get_post_field('post_content',$pages['ak-anti-nuisible']);
    kcf_assert20(substr_count($ak,'<details class="wp-block-details kcf-ak-faq-item"')===6,'six pest FAQs expected');
    kcf_assert20(substr_count($ak,'<div class="wp-block-group kcf-ak-step"')===3,'three contact steps expected');
    kcf_assert20(str_contains($ak,'subject=Demande%20de%20devis')&&str_contains($ak,'Commune%20%3A'),'prefilled quote email missing');
    $old_ak=file_get_contents('/project/scripts/fixtures/ak-nuisibles-18.html');
    $old_ak=str_replace('/wp-content/themes/kevcoachfit/',trailingslashit(get_theme_root_uri()).'kevcoachfit/',$old_ak);
    $find_services=function($blocks)use(&$find_services){$out=array();foreach($blocks as $block){if(preg_match('/\\bkcf-pest-(services|bees)\\b/',$block['attrs']['className']??'')){$out[]=serialize_block($block);}else{$out=array_merge($out,$find_services($block['innerBlocks']??array()));}}return $out;};
    kcf_assert20(count($find_services(parse_blocks($ak)))===2&&$find_services(parse_blocks($old_ak))===$find_services(parse_blocks($ak)),'flyer service or bee information changed');
    foreach(array('Traitement des fourmis','Traitement des cafards et blattes','7 h à 19 h','7 h à 16 h','Lundi à jeudi','2 au 4 novembre inclus') as $text){kcf_assert20(str_contains($ak,$text),'agreed AK content missing: '.$text);}
    kcf_assert20(!str_contains($ak,'punaises de lit')&&!str_contains($ak,'Traitement des mites'),'excluded service offered');
} catch(Throwable $e){$error=$e->getMessage();}
finally {
    foreach($created as $id){wp_delete_post($id,true);}if($fixture){wp_delete_post($fixture,true);}
    update_option('kcf_workshop_pages',$original_pages);update_option('kcf_workshop_design',$original_version);delete_option('kcf_workshop_pending_20');
    if($original_archive===null){delete_option('kcf_workshop_archive_19');}else{update_option('kcf_workshop_archive_19',$original_archive);}
}
if($error){WP_CLI::error($error);}
WP_CLI::success($checks.' checks: migrations, edits, drafts, fees, four programs, activity navigation, private URLs and SEO.');
