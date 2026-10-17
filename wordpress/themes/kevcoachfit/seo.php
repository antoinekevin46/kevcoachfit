<?php
/** Local search metadata. Defer to a dedicated SEO extension when one is active. */
defined('ABSPATH') || exit;
function kcf_search_pages() {
    return array(
        'accueil'=>array('title'=>'KevCoachFit à Loupiac : sport, nature et terroir','description'=>'Découvrez Kévin Antoine à Loupiac, dans le Lot : Vitali’Van, A.K Nuisibles en préparation, Kev’Miel et les vins Maison Lou’piac de Yohan.'),
        'vitalivan'=>array('title'=>'Fitness à Payrac et Calès, coaching à Loupiac | Vitali’Van','description'=>'Cours seniors, renforcement musculaire et cross-training à Calès et Payrac. Coaching privé autour de Loupiac, dans le Lot. Horaires, formules janvier–juin et septembre–Noël, et contact.'),
        'ak-anti-nuisible'=>array('title'=>'Frelons, guêpes et dératisation à Payrac | A.K Nuisibles','description'=>'Activité en préparation : frelons, guêpes, fourmis, cafards, dératisation et piégeage autour de Payrac, Loupiac, Souillac et Gourdon, dans le Lot.'),
        'terroir-loupiac'=>array('title'=>'Kev’Miel et vins Maison Lou’piac à Loupiac, Lot','description'=>'Kev’Miel : miel toutes fleurs et miel de forêt de Kévin. Vins et alcools Maison Lou’Piac de Yohan Antoine. Deux catalogues, retrait à Loupiac dans le Lot.'),
        'contact'=>array('title'=>'Contacter Kévin Antoine à Loupiac | KevCoachFit','description'=>'Contactez Kévin Antoine pour les cours Vitali’Van, le coaching et le miel à Loupiac. Retrouvez aussi le contact de Yohan pour les vins Maison Lou’Piac.'),
        'reserver'=>array('title'=>'Réserver un cours à Calès ou Payrac | Vitali’Van','description'=>'Choisissez votre séance Vitali’Van à Calès ou Payrac : 15 places par cours, liste d’attente et paiement sur place. Demande de coaching privé à domicile.'),
        'mes-reservations'=>array('title'=>'Mon inscription | Vitali’Van','description'=>'Consultez ou gérez votre inscription avec votre lien privé.'),
    );
}
function kcf_search_key($id) {
    $key=get_post_meta($id,'_kcf_workshop_source',true);
    if(!$key){$key=get_post_field('post_name',$id);}
    return isset(kcf_search_pages()[$key])?$key:'';
}
function kcf_search_metadata($id) {
    $key=kcf_search_key($id);$customized=false;
    $data=kcf_search_pages()[$key]??array('title'=>get_the_title($id).' | KevCoachFit','description'=>'');
    foreach(array('title','description') as $field){$custom=get_post_meta($id,'_kcf_search_'.$field,true);if(is_string($custom)&&trim($custom)!==''){$data[$field]=wp_strip_all_tags($custom);$customized=true;}}
    return $key||$customized?$data:array();
}
function kcf_search_external() {
    return (bool)apply_filters('kcf_search_managed_elsewhere',defined('WPSEO_VERSION')||defined('RANK_MATH_VERSION')||defined('SEOPRESS_VERSION')||defined('AIOSEO_VERSION')||defined('THE_SEO_FRAMEWORK_VERSION'));
}
function kcf_search_public($id) {
    return get_post_status($id)==='publish'&&!is_preview()&&empty($_GET['kcf_atelier'])&&(string)get_option('blog_public')==='1'&&!in_array(kcf_search_key($id),array('mes-reservations','reserver'),true);
}
add_filter('pre_get_document_title',function($title){
    if(kcf_search_external()||!is_singular('page')){return $title;}
    $data=kcf_search_metadata(get_queried_object_id());return isset($data['title'])?esc_html($data['title']):$title;
});
add_filter('wp_robots',function($robots){
    if(is_singular('page')&&kcf_search_key(get_queried_object_id())&&(is_preview()||!empty($_GET['kcf_atelier'])||get_post_status(get_queried_object_id())!=='publish'||in_array(kcf_search_key(get_queried_object_id()),array('mes-reservations','reserver'),true))){
        unset($robots['index'],$robots['follow']);$robots['noindex']=true;$robots['nofollow']=true;
    }
    return $robots;
});
function kcf_search_head() {
    if(kcf_search_external()||!is_singular('page')){return;}
    $id=get_queried_object_id();$data=kcf_search_metadata($id);if(!$data){return;}
    if($data['description']!==''){echo '<meta name="description" content="'.esc_attr($data['description']).'">'."\n";}
    if(!kcf_search_public($id)){return;}
    echo '<meta property="og:type" content="website">'."\n";
    foreach(array('title'=>$data['title'],'description'=>$data['description'],'url'=>get_permalink($id),'locale'=>'fr_FR') as $key=>$value){echo '<meta property="og:'.esc_attr($key).'" content="'.esc_attr($value).'">'."\n";}
    $key=kcf_search_key($id);
    $images=array('accueil'=>'vitali-renfort-interieur.webp','vitalivan'=>'vitali-seniors-interieur.webp','ak-anti-nuisible'=>'ak-carte-visite-originale.jpg','terroir-loupiac'=>'kevmiel-pots.webp');
    if(isset($images[$key])){
        $image=trailingslashit(get_stylesheet_directory_uri()).'assets/'.$images[$key];
        echo '<meta property="og:image" content="'.esc_url($image).'">'."\n";
        echo '<meta name="twitter:card" content="summary_large_image">'."\n";
    }
    $crumbs=array(array('@type'=>'ListItem','position'=>1,'name'=>'KevCoachFit','item'=>home_url('/')));
    if($key!=='accueil'){$crumbs[]=array('@type'=>'ListItem','position'=>2,'name'=>array('vitalivan'=>'Vitali’Van','ak-anti-nuisible'=>'A.K Nuisibles','terroir-loupiac'=>'Terroir Antoine','contact'=>'Contact')[$key]??get_the_title($id),'item'=>get_permalink($id));}
    if($key!=='accueil'){echo '<script type="application/ld+json">'.wp_json_encode(array('@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$crumbs),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>'."\n";}
    if(!in_array($key,array('accueil','vitalivan','contact'),true)){return;}
    $base=trailingslashit(home_url('/'));
    $schema=array('@context'=>'https://schema.org','@type'=>'LocalBusiness','@id'=>$base.'#kevcoachfit','name'=>'KevCoachFit','url'=>$base,'telephone'=>'+33674806707','email'=>'kevcoachfit@gmail.com','address'=>array('@type'=>'PostalAddress','addressLocality'=>'Loupiac','postalCode'=>'46350','addressRegion'=>'Lot','addressCountry'=>'FR'),'description'=>'Coaching sportif à domicile autour de Loupiac et cours collectifs Vitali’Van à Calès et Payrac.','areaServed'=>array('Loupiac 46350','Calès','Payrac'));
    echo '<script type="application/ld+json">'.wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>'."\n";
}
add_action('wp_head','kcf_search_head',5);
add_action('add_meta_boxes_page',function(){
    add_meta_box('kcf-search','Apparition dans les moteurs de recherche',function($post){
        if(kcf_search_external()){echo '<p>Une extension SEO est active : utilisez ses réglages pour cette page.</p>';return;}
        wp_nonce_field('kcf_search_save','kcf_search_nonce');$defaults=kcf_search_metadata($post->ID);
        echo '<p>Une formulation claire, avec l’activité et le lieu. Laissez vide pour conserver les textes prévus pour cette page.</p>';
        foreach(array('title'=>'Titre du résultat','description'=>'Description du résultat') as $field=>$label){$value=get_post_meta($post->ID,'_kcf_search_'.$field,true);echo '<p><label for="kcf-search-'.esc_attr($field).'">'.esc_html($label).'</label><br>';
            if($field==='title'){echo '<input class="widefat" id="kcf-search-title" name="kcf_search_title" value="'.esc_attr($value).'" placeholder="'.esc_attr($defaults[$field]??'').'">';}
            else{echo '<textarea class="widefat" rows="3" id="kcf-search-description" name="kcf_search_description" placeholder="'.esc_attr($defaults[$field]??'').'">'.esc_textarea($value).'</textarea>';}
            echo '</p>';
        }
    },'page','normal','default');
});
add_action('save_post_page',function($id){
    if(empty($_POST['kcf_search_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kcf_search_nonce'])),'kcf_search_save')||!current_user_can('edit_post',$id)||wp_is_post_autosave($id)||wp_is_post_revision($id)){return;}
    foreach(array('title','description') as $field){if(isset($_POST['kcf_search_'.$field])&&is_string($_POST['kcf_search_'.$field])){update_post_meta($id,'_kcf_search_'.$field,sanitize_text_field(wp_unslash($_POST['kcf_search_'.$field])));}}
});
// Private management pages must not enter the native public WordPress sitemap.
add_filter('wp_sitemaps_posts_query_args',function($args,$type){
    if($type!=='page'){return $args;}
    $private=get_posts(array('post_type'=>'page','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','meta_query'=>array(array('key'=>'_kcf_workshop_source','value'=>array('mes-reservations','reserver'),'compare'=>'IN'))));
    foreach(array('mes-reservations','reserver') as $slug){$by_slug=get_page_by_path($slug);if($by_slug){$private[]=$by_slug->ID;}}
    $args['post__not_in']=array_values(array_unique(array_merge($args['post__not_in']??array(),$private)));return $args;
},10,2);
