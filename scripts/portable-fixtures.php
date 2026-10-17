<?php
wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
// Temporary local pages only; does not replace atelier pages or production content.
if(($args[0]??'')==='delete'){
 foreach(get_posts(array('post_type'=>'page','post_status'=>'any','posts_per_page'=>-1,'meta_key'=>'_kcf_portable_test','meta_value'=>'20')) as $post){wp_delete_post($post->ID,true);}echo 'Removed local portable fixtures.';return;
}
$out=array();
foreach(array('accueil','vitalivan','ak-anti-nuisible','terroir-loupiac','contact','reserver','mes-reservations') as $slug){
 $body=file_get_contents('/project/artifacts/wordpress-import-20/'.$slug.'-portable.html');
 if($body===false){WP_CLI::error('Portable source unreadable.');}
 $id=wp_insert_post(array('post_type'=>'page','post_status'=>'draft','post_title'=>'Portable test '.$slug,'post_name'=>'portable-test-'.$slug,'post_content'=>wp_slash($body),'meta_input'=>array('_kcf_portable_test'=>'20')),true);
 if(is_wp_error($id)){WP_CLI::error('Failed to create local fixture.');}$out[$slug]=$id;
}
echo wp_json_encode($out);
