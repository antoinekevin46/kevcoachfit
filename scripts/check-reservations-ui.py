"""Exercise the actual booking form locally in private mode, never on the remote website."""
import asyncio,json,subprocess
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
async def main():
 cfg=json.loads(subprocess.check_output(['docker','compose','run','--rm','cli','wp','eval','echo wp_json_encode(kcfr_settings());'],cwd=ROOT,text=True))
 assert not cfg['live'];values=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line)
 errors=[];results=[]
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  ctx=await browser.new_context();page=await ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  await page.goto('http://127.0.0.1:8080/wp-login.php');await page.locator('#user_login').fill('atelier_kevin');await page.locator('#user_pass').fill(values['KCF_LOCAL_PASSWORD']);await page.locator('#wp-submit').click();await page.wait_for_url('**/wp-admin/**')
  for width in [320,390,768,1440]:
   await page.set_viewport_size({'width':width,'height':1000})
   await page.goto(f'http://127.0.0.1:8080/?page_id={cfg["booking_page_id"]}&preview=true',wait_until='domcontentloaded')
   await page.locator('.kcfr-form').first.wait_for()
   assert await page.locator('.kcfr-form').count()==2
   assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(width,'overflow')
   choices=await page.locator('#kcfr-session option').all_text_contents();assert len(choices)>5
   assert all('15 place' in c for c in choices[1:]),choices[:5]
   assert await page.locator('button[type=submit]',has_text='Tester').count()==2
   results.append({'width':width,'forms':2,'overflow':False,'mode':'private'})
  form=page.locator('.kcfr-form').first
  choice=await form.locator('select option').nth(1).get_attribute('value');await form.locator('select').select_option(choice)
  await form.locator('input[name=name]').fill('Test interface local')
  import uuid
  await form.locator('input[name=email]').fill('interface-'+str(uuid.uuid4())+'@example.invalid')
  await form.locator('input[type=checkbox]').check()
  assert await form.evaluate('f=>f.reportValidity()'),await form.evaluate('f=>[...f.elements].filter(x=>!x.checkValidity()).map(x=>x.name)')
  assert await page.evaluate('typeof kcfrApp!=="undefined"'),'Booking script not enqueued'
  async with page.expect_response(lambda r:'kcfr' in __import__('urllib.parse',fromlist=['unquote']).unquote(r.url) and r.request.method=='POST') as pending:
   await form.get_by_role('button',name='Tester la réservation',exact=True).click()
  response=await pending.value;booking=await response.json()
  assert response.ok and booking['demo'] is True and booking['status']=='reserved',{'http':response.status,'url':response.url,'error':booking.get('code')}
  await page.goto(booking['management_url'],wait_until='domcontentloaded')
  await page.get_by_role('heading',name='Place réservée',exact=False).wait_for()
  page.on('dialog',lambda dialog:dialog.accept())
  await page.get_by_role('button',name='Annuler mon inscription',exact=True).click()
  await page.get_by_role('heading',name='Inscription annulée',exact=True).wait_for()
  subprocess.run(['docker','compose','run','--rm','cli','wp','eval',"global $wpdb; $wpdb->delete($wpdb->prefix.'kcfr_bookings',array('id'=>"+str(int(booking['id']))+"));"],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
  await page.goto(f'http://127.0.0.1:8080/?page_id={cfg["booking_page_id"]}&preview=true',wait_until='domcontentloaded')
  api=await page.evaluate('({...kcfrApp})');prefix='parallel-'+uuid.uuid4().hex;gate=asyncio.Semaphore(4)
  async def reserve(index):
   async with gate:
    response=await ctx.request.post(api['endpoint']+'book',data={'session':int(choice),'name':'Test concurrence','email':prefix+'-'+str(index)+'@example.invalid'},headers={'X-KCFR-Nonce':api['nonce'],'X-WP-Nonce':api['restNonce']})
    return response.status,await response.json()
  try:
   concurrent=await asyncio.gather(*(reserve(i) for i in range(20)))
   assert all(status==200 for status,b in concurrent),[status for status,b in concurrent]
   assert sum(b['status']=='reserved' for status,b in concurrent)==15
   assert sum(b['status']=='waiting' for status,b in concurrent)==5
  finally:
   cleanup="global $wpdb; $wpdb->query($wpdb->prepare(\"DELETE FROM {$wpdb->prefix}kcfr_bookings WHERE email LIKE %s\",'"+prefix+"%'));"
   subprocess.run(['docker','compose','run','--rm','cli','wp','eval',cleanup],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
  await page.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=kcfr',wait_until='domcontentloaded')
  assert await page.get_by_role('heading',name='Vitali’Van — réservations et agenda',exact=True).count()==1
  assert await page.locator('input[name=google_client_secret]').input_value()==''
  assert not await page.locator('input[name=live]').is_checked()
  capacity=page.locator('input[name=capacity]').first
  assert await capacity.input_value()=='15'
  await capacity.fill('16');await capacity.locator('xpath=ancestor::form').get_by_role('button',name='Enregistrer',exact=True).click();await page.wait_for_url('**page=kcfr&saved=1')
  capacity=page.locator('input[name=capacity]').first;assert await capacity.input_value()=='16'
  await capacity.fill('15');await capacity.locator('xpath=ancestor::form').get_by_role('button',name='Enregistrer',exact=True).click();await page.wait_for_url('**page=kcfr&saved=1')
  assert await page.locator('input[name=capacity]').first.input_value()=='15'
  assert not errors,errors
  await browser.close()
 (ROOT/'docs/reservations-ui-verification.json').write_text(json.dumps({'renders':results,'admin_settings':'loaded, secret masked, private mode, capacity change 15 to 16 to 15 verified','http_booking_and_cancellation':'passed in private mode; no real email','concurrent_http_bookings':{'requests':20,'reserved':15,'waiting':5,'parallelism':4},'javascript_errors':errors},ensure_ascii=False,indent=2)+'\n')
 print('4 widths: actual private booking forms, 15 places, administrator settings, masked secret and no JS error verified.')
asyncio.run(main())
