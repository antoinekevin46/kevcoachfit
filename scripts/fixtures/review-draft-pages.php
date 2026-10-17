<?php
// Local review only. Never import fixture content on a public WordPress installation.
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('127.0.0.1','localhost'),true)){WP_CLI::error('Local review fixtures only.');}
wp_set_current_user(get_user_by('login','atelier_kevin')->ID);
$ids=array();
for($i=63;$i<=69;$i++){
 $existing=get_posts(array('post_type'=>'page','post_status'=>'any','meta_key'=>'_kcf_review_source','meta_value'=>(string)$i,'numberposts'=>1));
 $data=array('post_type'=>'page','post_status'=>'draft','post_title'=>'Review '.$i,'meta_input'=>array('_kcf_review_source'=>(string)$i));
 if($existing){$data['ID']=$existing[0]->ID;}
 $id=wp_insert_post($data,true);if(is_wp_error($id)){WP_CLI::error('Fixture failed');}$ids[$i]=$id;
}
foreach($ids as $remote=>$local){
 $s=file_get_contents('/project/artifacts/page-review/'.$remote.'-reviewed.html');
 foreach($ids as $rid=>$lid){$s=str_replace('https://kevcoachfit.com/?page_id='.$rid,'http://127.0.0.1:8080/?page_id='.$lid,$s);}
 wp_update_post(wp_slash(array('ID'=>$local,'post_content'=>$s)));
}
$previous=kcfr_settings();update_option('kcfr_review_previous',$previous);
$previous['booking_page_id']=$ids[68];$previous['manage_page_id']=$ids[69];update_option('kcfr_settings',$previous);
echo wp_json_encode($ids);
