"""Export the photo checklist as a self-contained PDF for use outside ChatGPT."""
import asyncio,re,html
from pathlib import Path
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent.parent
source=(ROOT/'docs/photos-a-faire-vitalivan.md').read_text()
lines=source.splitlines();parts=[];in_list=False;i=0
while i<len(lines):
 line=lines[i].strip()
 if not line:
  if in_list:parts.append('</ul>');in_list=False
  i+=1;continue
 if line.startswith('|'):
  rows=[]
  while i<len(lines) and lines[i].strip().startswith('|'):
   cells=[c.strip() for c in lines[i].strip().strip('|').split('|')]
   if not all(re.fullmatch(r':?-+:?',c) for c in cells):rows.append(cells)
   i+=1
  parts.append('<table><thead><tr>'+''.join('<th>'+html.escape(c)+'</th>' for c in rows[0])+'</tr></thead><tbody>')
  for row in rows[1:]:parts.append('<tr>'+''.join('<td>'+html.escape(c)+'</td>' for c in row)+'</tr>')
  parts.append('</tbody></table>');continue
 if line.startswith('# '):parts.append('<p class="brand">VITALI’VAN</p><h1>'+html.escape(line[2:])+'</h1>')
 elif line.startswith('## '):
  cls=' class="next-page"' if 'Photos utiles en complément' in line else ''
  parts.append('<h2'+cls+'>'+html.escape(line[3:])+'</h2>')
 elif line.startswith('- '):
  if not in_list:parts.append('<ul>');in_list=True
  parts.append('<li>'+html.escape(line[2:])+'</li>')
 else:parts.append('<p>'+html.escape(line)+'</p>')
 i+=1
if in_list:parts.append('</ul>')
css='''@page{size:A4;margin:14mm 15mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;font-size:10.5pt;line-height:1.43;color:#17251c;margin:0}h1{font-size:25pt;line-height:1.15;margin:0 0 6mm;letter-spacing:-.6pt}h2{font-size:15pt;margin:7mm 0 4mm;line-height:1.2;break-after:avoid}.brand{color:#2e603e;font-size:10pt;font-weight:bold;letter-spacing:2pt;margin:0 0 3mm}p{margin:0 0 4mm}table{width:100%;border-collapse:collapse;font-size:9.4pt;line-height:1.35;margin-top:4mm}th{background:#173b2a;color:#fff;text-align:left;padding:3mm}td{border-bottom:1px solid #d0d9ce;vertical-align:top;padding:2.7mm 2.5mm}td:first-child{width:24%;font-weight:bold}td:last-child{width:24%;color:#415343}tr:nth-child(even) td{background:#f0f5ed}tr{break-inside:avoid}ul{padding-left:5mm;margin:0 0 5mm}li{padding-left:1mm;margin-bottom:2.8mm;break-inside:avoid}h2.next-page{break-before:page;margin-top:0}'''
content='<!doctype html><html lang="fr"><meta charset="utf-8"><title>Vitali’Van — Photos à faire</title><style>'+css+'</style><body>'+''.join(parts)+'</body></html>'
(ROOT/'docs/photos-a-faire-vitalivan.html').write_text(content)
async def main():
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
  page=await browser.new_page();await page.set_content(content)
  assert await page.locator('tbody tr').count()==10
  pdf=ROOT/'artifacts/liste-photos-vitalivan.pdf'
  await page.pdf(path=str(pdf),print_background=True,prefer_css_page_size=True,tagged=True)
  await browser.close()
  assert pdf.read_bytes().startswith(b'%PDF')
  pdf.chmod(0o644)
  count=len(re.findall(rb'/Type\s*/Page\b',pdf.read_bytes()))
  assert count==2,('unexpected PDF page count',count)
  print('PDF créé et vérifié :',count,'pages, 10 sujets prioritaires, conseils et compléments conservés.')
asyncio.run(main())
