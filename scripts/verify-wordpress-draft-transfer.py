"""Read-only post-transfer checks. Reports no secrets or private page content."""
import hashlib,json,re,runpy,urllib.request,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
WORK=ROOT/'artifacts/wordpress-import-20'
module=runpy.run_path(str(ROOT/'scripts/wordpress-draft-transfer.py'));api=module['api']
def signature(page):
 return {**{key:page.get(key) for key in ['id','status','slug','title','template','featured_media','modified_gmt']},'raw_content':page.get('content',{}).get('raw')}
def public_response(url):
 try:
  with urllib.request.urlopen(url,timeout=30) as r:return r.status,r.read().decode(errors='replace')
 except urllib.error.HTTPError as e:return e.code,e.read().decode(errors='replace')
def main():
 manifest=json.loads((WORK/'manifest.json').read_text());before=json.loads((WORK/'pages-before.json').read_text())
 assert len(manifest['pages'])==7 and len(manifest['media'])==13
 after=api('/wp-json/wp/v2/pages?context=edit&status=publish,draft,pending,private,future&per_page=100')
 by_id={p['id']:p for p in after}
 for page in before:assert signature(page)==signature(by_id[page['id']]),'Existing page changed: '+str(page['id'])
 settings=api('/wp-json/wp/v2/settings');assert settings==json.loads((WORK/'settings-before.json').read_text()),'Site settings changed'
 themes=api('/wp-json/wp/v2/themes');old=json.loads((WORK/'themes-before.json').read_text())
 theme_sig=lambda a:sorted((t['stylesheet'],t['status'],t['version']) for t in a)
 assert theme_sig(themes)==theme_sig(old),'Theme status or version changed'
 plugins=api('/wp-json/wp/v2/plugins');old=json.loads((WORK/'plugins-before.json').read_text())
 plugin_sig=lambda a:sorted((t['plugin'],t['status'],t['version']) for t in a)
 assert plugin_sig(plugins)==plugin_sig(old),'Plugins changed'
 results=[]
 for slug,entry in manifest['pages'].items():
  page=api('/wp-json/wp/v2/pages/'+str(entry['id'])+'?context=edit');raw=page['content']['raw'];rendered=page['content']['rendered']
  assert page['status']=='draft' and entry['completed']
  assert hashlib.sha256(raw.encode()).hexdigest()==entry['sha256'],'Draft edited since transfer: '+slug
  expected=module['make_content'](slug,{s:e['id'] for s,e in manifest['pages'].items()},manifest['media'])
  assert module['canonical_blocks'](expected)==module['canonical_blocks'](raw),'Draft content differs beyond attribute formatting: '+slug
  assert rendered.count('<h1')==1 and 'kcf20-preview-css' in rendered and 'kcf20-preview-effects' in rendered
  assert '127.0.0.1' not in rendered and '[kcf_' not in rendered and 'Sarnels' not in rendered
  assert 'data-kcf-form=' not in rendered,'Old live form selectors present'
  status,public=public_response(entry['preview_url'])
  assert status in [401,403,404] and 'KCF_IMPORT_20_PORTABLE' not in public,'Draft visible anonymously: '+slug
  (WORK/(slug+'-rendered.html')).write_text(rendered)
  results.append({'page':slug,'id':entry['id'],'status':'draft','source_matches':True,'anonymous_preview_http':status})
 public_html=public_response('https://kevcoachfit.com/')[1]
 styles='\n'.join(re.findall(r'<style[^>]*>.*?</style>|<link[^>]+rel=[\'\"]stylesheet[\'\"][^>]*>',public_html,re.S))
 for slug in manifest['pages']:
  html=(WORK/(slug+'-rendered.html')).read_text()
  doc='<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+styles+'</head><body><div class="wp-site-blocks"><header>Original public header</header><main><div class="wp-block-post-content">'+html+'</div></main><footer>Original public footer</footer></div></body></html>'
  (WORK/(slug+'-server-preview.html')).write_text(doc)
 report={'pages':results,'existing_pages_preserved':len(before),'site_settings_preserved':True,'theme_and_plugins_preserved':True,'media_uploaded':len(manifest['media']),'production_bookings_enabled':False,'validation':'Authenticated REST render plus anonymous preview checks; no authenticated frontend browser session available.'}
 (ROOT/'docs/wordpress-drafts-20-verification.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
 print('7 private drafts verified; '+str(len(before))+' prior pages, settings, theme and plugins preserved.')
if __name__=='__main__':main()
