"""Run only against the isolated QA server; covers organizational persistence and workshop isolation."""
import requests,uuid,json,copy,pathlib,sys
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8099';OUT=pathlib.Path(sys.argv[2] if len(sys.argv)>2 else 'tools/qa/output');OUT.mkdir(parents=True,exist_ok=True)
checks=[]
def ok(label,condition=True):
 assert condition,label
 checks.append(label);print('OK',label,flush=True)
class Client:
 def __init__(self):
  self.s=requests.Session();self.s.trust_env=False;self.csrf=self.get('bootstrap')['csrf']
 def get(self,action):
  r=self.s.get(BASE+'/index.php',params={'api':action});assert r.status_code==200,r.text;return r.json()['data']
 def post(self,action,data=None,status=200):
  r=self.s.post(BASE+'/index.php',params={'api':action},json=data or {},headers={'X-CSRF-Token':self.csrf});d=r.json();assert r.status_code==status,(action,r.status_code,d)
  if status!=200:assert not d['ok'];return d
  self.csrf=d['data'].get('csrf',self.csrf);return d['data']
 def state(self):return self.get('state')
def key():return str(uuid.uuid4())
a=Client();state=a.post('demo_enter');c=state['company'];p=c['passport'];published=copy.deepcopy(state['plans'][0]['snapshot'])
ok('Новый демо-сеанс содержит руководство, бригадира и исполнителей',all(any(role in person['roles'] for person in p['people']) for role in ['chief','technologist','foreman','worker']))
p['structure']['workshops'].append({'id':'w-secondary','name':'Учебный второй цех'});p['structure']['brigades'].append({'id':'b-secondary','name':'Вторая бригада','workshop':'w-secondary','lead':'p-foreman-2'})
p['people'] += [{'id':'p-foreman-2','name':'Учебный бригадир 02','role':'Бригадир','roles':['foreman'],'skills':[],'active':True,'workshop':'w-secondary','brigade':'b-secondary'}, {'id':'p-worker-2','name':'Учебный исполнитель 02','role':'Швея','roles':['worker'],'skills':['sew'],'active':True,'workshop':'w-secondary','brigade':'b-secondary'}]
p['resources'].append({'id':'r-secondary','name':'Учебная машина 02','type':'sew','worker':'p-worker-2','workshop':'w-secondary','active':True})
a.post('passport_save',{'name':c['name'],'passport':p,'revision':c['revision'],'key':key()});state=a.state();p=state['company']['passport'];ok('Цех, бригада и роли сохраняются на сервере',len(p['structure']['workshops'])==2 and p['people'][-1]['brigade']=='b-secondary')
ok('Изменение структуры не переписывает опубликованный снимок',state['plans'][0]['snapshot']==published)
secondary={'width':12,'depth':8,'objects':[{'id':'f-secondary','resource':'r-secondary','type':'sew','x':0,'z':0,'rotation':0,'zone':'QA'}],'links':[]}
a.post('layout_save',{'revision':state['company']['revision'],'key':key(),'workshop_id':'w-secondary','layout':secondary});state=a.state();maps=state['company']['layout']['workshops'];ok('Новая схема цеха сохраняет прежнюю карту',len(maps['w-main']['objects'])==8 and maps['w-secondary']==secondary)
main=copy.deepcopy(maps['w-main']);main['objects'][0]['x']+=.5
a.post('layout_save',{'revision':state['company']['revision'],'key':key(),'workshop_id':'w-main','layout':main});state=a.state();ok('Сохранение основного цеха сохраняет вторую схему',state['company']['layout']['workshops']['w-secondary']==secondary)
ok('Корневой layout совместим с первым цехом',state['company']['layout']['objects']==state['company']['layout']['workshops']['w-main']['objects'] and state['company']['layout']['objects'][0]['x']==main['objects'][0]['x'])
def reject_layout(data):return a.post('layout_save',{'revision':a.state()['company']['revision'],'key':key(),**data},422)
reject_layout({'workshop_id':'w-main','layout':secondary});ok('Объект из другого цеха отклоняется')
reject_layout({'workshop_id':'w-main','layout':main,'assignments':{'r-secondary':'p-worker-2'}});ok('Назначение центра другого цеха отклоняется')
reject_layout({'workshop_id':'w-secondary','layout':secondary,'assignments':{'r-secondary':'p-sew-1'}});ok('Человек другого цеха не назначается на центр')
reject_layout({'workshop_id':'w-missing','layout':secondary});ok('Несуществующий цех отклоняется')
bad=copy.deepcopy(p);next(person for person in bad['people'] if person['id']=='p-foreman-2')['roles']=['worker']
a.post('passport_save',{'name':c['name'],'passport':bad,'revision':a.state()['company']['revision'],'key':key()},422);ok('Снятие роли действующего бригадира отклоняется')
bad=copy.deepcopy(p);next(person for person in bad['people'] if person['id']=='p-worker-2')['brigade']='b-sew'
a.post('passport_save',{'name':c['name'],'passport':bad,'revision':a.state()['company']['revision'],'key':key()},422);ok('Бригада другого цеха отклоняется')
bad=copy.deepcopy(p);bad['resources'][-1]['workshop']='w-main';bad['resources'][-1]['worker']='p-sew-1'
a.post('passport_save',{'name':c['name'],'passport':bad,'revision':a.state()['company']['revision'],'key':key()},422);ok('Перенос центра с прежней карты требует явного снятия')
stale=a.state()['company']['revision']-1;a.post('layout_save',{'revision':stale,'key':key(),'workshop_id':'w-secondary','layout':secondary},409);ok('Параллельная ревизия цеха защищена')
a.post('demo_role',{'role':'worker'});worker=a.state();ok('Работнику не выдаётся общая организационная структура', 'structure' not in worker['company']['passport'] and all('structure_snapshot' not in plan['snapshot'] for plan in worker['plans']))
a.post('passport_save',{'name':'QA','passport':p,'revision':1,'key':key()},403);ok('Структурные роли не подменяют серверные права')
# A separate empty tenant reproduces the old JSON contract without overwriting any existing company.
b=Client();c=b.post('register',{'name':'QA legacy','company':'QA старая структура','email':uuid.uuid4().hex+'@example.test','password':'Qa_demo_2026!'})['company'];legacy=copy.deepcopy(c['passport']);legacy.update({'description':'','area':None});legacy['people']=[{'id':'legacy-worker','name':'Учебный участник','role':'Швея','skills':['sew'],'active':True}];legacy['resources']=[{'id':'legacy-machine','name':'Учебная машина','type':'sew','worker':'legacy-worker','active':True}]
b.post('passport_save',{'name':c['name'],'passport':legacy,'revision':c['revision'],'key':key()});new=b.state()['company']['passport'];ok('Старый JSON получает основной цех без выдуманных руководителей',new['structure']['workshops'][0]['id']=='w-main' and new['people'][0]['roles']==['worker'])
(OUT/'organization-http.json').write_text(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2));print(json.dumps({'passed':len(checks)}))
