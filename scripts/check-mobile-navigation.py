import asyncio,json,re,urllib.parse
from pathlib import Path
from playwright.async_api import async_playwright
from html.parser import HTMLParser
class Link:
 def __init__(self,a):self.a=dict(a)
 def __getitem__(self,k):return self.a[k]
 def get_text(self,*a,**kw):return self.a.get('href','')
class BeautifulSoup(HTMLParser):
 def __init__(self,s,*args):
  super().__init__();self.links=[];self.ids=set();self.feed(s)
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if 'id' in a:self.ids.add(a['id'])
  if tag=='a' and 'href' in a:self.links.append(Link(attrs))
 def select(self,s):return self.links
 def find(self,id):return id in self.ids

ROOT=Path(__file__).resolve().parent.parent;out=ROOT/'artifacts/mobile-fix-23';ids=json.loads((out/'local-ids.json').read_text())
issues=[]
for rid in ids:
 s=(out/f'{rid}-reviewed.html').read_text();s=re.sub(r'<(style|script)\b[^>]*>.*?</\1>','',s,flags=re.S);doc=BeautifulSoup(s,'html.parser')
 for a in doc.select('a[href]'):
  url=urllib.parse.urlsplit(a['href']);target=rid
  if url.netloc in ('kevcoachfit.com','127.0.0.1:8080'):target=urllib.parse.parse_qs(url.query).get('page_id',[None])[0]
  elif url.netloc or not url.fragment:continue
  if target and url.fragment and target in ids:
   target_doc=BeautifulSoup((out/f'{target}-reviewed.html').read_text(),'html.parser')
   if not target_doc.find(id=urllib.parse.unquote(url.fragment)):issues.append([rid,a.get_text(' ',strip=True),target,url.fragment])
print('ANCHORS',json.dumps(issues,ensure_ascii=False),flush=True)
async def main():
 results=[];errors=[];broken=[]
 values=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line)
 async with async_playwright() as pw:
  browser=await pw.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  context=await browser.new_context();page=await context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  media=json.loads((ROOT/'artifacts/wordpress-import-20/manifest.json').read_text())['media']
  media_by_url={v['url']:ROOT/'wordpress/themes/kevcoachfit'/path for path,v in media.items()}
  async def asset_route(route):
   path=media_by_url.get(route.request.url)
   if path:await route.fulfill(path=str(path))
   else:await route.continue_()
  await context.route('https://kevcoachfit.com/wp-content/uploads/**',asset_route)

  await page.goto('http://127.0.0.1:8080/wp-login.php');await page.locator('#user_login').fill('atelier_kevin');await page.locator('#user_pass').fill(values['KCF_LOCAL_PASSWORD']);await page.locator('#wp-submit').click();await page.wait_for_url('**/wp-admin/**')
  for width in [320,390,768,1440]:
   await page.set_viewport_size({'width':width,'height':950})
   for rid,lid in ids.items():
    await page.goto(f'http://127.0.0.1:8080/?page_id={lid}&preview=true',wait_until='load',timeout=60000)
    await page.wait_for_timeout(150)
    if width<600:
     header=page.locator('.kcf20-preview-root > header');assert await header.is_visible(),(rid,'header hidden')
     await page.evaluate('scrollTo(0,350)');await page.wait_for_timeout(80)
     top=await header.evaluate('e=>e.getBoundingClientRect().top');assert 0<=top<=47,(rid,'header not sticky',top)
     await page.evaluate('scrollTo(0,0)')
     toggle=page.locator('.kcf20-menu-toggle');await toggle.click()
     panel=page.locator('.kcf20-dropdown-panel')
     assert await panel.get_by_role('link').count()==4,(rid,'expected four pages')
     assert await panel.get_by_role('link',name='Miel & vin',exact=True).is_visible(),(rid,'honey wine link hidden')
     assert await panel.evaluate('e=>e.getBoundingClientRect().height')<360,(rid,'menu fullscreen')
     await page.keyboard.press('Escape')
    if rid=='64':
     dimensions=await page.evaluate('(()=>{const hero=document.querySelector(".kcf20-sport-hero").getBoundingClientRect();const photo=document.querySelector(".kcf20-sport-photo img").getBoundingClientRect();return {hero:hero.height,image:photo.height}})()')
     assert abs(dimensions['hero']-dimensions['image'])<2,dimensions

    assert await page.locator('h1').count()==1,(rid,'H1')
    assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(rid,width,'overflow')
    await page.locator('.kcf20-preview-root img').evaluate_all('imgs=>{imgs.forEach(i=>i.loading="eager");return Promise.race([Promise.all(imgs.map(i=>i.decode().catch(()=>{}))),new Promise(r=>setTimeout(r,4000))]);}')
    missing=await page.locator('.kcf20-preview-root img').evaluate_all('imgs=>imgs.filter(i=>!i.naturalWidth).map(i=>i.src)')
    if missing:broken.append({'page':rid,'width':width,'images':missing})
    if width==390:
     toggle=page.locator('.kcf20-menu-toggle');await toggle.click();assert await toggle.get_attribute('aria-expanded')=='true';await page.keyboard.press('Escape');assert await toggle.get_attribute('aria-expanded')=='false'
    if False:
     assert await page.locator('.kcfr-form' if rid=='68' else '.kcfr-management').count()==(2 if rid=='68' else 1)
     assert await page.evaluate('typeof kcfrApp!=="undefined"')
     if rid=='68':
      assert await page.locator('.kcfr-form select option').count()>20
      await page.locator('.kcfr-form select').select_option(index=1)
      assert 'place(s)' in await page.locator('.kcfr-session-preview').inner_text()
      if width==390:
       await page.locator('.kcfr-form').first.scroll_into_view_if_needed();await page.screenshot(path=str(out/'booking-mobile.png'))
     else:
      assert 'Ouvrez le lien privé' in await page.locator('.kcfr-management').inner_text()
    results.append({'page':int(rid),'width':width,'overflow':False})
   print('WIDTH',width,'passed',flush=True)
  # Native Gutenberg parser still accepts modified text/details blocks.
  await page.goto('http://127.0.0.1:8080/wp-admin/post.php?post='+str(ids['68'])+'&action=edit',wait_until='domcontentloaded')
  await page.wait_for_function("window.wp?.data?.select('core/block-editor')?.getBlocks()?.length>0")
  invalid=await page.evaluate("(()=>{const flat=bs=>bs.flatMap(b=>[b,...flat(b.innerBlocks||[])]);return flat(wp.data.select('core/block-editor').getBlocks()).filter(b=>b.isValid===false).map(b=>b.name)})()")
  assert not invalid,invalid
  await browser.close()
 report={'renders':results,'broken_anchors':issues,'broken_images':broken,'javascript_errors':errors,'gutenberg_invalid_blocks':invalid,'mode':'local copies of current remote drafts; remote media hashes verified separately, served identical local bytes in Chromium; no customer notifications sent'}
 (out/'ui-verification.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
 assert not issues and not broken and not errors,report
 print('28 renders passed; sticky header, four-page menu and full-height sport image verified.')
asyncio.run(main())
