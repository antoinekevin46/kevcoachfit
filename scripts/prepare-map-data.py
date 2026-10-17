"""Build a local, simplified map from the public French administrative API."""
from pathlib import Path
from datetime import date
import json,urllib.request,math,hashlib
ROOT=Path(__file__).resolve().parent.parent
urls={'centres':'https://geo.api.gouv.fr/communes?codeDepartement=46&fields=nom,code,centre&format=json','contours':'https://geo.api.gouv.fr/communes?codeDepartement=46&fields=nom,code,centre,contour&format=geojson&geometry=contour'}
names=['Loupiac','Payrac','Calès','Saint-Projet','Souillac','Gourdon','Fajoles','Le Vigan-en-Quercy','Lanzac','Lamothe-Fénelon','Reilhaguet','Lacave','Pinsac','Masclat','Le Roc','Nadaillac-de-Rouge','Rouffilhac','Milhac','Anglars-Nozac','Saint-Cirq-Souillaguet']
def rdp(points,eps=.00018):
 if len(points)<3:return points
 a,b=points[0],points[-1];dx,dy=b[0]-a[0],b[1]-a[1];den=math.hypot(dx,dy)
 distances=[abs(dy*p[0]-dx*p[1]+b[0]*a[1]-b[1]*a[0])/den if den else math.dist(a,p) for p in points]
 index=max(range(len(points)),key=distances.__getitem__)
 if distances[index]>eps:return rdp(points[:index+1],eps)[:-1]+rdp(points[index:],eps)
 return [a,b]
raw={}
for name,url in urls.items():
 with urllib.request.urlopen(url,timeout=30) as r:raw[name]=r.read()
centres=json.loads(raw['centres']);features=json.loads(raw['contours'])['features'];lookup={x['properties']['code']:x for x in features}
towns=[]
for c in centres:
 if c['nom'] not in names:continue
 f=lookup[c['code']];geom=f['geometry'];rings=geom['coordinates'] if geom['type']=='Polygon' else [ring for poly in geom['coordinates'] for ring in poly]
 rings=[[[round(x,5),round(y,5)] for x,y in rdp(ring)] for ring in rings]
 lon,lat=c['centre']['coordinates']
 towns.append({'code':c['code'],'name':c['nom'],'label':'Le Vigan' if c['nom']=='Le Vigan-en-Quercy' else c['nom'],'lon':lon,'lat':lat,'rings':rings})
assert len(towns)==20
out={'source':'API Découpage administratif — geo.api.gouv.fr','retrieved':date.today().isoformat(),'urls':urls,'source_sha256':{k:hashlib.sha256(v).hexdigest() for k,v in raw.items()},'note':'Centres communaux, pas adresses des salles. Contours administratifs simplifiés, pas limites de temps de trajet.','towns':sorted(towns,key=lambda t:t['name'])}
p=ROOT/'wordpress/themes/kevcoachfit/data/communes-lot.json';p.write_text(json.dumps(out,ensure_ascii=False,separators=(',',':'))+'\n')
print('Carte locale :',len(towns),'communes vérifiées,',p.stat().st_size,'octets.')
