import asyncio,json,urllib.request,urllib.parse,re
from pathlib import Path
from playwright.async_api import async_playwright
root=Path(__file__).resolve().parent.parent;out=root/'artifacts/public-release-22';cache={};errors=[];results=[]
def fetch(url):
 with urllib.request.urlopen(url,timeout=30) as r:return {'body':r.read(),'content_type':r.headers.get_content_type()}
async def main():
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  context=await browser.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True)
  async def route_request(route):
   url=route.request.url
   if urllib.parse.urlsplit(url).hostname!='kevcoachfit.com' or route.request.method!='GET':return await route.abort()
   if url not in cache:cache[url]=await asyncio.to_thread(fetch,url)
   await route.fulfill(**cache[url])
  await context.route('**/*',route_request);page=await context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  for path in ['/','/vitalivan/','/ak-anti-nuisible/','/terroir-loupiac/']:
   url='https://kevcoachfit.com'+path;await page.goto(url,wait_until='networkidle',timeout=60000)
   assert await page.locator('.kcf20-preview-root').count()==1
   assert await page.locator('#kcf20-preview-css').evaluate('e=>e.textContent.includes("KCF_MOBILE_23")')
   await page.evaluate('scrollTo(0,350)');await page.wait_for_timeout(100)
   header=page.locator('.kcf20-preview-root > header');assert abs(await header.evaluate('e=>e.getBoundingClientRect().top'))<1
   await page.evaluate('scrollTo(0,0)')
   if path=='/vitalivan/':
    heights=await page.evaluate('(()=>{let a=document.querySelector(".kcf20-sport-hero").getBoundingClientRect(),b=document.querySelector(".kcf20-sport-photo img").getBoundingClientRect();return {hero:a.height,image:b.height}})()');assert abs(heights['hero']-heights['image'])<2,heights
    await page.screenshot(path=str(root/'artifacts/mobile-fix-23/after-mobile.png'))

   assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),path
   toggle=page.locator('.kcf20-menu-toggle');assert await toggle.count()==1
   await toggle.click();assert await toggle.get_attribute('aria-expanded')=='true'
   panel=page.locator('.kcf20-dropdown-panel');assert await panel.get_by_role('link').count()==4
   await panel.get_by_role('link',name='Miel & vin',exact=True).click();await page.wait_for_url('https://kevcoachfit.com/terroir-loupiac/')
   await page.get_by_role('heading',name='Terroir Antoine',exact=True,level=1).wait_for()
   assert await page.locator('.kcf20-dropdown-panel a[href="https://kevcoachfit.com/terroir-loupiac/"]').get_attribute('href')=='https://kevcoachfit.com/terroir-loupiac/'
   await page.goto(url,wait_until='networkidle')

   if path=='/vitalivan/':assert await page.get_by_role('link',name='Ouvrir @kev_coach_fit',exact=True).count()==1
   if path=='/terroir-loupiac/':assert await page.get_by_role('link',name='Ouvrir @yoantoine46',exact=True).count()==1
   assert await page.locator('a[href*="page_id=64"],a[href*="page_id=65"],a[href*="page_id=66"]').count()==0
   results.append({'url':url,'mobile_width':390,'overflow':False,'mobile_menu':'four visible pages; clicked Miel & vin and reached the correct page','sticky_header_after_scroll':True,'public_display':'verified from actual anonymous response'})
   print('Verified actual public mobile page:',path,flush=True)
  await browser.close()
 assert not errors,errors
 (root/'docs/mobile-23-live-verification.json').write_text(json.dumps({'pages':results,'javascript_errors':errors,'transport':'actual anonymous responses fetched over verified HTTPS and passed unchanged to Chromium; GET requests only','instagram_feed':'profile links only; no account authorization or automatic feed'},ensure_ascii=False,indent=2)+'\n')
asyncio.run(main())
