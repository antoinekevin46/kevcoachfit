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
   for width in [390,1440]:
    await page.set_viewport_size({'width':width,'height':1000});await page.goto('https://kevcoachfit.com'+path,wait_until='networkidle',timeout=90000)
    assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(path,width)
    nav=page.locator('.kcf20-activity-switch');assert await nav.count()==1
    hrefs=await nav.locator('a').evaluate_all('(es)=>es.map(e=>e.href)')
    assert hrefs==['https://kevcoachfit.com/','https://kevcoachfit.com/ak-anti-nuisible/','https://kevcoachfit.com/terroir-loupiac/']
    await nav.scroll_into_view_if_needed()
    assert await nav.get_by_role('link',name='A.K Nuisibles',exact=True).is_visible()
    if path=='/':
     colors=await page.locator('.kcf20-choice-card').evaluate_all('(es)=>es.map(e=>getComputedStyle(e).backgroundColor)');assert len(set(colors))==4
     social=page.locator('.kcf20-social-follow');background=await social.evaluate('e=>getComputedStyle(e).backgroundImage');assert 'linear-gradient' in background
     assert await social.get_by_role('link',name='Suivre @kev_coach_fit sur Instagram',exact=True).count()==1
     await social.screenshot(path=str(ROOT/f'artifacts/vivid-35/instagram-{width}.png'))
     await page.locator('.kcf20-choice-grid').screenshot(path=str(ROOT/f'artifacts/vivid-35/programmes-{width}.png'))
    rows.append({'path':path,'width':width,'overflow':False,'activity_links':hrefs});print('Verified',path,width,flush=True)
  await browser.close()
 assert not errors,errors
 (ROOT/'docs/vivid-35-browser.json').write_text(json.dumps({'anonymous_public_pages':rows,'javascript_errors':errors,'instagram_feed':'still pending owner source authorization; no fabricated posts'},ensure_ascii=False,indent=2)+'\n')
asyncio.run(main())
