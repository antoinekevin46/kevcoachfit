<?php
// Local environment only. No connection to kevcoachfit.com.
require_once ABSPATH.'wp-admin/includes/upgrade.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
if (!is_blog_installed()) {
    $password=getenv('KCF_LOCAL_PASSWORD');
    if (!$password) { WP_CLI::error('Missing local password.'); }
    wp_install('KevCoachFit — atelier local','atelier_kevin','atelier@example.test',true,'',$password);
}
update_option('timezone_string','Europe/Paris');
update_option('WPLANG','fr_FR');
update_user_meta(get_user_by('login','atelier_kevin')->ID,'show_admin_bar_front','false');
update_option('blog_public','0');
activate_plugin('kevcoachfit-workflows/kevcoachfit-workflows.php');
switch_theme('kevcoachfit');
wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
$pages=kcf_prepare_workshop_pages(WP_PLUGIN_DIR.'/kevcoachfit-workflows/starter-pages',true);
if(is_wp_error($pages)) { WP_CLI::error($pages->get_error_message()); }
WP_CLI::success('Local workshop ready: '.count($pages).' private pages.');
