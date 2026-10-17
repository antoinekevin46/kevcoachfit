<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('127.0.0.1','localhost'),true)){WP_CLI::error('Local tests only.');}
global $wpdb;$id=0;$error=null;
try{
 $now=time();$wpdb->insert($wpdb->prefix.'kcf_sessions',array('series'=>'test-regression-'.bin2hex(random_bytes(4)),'title'=>'Test local','venue'=>'Test','starts_at'=>kcf_utc($now+10800),'duration'=>45,'capacity'=>1));$id=$wpdb->insert_id;
 $a=kcf_book_session($id,array('name'=>'Test un','email'=>'one@example.invalid'),$now);$b=kcf_book_session($id,array('name'=>'Test deux','email'=>'two@example.invalid'),$now);$c=kcf_book_session($id,array('name'=>'Test trois','email'=>'three@example.invalid'),$now);
 $token=fn($x)=>explode('.',wp_parse_url($x['management_url'],PHP_URL_FRAGMENT),2)[1];
 kcf_manage_booking($a['id'],$token($a),'cancel',$now);
 $promoted=kcf_manage_booking($c['id'],$token($c),'status',$now+7201);
 if($promoted['status']!=='offered'||strtotime($promoted['offered_until'])!==$now+10800){throw new Exception('Late FIFO regression.');}
 $withdraw=kcf_manage_booking($c['id'],$token($c),'cancel',$now+7202);if($withdraw['status']!=='cancelled'){throw new Exception('Waitlist withdrawal cutoff regression.');}
 $date=(new DateTimeImmutable('next friday',kcf_timezone()))->format('Y-m-d');$r=kcf_save_request(array('name'=>'Test client','email'=>'hours@example.invalid','kind'=>'coaching','date'=>$date,'time'=>'23:30','address'=>'Adresse fictive de test local'));
 if(!is_wp_error($r)||$r->get_error_code()!=='hours'){throw new Exception('Midnight closing regression.');}
} catch(Throwable $e){$error=$e->getMessage();}
finally{$wpdb->query('ROLLBACK');if($id){$wpdb->delete($wpdb->prefix.'kcf_bookings',array('session_id'=>$id));$wpdb->delete($wpdb->prefix.'kcf_sessions',array('id'=>$id));}}
if($error){WP_CLI::error($error);}WP_CLI::success('3 original workflow regressions verified: late FIFO, withdrawal and midnight closing.');
