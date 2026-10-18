"""Transfer portable Gutenberg draft previews without changing the active theme or public pages.

Run prepare locally first. Remote writes require explicit user authorization.
Uses existing runtime credentials, never writes them to disk. Manifest/backups stay in ignored artifacts/.
"""
import argparse,base64,hashlib,html,json,mimetypes,os,re,urllib.parse,urllib.request,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
WORK=ROOT/'artifacts/wordpress-import-20'
LABELS={'accueil':'Accueil','vitalivan':'Vitali’Van','ak-anti-nuisible':'A.K Nuisibles','terroir-loupiac':'Terroir Antoine','contact':'Contact','reserver':'Réserver','mes-reservations':'Mon inscription'}
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*a,**k):return None

def save(name,data):
 p=WORK/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(data,ensure_ascii=False,indent=2));p.chmod(0o600)

def api(path,method='GET',data=None,headers=None):
 raw=os.environ['KEVCOACHFIT_WP_URL'].strip().rstrip('/')
 base=raw if raw.startswith('https://') else 'https://'+raw
 parsed=urllib.parse.urlsplit(base)
 assert parsed.scheme=='https' and parsed.hostname in ['kevcoachfit.com','www.kevcoachfit.com'] and not parsed.username and not parsed.password and parsed.path in ['', '/'],'Unexpected WordPress origin'
 pair=os.environ['KEVCOACHFIT_WP_USERNAME'].strip()+':'+os.environ['KEVCOACHFIT_WP_APPLICATION_PASSWORD'].strip()
 h={'Accept':'application/json','Authorization':'Basic '+base64.b64encode(pair.encode()).decode()}
 if headers:h.update(headers)
 if isinstance(data,(dict,list)):data=json.dumps(data).encode();h['Content-Type']='application/json'
 req=urllib.request.Request(base+path,data=data,method=method,headers=h)
 try:
  with urllib.request.build_opener(NoRedirect).open(req,timeout=90) as response:return json.load(response)
 except urllib.error.HTTPError as e:
  try:code=json.loads(e.read(20000)).get('code','unknown')
  except Exception:code='non_json'
  raise RuntimeError(f'WordPress HTTP {e.code}, {code}, {method} {path.split("?")[0]}') from None

def canonical_blocks(content):
 def normalize(match):
  attrs=json.dumps(json.loads(match[2]),ensure_ascii=False,sort_keys=True,separators=(',',':'))
  return '<!-- wp:'+match[1]+' '+attrs+(' /' if match[3] else ' ')+'-->'
 return re.sub(r'<!-- wp:([a-z0-9/-]+)\s+(\{.*?\})\s*(/)?-->',normalize,content,flags=re.S)

def load_sources():
 fragments=json.loads((WORK/'fragments.json').read_text())
 shell=(ROOT/'artifacts/kevcoachfit-vitrine-20.html').read_text()
 payload=json.loads(re.search(r'const data=(.*?);\nconst picker=',shell,re.S)[1])
 styles='\n'.join(re.findall(r'<style[^>]*>(.*?)</style>',payload['documents']['vitalivan'],re.S))
 for key,data in payload['assets'].items():styles=styles.replace('kcf-asset://'+key,data)
 # Same current assets as the standalone showcase, scoped by distinct class names.
 styles+='\n'+(ROOT/'wordpress/themes/kevcoachfit/draft-reservations.css').read_text()
 styles+='\n'+(ROOT/'wordpress/themes/kevcoachfit/mobile-navigation.css').read_text()
 styles+='\n'+(ROOT/'wordpress/themes/kevcoachfit/booking-shortcut.css').read_text()
 styles+='\n'+(ROOT/'wordpress/themes/kevcoachfit/programme-presentation.css').read_text()
 styles+='\n'+(ROOT/'wordpress/themes/kevcoachfit/vivid-activities.css').read_text()
 styles+='\n'+(ROOT/'wordpress/themes/kevcoachfit/lively-pages.css').read_text()
 styles+='\n'+(ROOT/'wordpress/themes/kevcoachfit/editorial-motion.css').read_text()
 styles+='\n'+(ROOT/'wordpress/themes/kevcoachfit/ak-photos.css').read_text()
 styles=styles.replace('kcf-','kcf20-')
 styles+='''
body:has(.kcf20-preview-root) .wp-site-blocks > header,
body:has(.kcf20-preview-root) .wp-site-blocks > footer { display:none !important; }
body:has(.kcf20-preview-root) .wp-site-blocks,
body:has(.kcf20-preview-root) .wp-site-blocks > main,
body:has(.kcf20-preview-root) .wp-block-post-content { max-width:none !important; padding:0 !important; margin:0 !important; }
.kcf20-preview-root { max-width:none !important; width:100%; margin:0 !important; padding:0 !important; }
.kcf20-preview-root > header,.kcf20-preview-root > footer { margin:0 !important; }
body:has(.kcf20-preview-root .kcf20-pest) { background:#0d0d0c; }
body:has(.kcf20-preview-root .kcf20-terroir) { background:#f7f1e6; }
'''
 scripts=(ROOT/'wordpress/themes/kevcoachfit/theme.js').read_text()+'\n'+(ROOT/'wordpress/themes/kevcoachfit/map.js').read_text()
 scripts+='\n'+(ROOT/'wordpress/themes/kevcoachfit/editorial-motion.js').read_text()
 scripts=scripts.replace('kcf-','kcf20-')
 scripts+='''
// Drafts are visual previews; never call the old public workflow endpoints.
document.querySelectorAll('.kcf20-preview-root form').forEach(form=>{
 form.removeAttribute('action');
 form.addEventListener('submit',event=>{event.preventDefault();event.stopImmediatePropagation();
 const result=form.querySelector('.kcf20-form-result');
 if(result){result.textContent='Démonstration : aucune demande n’a été envoyée. Contactez Kévin pour une réservation réelle.';result.focus();}
 },true);
});
document.querySelectorAll('[data-kcf20-management]').forEach(el=>el.textContent='Démonstration : aucune inscription réelle à afficher.');
document.querySelectorAll('[data-order-total]').forEach(total=>{const form=total.closest('form');form.addEventListener('input',()=>{total.textContent=new Intl.NumberFormat('fr-FR',{style:'currency',currency:'EUR'}).format([...form.querySelectorAll('[data-unit-cents]')].reduce((sum,input)=>sum+(Number(input.value)||0)*Number(input.dataset.unitCents),0)/100);});});
'''
 return fragments,styles,scripts

def make_content(slug,page_ids,media):
 fragments,styles,scripts=load_sources();parts=fragments['pages'][slug];body=parts['body']
 # Preserve native Gutenberg headings, paragraphs, tables, details and image blocks.
 body=re.sub(r'<!-- wp:shortcode -->\s*(\[kcf_[^\]]+\])\s*<!-- /wp:shortcode -->',lambda m:'<!-- wp:html -->'+fragments['shortcodes'][m[1]]+'<!-- /wp:html -->',body)
 body=re.sub(r'\[kcf_[^\]]+\]',lambda m:fragments['shortcodes'][m[0]],body)
 content='<!-- KCF_IMPORT_20_PORTABLE -->\n<!-- wp:html --><style id="kcf20-preview-css">'+styles+'</style><!-- /wp:html -->\n'
 content+='<!-- wp:group {"align":"full","className":"kcf20-preview-root","layout":{"type":"default"}} --><div class="wp-block-group alignfull kcf20-preview-root">'
 content+='<!-- wp:html -->'+parts['header']+'<!-- /wp:html -->'+body+'<!-- wp:html -->'+parts['footer']+'<!-- /wp:html -->'
 # WordPress convert_chars can entity-encode ampersands in raw JS. Transport the
 # same known preview runtime as UTF-8 base64, then insert a normal script element.
 encoded=base64.b64encode(scripts.encode()).decode()
 loader='const runtime=document.createElement("script");runtime.id="kcf20-preview-runtime";runtime.textContent=new TextDecoder("utf-8").decode(Uint8Array.from(atob("'+encoded+'"),c=>c.charCodeAt(0)));document.currentScript.after(runtime);'
 content+='</div><!-- /wp:group -->\n<!-- wp:html --><script id="kcf20-preview-effects">'+loader+'</script><!-- /wp:html -->' 
 # Only change the original kcf- prefix once; root is already named kcf20-.
 content=content.replace('kcf-','kcf20-')
 # Date suggestions from the local demo are display-only and carry no live endpoint.
 def asset(match):
  path=match[1]
  if path not in media:raise ValueError('Unmapped media: '+path)
  return media[path]['url']
 content=re.sub(r'(?:http://127\.0\.0\.1:8080)?/wp-content/themes/kevcoachfit/(assets/[^"<>\s]+)',asset,content)
 def link(match):
  raw=html.unescape(match[1])
  if not raw or raw.startswith('#'):return match[0]
  url=urllib.parse.urlsplit(urllib.parse.urljoin('https://kevcoachfit.com/',raw))
  if url.hostname in ['kevcoachfit.com','127.0.0.1'] and url.scheme in ['https','http']:
   target=url.path.strip('/') or 'accueil'
   if target in page_ids:
    dest='https://kevcoachfit.com/?page_id='+str(page_ids[target])+'&preview=true'+('#'+url.fragment if url.fragment else '')
    return 'href="'+html.escape(dest,quote=True)+'"'
  return match[0]
 content=re.sub(r'href="([^"]+)"',link,content)
 assert '127.0.0.1' not in content and not re.search(r'\[kcf_',content)
 assert 'data-kcf-form=' not in content and 'kcfApp.endpoint' not in content
 return content

def prepare():
 paths=set()
 for page in (ROOT/'wordpress/plugins/kevcoachfit-workflows/starter-pages').glob('*.html'):
  paths.update(re.findall(r'/wp-content/themes/kevcoachfit/(assets/[^"<>\s]+)',page.read_text()))
 media={p:{'url':'https://kevcoachfit.com/wp-content/themes/kevcoachfit/'+p} for p in paths}
 ids={slug:100000+i for i,slug in enumerate(LABELS)}
 for slug in LABELS:
  content=make_content(slug,ids,media);(WORK/(slug+'-portable.html')).write_text(content);(WORK/(slug+'-portable.html')).chmod(0o644)
 save('asset-paths.json',sorted(paths))
 print('Portable editable sources prepared: 7 pages, '+str(len(paths))+' media files; no remote changes.')

def transfer():
 assert (WORK/'account-before.json').exists() and (WORK/'pages-before.json').exists(),'Read-only backup missing'
 manifest=json.loads((WORK/'manifest.json').read_text()) if (WORK/'manifest.json').exists() else {'version':'20','pages':{},'media':{}}
 # Make resumable uploads and preserve source assets from the public theme directory.
 for path in json.loads((WORK/'asset-paths.json').read_text()):
  if path in manifest['media']:continue
  file=ROOT/'wordpress/themes/kevcoachfit'/path;digest=hashlib.sha256(file.read_bytes()).hexdigest()[:12]
  filename='kcf-maquette20-'+digest+'-'+file.name
  existing=api('/wp-json/wp/v2/media?search='+urllib.parse.quote('kcf-maquette20-'+digest)+'&context=edit&per_page=10')
  matches=[x for x in existing if filename in x['source_url'] or ('kcf-maquette20-'+digest) in x.get('slug','')]
  if matches:result=matches[0]
  else:
   result=api('/wp-json/wp/v2/media','POST',file.read_bytes(),{'Content-Type':mimetypes.guess_type(file.name)[0] or 'application/octet-stream','Content-Disposition':'attachment; filename="'+filename+'"'})
  manifest['media'][path]={'id':result['id'],'url':result['source_url'],'sha256':hashlib.sha256(file.read_bytes()).hexdigest()}
  save('manifest.json',manifest);print('Media ready: '+file.name,flush=True)
 # Never update a pre-existing page: these seven drafts are owned by this import manifest.
 old_ids={x['id'] for x in json.loads((WORK/'pages-before.json').read_text())}
 for slug,title in LABELS.items():
  if slug in manifest['pages']:continue
  existing=api('/wp-json/wp/v2/pages?context=edit&status=publish,draft,pending,private,future&slug=maquette-20-'+slug)
  assert not existing,'A page with the import slug already exists outside the manifest; review before continuing.'
  result=api('/wp-json/wp/v2/pages','POST',{'status':'draft','title':title+' — Maquette 20','slug':'maquette-20-'+slug,'content':'<!-- KCF_IMPORT_20_PORTABLE --><p>Préparation de la maquette privée.</p>','comment_status':'closed','ping_status':'closed'})
  assert result['status']=='draft' and result['id'] not in old_ids
  manifest['pages'][slug]={'id':result['id'],'status':'draft','completed':False}
  save('manifest.json',manifest);print('Draft created: '+slug,flush=True)
 ids={s:p['id'] for s,p in manifest['pages'].items()}
 for slug,page in manifest['pages'].items():
  assert page['id'] not in old_ids
  content=make_content(slug,ids,manifest['media'])
  current=api('/wp-json/wp/v2/pages/'+str(page['id'])+'?context=edit')
  assert current['status']=='draft','Imported page no longer a draft; refusing update.'
  if canonical_blocks(current['content']['raw'])!=canonical_blocks(content):
   assert current['content']['raw'].startswith('<!-- KCF_IMPORT_20_PORTABLE -->'),'Import marker missing; refusing update.'
   assert not page.get('completed'),'Completed draft may have user edits; refusing to overwrite.'
   result=api('/wp-json/wp/v2/pages/'+str(page['id']),'POST',{'status':'draft','content':content})
  else:result=current
  assert '<script id="kcf20-preview-effects">' in result['content']['raw'],'Preview script was filtered by WordPress'
  assert '<style id="kcf20-preview-css">' in result['content']['raw'],'Preview styles were filtered by WordPress'
  assert result['status']=='draft'
  assert canonical_blocks(result['content']['raw'])==canonical_blocks(content),'Saved content changed beyond Gutenberg attribute formatting'
  save(slug+'-after.json',result)
  page.update(completed=True,sha256=hashlib.sha256(result['content']['raw'].encode()).hexdigest(),source_sha256=hashlib.sha256(content.encode()).hexdigest(),edit_url='https://kevcoachfit.com/wp-admin/post.php?post='+str(page['id'])+'&action=edit',preview_url='https://kevcoachfit.com/?page_id='+str(page['id'])+'&preview=true')
  save('manifest.json',manifest);print('Draft ready: '+slug+' #'+str(page['id']),flush=True)
 print('All seven drafts transferred. No existing page, theme, plugin or site setting changed.')

if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('mode',choices=['prepare','transfer']);args=parser.parse_args()
 prepare() if args.mode=='prepare' else transfer()
