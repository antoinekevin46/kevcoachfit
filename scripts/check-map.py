"""Exercise the geographic map in the actual standalone visitor showcase."""
import asyncio,json
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
async def main():
 checks=[];errors=[];requests=[]
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  for width in [320,390,1440]:
   context=await browser.new_context(viewport={'width':width,'height':1000},has_touch=width<600)
   page=await context.new_page();page.set_default_timeout(12000)
   page.on('pageerror',lambda e:errors.append(str(e)))
   await page.route('http://127.0.0.1:8080/map-check',lambda route:route.fulfill(path=str(ROOT/'artifacts/kevcoachfit-vitrine-19.html'),content_type='text/html'))
   await page.goto('http://127.0.0.1:8080/map-check');await page.wait_for_timeout(200)
   page.on('request',lambda r:requests.append(r.url) if r.url.startswith(('http:','https:')) else None)
   for slug in ['vitalivan','ak-anti-nuisible']:
    await page.select_option('#page-picker',slug);await page.wait_for_function("slug=>document.getElementById('viewer').dataset.page===slug",arg=slug)
    frame=page.frames[1];root=frame.locator('[data-kcf-map]');svg=root.locator('svg');canvas=root.locator('.kcf-map-canvas')
    await canvas.scroll_into_view_if_needed()
    assert await root.locator('[data-map-select] option').count()==21
    assert await root.locator('[data-town]').count()==20
    assert await root.get_attribute('data-zoom')=='1.00'
    await root.locator('[data-map-action="in"]').click();assert float(await root.get_attribute('data-zoom'))>1
    await root.locator('[data-map-action="reset"]').click();assert await root.get_attribute('data-zoom')=='1.00'
    await root.locator('[data-map-select]').select_option('46178');assert await root.locator('[data-map-name]').inner_text()=='Loupiac'
    assert float(await root.get_attribute('data-zoom'))>=2
    await root.locator('[data-map-action="reset"]').click()
    if slug=='vitalivan':
     assert await root.get_attribute('data-mode')=='courses'
     assert await root.locator('.kcf-map-town.is-active').count()==3
     await root.locator('[data-map-select]').select_option('46290');assert 'prévu' in await root.locator('[data-map-description]').inner_text()
     await root.locator('[data-map-mode="coaching"]').click();assert await root.get_attribute('data-mode')=='coaching'
     assert await root.locator('.kcf-map-town.is-active').count()==1
     assert await root.locator('.kcf-map-town.is-active').get_attribute('data-town')=='46178'
     assert '15 minutes' in await root.locator('[data-map-description]').inner_text()
     await root.locator('[data-map-select]').select_option('46127');assert 'vérifiez' in await root.locator('[data-map-description]').inner_text()
     await root.locator('[data-map-mode="courses"]').click()
    else:
     assert await root.locator('[data-map-mode]').count()==0
     assert await root.get_attribute('data-mode')=='protection'
     assert await root.locator('.kcf-map-town.is-active').get_attribute('data-town')=='46215'
     assert '20 minutes' in await root.locator('[data-map-description]').inner_text()
    await svg.focus();await page.keyboard.press('+');assert float(await root.get_attribute('data-zoom'))>1
    before=await root.locator('.kcf-map-stage').get_attribute('transform');await page.keyboard.press('ArrowLeft');assert before!=await root.locator('.kcf-map-stage').get_attribute('transform')
    await page.keyboard.press('Home');assert await root.get_attribute('data-zoom')=='1.00'
    assert await svg.evaluate('el=>getComputedStyle(el).touchAction')=='pan-y'
    await root.locator('[data-map-action="drag"]').click();assert await svg.evaluate('el=>getComputedStyle(el).touchAction')=='none'
    if width==390:
     await canvas.scroll_into_view_if_needed();box=await svg.bounding_box();assert box
     x,y=box['x']+box['width']/2,box['y']+box['height']/2
     cdp=await context.new_cdp_session(page)
     await cdp.send('Input.dispatchTouchEvent',{'type':'touchStart','touchPoints':[{'x':x-20,'y':y,'id':1},{'x':x+20,'y':y,'id':2}]})
     await cdp.send('Input.dispatchTouchEvent',{'type':'touchMove','touchPoints':[{'x':x-45,'y':y,'id':1},{'x':x+45,'y':y,'id':2}]})
     await cdp.send('Input.dispatchTouchEvent',{'type':'touchEnd','touchPoints':[]})
     assert float(await root.get_attribute('data-zoom'))>1,(slug,'pinch')
     await cdp.detach()
    if width==1440:
     await canvas.scroll_into_view_if_needed();box=await svg.bounding_box();assert box
     before=await root.locator('.kcf-map-stage').get_attribute('transform')
     await page.mouse.move(box['x']+box['width']/2,box['y']+box['height']/2)
     await page.mouse.down();await page.mouse.move(box['x']+box['width']/2+55,box['y']+box['height']/2+20,steps=6);await page.mouse.up()
     assert before!=await root.locator('.kcf-map-stage').get_attribute('transform'),(slug,'drag')
    await root.locator('[data-map-action="reset"]').click()
    if width in [390,1440]:await canvas.screenshot(path=str(ROOT/'docs'/f'carte-19-{slug}-{width}.png'))
    assert await frame.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(slug,width,'overflow')
    checks.append({'page':slug,'width':width,'markers':20,'zoom':'passed','select':'passed','modes':'passed','keyboard':'passed','touch_scroll':'pan-y by default','pinch':'passed' if width==390 else 'not run','drag':'passed' if width==1440 else 'not run'})
   await context.close()
  assert not errors,errors
  assert not requests,requests
  await browser.close()
 (ROOT/'docs/carte-19-verification.json').write_text(json.dumps({'checks':checks,'javascript_errors':errors,'network_requests':requests},ensure_ascii=False,indent=2)+'\n')
 print('Cartes vérifiées sur 6 rendus : zoom, sélection, vues, clavier, déplacement et pincement tactile ; aucune requête réseau.')
asyncio.run(main())
