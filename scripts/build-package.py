"""Reproducible installable package; never includes local state or credentials."""
from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED
import re,hashlib,json
root=Path(__file__).resolve().parent.parent
out=root/'artifacts';out.mkdir(exist_ok=True)
theme=root/'wordpress/themes/kevcoachfit';plugin=root/'wordpress/plugins/kevcoachfit-workflows'
version=re.search(r'^Version:\s*(.*)$',(theme/'style.css').read_text(),re.M).group(1)
theme_zip=out/'kevcoachfit-theme-19.zip'
with ZipFile(theme_zip,'w',ZIP_DEFLATED) as z:
 for f in sorted(theme.rglob('*')):
  if f.is_file():z.write(f,Path('kevcoachfit')/f.relative_to(theme))
bundle=plugin/'bundled-theme';bundle.mkdir(exist_ok=True)
content=theme_zip.read_bytes();(bundle/'theme.zip').write_bytes(content)
(bundle/'manifest.json').write_text(json.dumps({'version':version,'sha256':hashlib.sha256(content).hexdigest()})+'\n')
pack=out/'kevcoachfit-pack-19.zip'
with ZipFile(pack,'w',ZIP_DEFLATED) as z:
 for f in sorted(plugin.rglob('*')):
  if f.is_file():z.write(f,Path('kevcoachfit-workflows')/f.relative_to(plugin))
for p in [theme_zip,pack]:
 p.chmod(0o644)
 with ZipFile(p) as z:
  assert z.testzip() is None
  assert not any(any(bad in Path(n).parts for bad in ['.env','.git','runtime']) for n in z.namelist())
 print(p.name,p.stat().st_size,'bytes; archive intacte')
