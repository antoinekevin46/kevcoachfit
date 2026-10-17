"""Use Amelia's installed, authenticated MCP interface. No credential values are persisted."""
import os,base64,json,urllib.request,urllib.error
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*a,**k):return None
class AmeliaClient:
 def __init__(self):
  self.session=None;self.counter=0;self.opener=urllib.request.build_opener(NoRedirect)
 def rpc(self,method,params=None,notification=False):
  self.counter+=1;body={'jsonrpc':'2.0','method':method,'params':params or {}}
  if not notification:body['id']=self.counter
  pair=os.environ['KEVCOACHFIT_WP_USERNAME'].strip()+':'+os.environ['KEVCOACHFIT_WP_APPLICATION_PASSWORD'].strip()
  headers={'Authorization':'Basic '+base64.b64encode(pair.encode()).decode(),'Content-Type':'application/json','Accept':'application/json, text/event-stream','MCP-Protocol-Version':'2025-06-18'}
  if self.session:headers['Mcp-Session-Id']=self.session
  req=urllib.request.Request('https://kevcoachfit.com/wp-json/mcp/amelia-mcp-server',data=json.dumps(body).encode(),headers=headers,method='POST')
  try:
   with self.opener.open(req,timeout=60) as response:
    self.session=response.headers.get('Mcp-Session-Id') or self.session
    raw=response.read().decode()
    if not raw:return {}
    if raw.lstrip().startswith('data:') or raw.lstrip().startswith('event:'):
     messages=[json.loads(line[5:].strip()) for line in raw.splitlines() if line.startswith('data:')]
     return messages[-1] if messages else {}
    return json.loads(raw)
  except urllib.error.HTTPError as e:
   raw=e.read().decode()
   try:d=json.loads(raw);error=d.get('error',{});message=error.get('message') if isinstance(error,dict) else error
   except Exception:message='non JSON response'
   raise RuntimeError('MCP HTTP '+str(e.code)+': '+str(message)) from None
 def initialize(self):
  result=self.rpc('initialize',{'protocolVersion':'2025-06-18','capabilities':{},'clientInfo':{'name':'KevCoachFit setup','version':'1.0'}})
  assert 'result' in result,'MCP initialization failed'
  self.rpc('notifications/initialized',notification=True)
 def tools(self):return self.rpc('tools/list')
 def call(self,name,arguments):return self.rpc('tools/call',{'name':name,'arguments':arguments})
if __name__=='__main__':
 from pathlib import Path
 c=AmeliaClient();c.initialize();result=c.tools();p=Path(__file__).resolve().parent.parent/'artifacts/amelia-tools.json';p.write_text(json.dumps(result,ensure_ascii=False));p.chmod(0o600)
 print(json.dumps({'tools':[{'name':t['name'],'description':t.get('description','')[:110]} for t in result.get('result',{}).get('tools',[])]},ensure_ascii=False))
