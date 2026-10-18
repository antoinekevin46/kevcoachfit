"""Check the actual anonymous homepage: aligned cards, frame, and video playback."""
import asyncio,json,urllib.request,urllib.parse
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent;cache={};errors=[]
def fetch(url):
 with urllib.request.urlopen(url,timeout=45) as r:return {'body':r.read(),'content_type':r.headers.get_content_type()}
async def main():
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  ctx=await browser.new_context()
  async def route(r):
   url=r.request.url
   if urllib.parse.urlsplit(url).hostname!='kevcoachfit.com' or r.request.method!='GET':return await r.abort()
   if url not in cache:cache[url]=await asyncio.to_thread(fetch,url)
   await r.fulfill(**cache[url])
  await ctx.route('**/*',route);page=await ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  rows=[]
  for width in [320,390,1440,2507]:
   await page.set_viewport_size({'width':width,'height':1000});await page.goto('https://kevcoachfit.com/',wait_until='networkidle',timeout=90000)
   assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
   cards=await page.locator('.kcf20-choice-card').evaluate_all('(es)=>es.map(e=>{const r=e.getBoundingClientRect();return {top:r.top,height:r.height}})')
   assert len(cards)==4
   if width>700:
    assert abs(cards[0]['top']-cards[1]['top'])<1,cards
    assert abs(cards[2]['top']-cards[3]['top'])<1,cards
    assert max(x['height'] for x in cards)-min(x['height'] for x in cards)<2,cards
   assert await page.locator('#votre-coach .kcf20-photo-slot').count()==0
   for ident in ['vitali-seniors','vitali-renfort','vitali-training','vitali-coach']:
    assert len(await page.locator('#'+ident).inner_text())>600
   if width>=1440:
    coach_rect=await page.locator('#votre-coach').bounding_box()
    assert coach_rect['height']<700,coach_rect
   if width in [390,1440]:await page.locator('#votre-coach').screenshot(path=str(ROOT/f'artifacts/visual-30/coach-{width}.png'))
   v=page.locator('.kcf20-video-section video');rect=await v.bounding_box();assert abs(rect['width']/rect['height']-16/9)<.01,rect
   await v.scroll_into_view_if_needed()
   await v.evaluate('(v)=>{v.load();}')
   await page.wait_for_function('document.querySelector(".kcf20-video-section video").readyState>=2',timeout=45000)
   duration=await v.evaluate('(v)=>v.duration');assert 25.9<duration<26.2,duration
   await v.evaluate('(v)=>v.play()');await page.wait_for_function('document.querySelector(".kcf20-video-section video").currentTime>.2',timeout=10000)
   await v.evaluate('(v)=>v.pause()')
   if width in [390,1440]:await page.locator('.kcf20-video-section').screenshot(path=str(ROOT/f'artifacts/visual-30/player-{width}.png'))
   rows.append({'viewport':width,'horizontal_overflow':False,'cards':cards,'video_frame':rect,'video_duration':duration,'playback':'passed'})
   print('Verified cards and playback at',width,flush=True)
  await browser.close()
 assert not errors,errors
 (ROOT/'docs/visual-30-browser.json').write_text(json.dumps({'actual_anonymous_homepage':True,'renders':rows,'javascript_errors':errors},ensure_ascii=False,indent=2)+'\n')
asyncio.run(main())
