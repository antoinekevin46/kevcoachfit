"""Check current public color blocks and cross-activity footer links, without external calls."""
import asyncio,json,urllib.request,urllib.parse
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent;cache={};errors=[]
def fetch(u):
 with urllib.request.urlopen(u,timeout=45) as r:return {'body':r.read(),'content_type':r.headers.get_content_type()}
async def main():
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
  ctx=await browser.new_context()
  async def route(r):
   u=r.request.url
   if urllib.parse.urlsplit(u).hostname!='kevcoachfit.com' or r.request.method!='GET':return await r.abort()
   if u not in cache:cache[u]=await asyncio.to_thread(fetch,u)
   await r.fulfill(**cache[u])
  await ctx.route('**/*',route);page=await ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  rows=[]
  for path in ['/','/ak-anti-nuisible/','/terroir-loupiac/','/contac/','/reserver/','/mes-reservations/']:
   for width in [320,390,1440]:
    await page.set_viewport_size({'width':width,'height':1000});await page.goto('https://kevcoachfit.com'+path,wait_until='networkidle',timeout=90000)
    assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(path,width)
    nav=page.locator('.kcf20-activity-switch');assert await nav.count()==1
    hrefs=await nav.locator('a').evaluate_all('(es)=>es.map(e=>e.href)')
    assert hrefs==['https://kevcoachfit.com/','https://kevcoachfit.com/ak-anti-nuisible/','https://kevcoachfit.com/terroir-loupiac/']
    await nav.scroll_into_view_if_needed()
    assert await nav.get_by_role('link',name='A.K Nuisibles',exact=True).is_visible()
    if path=='/ak-anti-nuisible/':
     assert await page.locator('.kcf20-pest').evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(255, 249, 233)'
     assert await page.locator('.kcf20-route-panel a[href*="google.com/maps/dir/"]').count()==1
     route=await page.locator('.kcf20-route-panel a[href*="google.com/maps/dir/"]').get_attribute('href')
     query=urllib.parse.parse_qs(urllib.parse.urlsplit(route).query)
     assert query['destination']==['Payrac 46350 France'] and 'origin' not in query
     assert await page.locator('.kcf20-pest-bees img[src*="abeilles-verger-presentation"]').count()==1
     if width==1440:
      cards=await page.locator('.kcf20-ak-step').evaluate_all('(es)=>es.map(e=>({top:e.getBoundingClientRect().top,height:e.getBoundingClientRect().height}))')
      assert max(c['top'] for c in cards)-min(c['top'] for c in cards)<1
     await page.locator('.kcf20-ak-process').screenshot(path=str(ROOT/f'artifacts/lively-38/ak-process-{width}.png'))
    if path=='/terroir-loupiac/':
     assert await page.locator('.kcf20-terroir-story img').count()==1
     assert await page.locator('.kcf20-terroir-perks span').count()==3
     await page.locator('.kcf20-terroir-story').screenshot(path=str(ROOT/f'artifacts/lively-38/terroir-story-{width}.png'))
    if path=='/contac/':assert await page.locator('.kcf20-contact-grid .kcf20-contact-portrait img').count()==1
    if path=='/reserver/':
     assert await page.locator('.kcf20-page-banner img').count()==1
     assert await page.locator('form[data-kcfr-form]').count()==0
    if path=='/mes-reservations/':assert await page.locator('.kcf20-tracking-page').count()==1
    if path=='/':
     assert await page.locator('.kcf20-social-photo-grid img').count()==3
     assert await page.get_by_text('Ces images ne sont pas des publications Instagram',exact=False).count()==1
     colors=await page.locator('.kcf20-choice-card').evaluate_all('(es)=>es.map(e=>getComputedStyle(e).backgroundColor)');assert len(set(colors))==4
     social=page.locator('.kcf20-social-follow');background=await social.evaluate('e=>getComputedStyle(e).backgroundImage');assert 'linear-gradient' in background
     assert await social.get_by_role('link',name='Suivre @kev_coach_fit sur Instagram',exact=True).count()==1
     await social.screenshot(path=str(ROOT/f'artifacts/lively-38/instagram-{width}.png'))
     await page.locator('.kcf20-choice-grid').screenshot(path=str(ROOT/f'artifacts/lively-38/programmes-{width}.png'))
    rows.append({'path':path,'width':width,'overflow':False,'activity_links':hrefs});print('Verified',path,width,flush=True)
  await browser.close()
 assert not errors,errors
 (ROOT/'docs/lively-38-browser.json').write_text(json.dumps({'anonymous_public_pages':rows,'javascript_errors':errors,'instagram_feed':'still pending owner source authorization; no fabricated posts'},ensure_ascii=False,indent=2)+'\n')
asyncio.run(main())
