"""Verify the new first-visit journey, FAQ, planning zoom and honest photo/review placeholders."""
import asyncio,json
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
async def main():
 results=[];errors=[]
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  page=await browser.new_page();page.set_default_timeout(15000);page.on('pageerror',lambda e:errors.append(str(e)))
  await page.route('http://127.0.0.1:8080/vitali-check',lambda route:route.fulfill(path=str(ROOT/'artifacts/kevcoachfit-vitrine-18.html'),content_type='text/html'))
  await page.goto('http://127.0.0.1:8080/vitali-check')
  await page.select_option('#page-picker','vitalivan');await page.wait_for_function("document.getElementById('viewer').dataset.page==='vitalivan'")
  f=page.frames[1]
  for width in [320,390,768,1440]:
   await page.set_viewport_size({'width':width,'height':1000})
   assert await f.locator('.kcf-choice-card').count()==4
   assert await f.locator('.kcf-photo-slot').count()==5
   assert await f.locator('.kcf-faq-item').count()==8
   assert await f.locator('.kcf-program-card').count()==4
   assert not await f.locator('.kcf-participant-reviews blockquote,.kcf-participant-reviews .stars').count()
   assert 'avec l’accord' in await f.locator('.kcf-participant-reviews').inner_text()
   for link in await f.locator('.kcf-choice-card a').all():
    href=await link.get_attribute('href');assert href.startswith('#') and await f.locator(href).count()==1
   poster=f.locator('.kcf-planning-poster');await poster.locator('img').evaluate('i=>i.decode()')
   await poster.locator('.kcf-image-zoom').click()
   dialog=f.locator('dialog.kcf-lightbox');assert await dialog.is_visible()
   assert await dialog.locator('img').evaluate('i=>i.naturalWidth>0')
   await dialog.get_by_role('button',name='Zoom +',exact=True).click()
   assert 'is-zoomed' in await dialog.get_attribute('class')
   await page.keyboard.press('Escape');assert not await dialog.is_visible()
   first=f.locator('.kcf-faq-item summary').first;await first.focus();await page.keyboard.press('Enter')
   assert await first.evaluate('e=>e.parentElement.open')
   await f.locator('.kcf-faq-item').evaluate_all('list=>list.forEach(d=>d.open=true)')
   assert await f.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(width,'expanded FAQ overflow')
   if width in [390,1440]:
    # Capture the same document outside its iframe to avoid screenshot clipping of tall sections.
    snap=await browser.new_page(viewport={'width':width,'height':1000})
    await snap.set_content(await page.locator('#viewer').get_attribute('srcdoc'))
    await snap.locator('img').evaluate_all('imgs=>{imgs.forEach(i=>i.loading="eager");return Promise.race([Promise.all(imgs.map(i=>i.decode().catch(()=>{}))),new Promise(resolve=>setTimeout(resolve,3000))]);}')
    await snap.add_style_tag(content='.kcf-jump-links{position:static !important}')
    await snap.locator('.kcf-course-guide').screenshot(path=str(ROOT/'docs'/f'vitali-18-guide-{width}.png'))
    await snap.locator('.kcf-coach-section').screenshot(path=str(ROOT/'docs'/f'vitali-18-coach-{width}.png'))
    await snap.locator('.kcf-planning-layout').screenshot(path=str(ROOT/'docs'/f'vitali-18-planning-{width}.png'))
    await snap.close()
   results.append({'width':width,'guide_cards':4,'photo_slots':5,'faq_items':8,'original_programs':4,'planning_zoom':'passed','faq_keyboard':'passed','expanded_faq_overflow':False})
   await f.locator('.kcf-faq-item').evaluate_all('list=>list.forEach(d=>d.open=false)')
  await page.set_viewport_size({'width':320,'height':1000})
  await f.locator('.kcf-faq-item').evaluate_all('list=>list.forEach(d=>d.open=true)')
  await f.add_style_tag(content='html{font-size:200% !important}')
  assert await f.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'large text with open FAQ overflow'
  assert not errors,errors
  await browser.close()
 (ROOT/'docs/vitali-18-verification.json').write_text(json.dumps({'results':results,'large_text':'320px, 200%, all FAQs open: passed','javascript_errors':errors},ensure_ascii=False,indent=2)+'\n')
 print('Guide, 5 emplacements photo, 8 questions, planning agrandi, clavier et texte à 200 % vérifiés.')
asyncio.run(main())
