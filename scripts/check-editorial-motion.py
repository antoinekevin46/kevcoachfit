import asyncio,json,urllib.request,urllib.parse
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent;cache={};errors=[]
def fetch(u):
 with urllib.request.urlopen(u,timeout=45) as r:return {'body':r.read(),'content_type':r.headers.get_content_type()}
async def main():
 async with async_playwright() as p:
  b=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox']);rows=[]
  for mode in ['normal','reduced','no-js']:
   ctx=await b.new_context(viewport={'width':390,'height':900},java_script_enabled=mode!='no-js',reduced_motion='reduce' if mode=='reduced' else 'no-preference')
   async def route(r):
    u=r.request.url
    if urllib.parse.urlsplit(u).hostname!='kevcoachfit.com' or r.request.method!='GET':return await r.abort()
    if u not in cache:cache[u]=await asyncio.to_thread(fetch,u)
    await r.fulfill(**cache[u])
   await ctx.route('**/*',route);page=await ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
   for path in ['/','/ak-anti-nuisible/','/terroir-loupiac/']:
    await page.goto('https://kevcoachfit.com'+path,wait_until='networkidle',timeout=90000)
    assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
    target=page.locator('.kcf20-social-follow' if path=='/' else '.kcf20-route-panel' if path=='/ak-anti-nuisible/' else '.kcf20-terroir-story')
    await target.scroll_into_view_if_needed()
    if mode=='normal':
     await page.wait_for_function('(el)=>el.classList.contains("kcf20-motion-entered")',arg=await target.element_handle())
     await page.wait_for_function('(el)=>parseFloat(getComputedStyle(el).opacity)>.999',arg=await target.element_handle())
    else:assert not await target.evaluate('e=>e.classList.contains("kcf20-motion-entered")')
    assert await target.is_visible()
    if mode!='no-js':
     assert await target.evaluate('e=>parseFloat(getComputedStyle(e).opacity)')>.99
    rows.append({'path':path,'mode':mode,'visible':True,'overflow':False});print('Verified motion/accessibility',path,mode,flush=True)
   await ctx.close()
  await b.close()
 assert not errors,errors
 (ROOT/'docs/editorial-motion-39-browser.json').write_text(json.dumps({'checks':rows,'javascript_errors':errors,'remote_external_calls':False},ensure_ascii=False,indent=2)+'\n')
asyncio.run(main())
