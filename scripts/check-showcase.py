"""Offline browser verification: distinct activity navigation, pricing, responsive flow, no submissions."""
import asyncio,json,re
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
PAGES=['accueil','vitalivan','ak-anti-nuisible','terroir-loupiac','contact','reserver','mes-reservations']
async def main():
 results=[];errors=[];requests=[]
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  page=await browser.new_page();page.set_default_timeout(10000)
  page.on('pageerror',lambda e:errors.append(str(e)))
  await page.route('http://127.0.0.1:8080/offline-check',lambda route:route.fulfill(path=str(ROOT/'artifacts/kevcoachfit-vitrine-20.html'),content_type='text/html'))
  await page.goto('http://127.0.0.1:8080/offline-check');await page.wait_for_timeout(500)
  page.on('request',lambda r:requests.append(r.url) if r.url.startswith(('http:','https:')) else None)
  for width in [320,390,768,1440]:
   await page.set_viewport_size({'width':width,'height':900})
   for slug in PAGES:
    await page.select_option('#page-picker',slug);await page.wait_for_function("slug=>document.getElementById('viewer').dataset.page===slug",arg=slug)
    frame=page.frames[1]
    await frame.locator('img').evaluate_all('imgs=>{imgs.forEach(i=>i.loading="eager");return Promise.race([Promise.all(imgs.map(i=>i.decode().catch(()=>{}))),new Promise(resolve=>setTimeout(resolve,3000))]);}')
    state=await frame.evaluate('''()=>({width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1,h1:document.querySelectorAll('h1').length,broken:[...document.images].filter(i=>!i.naturalWidth).map(i=>i.alt),brand:document.querySelector('.kcf-brand a')?.textContent,nav:[...document.querySelectorAll('header nav a')].map(a=>a.textContent),header:document.querySelector('header')?.innerText})''')
    assert not state['overflow'],(slug,width,'overflow')
    assert state['h1']==1,(slug,width,'H1')
    assert not state['broken'],(slug,width,'broken images',state['broken'])
    if slug in ['vitalivan','reserver','mes-reservations']:assert state['brand']=='Vitali’Van',(slug,state)
    if slug=='ak-anti-nuisible':assert state['brand']=='A.K Nuisibles' and 'Réserver' not in state['header'],state
    if slug=='terroir-loupiac':assert state['brand']=='Terroir Antoine' and 'Réserver' not in state['header'],state
    if slug in ['vitalivan','reserver']:
     prices=await frame.locator('.kcf-period-prices').inner_text()
     for text in ['120 €','220 €','80 €','147 €','Janvier–juin','Septembre–Noël']:assert text in prices,(slug,text)
     assert not re.search(r'\b(?:60|110)\s*€\s*/\s*trimestre',await frame.locator('body').inner_text())
    if width in [390,1440] and slug in ['vitalivan','ak-anti-nuisible','terroir-loupiac']:
     await page.screenshot(path=str(ROOT/'docs'/f'vitrine-20-{slug}-{width}.png'),full_page=False)
    results.append({'page':slug,'width':width,'brand':state['brand'],'overflow':state['overflow'],'h1':state['h1']})
  # Large-text reflow on the new fees and independent headers.
  for slug in ['vitalivan','ak-anti-nuisible','terroir-loupiac','reserver']:
   await page.select_option('#page-picker',slug);await page.wait_for_function("slug=>document.getElementById('viewer').dataset.page===slug",arg=slug)
   frame=page.frames[1];await page.set_viewport_size({'width':320,'height':900})
   await frame.add_style_tag(content='html {font-size:200% !important}')
   assert await frame.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(slug,'large-text overflow')
  # Compact menu and navigation between universes without contacting WordPress.
  await page.select_option('#page-picker','accueil');await page.wait_for_function("document.getElementById('viewer').dataset.page==='accueil'")
  frame=page.frames[1];toggle=frame.locator('.kcf-menu-toggle');await toggle.click()
  assert await toggle.get_attribute('aria-expanded')=='true'
  await frame.locator('header nav a',has_text='A.K Nuisibles').click();await page.wait_for_function("document.getElementById('viewer').dataset.page==='ak-anti-nuisible'")
  assert await page.locator('#page-picker').input_value()=='ak-anti-nuisible'
  assert not requests,requests
  assert not errors,errors
  await browser.close()
 (ROOT/'docs/vitrine-20-verification.json').write_text(json.dumps({'renders':results,'large_text':'4 pages at 320px and 200%','mobile_menu':'passed','cross_section_link':'passed','network_requests':requests,'javascript_errors':errors},ensure_ascii=False,indent=2)+'\n')
 print('28 rendus vérifiés, tarifs, menus, images et grands textes : succès ; aucune requête réseau depuis la vitrine.')
asyncio.run(main())
