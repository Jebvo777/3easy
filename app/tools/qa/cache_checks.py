"""Read-only checks against the separate QA server."""
import requests,re,hashlib,pathlib,json,sys
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8099';OUT=pathlib.Path(sys.argv[2] if len(sys.argv)>2 else 'tools/qa/output');OUT.mkdir(parents=True,exist_ok=True)
s=requests.Session();s.trust_env=False;r=s.get(BASE+'/');r.raise_for_status();checks=[]
def ok(label,condition):
 assert condition,label
 checks.append(label);print('OK',label,flush=True)
ok('HTML запрещает смешанный кеш',all(word in r.headers.get('Cache-Control','') for word in ['no-store','no-cache','must-revalidate']))
ok('При новой сборке передана очистка HTTP-кеша',r.headers.get('Clear-Site-Data')=='"cache"')
again=s.get(BASE+'/');ok('Сеанс не очищает cookie/storage и повторно не сбрасывает кеш', 'Clear-Site-Data' not in again.headers and s.cookies)
js=re.search(r'<script type="module" src="(assets/runtime/app\.[a-f0-9]{16}\.js)"',r.text).group(1);css=re.search(r'<link rel="stylesheet" href="(assets/runtime/ui\.[a-f0-9]{16}\.css)"',r.text).group(1)
for p in [js,css]:
 asset=s.get(BASE+'/'+p);asset.raise_for_status();digest=hashlib.sha256(asset.content).hexdigest()[:16];ok('Хеш ресурса соответствует его имени '+p.split('/')[-1],digest in p)
text=s.get(BASE+'/'+js).text;ok('Bundle не импортирует старые отдельные JS-модули',not re.search(r'import\(["\']\.?/?[^"\']+\.js',text))
for p in ['assets/media/alpine.webp','assets/media/logo.png','assets/media/brushed-titanium-v3.webp','assets/media/lakes_1k.hdr']:
 asset=s.get(BASE+'/'+p);ok('Материал доставляется: '+p.split('/')[-1],asset.status_code==200 and len(asset.content)>1000)
(OUT/'cache-http.json').write_text(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2));print(json.dumps({'passed':len(checks)}))
