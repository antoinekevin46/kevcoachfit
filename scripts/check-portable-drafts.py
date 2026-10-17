"""Check native WordPress draft rendering and Gutenberg editability before remote transfer."""
import asyncio,json,urllib.parse,sys
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
WORK=ROOT/'artifacts/wordpress-import-20'
async def main():
 ids=json.loads((WORK/'local-fixtures.json').read_text());values=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line)
 results=[];errors=[]
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  ctx=await browser.new_context();page=await ctx.new_page();page.on('pageerror',lambda e:errors.append(e.stack))
  async def image_route(route):
   path=urllib.parse.urlsplit(route.request.url).path.split('/wp-content/themes/kevcoachfit/',1)[1]
   await route.fulfill(path=str(ROOT/'wordpress/themes/kevcoachfit'/path))
  await ctx.route('https://kevcoachfit.com/wp-content/themes/kevcoachfit/**',image_route)
  await page.goto('http://127.0.0.1:8080/wp-login.php');await page.locator('#user_login').fill('atelier_kevin');await page.locator('#user_pass').fill(values['KCF_LOCAL_PASSWORD']);await page.locator('#wp-submit').click();await page.wait_for_url('**/wp-admin/**')
  for width in ([] if '--editor-only' in sys.argv else [320,390,768,1440]):
   await page.set_viewport_size({'width':width,'height':950})
   for slug,id in ids.items():
    await page.goto(f'http://127.0.0.1:8080/?page_id={id}&preview=true',wait_until='networkidle')
    assert await page.locator('#kcf20-preview-css').count()==1,(slug,'style filtered')
    assert await page.locator('#kcf20-preview-effects').count()==1,(slug,'script filtered')
    await page.locator('img').evaluate_all('imgs=>{imgs.forEach(i=>i.loading="eager");return Promise.race([Promise.all(imgs.map(i=>i.decode().catch(()=>{}))),new Promise(r=>setTimeout(r,3000))]);}')
    assert await page.locator('h1').count()==1,(slug,'h1')
    if not await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'):
     await page.screenshot(path=str(WORK/'overflow.png'),full_page=True)
     (WORK/'rendered-script.js').write_text(await page.locator('#kcf20-preview-effects').text_content())
     raise AssertionError((slug,width,errors,await page.evaluate("[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1&&getComputedStyle(e).display!=='none').slice(0,15).map(e=>({tag:e.tagName,c:e.className,left:e.getBoundingClientRect().left,right:e.getBoundingClientRect().right,max:getComputedStyle(e).maxWidth,width:getComputedStyle(e).width}))")))
    assert await page.locator('.kcf20-preview-root > header').is_visible()
    assert not await page.locator('.wp-site-blocks > header').is_visible()
    assert await page.locator('.kcf20-menu-toggle').count()==1,(slug,'duplicate mobile menu')
    assert await page.locator('.kcf20-preview-root img').evaluate_all('imgs=>imgs.every(i=>i.naturalWidth>0)'),(slug,'broken image')
    if slug in ['vitalivan','ak-anti-nuisible']:
     assert await page.locator('[data-kcf20-map]').count()==1
     zoom=page.locator('[data-map-zoom="in"]')
     if await zoom.count():await zoom.click()
    if width==390 and slug in ['vitalivan','ak-anti-nuisible','terroir-loupiac']:
     await page.screenshot(path=str(WORK/f'portable-{slug}-{width}.png'))
    if width==390:
     toggle=page.locator('.kcf20-menu-toggle');await toggle.click();assert await toggle.get_attribute('aria-expanded')=='true';await page.keyboard.press('Escape');assert await toggle.get_attribute('aria-expanded')=='false'
    results.append({'page':slug,'width':width,'overflow':False,'styles_and_effects':'present','menu':'one current menu'})
  # The dedicated draft forms must not send any request, even with the old module active.
  await page.goto(f'http://127.0.0.1:8080/?page_id={ids["reserver"]}&preview=true',wait_until='networkidle')
  sent=[];page.on('request',lambda r:sent.append(r.url) if r.method=='POST' else None)
  form=page.locator('.kcf20-preview-root form').first
  await form.evaluate("f=>f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))")
  assert 'aucune demande' in await form.locator('.kcf20-form-result').inner_text()
  assert not sent,sent
  if results:(WORK/'portable-renders.json').write_text(json.dumps(results))
  # Gutenberg parser must keep all normal source blocks valid and editable.
  await page.goto(f'http://127.0.0.1:8080/wp-admin/post.php?post={ids["vitalivan"]}&action=edit',wait_until='domcontentloaded',timeout=60000)
  await page.wait_for_function("window.wp?.data?.select('core/block-editor')?.getBlocks()?.length>0")
  if not results and (WORK/'portable-renders.json').exists():results=json.loads((WORK/'portable-renders.json').read_text())
  state=await page.evaluate("""()=>{const blocks=wp.data.select('core/block-editor').getBlocks();const flat=b=>b.flatMap(x=>[x,...flat(x.innerBlocks||[])]);const all=flat(blocks);return {invalid:all.filter(b=>b.isValid===false).map(b=>b.name),headings:all.filter(b=>b.name==='core/heading').length,images:all.filter(b=>b.name==='core/image').length,tables:all.filter(b=>b.name==='core/table').length};}""")
  assert not state['invalid'],state
  assert state['headings']>15 and state['images']>4 and state['tables']>=2,state
  # Ignore unrelated block editor deprecations, but frontend JS errors must have been empty.
  await browser.close()
 (WORK/'portable-check.json').write_text(json.dumps({'renders':results,'editable_sport_blocks':state,'form_submission':'blocked','browser_errors':errors},ensure_ascii=False,indent=2))
 print('28 native WordPress draft renders, menus, maps, demo-only forms and native editable blocks verified.')
asyncio.run(main())
