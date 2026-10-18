"""Verify real public motion timings, pause, reduction preferences and AK photographs."""
import asyncio,json,urllib.request,urllib.parse
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent;cache={};errors=[];out=ROOT/'artifacts/motion-40';out.mkdir(exist_ok=True)
def fetch(u):
 with urllib.request.urlopen(u,timeout=45) as r:return {'body':r.read(),'content_type':r.headers.get_content_type()}
async def main():
 async with async_playwright() as p:
  b=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox']);rows=[]
  for mode,width in [('normal',390),('normal',1440),('reduced',390),('no-js',390)]:
   ctx=await b.new_context(viewport={'width':width,'height':1000},java_script_enabled=mode!='no-js',reduced_motion='reduce' if mode=='reduced' else 'no-preference')
   async def route(r):
    u=r.request.url
    if urllib.parse.urlsplit(u).hostname!='kevcoachfit.com' or r.request.method!='GET':return await r.abort()
    if u not in cache:cache[u]=await asyncio.to_thread(fetch,u)
    await r.fulfill(**cache[u])
   await ctx.route('**/*',route);page=await ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
   for path in ['/','/ak-anti-nuisible/','/terroir-loupiac/']:
    await page.goto('https://kevcoachfit.com'+path,wait_until='networkidle',timeout=90000)
    ribbon=page.locator('.kcf20-motion-ribbon');assert await ribbon.count()==1
    track=ribbon.locator('.kcf20-ribbon-track');button=ribbon.locator('button')
    assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
    h1=page.locator('.kcf20-page h1');timing=await h1.evaluate('e=>({duration:getComputedStyle(e).animationDuration,name:getComputedStyle(e).animationName})')
    if mode=='normal':
     assert timing['duration']=='1.15s',timing
     await ribbon.scroll_into_view_if_needed()
     matrix1=await track.evaluate('e=>getComputedStyle(e).transform');await page.wait_for_timeout(250);matrix2=await track.evaluate('e=>getComputedStyle(e).transform');assert matrix1!=matrix2
     await button.click();assert await button.get_attribute('aria-pressed')=='true'
     assert await track.evaluate('e=>getComputedStyle(e).animationPlayState')=='paused'
     stopped1=await track.evaluate('e=>getComputedStyle(e).transform');await page.wait_for_timeout(250);stopped2=await track.evaluate('e=>getComputedStyle(e).transform');assert stopped1==stopped2
     await button.click();assert await button.get_attribute('aria-pressed')=='false'
     cards=page.locator('.kcf20-choice-card' if path=='/' else '.kcf20-ak-step' if path=='/ak-anti-nuisible/' else '.kcf20-terroir-story')
     await cards.first.scroll_into_view_if_needed()
     await page.wait_for_function('(e)=>e.classList.contains("kcf20-motion-entered")',arg=await cards.first.element_handle())
     card_timing=await cards.first.evaluate('e=>({duration:getComputedStyle(e).animationDuration,delay:getComputedStyle(e).animationDelay})');assert card_timing['duration']=='0.95s',card_timing
     delays=await cards.evaluate_all('(es)=>es.slice(0,3).map(e=>getComputedStyle(e).animationDelay)')
    else:
     assert await button.is_hidden()
     assert await track.evaluate('e=>getComputedStyle(e).animationName')=='none'
     card_timing=None;delays=[]
    if path=='/ak-anti-nuisible/':
     assert await page.locator('.kcf20-ak-real-species img').count()==5
     assert await page.locator('.kcf20-ak-extra-photo img').count()==2
     assert await page.locator('.kcf20-swarm-photo img').count()==2
     assert await page.locator('.kcf20-service-map .kcf20-map-heading h3').evaluate('e=>getComputedStyle(e).color')=='rgb(255, 255, 255)'
     assert await page.locator('.kcf20-route-panel a[href*="maps/dir/"]').count()==1
     href=await page.locator('.kcf20-route-panel a[href*="maps/dir/"]').get_attribute('href');assert urllib.parse.parse_qs(urllib.parse.urlsplit(href).query)['destination']==['Loupiac 46350 France']
     for image in await page.locator('.kcf20-ak-real-species img,.kcf20-swarm-photo img').all():
      await image.scroll_into_view_if_needed();await image.evaluate('(e)=>e.decode()')
      assert await image.evaluate('e=>getComputedStyle(e).objectFit')=='contain'
     if mode=='normal':
      await page.locator('#prestations-ak').screenshot(path=str(out/f'species-{width}.png'))
      await page.locator('#essaims-ak').screenshot(path=str(out/f'swarm-{width}.png'))
    rows.append({'path':path,'mode':mode,'width':width,'title':timing,'card':card_timing,'first_card_delays':delays,'pause_resume':'passed' if mode=='normal' else 'motion disabled','overflow':False});print('Verified timing/photos',path,mode,width,flush=True)
   await ctx.close()
  await b.close()
 assert not errors,errors
 (ROOT/'docs/motion-40-browser.json').write_text(json.dumps({'checks':rows,'javascript_errors':errors,'external_google_maps_not_contacted':True},ensure_ascii=False,indent=2)+'\n')
asyncio.run(main())
