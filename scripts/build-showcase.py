"""Capture local WordPress previews as a standalone, offline, non-submitting showcase."""
import asyncio,base64,json,re,subprocess,urllib.parse
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
BASE='http://127.0.0.1:8080'
LABELS={'accueil':'Le portail','vitalivan':'Vitali’Van','ak-anti-nuisible':'A.K Nuisibles','terroir-loupiac':'Terroir Antoine','contact':'Contact','reserver':'Réserver · démo','mes-reservations':'Mon inscription · démo'}
async def main():
 raw=subprocess.check_output(['docker','compose','run','--rm','cli','wp','eval','echo wp_json_encode(get_option("kcf_workshop_pages"));'],cwd=ROOT,text=True)
 pages=json.loads(raw);assets={};cache={};documents={}
 local_values=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line)
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  context=await browser.new_context()
  page=await context.new_page();await page.goto(BASE+'/wp-login.php')
  await page.locator('#user_login').fill('atelier_kevin');await page.locator('#user_pass').fill(local_values['KCF_LOCAL_PASSWORD'])
  await page.locator('#wp-submit').click();await page.wait_for_url('**/wp-admin/**')
  async def asset(url):
   url=url.replace('&amp;','&')
   if url in cache:return cache[url]
   assert urllib.parse.urlparse(url).netloc=='127.0.0.1:8080',url
   response=await context.request.get(url)
   assert response.ok, (response.status,url)
   data=await response.body();mime=response.headers.get('content-type','application/octet-stream').split(';')[0]
   key='asset'+str(len(assets));cache[url]='kcf-asset://'+key
   assets[key]='data:'+mime+';base64,'+base64.b64encode(data).decode()
   return cache[url]
  async def css_embed(text,base):
   for match in list(re.finditer(r'url\([\'\"]?([^\)\'\"]+)[\'\"]?\)',text)):
    path=match[1]
    if path.startswith(('data:','#','kcf-asset:')):continue
    full=urllib.parse.urljoin(base,path)
    text=text.replace(match[0],'url("'+await asset(full)+'")')
   return text
  theme_js=(ROOT/'wordpress/themes/kevcoachfit/theme.js').read_text()
  for slug in LABELS:
   url=BASE+'/?page_id='+str(pages[slug])+'&preview=true&kcf_atelier=1&wp_theme_preview=kevcoachfit'
   response=await context.request.get(url);assert response.ok
   html=await response.text();assert '<h1' in html,slug
   html=re.sub(r'<script\b[^>]*>.*?</script>','',html,flags=re.S|re.I)
   # WP admin bar and its generated inline mobile offsets do not belong in the demo.
   assert 'id="wpadminbar"' not in html, 'Disable the local user admin bar before capture.'
   html=html.replace('admin-bar','').replace('customize-support','')
   html=re.sub(r'<style[^>]*id=[\'\"]admin-bar-inline-css[\'\"][^>]*>.*?</style>','',html,flags=re.S)
   for m in list(re.finditer(r'<link\b[^>]+>',html)):
    tag=m[0]
    href=re.search(r'href=[\'\"]([^\'\"]+)',tag)
    if 'stylesheet' in tag and href:
     link=urllib.parse.urljoin(BASE,href[1].replace('&amp;','&'))
     css_response=await context.request.get(link);assert css_response.ok,link
     css=await css_embed(await css_response.text(),link)
     html=html.replace(tag,'<style>'+css+'</style>')
    else:html=html.replace(tag,'')
   for m in list(re.finditer(r'(src|poster)=[\'\"]([^\'\"]+)[\'\"]',html)):
    target=urllib.parse.urljoin(BASE,m[2].replace('&amp;','&'))
    if target.startswith(BASE):html=html.replace(m[0],m[1]+'="'+await asset(target)+'"')
   # Remove network connections, resource hints, and any live form endpoints.
   html=re.sub(r'<meta[^>]+(?:og:|twitter:)[^>]*>','',html)
   html=re.sub(r'\saction=[\'\"][^\'\"]*[\'\"]','',html)
   html=re.sub(r'<style([^>]*)>(.*?)</style>',lambda m:'<style'+m[1]+'>'+m[2]+'</style>',html,flags=re.S)
   # Styles may contain local font URLs in inline global styles as well.
   for m in list(re.finditer(r'<style[^>]*>(.*?)</style>',html,re.S)):
    html=html.replace(m[0],'<style>'+await css_embed(m[1],BASE+'/')+'</style>')
   bridge='''
const routes=ROUTES;
document.addEventListener('click',event=>{
 const link=event.target.closest('a');if(!link)return;
 const href=link.getAttribute('href');if(!href||href.startsWith('#'))return;
 try {const url=new URL(href,'http://127.0.0.1:8080');
 if(url.hostname==='127.0.0.1'||url.hostname==='kevcoachfit.com'){
  const id=url.searchParams.get('page_id')||url.searchParams.get('p');
  const slug=routes[id]||url.pathname.replace(/^\\/|\\/$/g,'')||'accueil';
  if(Object.values(routes).includes(slug)){event.preventDefault();parent.postMessage({kcfPage:slug,anchor:url.hash},'*');}
 }
 }catch(error){}
});
document.querySelectorAll('form').forEach(form=>{
 form.addEventListener('submit',event=>{event.preventDefault();const result=form.querySelector('.kcf-form-result');if(result){result.textContent='Démonstration : aucune demande n’a été envoyée. Pour une réservation réelle, appelez Kévin au 06 74 80 67 07.';}},true);
});
document.querySelectorAll('[data-kcf-management]').forEach(el=>el.textContent='Démonstration : aucune inscription réelle à afficher.');
document.querySelectorAll('[data-order-total]').forEach(total=>{const form=total.closest('form');form.addEventListener('input',()=>{total.textContent=new Intl.NumberFormat('fr-FR',{style:'currency',currency:'EUR'}).format([...form.querySelectorAll('[data-unit-cents]')].reduce((sum,input)=>sum+(Number(input.value)||0)*Number(input.dataset.unitCents),0)/100);});});
'''.replace('ROUTES',json.dumps({str(v):k for k,v in pages.items()}))
   html=html.replace('</body>','<script>'+bridge+'</script><script>'+theme_js+'</script></body>')
   documents[slug]=html
  await browser.close()
 payload=json.dumps({'documents':documents,'assets':assets,'labels':LABELS},ensure_ascii=False).replace('<','\\u003c')
 options=''.join('<option value="'+k+'">'+v+'</option>' for k,v in LABELS.items())
 shell=r'''<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KevCoachFit — Vitrine 16</title><style>*{box-sizing:border-box}body{margin:0;background:#111;color:white;font:14px/1.4 system-ui,sans-serif}.toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid #555}label{font-weight:700}select{max-width:100%;background:#202020;color:white;border:1px solid #777;border-radius:24px;padding:9px 14px;font:inherit}small{color:#ccc}.viewer{width:100%;height:calc(100dvh - 78px);border:0;display:block;background:white}@media(max-width:600px){.toolbar{gap:6px;padding:8px 12px}small{width:100%;font-size:11px}.viewer{height:calc(100dvh - 94px)}} </style><div class="toolbar"><label for="page-picker">Vitrine 16</label><select id="page-picker">OPTIONS</select><small>Maquette privée · formulaires de démonstration · tarifs par période validés</small></div><iframe id="viewer" class="viewer" title="Aperçu du site" sandbox="allow-scripts allow-same-origin allow-popups"></iframe><script>
const data=PAYLOAD;
const picker=document.getElementById('page-picker'),viewer=document.getElementById('viewer');
function show(slug,anchor=''){
 if(!data.documents[slug])return;
 picker.value=slug;document.title=data.labels[slug]+' — Vitrine KevCoachFit';
 viewer.onload=()=>{if(anchor){const el=viewer.contentDocument.getElementById(anchor.slice(1));el?.scrollIntoView();}};
 viewer.srcdoc=data.documents[slug].replace(/kcf-asset:\/\/(asset\d+)/g,(_,key)=>data.assets[key]);
}
picker.addEventListener('change',()=>show(picker.value));
window.addEventListener('message',event=>{if(event.source===viewer.contentWindow&&event.data?.kcfPage)show(event.data.kcfPage,event.data.anchor||'');});
show('accueil');
</script></html>'''.replace('OPTIONS',options).replace('PAYLOAD',payload)
 out=ROOT/'artifacts/kevcoachfit-vitrine-16.html';out.write_text(shell);out.chmod(0o644)
 print('Vitrine autonome créée :',len(documents),'pages,',len(assets),'ressources intégrées,',out.stat().st_size,'octets.')
asyncio.run(main())
