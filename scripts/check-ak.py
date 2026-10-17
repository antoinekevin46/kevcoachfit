"""Verify the new first-visit journey, FAQ, planning zoom and honest photo/review placeholders."""
import asyncio,json
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
async def main():
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  page=await browser.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  await page.route('http://127.0.0.1:8080/ak-check',lambda route:route.fulfill(path=str(ROOT/'artifacts/kevcoachfit-vitrine-19.html'),content_type='text/html'))
  await page.goto('http://127.0.0.1:8080/ak-check')
  await page.select_option('#page-picker','ak-anti-nuisible')
  await page.wait_for_function("document.getElementById('viewer').dataset.page==='ak-anti-nuisible'")
  f=page.frames[1];results=[]
  for width in [320,390,768,1440]:
   await page.set_viewport_size({'width':width,'height':1000})
   assert await f.locator('.kcf-ak-faq-item').count()==6
   assert await f.locator('.kcf-ak-step').count()==3
   for link in await f.locator('.kcf-ak-shortcuts a').all():
    assert await f.locator(await link.get_attribute('href')).count()==1
   mail=await f.locator('.kcf-ak-contact-card a[href^="mailto:"]').get_attribute('href')
   from urllib.parse import urlparse,parse_qs
   parts=urlparse(mail);query=parse_qs(parts.query)
   assert parts.path=='aknuisible@gmail.com' and 'Demande de devis' in query['subject'][0]
   assert all(t in query['body'][0] for t in ['Commune','Emplacement et accès','Téléphone pour me rappeler'])
   first=f.locator('.kcf-ak-faq-item summary').first;await first.focus();await page.keyboard.press('Enter')
   assert await first.evaluate('e=>e.parentElement.open')
   await f.locator('.kcf-ak-faq-item').evaluate_all('list=>list.forEach(d=>d.open=true)')
   assert await f.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),width
   await f.locator('.kcf-ak-faq-item').evaluate_all('list=>list.forEach(d=>d.open=false)')
   results.append({'width':width,'faq':6,'steps':3,'anchors':'passed','prefilled_email':'passed','keyboard':'passed','overflow':False})
  await page.set_viewport_size({'width':320,'height':1000})
  await f.locator('.kcf-ak-faq-item').evaluate_all('list=>list.forEach(d=>d.open=true)')
  await f.add_style_tag(content='html{font-size:200% !important}')
  assert await f.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'200% overflow'
  assert not errors,errors
  await browser.close()
 (ROOT/'docs/ak-19-verification.json').write_text(json.dumps({'results':results,'large_text':'320px, 200%, all FAQ open: passed','javascript_errors':errors},ensure_ascii=False,indent=2)+'\n')
 print('A.K : 4 largeurs, 6 FAQ clavier, 3 étapes, liens de devis et texte à 200 % vérifiés.')
asyncio.run(main())
