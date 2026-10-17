"""Bind functional shortcodes after the owner installs the booking extension.

Requires explicit authorization. Refuses missing blocks and preserves all other remote edits.
"""
from pathlib import Path
import base64,hashlib,json,re,runpy
ROOT=Path(__file__).resolve().parent.parent
module=runpy.run_path(str(ROOT/'scripts/wordpress-draft-transfer.py'));api=module['api']
def bind():
 health=api('/wp-json/kcfr/v1/health')
 assert health['version']=='1.0.0' and not health['live'],'Install extension in private mode first.'
 backup=ROOT/'artifacts/reservations-binding';backup.mkdir(exist_ok=True)
 for id,expected in [(68,{'book','request'}),(69,{'manage'})]:
  post=api('/wp-json/wp/v2/pages/'+str(id)+'?context=edit');assert post['status']=='draft'
  source=post['content']['raw'];found=set()
  def replace_block(match):
   block=match[1]
   if 'data-kcf20-form="book"' in block:found.add('book');return '<!-- wp:shortcode -->[kcfr_booking]<!-- /wp:shortcode -->'
   if 'data-kcf20-form="request"' in block and 'name="kind" value="coaching"' in block:found.add('request');return '<!-- wp:shortcode -->[kcfr_coaching]<!-- /wp:shortcode -->'
   if re.search(r'<section\b[^>]*\bdata-kcf20-management(?:\s|=|>)',block):found.add('manage');return '<!-- wp:shortcode -->[kcfr_manage]<!-- /wp:shortcode -->'
   if 'id="kcf20-preview-effects"' in block:
    encoded=re.search(r'atob\("([A-Za-z0-9+/=]+)"\)',block);assert encoded,'Preview runtime transport changed; review required.'
    runtime=base64.b64decode(encoded[1]).decode();runtime=runtime.replace(".kcf20-preview-root form'", ".kcf20-preview-root form[data-kcf20-form]'")
    return '<!-- wp:html -->'+block.replace(encoded[1],base64.b64encode(runtime.encode()).decode())+'<!-- /wp:html -->'
   return match[0]
  content=re.sub(r'<!-- wp:html -->(.*?)<!-- /wp:html -->',replace_block,source,flags=re.S)
  if not found and all(tag in source for tag in (['[kcfr_booking]','[kcfr_coaching]'] if id==68 else ['[kcfr_manage]'])):continue
  assert found==expected,'A demonstration block was changed; review before editing.'
  path=backup/(str(id)+'-before.json');path.write_text(json.dumps(post,ensure_ascii=False));path.chmod(0o600)
  # Re-read immediately before writing to avoid overwriting concurrent editor changes.
  check=api('/wp-json/wp/v2/pages/'+str(id)+'?context=edit');assert check['content']['raw']==source,'Page was edited during preparation.'
  result=api('/wp-json/wp/v2/pages/'+str(id),'POST',{'status':'draft','content':content});assert result['status']=='draft'
  print('Bound private functional forms on draft '+str(id))
 result=api('/wp-json/kcfr/v1/settings','POST',{'booking_page_id':68,'manage_page_id':69,'live':False});assert not result['live']
 print('Existing public pages and their publication states unchanged.')
if __name__=='__main__':bind()
