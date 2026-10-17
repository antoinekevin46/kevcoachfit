"""Prepare isolated activity previews. --transfer creates private drafts only; no DNS or public changes."""
import argparse,json,re,runpy,urllib.parse,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
OUT=ROOT/'artifacts/independent-sites-26'
SITES={
 'sport':{'source':36,'title':'Vitali’Van — futur site autonome · aperçu','path':'vitalivan','contact':'reservation','links':[('Programmes','programmes'),('Planning','planning'),('Tarifs','tarifs'),('Contact et RDV','reservation')]},
 'ak':{'source':41,'title':'A.K Nuisibles — futur site autonome · aperçu','path':'ak-anti-nuisible','contact':'contact-ak','links':[('Prestations','prestations-ak'),('Essaims d’abeilles','essaims-ak'),('Secteur et contact','contact-ak'),('Devis gratuit','demarche-ak')]},
 'terroir':{'source':37,'title':'Miel et vin — futur site autonome · aperçu','path':'terroir-loupiac','contact':'retrait-terroir','links':[('Les miels','miels-de-kevin'),('Vins et spiritueux','maison-loupiac'),('Retrait et contacts','retrait-terroir')]}
}
def isolate(content,slug):
 cfg=SITES[slug]
 # Keep local and social contacts; remove the cross-business directory in the footer.
 content,n=re.subn(r'<details class="kcf20-other-spaces">.*?</details>','',content,count=1,flags=re.S)
 assert n==1,'Cross-activity directory changed; review source first.'
 links=''.join('<li class="wp-block-navigation-item"><a class="wp-block-navigation-item__content" href="#'+anchor+'">'+label+'</a></li>' for label,anchor in cfg['links'])
 content,n=re.subn(r'(<nav\b[^>]*><ul class="wp-block-navigation__container">).*?(</ul></nav>)',lambda m:m[1]+links+m[2],content,count=1,flags=re.S)
 assert n==1,'Navigation changed; review source first.'
 def href(m):
  url=urllib.parse.urlsplit(m[1].replace('&amp;','&'))
  if url.hostname not in ('kevcoachfit.com','www.kevcoachfit.com'):return m[0]
  if url.path.startswith('/wp-content/'):return m[0] # existing media remain usable in these private previews
  own='/'+cfg['path']+'/'
  if url.path in ('/','',own):target='#'+url.fragment if url.fragment else '#'
  elif slug=='sport' and url.path in ('/reserver/','/contac/','/contact/'):target='#'+cfg['contact']
  else:raise AssertionError('Unexpected cross-activity page link: '+url.path)
  return 'href="'+target+'"'
 content=re.sub(r'href="([^"]+)"',href,content)
 assert '[kcfr_' not in content and 'kcf20-other-spaces' not in re.sub(r'<(style|script)\b[^>]*>.*?</\1>','',content,flags=re.S)
 content=content.replace('<!-- KCF_IMPORT_20_PORTABLE -->','<!-- KCF_INDEPENDENT_PREVIEW_26 '+slug+' -->',1)
 return content

def main():
 parser=argparse.ArgumentParser();parser.add_argument('--transfer',action='store_true');args=parser.parse_args();OUT.mkdir(exist_ok=True)
 for slug,cfg in SITES.items():
  source=json.loads((OUT/(str(cfg['source'])+'-source.json')).read_text())
  output=OUT/(slug+'-prepared.html');output.write_text(isolate(source['content']['raw'],slug));output.chmod(0o644)
 if not args.transfer:print('Three isolated previews prepared; no remote changes.');return
 api=runpy.run_path(str(ROOT/'scripts/wordpress-draft-transfer.py'))['api'];manifest_path=OUT/'draft-manifest.json';manifest=json.loads(manifest_path.read_text()) if manifest_path.exists() else {}
 # Preserve all three public source pages and the site's homepage setting.
 before={cfg['source']:api('/wp-json/wp/v2/pages/'+str(cfg['source'])+'?context=edit') for cfg in SITES.values()};home=api('/wp-json/wp/v2/settings')['page_on_front']
 for ident,current in before.items():
  snapshot=json.loads((OUT/(str(ident)+'-source.json')).read_text());assert current['content']['raw']==snapshot['content']['raw'],'Public source edited since preparation; refresh before creating preview.'
 for slug,cfg in SITES.items():
  if slug in manifest:continue
  existing=api('/wp-json/wp/v2/pages?status=draft&slug=site-autonome-26-'+slug);assert not existing,'Preview slug already exists outside the manifest; review before importing.'
  source=(OUT/(slug+'-prepared.html')).read_text()
  p=api('/wp-json/wp/v2/pages','POST',{'title':cfg['title'],'slug':'site-autonome-26-'+slug,'status':'draft','content':source,'comment_status':'closed','ping_status':'closed'})
  saved=api('/wp-json/wp/v2/pages/'+str(p['id'])+'?context=edit');assert saved['status']=='draft'
  canonical=runpy.run_path(str(ROOT/'scripts/wordpress-draft-transfer.py'))['canonical_blocks'];assert canonical(saved['content']['raw'])==canonical(source)
  manifest[slug]={'id':saved['id'],'preview_url':'https://kevcoachfit.com/?page_id='+str(saved['id'])+'&preview=true','editor_url':'https://kevcoachfit.com/wp-admin/post.php?post='+str(saved['id'])+'&action=edit','status':'draft','source_id':cfg['source']}
  manifest_path.write_text(json.dumps(manifest,ensure_ascii=False,indent=2));print('Private isolated preview:',slug,saved['id'],flush=True)
 for ident,old in before.items():
  after=api('/wp-json/wp/v2/pages/'+str(ident)+'?context=edit');assert after['status']==old['status'] and after['content']['raw']==old['content']['raw']
 assert api('/wp-json/wp/v2/settings')['page_on_front']==home
 (ROOT/'docs/independent-sites-26-previews.json').write_text(json.dumps({'previews':manifest,'public_source_pages_preserved':True,'homepage_preserved':True,'dns_changed':False,'domains_purchased':False,'independent_domains_active':False},ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
