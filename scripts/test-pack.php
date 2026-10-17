<?php
$before=array(get_option('stylesheet'),get_option('template'),get_option('page_on_front'));
$root=sys_get_temp_dir().'/kcf-pack16-'.wp_generate_password(12,false,false);
wp_mkdir_p($root.'/unrelated');file_put_contents($root.'/unrelated/sentinel','keep');
$original_dirs=$GLOBALS['wp_theme_directories'];$GLOBALS['wp_theme_directories']=array($root,$root.'/unused');
$theme_root=function()use($root){return $root;};$theme_roots=function()use($root){return array('kevcoachfit'=>$root);};
add_filter('theme_root',$theme_root);add_filter('pre_option_stylesheet_root',$theme_root);add_filter('pre_option_template_root',$theme_root);add_filter('pre_site_transient_theme_roots',$theme_roots);
$error=null;
try {
 wp_set_current_user(0);if(!is_wp_error(kcf_ensure_bundled_theme())){throw new Exception('Theme install allowed without admin.');}
 wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
 $result=kcf_ensure_bundled_theme();if(is_wp_error($result)){throw new Exception('Fresh installation failed: '.$result->get_error_code());}
 foreach(array('style.css','theme.json','sections.php','seo.php','service-map.php','map.js','data/communes-lot.json','assets/kevmiel-logo.png','assets/kevmiel-pots.webp','assets/ak-flyer-original.jpg','assets/ak-carte-visite-originale.jpg','assets/vitalivan-flyer-recto.jpg','assets/vitalivan-flyer-verso.jpg','assets/vitalivan-presentation.mp4') as $file){if(!is_file($root.'/kevcoachfit/'.$file)){throw new Exception('Missing theme asset: '.$file);}}
 $style=$root.'/kevcoachfit/style.css';$content=file_get_contents($style);$hash=hash_file('sha256',$style);
 if(true!==kcf_ensure_bundled_theme()||hash_file('sha256',$style)!==$hash){throw new Exception('Repeated install changed theme.');}
 file_put_contents($style,preg_replace('/^Version:\s*.+$/m','Version: 0.11.1',$content));wp_clean_themes_cache(false);
 $result=kcf_ensure_bundled_theme();if(is_wp_error($result)||hash_file('sha256',$style)!==$hash){throw new Exception('Upgrade failed.');}
 if(file_get_contents($root.'/unrelated/sentinel')!=='keep'){throw new Exception('Unrelated files changed.');}
 if($before!==array(get_option('stylesheet'),get_option('template'),get_option('page_on_front'))){throw new Exception('Public settings changed.');}
} catch(Throwable $e){$error=$e->getMessage();}
finally {
 remove_filter('theme_root',$theme_root);remove_filter('pre_option_stylesheet_root',$theme_root);remove_filter('pre_option_template_root',$theme_root);remove_filter('pre_site_transient_theme_roots',$theme_roots);$GLOBALS['wp_theme_directories']=$original_dirs;
 require_once ABSPATH.'wp-admin/includes/file.php';WP_Filesystem();global $wp_filesystem;$wp_filesystem->delete($root,true);wp_clean_themes_cache(false);
}
if($error){WP_CLI::error($error);}
WP_CLI::success('Pack 17: actual fresh install, upgrade, repeated preparation, required assets, rights, unrelated files and public settings verified.');
