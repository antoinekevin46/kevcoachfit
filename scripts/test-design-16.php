<?php
// Integration checks against the local WordPress only.
global $checks; $checks=0;
function kcf_assert16($condition,$message){global $checks;$checks++;if(!$condition){throw new Exception($message);}}
wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
$original_pages=get_option('kcf_workshop_pages');
$original_version=get_option('kcf_workshop_design');
$original_archive=get_option('kcf_workshop_archive_15',null);
$before=array(get_option('stylesheet'),get_option('template'),get_option('page_on_front'));
$created=array();$fixture=0;$error=null;
try {
    $fixture=wp_insert_post(array('post_type'=>'page','post_status'=>'draft','post_title'=>'Migration fixture 15','post_content'=>'Personal edited content, keep this.','meta_input'=>array('_kcf_workshop_source'=>'vitalivan','_kcf_workshop_design'=>'15')));
    update_option('kcf_workshop_pages',array('vitalivan'=>$fixture));update_option('kcf_workshop_design','15');
    $pages=kcf_prepare_workshop_pages(WP_PLUGIN_DIR.'/kevcoachfit-workflows/starter-pages',true);
    kcf_assert16(!is_wp_error($pages),'migration returns an error');$created=array_values($pages);
    kcf_assert16(count($pages)===7,'seven pages expected');
    kcf_assert16(get_option('kcf_workshop_design')==='16','wrong target version');
    kcf_assert16(get_post_field('post_content',$fixture)==='Personal edited content, keep this.','old edit lost');
    kcf_assert16(get_option('kcf_workshop_archive_15')['vitalivan']===$fixture,'old draft not archived');
    foreach($pages as $slug=>$id){
        kcf_assert16(get_post_status($id)==='draft','new page published');
        kcf_assert16(get_post_meta($id,'_kcf_workshop_design',true)==='16','wrong draft metadata');
        kcf_assert16(str_contains(get_post_field('post_name',$id),'atelier-16-'),'wrong draft URL');
        kcf_assert16(count(array_filter(parse_blocks(get_post_field('post_content',$id)),fn($b)=>!empty($b['blockName'])))>0,'missing Gutenberg blocks');
    }
    $body=get_post_field('post_content',$pages['vitalivan']);
    foreach(array('120 € / période','220 € / période','80 € / période','147 € / période','8 €','Vitali’Seniors','Vitali’Renfort','Vitali’Training','Vitali’Coach','CFPPA','Pass’Sport','Chèques-Vacances','Coupons Sport','Sport sur Ordonnance') as $text){kcf_assert16(str_contains($body,$text),'offer missing: '.$text);}
    kcf_assert16(!preg_match('/(?:60|110)€?\/trimestre/u',$body),'old fees still active');
    wp_update_post(array('ID'=>$pages['vitalivan'],'post_content'=>$body.'\n<!-- retained edit -->'));
    $again=kcf_prepare_workshop_pages(WP_PLUGIN_DIR.'/kevcoachfit-workflows/starter-pages',true);
    kcf_assert16($again===$pages,'repeat preparation created new drafts');
    kcf_assert16(str_contains(get_post_field('post_content',$pages['vitalivan']),'retained edit'),'new personal edit lost');
    kcf_assert16($before===array(get_option('stylesheet'),get_option('template'),get_option('page_on_front')),'public settings changed');
    foreach(array('sport','protection','terroir','portail') as $section){
        $head=kcf_section_header($section);$foot=kcf_section_footer($section);$info=kcf_section_info($section);
        kcf_assert16(str_contains($head,$info['brand']),'brand not shown');
        kcf_assert16(str_contains($head,'<nav'),'navigation missing');
        if($section==='protection'){kcf_assert16(!str_contains($head,'/reserver/'),'sport navigation leaked into pest header');kcf_assert16(str_contains($foot,'aknuisible@gmail.com'),'wrong pest email');}
        if($section==='terroir'){kcf_assert16(!str_contains($head,'/reserver/'),'sport navigation leaked into terroir');kcf_assert16(str_contains($foot,'06 33 17 63 28'),'Yohan contact missing');}
    }
    $_GET['kcf_atelier']='1';
    $url=kcf_section_url('vitalivan','tarifs');
    kcf_assert16(str_contains($url,'preview=true')&&str_ends_with($url,'#tarifs'),'private link not retained');unset($_GET['kcf_atelier']);
    kcf_assert16(str_ends_with(kcf_section_url('vitalivan','tarifs'),'/vitalivan/#tarifs'),'wrong normal link');
    foreach($pages as $slug=>$id){kcf_assert16(kcf_search_key($id)===$slug,'SEO identity wrong');}
    kcf_assert16(kcf_section_key($pages['reserver'])==='sport','booking not in sports');
    kcf_assert16(kcf_section_key($pages['terroir-loupiac'])==='terroir','terroir context wrong');
    kcf_assert16(kcf_section_key($pages['ak-anti-nuisible'])==='protection','pest context wrong');
    $old_public=get_option('blog_public');update_option('blog_public','1');
    foreach(array('reserver','mes-reservations') as $key){wp_update_post(array('ID'=>$pages[$key],'post_status'=>'publish'));kcf_assert16(!kcf_search_public($pages[$key]),'workflow page indexable');}
    update_option('blog_public',$old_public);
    kcf_assert16(str_contains(kcf_search_pages()['vitalivan']['description'],'janvier–juin'),'SEO fee period missing');
} catch(Throwable $e){$error=$e->getMessage();}
finally {
    foreach($created as $id){wp_delete_post($id,true);}if($fixture){wp_delete_post($fixture,true);}
    update_option('kcf_workshop_pages',$original_pages);update_option('kcf_workshop_design',$original_version);delete_option('kcf_workshop_pending_16');
    if($original_archive===null){delete_option('kcf_workshop_archive_15');}else{update_option('kcf_workshop_archive_15',$original_archive);}
}
if($error){WP_CLI::error($error);}
WP_CLI::success($checks.' checks: migrations, edits, drafts, fees, four programs, activity navigation, private URLs and SEO.');
