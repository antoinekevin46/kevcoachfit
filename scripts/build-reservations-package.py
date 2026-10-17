"""Build the standalone reservations extension, without theme updates or credential files."""
from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED
import hashlib,json,re
ROOT=Path(__file__).resolve().parent.parent
source=ROOT/'wordpress/plugins/kevcoachfit-reservations'
version=re.search(r'Version:\s*([0-9.]+)',(source/'kevcoachfit-reservations.php').read_text())[1]
out=ROOT/('artifacts/kevcoachfit-reservations-'+version+'.zip');out.parent.mkdir(exist_ok=True)
with ZipFile(out,'w',ZIP_DEFLATED) as z:
 for file in sorted(source.iterdir()):
  if file.is_file() and file.suffix in ['.php','.js','.css']:z.write(file,'kevcoachfit-reservations/'+file.name)
with ZipFile(out) as z:
 assert z.testzip() is None
 assert 'Plugin Name:' in z.read('kevcoachfit-reservations/kevcoachfit-reservations.php').decode()
 assert len(z.namelist())==8
 assert all(not any(x in name for x in ['.env','tokens','local-test','theme.zip']) for name in z.namelist())
copy=ROOT/('docs/kevcoachfit-reservations-'+version+'.zip');copy.write_bytes(out.read_bytes());copy.chmod(0o644);out.chmod(0o644)
(ROOT/'docs/reservations-package.json').write_text(json.dumps({'version':version,'bytes':out.stat().st_size,'sha256':hashlib.sha256(out.read_bytes()).hexdigest(),'files':8,'contains_theme':False,'contains_credentials':False},indent=2)+'\n')
print(out.name,str(out.stat().st_size)+' bytes; valid standalone WordPress plugin archive, no theme or credentials.')
