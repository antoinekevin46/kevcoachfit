<?php
$mode=$args[0]??'prepare';
if($mode==='restore'){
    $state=get_option('kcf_seo_test_state');
    if($state){foreach($state['posts'] as $id=>$status){wp_update_post(array('ID'=>$id,'post_status'=>$status));}update_option('blog_public',$state['blog_public']);delete_option('kcf_seo_test_state');}
    echo 'Restored local drafts.';return;
}
if(get_option('kcf_seo_test_state')){WP_CLI::error('Restore previous SEO fixtures before preparing again.');}
$pages=get_option('kcf_workshop_pages');$state=array('blog_public'=>get_option('blog_public'),'posts'=>array());
foreach($pages as $id){$state['posts'][$id]=get_post_status($id);}
update_option('kcf_seo_test_state',$state);update_option('blog_public','1');
foreach($pages as $id){wp_update_post(array('ID'=>$id,'post_status'=>'publish'));}
echo wp_json_encode($pages);
