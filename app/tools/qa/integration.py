"""EASY3 integration scenarios against an isolated test deployment. Python + requests.
Run: python tools/qa/integration.py http://127.0.0.1:8099
Creates synthetic demo tenants and one registered account; never run on production data.
"""
import sys,json,uuid,datetime,pathlib,collections
import requests
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8099'
OUT=pathlib.Path(sys.argv[2] if len(sys.argv)>2 else 'tools/qa/output');OUT.mkdir(parents=True,exist_ok=True)
checks=[]
def record(name,condition=True):
 assert condition,name
 checks.append(name)
 print('OK',name,flush=True)
class Client:
 def __init__(self):
  self.s=requests.Session();self.s.trust_env=False;self.csrf=self.get('bootstrap')['csrf']
 def get(self,action):
  r=self.s.get(BASE+'/index.php',params={'api':action},timeout=20);d=r.json();assert r.status_code==200,(action,r.status_code,d)
  return d['data']
 def post(self,action,data=None,status=200,token=True):
  r=self.s.post(BASE+'/index.php',params={'api':action},json=data or {},headers={'X-CSRF-Token':self.csrf} if token else {},timeout=30)
  d=r.json();assert r.status_code==status,(action,r.status_code,d)
  if status==200:
   assert d['ok'];out=d['data'];self.csrf=out.get('csrf',self.csrf);return out
  assert not d['ok'];return d
 def state(self):return self.get('state')
 def role(self,role):return self.post('demo_role',{'role':role})
def k():return str(uuid.uuid4())
a,b=Client(),Client()
a.post('demo_enter',token=False,status=419);record('CSRF отклоняет запрос без токена')
x=a.post('demo_enter');y=b.post('demo_enter');record('Демо создаёт отдельные компании',x['company']['id']!=y['company']['id'])
record('Учебный план опубликован',len(x['tasks'])==18 and x['plans'][0]['state']=='published')
record('Очередь доработки доступна',sum(t['rework'] for t in x['tasks'])==2)
for path in ['assets/js/app.js','assets/js/loop.js','assets/js/builder.js','assets/vendor/three-kit.js','assets/css/app.css','assets/media/alpine.webp','assets/media/logo.png']:
 r=a.s.get(BASE+'/'+path);assert r.status_code==200,path
record('Локальные ресурсы доставляются по HTTP')
bad=a.post('order_archive',{'id':y['orders'][0]['id'],'revision':1,'key':k()},status=404);record('Чужая компания недоступна по идентификатору')
w=a.role('worker');record('Экономика не выдаётся работнику',w['economy']=={} and 'economy' not in w['company']['passport'] and all('price_cents' not in o for o in w['orders']))
record('Работник получает только собственные задания и людей',all(t['worker_id']=='p-sew-1' for t in w['tasks']) and len(w['company']['passport']['people'])==1)
a.post('plan_calculate',{'key':k()},status=403);record('Работник не рассчитывает портфель')
a.post('production_event',{'key':k(),'task_id':next(t['id'] for t in x['tasks'] if t['worker_id']!='p-sew-1'),'revision':1,'kind':'start'},status=403)
record('Чужое задание работника защищено серверными правами')
a.role('owner');today=datetime.date.today();goal={'id':'','title':'QA · тестовая партия','product':'Учебное изделие','customer':'Тестовый заказчик','mode':'CONTRACT','quantity':20,'deadline':str(today+datetime.timedelta(days=35)),'start_date':str(today),'price_cents':100000,'price_basis':'unit','target_cents':None,'material_owner':'customer','expenses':[{'id':'qa-exp','name':'Учебная подготовка','cents':10000}],'key':k()}
g=a.post('order_save',goal);gid=g['id'];record('Цель создаётся и сохраняется в БД',any(o['id']==gid for o in a.state()['orders']))
cycle=[{'id':'a','name':'A','type':'cut','seconds':30,'setup_minutes':0,'source':'QA','batch':10,'dependencies':['b']},{'id':'b','name':'B','type':'pack','seconds':30,'setup_minutes':0,'source':'QA','batch':10,'dependencies':['a']}]
a.post('route_save',{'key':k(),'order_id':gid,'revision':1,'route':cycle},status=422);record('Циклическая технология отклоняется')
route=[{'id':'qa-cut','name':'QA Раскрой','type':'cut','seconds':30,'setup_minutes':0,'source':'Учебная QA-норма','batch':10,'dependencies':[]},{'id':'qa-sew','name':'QA Пошив','type':'sew','seconds':None,'setup_minutes':0,'source':'Учебная QA-норма','batch':10,'dependencies':['qa-cut']},{'id':'qa-pack','name':'QA Упаковка','type':'pack','seconds':30,'setup_minutes':0,'source':'Учебная QA-норма','batch':10,'dependencies':['qa-sew']}]
a.post('route_save',{'key':k(),'order_id':gid,'revision':1,'route':route});incomplete=a.post('plan_calculate',{'key':k(),'strategy':'deadline'});record('Неизвестная норма даёт неполный расчёт',incomplete['status']=='incomplete')
a.post('plan_approve',{'id':incomplete['id'],'stage':'foreman','key':k()},status=422);record('Неполный план не согласуется')
route[1]['seconds']=30;a.post('route_save',{'key':k(),'order_id':gid,'revision':2,'route':route});result=a.post('plan_calculate',{'key':k(),'strategy':'deadline'});pid=result['id'];record('Совместный календарь рассчитан',result['status']=='feasible')
c=a.state()['company'];a.post('layout_save',{'key':k(),'revision':c['revision'],'layout':c['layout'],'new_resources':[]})
a.post('plan_approve',{'id':pid,'stage':'foreman','key':k()},status=409);record('Изменившаяся основа сбрасывает возможность согласования')
a.post('layout_save',{'key':k(),'revision':c['revision'],'layout':c['layout'],'new_resources':[]},status=409);record('Параллельная ревизия защищена от перезаписи')
result=a.post('plan_calculate',{'key':k(),'strategy':'deadline'});pid=result['id'];state=a.state();plan=next(p for p in state['plans'] if p['id']==pid);snapshot=plan['snapshot'];resources={r['id']:r for r in state['company']['passport']['resources']};people={p['id']:p for p in state['company']['passport']['people']}
work=[]
for t in snapshot['schedule']:
 if t['pinned']:continue
 op=next(op for op in snapshot['routes'][t['order_id']] if op['id']==t['operation_id']);assert resources[t['resource_id']]['type']==op['type'];assert op['type'] in people[t['worker_id']]['skills']
 seconds=0
 for start,end in t['slots']:
  s=datetime.datetime.fromisoformat(start);e=datetime.datetime.fromisoformat(end);assert s.isoweekday() in [1,2,3,4,5];assert (s.hour>=9 and e.hour<=18);assert not (s.hour<14 and e.hour>13);seconds+=(e-s).total_seconds();work.append((t['resource_id'],t['worker_id'],s,e))
 assert seconds==t['seconds']
for i,a_slot in enumerate(work):
 for b_slot in work[:i]:
  if a_slot[0]==b_slot[0] or a_slot[1]==b_slot[1]:assert a_slot[3]<=b_slot[2] or b_slot[3]<=a_slot[2]
record('Интервалы учитывают навыки, перерывы и неперекрытие общих людей/центров')
a.role('technologist');a.post('plan_approve',{'id':pid,'stage':'foreman','key':k()},status=403);record('Технолог не согласует этап мастера')
a.role('foreman');a.post('plan_approve',{'id':pid,'stage':'foreman','key':k()});a.role('technologist');a.post('plan_approve',{'id':pid,'stage':'technologist','key':k()});a.post('plan_publish',{'id':pid,'key':k()},status=403);record('Технолог не публикует задания')
a.role('chief');a.post('plan_approve',{'id':pid,'stage':'chief','key':k()});a.post('plan_publish',{'id':pid,'key':k()});record('Три роли согласуют и публикуют одну версию',next(p for p in a.state()['plans'] if p['id']==pid)['state']=='published')
a.role('owner');a.post('plan_approve',{'id':pid,'stage':'foreman','key':k()},status=422);record('Опубликованный снимок не редактируется')
def task(op):return next(t for t in a.state()['tasks'] if t['order_id']==gid and t['operation_id']==op)
def event(op,kind,extra=None,status=200,key_value=None):
 t=task(op);return a.post('production_event',{'key':key_value or k(),'task_id':t['id'],'revision':t['revision'],'kind':kind,**(extra or {})},status=status)
event('qa-cut','release',{'quantity':1},422);record('Выпуск до начала работы отклоняется')
t=task('qa-cut');start_body={'key':k(),'task_id':t['id'],'revision':t['revision'],'kind':'start'};first=a.post('production_event',start_body);again=a.post('production_event',start_body);record('Повтор команды не создаёт второй факт',first['event_id']==again['event_id'])
a.post('production_event',{**start_body,'kind':'pause','reason':'QA'},status=409);record('Тот же ключ с другим содержимым даёт конфликт')
event('qa-cut','release',{'quantity':21},422);record('Превышение остатка выпуска отклоняется')
event('qa-cut','release',{'quantity':20});event('qa-cut','transfer',{'quantity':21},422);event('qa-cut','transfer',{'quantity':20});record('Выпуск и передача сохраняют отдельные количества',task('qa-cut')['state']=='completed')
for op in ['qa-sew','qa-pack']:
 event(op,'start');event(op,'release',{'quantity':20});event(op,'transfer',{'quantity':20})
record('Переданная партия проходит технологические зависимости')
event('qa-pack','quality',{'quantity':20,'accepted':19,'rework':2,'scrap':0,'reason':'QA'},422);record('Несходящийся баланс ОТК отклоняется')
event('qa-pack','quality',{'quantity':20,'accepted':18,'rework':2,'scrap':0,'reason':'Учебный дефект'});record('ОТК разделяет годное и доработку',task('qa-pack')['accepted']==18 and task('qa-pack')['rework']==2)
event('qa-pack','quality',{'quantity':1,'accepted':1,'rework':0,'scrap':0,'reason':''},422);record('Повторная первичная приёмка не удваивает выпуск')
event('qa-pack','rework_release',{'quantity':3,'reason':'QA'},422);event('qa-pack','rework_release',{'quantity':2,'reason':'Строчка исправлена'});event('qa-pack','rework_quality',{'quantity':2,'accepted':2,'rework':0,'scrap':0,'reason':''});final=task('qa-pack');record('Доработка гасится без повторного начисления выпуска',final['accepted']==20 and final['released']==20 and final['rework']==0 and final['state']=='completed')
state=a.state();record('Новые факты отмечают прогноз устаревшим',state['forecast_stale'])
a.role('qc');t=next(t for t in state['tasks'] if t['state']=='ready');a.post('production_event',{'key':k(),'task_id':t['id'],'revision':t['revision'],'kind':'start'},status=403);record('ОТК не записывает начало чужой работы')
a.role('owner');state=a.state();fixture_path=OUT/'state.json';fixture_path.parent.mkdir(parents=True,exist_ok=True);fixture_path.write_text(json.dumps(state,ensure_ascii=False));record('Состояние сохранено для проверки представлений')
registered=Client();email='qa-'+uuid.uuid4().hex+'@example.test';reg=registered.post('register',{'email':email,'password':'Qa_demo_2026!','name':'QA','company':'QA пустая компания'});record('Регистрация не требует полного паспорта',not reg['orders'] and not reg['company']['passport']['people'])
registered.post('logout');registered.post('login',{'email':email,'password':'Qa_demo_2026!'});record('Пароль хешируется и позволяет повторный вход')
registered.post('demo_role',{'role':'owner'},status=403);record('Обычный аккаунт не переключает полномочия')
report={'passed':len(checks),'checks':checks,'database':'MariaDB 10.11.14','runtime':'PHP 8.3.6','browser_visual_qa':False};(OUT/'integration.json').write_text(json.dumps(report,ensure_ascii=False,indent=2));print(json.dumps({'passed':len(checks)},ensure_ascii=False),flush=True)
