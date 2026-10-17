import asyncio,json
from pathlib import Path
from playwright.async_api import async_playwright
root=Path(__file__).resolve().parent.parent;out=root/'artifacts/rdv-25'
async def main():
 ids=json.loads((out/'local-ids.json').read_text());values=dict(line.split('=',1) for line in (root/'.env').read_text().splitlines() if '=' in line);results=[];errors=[]
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage']);ctx=await browser.new_context();page=await ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  await page.goto('http://127.0.0.1:8080/wp-login.php');await page.locator('#user_login').fill('atelier_kevin');await page.locator('#user_pass').fill(values['KCF_LOCAL_PASSWORD']);await page.locator('#wp-submit').click();await page.wait_for_url('**/wp-admin/**')
  for width in [320,390,768,1440]:
   await page.set_viewport_size({'width':width,'height':844})
   for source,lid in ids.items():
    await page.goto('http://127.0.0.1:8080/?page_id='+str(lid)+'&preview=true',wait_until='domcontentloaded');await page.wait_for_timeout(150)
    cta=page.get_by_role('link',name='Rendez-vous et réservation des cours',exact=True);assert await cta.is_visible();assert await cta.get_attribute('href')=='https://kevcoachfit.com/reserver/'
    assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(source,width,'overflow')
    if width<600:
     assert await cta.locator('.kcf20-rdv-mobile').is_visible()
     await page.evaluate('scrollTo(0,350)');assert await cta.is_visible();assert await cta.evaluate('e=>e.getBoundingClientRect().top')<120
     menu=page.locator('.kcf20-menu-toggle');await menu.click();assert await page.locator('.kcf20-dropdown-panel').get_by_role('link').count()==4;assert await page.get_by_role('link',name='Miel & vin',exact=True).is_visible();await page.keyboard.press('Escape')
     if width==390 and source=='36':
      await page.evaluate('scrollTo(0,0)');await page.screenshot(path=str(out/'rdv-mobile.png'))
    results.append({'source_page':int(source),'width':width,'booking_shortcut_visible':True,'overflow':False})
   print('Passed width',width,flush=True)
  await browser.close()
 assert not errors,errors
 (root/'docs/rdv-25-ui-verification.json').write_text(json.dumps({'renders':results,'javascript_errors':errors,'booking_activation':False},indent=2)+'\n')
asyncio.run(main())
