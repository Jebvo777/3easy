"""Post-publication checks on an isolated copy; MySQL DML is adapted to SQLite.

Does not claim a native MySQL or hosting test. Requires the conversation's private
parsed attachment; no credentials, live database or external AI is used.
"""
import argparse
import copy
from datetime import datetime
import hashlib
import json
from pathlib import Path
import re
import sqlite3


def statements(text):
    parts=[];start=0;pos=0;quote=False
    while pos<len(text):
        ch=text[pos]
        if quote:
            if ch=='\\':pos+=2;continue
            if ch=="'":
                if pos+1<len(text) and text[pos+1]=="'":pos+=2;continue
                quote=False
        elif ch=="'":quote=True
        elif text[pos:pos+2]=='--':
            end=text.find('\n',pos)
            if end<0:end=len(text)
            text=text[:pos]+' '*(end-pos)+text[end:]
            pos=end;continue
        elif ch==';':
            if text[start:pos].strip():parts.append(text[start:pos].strip())
            start=pos+1
        pos+=1
    if quote:raise ValueError('Unclosed SQL string')
    if text[start:].strip():parts.append(text[start:].strip())
    return parts


def literal(value):
    if value is None:return 'NULL'
    if isinstance(value,(int,float)):return str(value)
    return "'"+str(value).replace("'","''")+"'"


def adapt(statement,variables):
    out=[];pos=0
    while pos<len(statement):
        ch=statement[pos]
        if ch=="'":
            pos+=1;value=[]
            while pos<len(statement):
                ch=statement[pos];pos+=1
                if ch=='\\':
                    ch=statement[pos];pos+=1
                    value.append({'n':'\n','r':'\r','t':'\t','0':'\0','b':'\b','Z':'\x1a'}.get(ch,ch))
                elif ch=="'":
                    if pos<len(statement) and statement[pos]=="'":value.append("'");pos+=1
                    else:break
                else:value.append(ch)
            out.append(literal(''.join(value)))
        elif ch=='@':
            end=pos+1
            while end<len(statement) and (statement[end].isalnum() or statement[end] in '_@.'):end+=1
            out.append(literal(variables.get(statement[pos:end])))
            pos=end
        else:out.append(ch);pos+=1
    result=''.join(out)
    result=re.sub(r'\s+FOR UPDATE\s*$','',result,flags=re.I)
    result=re.sub(r'\s+FROM DUAL(?=\s|$)','',result,flags=re.I)
    return result


def schema(db,source):
    for table,body in re.findall(r'CREATE TABLE `([^`]+)`\s*\((.*?)\) ENGINE=',source,re.S):
        fields=[]
        for name,decl in re.findall(r'^\s*`([^`]+)`\s+(.+?)(?:,)?$',body,re.M):
            number=bool(re.match(r'(tinyint|int|bigint|smallint|decimal|float|double)',decl,re.I))
            definition='`'+name+'` '+('INTEGER' if number else 'TEXT')
            if 'NOT NULL' in decl:definition+=' NOT NULL'
            default=re.search(r"DEFAULT\s+('(?:[^']|'')*'|CURRENT_TIMESTAMP|NULL|-?\d+)",decl,re.I)
            if default:definition+=' DEFAULT '+default[1]
            fields.append(definition)
        for tb,changes in re.findall(r'ALTER TABLE `([^`]+)`(.*?);',source,re.S):
            if tb!=table:continue
            for cols in re.findall(r'ADD PRIMARY KEY\s*\(([^)]+)\)',changes):fields.append('PRIMARY KEY ('+cols+')')
            for cols in re.findall(r'ADD UNIQUE KEY\s+`[^`]+`\s*\(([^)]+)\)',changes):fields.append('UNIQUE ('+cols+')')
            for cols,ref,remote in re.findall(r'FOREIGN KEY\s*\(([^)]+)\) REFERENCES `([^`]+)`\s*\(([^)]+)\)',changes):fields.append('FOREIGN KEY ('+cols+') REFERENCES `'+ref+'` ('+remote+') DEFERRABLE INITIALLY DEFERRED')
        db.execute('CREATE TABLE `'+table+'` ('+','.join(fields)+')')


def connect(path):
    db=sqlite3.connect(path);db.row_factory=sqlite3.Row
    db.execute('PRAGMA foreign_keys=ON')
    db.create_function('IF',3,lambda c,a,b:a if c else b)
    db.create_function('CONCAT',-1,lambda *v:None if any(x is None for x in v) else ''.join(map(str,v)))
    db.create_function('SHA2',2,lambda s,b:None if s is None else hashlib.sha256(str(s).encode()).hexdigest())
    def json_length(value,path='$'):
        value=json.loads(value)
        for key in path.removeprefix('$.').split('.'):
            if key and key!='$':value=value[key]
        return len(value)
    db.create_function('JSON_LENGTH',-1,json_length)
    return db


def execute_seed(db,seed):
    variables={'@@SESSION.sql_mode':'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION','@@SESSION.time_zone':'+00:00'}
    for index,statement in enumerate(seed):
        try:
            if statement.startswith('SET NAMES') or statement.startswith('SET SESSION'):continue
            m=re.match(r'SET\s+(@\w+)\s*=(.*)',statement,re.S|re.I)
            if m:variables[m[1]]=db.execute('SELECT '+adapt(m[2],variables)).fetchone()[0]
            elif statement=='START TRANSACTION':db.execute('BEGIN')
            else:db.execute(adapt(statement,variables)).fetchall()
        except Exception as e:
            if db.in_transaction:db.rollback()
            raise RuntimeError(f'SQL statement {index+1}: {statement[:75]}: {e}') from e
    return variables


def database_digest(db,excluded=()):
    data={}
    for row in db.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"):
        name=row[0]
        if name in excluded:continue
        values=[dict(x) for x in db.execute('SELECT * FROM `'+name+'`')]
        data[name]=sorted(values,key=lambda x:json.dumps(x,sort_keys=True,ensure_ascii=False))
    return hashlib.sha256(json.dumps(data,sort_keys=True,ensure_ascii=False).encode()).hexdigest()


def require(condition,message):
    if not condition:raise AssertionError(message)


parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--source-sql',type=Path,required=True)
parser.add_argument('--source-data',type=Path,required=True)
parser.add_argument('--seed',type=Path,required=True)
parser.add_argument('--output',type=Path,required=True)
args=parser.parse_args();args.output.mkdir(parents=True,exist_ok=True)
data=json.loads(args.source_data.read_text());raw=args.source_sql.read_text()
seed=statements(args.seed.read_text());checks=[]
cid='96941cfaf7e78052df60fbc06d541d9d'
baseline=args.output/'baseline.sqlite'
baseline.unlink(missing_ok=True)
db=connect(baseline);schema(db,raw);db.execute('BEGIN')
for table,rows in data.items():
    for row in rows:
        columns=list(row)
        db.execute('INSERT INTO `'+table+'` ('+','.join('`'+c+'`' for c in columns)+') VALUES ('+','.join('?' for _ in columns)+')',list(row.values()))
db.commit()
require(not db.execute('PRAGMA foreign_key_check').fetchall(),'Attachment foreign keys')
checks.append('Source attachment imported with its actual keys and uniqueness constraints')
before={table:[dict(x) for x in db.execute('SELECT * FROM `'+table+'`')] for table in data}
unchanged_digest=database_digest(db,excluded=('companies','orders','tasks','plan_workspaces','plan_workspace_revisions','plan_versions','production_events','economy_observations','audit_log'))
path=args.output/'filled.sqlite';path.unlink(missing_ok=True)
filled=connect(path);db.backup(filled)
v=execute_seed(filled,seed)
require(v['@demo_apply']==1,'Initial import did not apply')
require(not filled.execute('PRAGMA foreign_key_check').fetchall(),'Expansion foreign keys')
checks.append('Entire delivered SQL executed on isolated adapter with foreign keys enabled')
require(unchanged_digest==database_digest(filled,excluded=('companies','orders','tasks','plan_workspaces','plan_workspace_revisions','plan_versions','production_events','economy_observations','audit_log')),'Accounts/imports/AI changed')
checks.append('All accounts, roles, credentials, AI jobs, imports and unrelated tables unchanged')
for table,records in before.items():
    if not records or 'company_id' not in records[0]:continue
    for row in records:
        if row['company_id']==cid:continue
        keys=['id'] if 'id' in row else ['user_id','company_id'] if table=='memberships' else ['company_id','actor_id','command_key']
        current=filled.execute('SELECT * FROM `'+table+'` WHERE '+' AND '.join('`'+k+'`=?' for k in keys),[row[k] for k in keys]).fetchone()
        require(current and dict(current)==row,'Other company changed in '+table)
for company in data['companies']:
    if company['id']!=cid:require(dict(filled.execute('SELECT * FROM companies WHERE id=?',[company['id']]).fetchone())==company,'Other passport changed')
checks.append('All 35 other companies and their business records preserved byte for byte')
for table in ['plan_versions','production_events','economy_observations','plan_workspace_revisions']:
    for row in before[table]:
        if row['company_id']==cid:
            require(dict(filled.execute('SELECT * FROM `'+table+'` WHERE id=?',[row['id']]).fetchone())==row,'Historical record rewritten: '+table)
checks.append('Existing published snapshots, events, observations and saved workspace revisions immutable')
company=dict(filled.execute('SELECT * FROM companies WHERE id=?',[cid]).fetchone());passport=json.loads(company['passport']);layout=json.loads(company['layout'])
orders=[dict(x) for x in filled.execute('SELECT * FROM orders WHERE company_id=?',[cid])]
new_orders=[o for o in orders if o['title'].startswith('D26-')];new_ids={o['id'] for o in new_orders}
require(len(new_orders)==24 and sum(o['quantity'] for o in new_orders)==12000,'Portfolio size')
require(all(sum(o['deadline'].startswith(m) for o in new_orders)==8 for m in ['2026-10','2026-11','2026-12']),'Month coverage')
checks.append('24 new goals / 12000 units / 8 deadlines in each Q4 month')
require(len(passport['people'])==46 and len(passport['resources'])==39 and len(passport['structure']['workshops'])==2,'Factory size')
people={p['id']:p for p in passport['people']};resources={r['id']:r for r in passport['resources']}
require(len(people)==46 and len(resources)==39,'Duplicate human or resource identifiers')
for r in resources.values():
    if r['worker']:require(people[r['worker']]['workshop']==r['workshop'] and r['type'] in people[r['worker']]['skills'],'Resource/worker mismatch')
checks.append('Two workshops / 46 people / 39 distinct compatible centres / four brigades')
tasks=[dict(x) for x in filled.execute('SELECT * FROM tasks WHERE company_id=?',[cid])]
new_tasks=[t for t in tasks if t['order_id'] in new_ids];by_task={t['id']:t for t in tasks}
require(len(new_tasks)==2289,'Task count')
busy={};operation_count=0
for order in new_orders:
    route=json.loads(order['route']);operation_count+=len(route);operations={o['id']:o for o in route}
    require(all(o['seconds']>0 and o['source'] and not o.get('ai_origin') for o in route),'Missing/fake norms')
    require(sum(t['quantity'] for t in new_tasks if t['order_id']==order['id'] and t['is_final'])==order['quantity'],'Final quantity differs from goal')
    for op in route:require(all(d in operations for d in op['dependencies']),'Dangling route dependency')
for t in new_tasks:
    require(0<=t['accepted']+t['rework']+t['scrap']<=t['transferred']<=t['released']<=t['quantity'],'Task quantity conservation')
    require(0<=t['rework_ready']<=t['rework'],'Rework conservation')
    require(t['resource_id'] in resources and t['worker_id'] in people,'Missing assignment')
    r=resources[t['resource_id']];p=people[t['worker_id']]
    require(r['workshop']==p['workshop'] and r['type'] in p['skills'],'Assignment skill/workshop mismatch')
    for dep in json.loads(t['dependencies']):
        require(dep in by_task and by_task[dep]['order_id']==t['order_id'],'Missing/cross-company task predecessor')
        require(by_task[dep]['planned_end']<=t['planned_start'],'Predecessor planned after consumer')
        if t['released']:require(by_task[dep]['transferred']>=t['released'],'Fact released without input')
    meta=json.loads(t['planning_meta']);seconds=0
    for a,b in meta['slots']:
        a=datetime.fromisoformat(a);b=datetime.fromisoformat(b)
        require(a<b and a.date()==b.date() and a.isoweekday()<=5,'Invalid workday slot')
        require((9<=a.hour and b.hour<=13) or (14<=a.hour and b.hour<=18),'Outside shift / lunch')
        seconds+=int((b-a).total_seconds())
        for key in ['r:'+t['resource_id'],'p:'+t['worker_id']]:busy.setdefault(key,[]).append((a,b,t['id']))
    require(seconds==meta['norm_seconds'],'Job duration differs from reserved slots')
for key,slots in busy.items():
    slots.sort()
    require(all(a[1]<=b[0] for a,b in zip(slots,slots[1:])),'Resource/worker double-booked: '+key)
require(operation_count==480,'Route operation count')
checks.append('480 timed route operations and 2289 assignments with intact dependencies and quantities')
checks.append('Every new work slot respects weekdays, lunch and exclusive resource AND human capacity')
for wid,room in layout['workshops'].items():
    positioned=set()
    sizes={'cut':(2.8,1.6),'sew':(1.5,1),'overlock':(1.5,1),'iron':(1.8,1),'hand':(1.8,1),'qc':(1.8,1),'pack':(1.8,1),'storage':(1.7,.8)}
    for i,o in enumerate(room['objects']):
        w,d=sizes[o['type']]
        require(abs(o['x'])+w/2<=room['width']/2 and abs(o['z'])+d/2<=room['depth']/2,'Object out of room')
        if o['resource']:
            require(resources[o['resource']]['workshop']==wid and o['resource'] not in positioned,'Map resource duplication/wrong workshop');positioned.add(o['resource'])
        for q in room['objects'][:i]:
            qw,qd=sizes[q['type']]
            require(abs(o['x']-q['x'])>=(w+qw)/2+.15 or abs(o['z']-q['z'])>=(d+qd)/2+.15,'Layout collision')
    object_ids={o['id'] for o in room['objects']}
    require(all(a in object_ids and b in object_ids and a!=b for a,b in room['links']),'Broken map edge')
checks.append('Both workshop maps fit their rooms with no collisions or dangling flow links')
events=[dict(x) for x in filled.execute('SELECT * FROM production_events WHERE company_id=?',[cid]) if x['task_id'] in {t['id'] for t in new_tasks}]
fold={t['id']:dict(state='ready',released=0,transferred=0,accepted=0,rework=0,rework_ready=0,scrap=0,revision=1) for t in new_tasks}
for e in sorted(events,key=lambda e:(e['task_id'],json.loads(e['details'])['_task_revision'])):
    require(e['created_at']<'2026-10-19 00:00:00','Future production fact')
    require(e['created_at'][:7]=='2026-10','Fact outside past October')
    task=by_task[e['task_id']];f=fold[e['task_id']];d=json.loads(e['details']);kind=e['kind'];q=e['quantity']
    require(d['_task_revision']==f['revision']+1,'Event revision gap')
    if kind=='start':require(f['state'] in ['ready','paused'],'Invalid start');f['state']='running'
    elif kind=='pause':require(f['state']=='running','Invalid pause');f['state']='paused'
    elif kind=='release':require(f['state']=='running','Release before start');f['released']+=q;f['state']='awaiting_transfer' if f['released']==task['quantity'] else 'running'
    elif kind=='transfer':require(q<=f['released']-f['transferred'],'Transfer exceeds release');f['transferred']+=q;f['state']='quality' if task['is_final'] else 'completed'
    elif kind in ['quality','rework_quality']:
        require(task['is_final'] and q==d['accepted']+d['rework']+d['scrap'],'Invalid QC split')
        require(q<=(f['transferred']-f['accepted']-f['rework']-f['scrap'] if kind=='quality' else f['rework_ready']),'Inspection exceeds queue')
        f['accepted']+=d['accepted'];f['scrap']+=d['scrap']
        if kind=='quality':f['rework']+=d['rework']
        else:f['rework']-=d['accepted']+d['scrap'];f['rework_ready']-=q
    elif kind=='rework_release':require(q<=f['rework']-f['rework_ready'],'Rework released twice');f['rework_ready']+=q
    else:raise AssertionError('Unsupported event kind')
    if task['is_final'] and f['transferred']==task['quantity']:
        f['state']='completed' if f['accepted']+f['scrap']==task['quantity'] and f['rework']==0 else 'rework' if f['rework'] else 'quality'
    f['revision']+=1
for task in new_tasks:require(all(task[k]==v for k,v in fold[task['id']].items()),'Task state does not match event replay')
checks.append('All added event chains replay to the exact task counters, revisions and state')
checks.append('No future accepted output, costs on unaccepted output or November/December production facts')
for ob in filled.execute('SELECT * FROM economy_observations WHERE company_id=?',[cid]):
    if ob['order_id'] not in new_ids:continue
    order=next(o for o in new_orders if o['id']==ob['order_id']);accepted=sum(t['accepted'] for t in new_tasks if t['order_id']==ob['order_id'] and t['is_final'])
    require(ob['accepted_quantity']==accepted,'Observation not comparable to accepted output')
    require(ob['recognized_cents']==(None if order['mode']=='OWN' else accepted*order['price_cents']),'Recognition differs from accepted contract price')
    require(all(ob[k]>=0 for k in ['direct_cents','labor_cents','common_cents']),'Negative/unknown cost')
checks.append('Cumulative actual economics covers accepted units exactly; OWN revenue stays unknown')
incident=filled.execute("SELECT t.* FROM tasks t JOIN orders o ON o.id=t.order_id WHERE o.title LIKE 'D26-10-04%' AND t.rework=8").fetchone()
require(incident and incident['rework_ready']==2 and incident['state']=='rework','QC scenario missing')
require(not filled.execute("SELECT id FROM tasks WHERE company_id=? AND state='paused'",[cid]).fetchall(),'Old pause masks new QC anomaly')
latest=filled.execute('SELECT * FROM production_events WHERE company_id=? ORDER BY created_at DESC,id DESC LIMIT 60',[cid]).fetchall()
require(any(e['task_id']==incident['id'] and e['kind'] in ['quality','rework_quality'] for e in latest),'QC reason missing from desktop event window')
checks.append('QC anomaly reaches the desktop: 8 in rework / 2 awaiting repeat QC / reason in latest events')
filled.commit();one_time_digest=database_digest(filled);v=execute_seed(filled,seed)
require(v['@demo_apply']==0 and v['@demo_already']==1 and database_digest(filled)==one_time_digest,'Second import changed data')
checks.append('Second import is a complete no-op, including facts and user edits')
for name,mutation in [('edited-passport',"UPDATE companies SET revision=revision+1 WHERE id='"+cid+"'"),('edited-task',"UPDATE tasks SET revision=revision+1 WHERE company_id='"+cid+"'"),('not-a-demo',"UPDATE companies SET is_demo=0 WHERE id='"+cid+"'")]:
    test=connect(':memory:');db.backup(test);test.execute(mutation);test.commit();original=database_digest(test);v=execute_seed(test,seed)
    require(v['@demo_apply']==0 and database_digest(test)==original,'Source guard failed: '+name);test.close()
checks.append('Changed passport, changed tasks and non-demo targets each refuse the entire expansion')
report=dict(passed=len(checks),checks=checks,adapter='SQLite with actual FK/unique constraints; MySQL session variables and scalar functions translated',native_mysql=False,source_rows={k:len(v) for k,v in data.items()},added=dict(orders=len(new_orders),tasks=len(new_tasks),events=len(events)+1),filled_database=str(path),limits=['No connection to the user hosting or MySQL server. Native MySQL execution and visual browser/GPU checks are not claimed.'])
(args.output/'sql-checks.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({k:v for k,v in report.items() if k not in ['source_rows','filled_database']},ensure_ascii=False))
