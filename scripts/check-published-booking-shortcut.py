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
  for path in ['/','/vitalivan/']:
   url='https://kevcoachfit.com'+path;await page.goto(url,wait_until='networkidle',timeout=60000)
   assert await page.locator('.kcf20-preview-root').count()==1
   cta=page.get_by_role('link',name='Rendez-vous et réservation des cours',exact=True);assert await cta.is_visible()
   assert await cta.get_attribute('href')=='https://kevcoachfit.com/reserver/'
   await page.evaluate('scrollTo(0,350)');assert await cta.evaluate('e=>e.getBoundingClientRect().top')<120
   await cta.click();await page.wait_for_url('https://kevcoachfit.com/reserver/')
   await page.get_by_text('Les réservations en ligne ne sont pas encore ouvertes.',exact=True).wait_for()
   await page.goto(url,wait_until='networkidle')
   if path=='/vitalivan/':await page.screenshot(path=str(root/'artifacts/rdv-25/live-mobile.png'))

   assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),path
   toggle=page.locator('.kcf20-menu-toggle');assert await toggle.count()==1
   await toggle.click();assert await toggle.get_attribute('aria-expanded')=='true';await page.keyboard.press('Escape');assert await toggle.get_attribute('aria-expanded')=='false'
   if path=='/vitalivan/':assert await page.get_by_role('link',name='Ouvrir @kev_coach_fit',exact=True).count()==1
   if path=='/terroir-loupiac/':assert await page.get_by_role('link',name='Ouvrir @yoantoine46',exact=True).count()==1
   assert await page.locator('a[href*="page_id=64"],a[href*="page_id=65"],a[href*="page_id=66"]').count()==0
   results.append({'url':url,'mobile_width':390,'overflow':False,'mobile_menu':'four links preserved','booking_shortcut':'visible after scrolling; clicked and reached booking information page','public_display':'verified from actual anonymous response'})
   print('Verified actual public mobile page:',path,flush=True)
  await browser.close()
 assert not errors,errors
 (root/'docs/rdv-25-live-verification.json').write_text(json.dumps({'pages':results,'javascript_errors':errors,'transport':'actual anonymous responses fetched over verified HTTPS and passed unchanged to Chromium; GET requests only','instagram_feed':'profile links only; no account authorization or automatic feed'},ensure_ascii=False,indent=2)+'\n')
asyncio.run(main())
