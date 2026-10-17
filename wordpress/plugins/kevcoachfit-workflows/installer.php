<?php
defined('ABSPATH')||exit;
add_action('admin_menu',function(){add_submenu_page('kcf-requests','Aperçu privé du site','Aperçu privé du site','manage_options','kcf-workshop','kcf_workshop_admin');});
function kcf_workshop_url($slug){
    $pages=get_option('kcf_workshop_pages',array());$id=$pages[$slug]??0;
    if(!$id||!get_post($id)){return '';}
    return add_query_arg(array('kcf_atelier'=>'1','wp_theme_preview'=>'kevcoachfit'),get_preview_post_link($id));
}
function kcf_ensure_bundled_theme(){
    $directory=__DIR__.'/bundled-theme';
    if(!is_readable($directory.'/theme.zip')){return true;}
    if(!current_user_can('install_themes')||!current_user_can('update_themes')){return kcf_error('theme_access','Le compte doit pouvoir installer et mettre à jour les thèmes.',403);}
    $manifest=json_decode(file_get_contents($directory.'/manifest.json'),true);
    if(!is_array($manifest)||empty($manifest['version'])||empty($manifest['sha256'])||!hash_equals($manifest['sha256'],hash_file('sha256',$directory.'/theme.zip'))){return kcf_error('theme_integrity','Le paquet du thème est incomplet. Téléversez à nouveau le pack complet.');}
    $current=wp_get_theme('kevcoachfit');
    if($current->exists()&&version_compare($current->get('Version'),$manifest['version'],'>=')){return true;}
    require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
    if('direct'!==get_filesystem_method(array(),get_theme_root())){return kcf_error('theme_filesystem','L’hébergement demande un accès aux fichiers pour installer le thème. Transmettez ce message, sans identifiants.');}
    $skin=new Automatic_Upgrader_Skin();$upgrader=new Theme_Upgrader($skin);
    $result=$upgrader->install($directory.'/theme.zip',array('overwrite_package'=>$current->exists(),'clear_update_cache'=>false));
    if(is_wp_error($result)){return $result;}
    if(!$result){return kcf_error('theme_install','WordPress n’a pas pu installer le thème inclus. Vérifiez les droits d’écriture des thèmes.');}
    return true;
}
function kcf_prepare_workshop_pages($directory,$new_design=false){
    if(!current_user_can('manage_options')){return kcf_error('access','Accès refusé.',403);}
    $titles=array('accueil'=>'Accueil','vitalivan'=>'Vitali’Van','terroir-loupiac'=>'Terroir Antoine','contact'=>'Contact','reserver'=>'Réserver','mes-reservations'=>'Mon inscription','ak-anti-nuisible'=>'A.K Nuisibles');
    // Check the entire bundle before creating pages.
    foreach($titles as $slug=>$title){if(!is_readable($directory.'/'.$slug.'.html')){return kcf_error('bundle','Le dossier des pages manque. Utilisez le module ZIP complet.');}}
    $theme_result=kcf_ensure_bundled_theme();if(is_wp_error($theme_result)){return $theme_result;}
    $pages=get_option('kcf_workshop_pages',array());
    $version=get_option('kcf_workshop_design','01');
    $transition=($new_design||!$pages)&&'17'!==$version;
    if($transition){
        if($pages){update_option('kcf_workshop_archive_'.$version,$pages,false);}
        $pages=get_option('kcf_workshop_pending_17',array());$version='17';
    }
    foreach($titles as $slug=>$title){
        if(!empty($pages[$slug])&&get_post($pages[$slug])){continue;}
        $content=file_get_contents($directory.'/'.$slug.'.html');
        $content=str_replace('/wp-content/themes/kevcoachfit/',trailingslashit(get_theme_root_uri()).'kevcoachfit/',$content);
        $suffix=' — Atelier privé · Visuel '.$version;
        $id=wp_insert_post(array('post_type'=>'page','post_status'=>'draft','post_title'=>$title.$suffix,'post_name'=>('01'===$version?'atelier-':'atelier-'.$version.'-').$slug,'post_content'=>$content,'comment_status'=>'closed','ping_status'=>'closed','meta_input'=>array('_kcf_workshop_source'=>$slug,'_kcf_workshop_design'=>$version)),true);
        if(is_wp_error($id)){update_option($transition?'kcf_workshop_pending_17':'kcf_workshop_pages',$pages,false);return $id;}
        $pages[$slug]=$id;
        if($transition){update_option('kcf_workshop_pending_17',$pages,false);}
    }
    update_option('kcf_workshop_pages',$pages,false);update_option('kcf_workshop_design',$version,false);delete_option('kcf_workshop_pending_17');kcf_seed_sessions();return $pages;
}
function kcf_workshop_admin(){
    if(!current_user_can('manage_options')){wp_die('Accès refusé.');}
    echo '<div class="wrap"><h1>Aperçu privé de KevCoachFit</h1><p>Cette étape prépare des pages brouillons et permet de les parcourir avec le thème KevCoachFit dans votre session administrateur. Elle ne publie pas les pages, ne change pas la page d’accueil et n’active pas le thème pour les visiteurs.</p>';
    $theme=wp_get_theme('kevcoachfit');
    if(is_readable(__DIR__.'/bundled-theme/theme.zip')){echo '<p><strong>Pack complet :</strong> le thème et les images sont inclus. Le bouton de préparation installe aussi le thème si nécessaire, sans l’activer pour les visiteurs. Si KevCoachFit est déjà votre thème public, sa mise à jour modifie son apparence.</p>';}
    elseif(!$theme->exists()){echo '<div class="notice notice-warning"><p>Installez d’abord le fichier kevcoachfit-theme.zip dans Apparence → Thèmes → Ajouter → Téléverser. Laissez ensuite ce thème sans l’activer.</p></div>';}
    if(isset($_GET['prepared'])){echo '<div class="notice notice-success"><p>Les pages de l’atelier sont prêtes. Les brouillons existants ont été conservés.</p></div>';}
    if(is_dir(__DIR__.'/starter-pages')){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kcf_prepare_workshop">';wp_nonce_field('kcf_prepare_workshop');submit_button('Préparer les pages de démonstration');echo '</form>';}
    else{echo '<p>Dans l’environnement cloud, les pages sont préparées par les scripts. Le ZIP à installer sur votre hébergement inclut le dossier nécessaire.</p>';}
    $pages=get_option('kcf_workshop_pages',array());
    if($pages&&'17'!==get_option('kcf_workshop_design','01')&&is_dir(__DIR__.'/starter-pages')){
        echo '<h2>Nouvelle direction visuelle</h2><p>Créez sept nouveaux brouillons : trois espaces autonomes, cartes interactives locales, navigation par activité, tarifs par période et référencement local. Aucun nouveau domaine n’est nécessaire pour cet essai. Vos brouillons précédents sont conservés. Le thème public et la page d’accueil publique restent en place.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kcf_prepare_workshop_design">';wp_nonce_field('kcf_prepare_workshop_design');submit_button('Préparer le nouveau visuel 17');echo '</form>';
    }
    if($pages){echo '<ul>';foreach($pages as $slug=>$id){if(!get_post($id)){continue;}echo '<li><a href="'.esc_url(kcf_workshop_url($slug)).'" target="_blank" rel="noopener">'.esc_html(get_the_title($id)).'</a> — <a href="'.esc_url(get_edit_post_link($id)).'">Modifier</a></li>';}echo '</ul><p>Les inscriptions et demandes sont des essais privés. Google Agenda, les trajets, les e-mails et les SMS restent à connecter.</p>';}
    foreach(array('01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16') as $archived_version){
        $old=get_option('kcf_workshop_archive_'.$archived_version,array());
        if($old){echo '<h2>Brouillons précédents conservés · '.esc_html($archived_version).'</h2><ul>';foreach($old as $id){if(get_post($id)){echo '<li><a href="'.esc_url(get_edit_post_link($id)).'">'.esc_html(get_the_title($id)).'</a></li>';}}echo '</ul>';}
    }
    echo '</div>';
}
add_action('admin_post_kcf_prepare_workshop',function(){
    if(!current_user_can('manage_options')){wp_die('Accès refusé.',403);}check_admin_referer('kcf_prepare_workshop');
    $result=kcf_prepare_workshop_pages(__DIR__.'/starter-pages');if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
    wp_safe_redirect(admin_url('admin.php?page=kcf-workshop&prepared=1'));exit;
});
add_action('admin_post_kcf_prepare_workshop_design',function(){
    if(!current_user_can('manage_options')){wp_die('Accès refusé.',403);}check_admin_referer('kcf_prepare_workshop_design');
    $result=kcf_prepare_workshop_pages(__DIR__.'/starter-pages',true);if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
    wp_safe_redirect(admin_url('admin.php?page=kcf-workshop&prepared=9'));exit;
});
add_filter('render_block',function($html){
    if(($_GET['kcf_atelier']??'')!=='1'||!current_user_can('manage_options')){return $html;}
    $pages=get_option('kcf_workshop_pages',array());if(!$pages){return $html;}
    return preg_replace_callback('/href="([^"]*)"/',function($match)use($pages){
        $url=html_entity_decode($match[1],ENT_QUOTES,'UTF-8');$u=wp_parse_url($url);if(!$u){return $match[0];}
        if(isset($u['scheme'])&&!in_array($u['scheme'],array('http','https'),true)){return $match[0];}
        if(isset($u['host'])&&$u['host']!==wp_parse_url(home_url(),PHP_URL_HOST)){return $match[0];}
        parse_str($u['query']??'',$query);if(isset($query['kcf_atelier'])){return $match[0];}
        if(!isset($u['path'])&&!isset($u['host'])){return $match[0];}
        $slug=trim($u['path']??'','/');$base=trim(wp_parse_url(home_url(),PHP_URL_PATH)??'','/');
        if($base&&str_starts_with($slug,$base.'/')){$slug=substr($slug,strlen($base)+1);}
        if($slug===''||$slug===$base){$slug='accueil';}
        if(!isset($pages[$slug])){return $match[0];}
        return 'href="'.esc_url(kcf_workshop_url($slug).(isset($u['fragment'])?'#'.$u['fragment']:'')).'"';
    },$html);
});
