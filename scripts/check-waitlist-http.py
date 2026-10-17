"""Exercise FIFO through real local HTTP endpoints; no live site or client messages."""
import asyncio,json,subprocess,uuid
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
CLI=['docker','compose','run','--rm','cli','wp','eval']
def php(code):return subprocess.check_output(CLI+[code],cwd=ROOT,text=True).strip()
async def main():
 cfg=json.loads(php('echo wp_json_encode(kcfr_settings());'));assert not cfg['live']
 prefix='test-http-fifo-'+uuid.uuid4().hex
 sid=int(php("global $wpdb; $wpdb->insert($wpdb->prefix.'kcfr_sessions',array('series'=>'"+prefix+"','title'=>'Local FIFO','venue'=>'Local uniquement','starts_at'=>kcfr_utc(time()+8*HOUR_IN_SECONDS),'duration'=>45,'capacity'=>1));echo $wpdb->insert_id;"))
 values=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line)
 steps=[];count_before=int(php('global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox");'))
 try:
  async with async_playwright() as p:
   browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
   context=await browser.new_context();page=await context.new_page()
   await page.goto('http://127.0.0.1:8080/wp-login.php');await page.locator('#user_login').fill('atelier_kevin');await page.locator('#user_pass').fill(values['KCF_LOCAL_PASSWORD']);await page.locator('#wp-submit').click();await page.wait_for_url('**/wp-admin/**')
   await page.goto('http://127.0.0.1:8080/?page_id='+str(cfg['booking_page_id'])+'&preview=true')
   api=await page.evaluate('({...kcfrApp})');assert api['endpoint'].startswith('http://127.0.0.1:8080/')
   async def call(route,data,expected=200):
    response=await context.request.post(api['endpoint']+route,data=data,headers={'X-KCFR-Nonce':api['nonce'],'X-WP-Nonce':api['restNonce']})
    result=await response.json();assert response.status==expected,(route,response.status,result.get('code'));return result
   def key(b):return b['management_url'].split('#')[1].split('.',1)[1]
   async def manage(b,operation='status',expected=200):return await call('manage',{'id':b['id'],'key':key(b),'operation':operation},expected)
   participants=[]
   for n in range(3):participants.append(await call('book',{'session':sid,'name':'FIFO HTTP '+str(n),'email':prefix+str(n)+'@example.invalid'}))
   assert [b['status'] for b in participants]==['reserved','waiting','waiting'];steps.append('one place and two waiting')
   await call('book',{'session':sid,'name':'FIFO HTTP 0','email':prefix+'0@example.invalid'},409);steps.append('duplicate refused')
   await call('manage',{'id':participants[0]['id'],'key':'0'*64,'operation':'cancel'},403);steps.append('invalid private link refused')
   assert (await manage(participants[0],'cancel'))['status']=='cancelled'
   offered=await manage(participants[1]);assert offered['status']=='offered';assert (await manage(participants[2]))['status']=='waiting';steps.append('first waiting participant alone receives offer')
   # Simulate the clock reaching the offer deadline, then use actual HTTP management.
   php("global $wpdb; $wpdb->update($wpdb->prefix.'kcfr_bookings',array('offered_until'=>kcfr_utc(time()-1)),array('id'=>"+str(participants[1]['id'])+"));")
   assert (await manage(participants[1]))['status']=='expired';assert (await manage(participants[2]))['status']=='offered';steps.append('expired offer passes to next participant')
   await manage(participants[1],'confirm',409);steps.append('expired offer cannot be accepted')
   assert (await manage(participants[2],'confirm'))['status']=='reserved';steps.append('valid offer confirmed')
   await page.goto(participants[2]['management_url']);await page.get_by_role('heading',name='Place réservée',exact=False).wait_for();steps.append('private management link renders actual booking')
   page.on('dialog',lambda dialog:dialog.accept());await page.get_by_role('button',name='Annuler mon inscription',exact=True).click();await page.get_by_role('heading',name='Inscription annulée',exact=True).wait_for();steps.append('browser cancellation succeeds')
   await browser.close()
  count_after=int(php('global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kcfr_outbox");'));assert count_after==count_before;steps.append('no client notification queued')
  report={'version':'1.0.1','target':'local WordPress real HTTP and browser','steps':steps,'public_site_modified':False,'email_or_sms_sent':False}
  (ROOT/'docs/waitlist-http-verification.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(str(len(steps))+' real HTTP/browser FIFO checks passed; no client messages.')
 finally:
  php("global $wpdb; $wpdb->delete($wpdb->prefix.'kcfr_bookings',array('session_id'=>"+str(sid)+")); $wpdb->delete($wpdb->prefix.'kcfr_sessions',array('id'=>"+str(sid)+"));")
asyncio.run(main())
