import asyncio,json,urllib.request,urllib.parse,re
from pathlib import Path
from playwright.async_api import async_playwright
root=Path(__file__).resolve().parent.parent;out=root/'artifacts/public-release-22';cache={};errors=[];results=[]
def fetch(url):
 with urllib.request.urlopen(url,timeout=30) as r:return {'body':r.read(),'content_type':r.headers.get_content_type()}
async def main():
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  context=await browser.new_context(viewport={'width':390,'height':900})
  async def route_request(route):
   url=route.request.url
   if urllib.parse.urlsplit(url).hostname!='kevcoachfit.com' or route.request.method!='GET':return await route.abort()
   if url not in cache:cache[url]=await asyncio.to_thread(fetch,url)
   await route.fulfill(**cache[url])
  await context.route('**/*',route_request);page=await context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  for path in ['/','/ak-anti-nuisible/','/terroir-loupiac/','/contac/','/reserver/']:
   url='https://kevcoachfit.com'+path;await page.goto(url,wait_until='networkidle',timeout=60000)
   assert await page.locator('.kcf20-preview-root').count()==1
   assert await page.locator('.kcf20-other-spaces').count()==0
   root_content=page.locator('.kcf20-preview-root')
   if path in ['/','/contac/','/reserver/']:
    assert await root_content.locator('a[href*="ak-anti-nuisible"],a[href*="terroir-loupiac"],a[href*="yoantoine46"]').count()==0
   await page.evaluate('scrollTo(0,350)');assert await page.locator('.kcf20-preview-root > header').evaluate('e=>e.getBoundingClientRect().top')<1
   await page.evaluate('scrollTo(0,0)')
   if path=='/':
    await page.screenshot(path=str(root/'artifacts/specialisation-27/sport-home-mobile.png'))
    cta=page.get_by_role('link',name='Rendez-vous et réservation des cours',exact=True);assert await cta.is_visible()
    await cta.click();await page.wait_for_url('https://kevcoachfit.com/reserver/');await page.get_by_text('Les réservations en ligne ne sont pas encore ouvertes.',exact=True).wait_for();await page.goto(url,wait_until='networkidle')

   assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),path
   toggle=page.locator('.kcf20-menu-toggle');assert await toggle.count()==1
   await toggle.click();assert await toggle.get_attribute('aria-expanded')=='true'
   labels=await page.locator('.kcf20-dropdown-panel a').all_text_contents()
   assert not any(label in ['Accueil','Vitali’Van','A.K Nuisibles','Miel & vin'] for label in labels),(path,labels)
   await page.keyboard.press('Escape');assert await toggle.get_attribute('aria-expanded')=='false'

   if path=='/vitalivan/':assert await page.get_by_role('link',name='Ouvrir @kev_coach_fit',exact=True).count()==1
   if path=='/terroir-loupiac/':assert await page.get_by_role('link',name='Ouvrir @yoantoine46',exact=True).count()==1
   assert await page.locator('a[href*="page_id=64"],a[href*="page_id=65"],a[href*="page_id=66"]').count()==0
   results.append({'url':url,'mobile_width':390,'overflow':False,'mobile_menu':'activity-specific links only','sticky_header':True,'public_display':'verified from actual anonymous response'})
   print('Verified actual public mobile page:',path,flush=True)
  await browser.close()
 assert not errors,errors
 (root/'docs/specialisation-27-live-mobile.json').write_text(json.dumps({'pages':results,'javascript_errors':errors,'transport':'actual anonymous responses fetched over verified HTTPS and passed unchanged to Chromium; GET requests only','instagram_feed':'profile links only; no account authorization or automatic feed'},ensure_ascii=False,indent=2)+'\n')
asyncio.run(main())
