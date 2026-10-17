"""Exercise actual public page head output locally, then restore private drafts."""
import subprocess,json,re,urllib.request,html
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
cmd=['docker','compose','run','--rm','cli','wp','eval-file','/project/scripts/seo-fixture.php']
checks=[]
def check(test,label):
 assert test,label
 checks.append(label)
pages=json.loads(subprocess.check_output(cmd+['prepare'],cwd=ROOT,text=True))
try:
 titles=[]
 for slug,id in pages.items():
  url='http://127.0.0.1:8080/?page_id='+str(id)
  raw=urllib.request.urlopen(url,timeout=20).read().decode()
  head=raw.split('</head>')[0]
  title=html.unescape(re.search(r'<title>(.*?)</title>',head,re.S)[1]);titles.append(title)
  check(len(re.findall(r'<h1\b',raw))==1,slug+': one H1')
  check(len(re.findall(r'<meta name="description"',head))==1,slug+': unique description')
  check(len(re.findall(r'rel=[\'\"]canonical[\'\"]',head))==1,slug+': native canonical')
  schema=[json.loads(s) for s in re.findall(r'<script type="application/ld\+json">(.*?)</script>',head,re.S)]
  robots=re.search(r'<meta name=[\'\"]robots[\'\"] content=[\'\"]([^\'\"]+)',head)[1]
  if slug in ['reserver','mes-reservations']:
   check('noindex' in robots,slug+': workflow noindex')
   check(not schema,slug+': no public schema on workflow')
  else:
   check('noindex' not in robots,slug+': public page indexable')
   check(len(re.findall(r'property="og:title"',head))==1,slug+': unique social title')
   if slug!='accueil':
    crumb=[s for s in schema if s['@type']=='BreadcrumbList']
    check(len(crumb)==1 and len(crumb[0]['itemListElement'])==2,slug+': coherent breadcrumbs')
   if slug in ['accueil','vitalivan','contact']:
    biz=[s for s in schema if s['@type']=='LocalBusiness']
    check(len(biz)==1 and 'streetAddress' not in biz[0]['address'],slug+': no private street')
   else:check(not any(s['@type']=='LocalBusiness' for s in schema),slug+': no invented business establishment')
   check('aggregateRating' not in head and 'reviewCount' not in head,slug+': no fabricated reviews')
  if slug=='vitalivan':check('janvier–juin' in head,'sports: periods in metadata')
  if slug=='ak-anti-nuisible':check('Activité en préparation' in head,'pest: truthful prelaunch metadata')
 check(len(titles)==len(set(titles)),'all SEO titles unique')
 Path(ROOT/'docs/seo-20-verification.json').write_text(json.dumps({'checks':checks,'passed':len(checks),'target':'local WordPress 6.8.3; no remote site writes'},ensure_ascii=False,indent=2)+'\n')
 print(str(len(checks))+' contrôles SEO sur les vraies réponses HTTP : succès.')
finally:
 subprocess.run(cmd+['restore'],cwd=ROOT,check=True)
