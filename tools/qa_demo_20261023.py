"""Run only after the complete v7.2 build is published; isolated SQL adapter."""
from pathlib import Path
import argparse,json,re,sqlite3,copy
from datetime import datetime
# Reuse the quote-aware MySQL transport and schema reader, never its older checks.
shared=Path(__file__).with_name('qa_demo_q4_2026.py').read_text().split('parser=argparse.ArgumentParser')[0]
exec(compile(shared,'qa-demo-helpers','exec'))
p=argparse.ArgumentParser();p.add_argument('--source-sql',required=True,type=Path);p.add_argument('--source-data',required=True,type=Path);p.add_argument('--seed',required=True,type=Path);p.add_argument('--output',required=True,type=Path);a=p.parse_args();a.output.mkdir(parents=True,exist_ok=True)
data=json.loads(a.source_data.read_text());cid=sorted([c for c in data['companies'] if c['is_demo']],key=lambda c:c['created_at'])[-1]['id'];checks=[]
def check(label,condition):
 require(condition,label);checks.append(label)
def migrate(db):
 raw=Path(__file__).resolve().parents[1].joinpath('app/database/update_20261023.sql').read_text()
 for s in statements(raw):
  s=re.sub(r'\) ENGINE=.*$',')',s,flags=re.S);s=re.sub(r'UNIQUE KEY \w+\s*\(','UNIQUE (',s);s=s.replace(' ON UPDATE CURRENT_TIMESTAMP','');db.execute(s)
base=connect(a.output/'baseline.sqlite');schema(base,a.source_sql.read_text());base.execute('BEGIN')
for table,records in data.items():
 for v in records:base.execute('INSERT INTO `'+table+'` ('+','.join('`'+k+'`' for k in v)+') VALUES ('+','.join('?' for k in v)+')',list(v.values()))
base.commit();check('Original attachment foreign keys',not base.execute('PRAGMA foreign_key_check').fetchall())
migrate(base);migrate(base);base.commit();check('Migration repeat is harmless and starts empty',base.execute('SELECT COUNT(*) FROM order_preparations').fetchone()[0]==0)
filled=connect(a.output/'filled.sqlite');base.backup(filled);seed=statements(a.seed.read_text());v=execute_seed(filled,seed);check('Whole delivered SQL applied to isolated copy',v['@demo_apply']==1);check('All foreign keys after import',not filled.execute('PRAGMA foreign_key_check').fetchall())
for table,records in data.items():
 if not records:continue
 if table in ['users','memberships','ai_generations','assistant_jobs','document_imports','idempotency','auth_limits','ai_provider_limits']:
  check('Preserved all '+table,[dict(x) for x in filled.execute('SELECT * FROM `'+table+'`')]==[dict(x) for x in base.execute('SELECT * FROM `'+table+'`')])
 for row in records:
  unrelated=row.get('company_id')!=cid if 'company_id' in row else table=='companies' and row['id']!=cid
  historical=table in ['tasks','plan_versions','production_events','economy_observations','plan_workspace_revisions']
  if not (unrelated or historical) or 'id' not in row:continue
  require(dict(filled.execute('SELECT * FROM `'+table+'` WHERE id=?',[row['id']]).fetchone())==row,'Historical/unrelated row changed: '+table)
checks.append('All unrelated companies and every previous task, plan and event preserved')
c=dict(filled.execute('SELECT * FROM companies WHERE id=?',[cid]).fetchone());passport=json.loads(c['passport']);resources={x['id']:x for x in passport['resources']};people={x['id']:x for x in passport['people']};departments={x['id']:x for x in passport['structure']['workshops']}
check('55 people, 48 resources, two assembly shops and one preparation department',len(people)==55 and len(resources)==48 and sum(w['kind']=='assembly' for w in departments.values())==2 and sum(w['kind']=='preparation' for w in departments.values())==1)
for r in resources.values():
 require((r['type']=='cut')==(departments[r['workshop']]['kind']=='preparation') or r['type']=='hand','Department boundary')
 if r['worker']:
  person=people[r['worker']];require(person['workshop']==r['workshop'] and r['type'] in person['skills'],'Work assignment incompatible')
  if r['type']=='special':require(r['specialization'] in person['specializations'],'Specialization mismatch')
orders=[dict(x) for x in filled.execute('SELECT * FROM orders WHERE company_id=?',[cid])];new=[o for o in orders if o['title'].startswith('D26-')];newids={o['id'] for o in new}
check('24 added goals, 8 in each Q4 month, 27 total',len(new)==24 and len(orders)==27 and all(sum(o['deadline'].startswith(m) for o in new)==8 for m in ['2026-10','2026-11','2026-12']))
preps=[dict(x) for x in filled.execute('SELECT * FROM order_preparations WHERE company_id=?',[cid])];check('Separate preparation for every goal',len(preps)==27)
for p in preps:
 state=json.loads(p['state_json']);o=next(o for o in orders if o['id']==p['order_id']);require(departments[state['department_id']]['kind']=='preparation','Preparation department');require(sum(v['quantity'] for v in state['items'])==o['quantity'],'Preparation quantity');require(all(v['ready']+v['expected']==v['quantity'] and (not v['expected'] or v['ready_date']>='2026-10-23') for v in state['items']),'Preparation fact/expectation');require(len([e for e in json.loads(o['expenses']) if e['id']=='preparation-cost'])==1,'Duplicate preparation cost')
checks.append('Supplies use separate facts/dates and their cost occurs once')
tasks=[dict(x) for x in filled.execute('SELECT * FROM tasks WHERE company_id=?',[cid])];newtasks=[t for t in tasks if t['order_id'] in newids];bytask={t['id']:t for t in tasks};busy={};opcount=0
for o in new:
 route=json.loads(o['route']);opcount+=len(route);ops={op['id']:op for op in route};require(all(op['type']!='cut' and op['seconds']>0 and op['source'] and all(d in ops for d in op['dependencies']) for op in route),'Bad assembly technology');require(sum(t['quantity'] for t in newtasks if t['order_id']==o['id'] and t['is_final'])==o['quantity'],'Final output count')
for t in newtasks:
 require(0<=t['accepted']+t['rework']+t['scrap']<=t['transferred']<=t['released']<=t['quantity'] and 0<=t['rework_ready']<=t['rework'],'Quantity conservation')
 r=resources[t['resource_id']];person=people[t['worker_id']];require(departments[r['workshop']]['kind']=='assembly' and r['workshop']==person['workshop'],'Assembly uses preparation worker')
 for d in json.loads(t['dependencies']):require(bytask[d]['order_id']==t['order_id'] and bytask[d]['planned_end']<=t['planned_start'],'Bad dependency/calendar')
 meta=json.loads(t['planning_meta']);duration=0
 for start,end in meta['slots']:
  start=datetime.fromisoformat(start);end=datetime.fromisoformat(end);require(start<end and start.date()==end.date() and start.isoweekday()<=5,'Nonworking date');require((9<=start.hour and end.hour<=13) or (14<=start.hour and end.hour<=18),'Lunch/shift overlap');duration+=int((end-start).total_seconds())
  for key in ['r:'+t['resource_id'],'p:'+t['worker_id']]:busy.setdefault(key,[]).append((start,end))
 require(duration==meta['norm_seconds'],'Duration not reserved')
for key,slots in busy.items():
 slots.sort();require(all(x[1]<=y[0] for x,y in zip(slots,slots[1:])),'Double booking '+key)
check('432 assembly operations and 1938 assignments with exclusive people/equipment',opcount==432 and len(newtasks)==1938)
events=[dict(x) for x in filled.execute('SELECT * FROM production_events WHERE company_id=?',[cid]) if x['task_id'] in {t['id'] for t in newtasks}];check('No future production facts',all(e['created_at']<'2026-10-23 00:00:00' for e in events))
incident=filled.execute("SELECT t.* FROM tasks t JOIN orders o ON t.order_id=o.id WHERE o.company_id=? AND o.title LIKE 'D26-10-05%' AND t.rework=8",[cid]).fetchone();check('Buttonhole defect at final QC: 8 rework, 2 awaiting reinspection',incident and incident['is_final'] and incident['rework_ready']==2 and incident['state']=='rework')
original=database_digest(filled);v=execute_seed(filled,seed);check('Second import is a complete no-op',v['@demo_already']==1 and v['@demo_apply']==0 and original==database_digest(filled))
for mutation in ["UPDATE companies SET revision=revision+1 WHERE id='"+cid+"'","UPDATE tasks SET revision=revision+1 WHERE company_id='"+cid+"'","UPDATE companies SET is_demo=0 WHERE id='"+cid+"'"]:
 copydb=connect(':memory:');base.backup(copydb);copydb.execute(mutation);copydb.commit();before=database_digest(copydb);v=execute_seed(copydb,seed);require(v['@demo_apply']==0 and before==database_digest(copydb),'Changed-source guard');copydb.close()
checks.append('Changed source, changed tasks and non-demo targets refuse seed')
report=dict(passed=len(checks),checks=checks,native_mysql=False,adapter='SQLite with actual FK/unique constraints; MySQL DML/session variables translated',limits=['No connection to hosting MySQL; native MySQL and GPU screenshots not claimed'])
(a.output/'sql-checks.json').write_text(json.dumps(report,ensure_ascii=False,indent=2));print(json.dumps(report,ensure_ascii=False))
