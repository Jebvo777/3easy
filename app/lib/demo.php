<?php
declare(strict_types=1);

/** Only the explicitly seeded synthetic factory is a public demo template. */
function demoTemplateSource():?array{
 static $loaded=false,$template=null;
 if($loaded)return $template;$loaded=true;
 $candidates=rows("SELECT c.*,a.details AS seed_details,(SELECT COUNT(*) FROM orders o WHERE o.company_id=c.id AND o.status<>'archived') AS goal_count FROM companies c JOIN audit_log a ON a.company_id=c.id WHERE c.is_demo=1 AND a.action='demo_q4_2026_seed' ORDER BY a.created_at DESC,c.created_at DESC LIMIT 5");
 foreach($candidates as $c){
  $seed=decode($c['seed_details']);
  if(($seed['source']??'')!=='easy3-demo-q4-20261023-v2'||empty($seed['synthetic'])||(int)$c['goal_count']<24)continue;
  $template=$c;break;
 }
 return $template;
}

function demoSessionInfo(array $company,array $orders,array $plans,array $tasks,array $audit):?array{
 if(empty($_SESSION['demo_company'])||$_SESSION['demo_company']!==$company['id']||empty($company['is_demo']))return null;
 $template=demoTemplateSource();$upgrade=$template&&$company['role']==='owner'&&count($orders)<(int)$template['goal_count'];
 $defaults=['Худи «Север»'=>600,'Футболка «Линия»'=>350,'Свитшот «Туман»'=>280];$untouched=count($orders)===3;
 foreach($orders as $o)if(($defaults[$o['title']]??null)!==(int)$o['quantity']||(int)$o['revision']!==1||$o['status']!=='draft')$untouched=false;
 return ['template_available'=>$template!==null,'goal_count'=>$template?(int)$template['goal_count']:0,'upgrade_available'=>(bool)$upgrade,'restore_on_open'=>(bool)($upgrade&&$untouched&&(int)$company['revision']===1&&!$plans&&!$tasks&&!$audit)];
}

function demoActors(string $owner,string $company):array{
 $actors=['owner'=>$owner];
 foreach(['foreman'=>'Мастер','technologist'=>'Технолог','chief'=>'Начальник производства','worker'=>'Швея 01','qc'=>'Контролёр ОТК'] as $role=>$name){
  $id=uid();query('INSERT INTO users (id,email,name,password_hash) VALUES (?,?,?,?)',[$id,$role.'-'.$id.'@demo.easy3.local',$name,password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
  query('INSERT INTO memberships (user_id,company_id,role) VALUES (?,?,?)',[$id,$company,$role]);$actors[$role]=$id;
 }
 return $actors;
}

/** Copy in bounded batches: no N+1 round trips for thousands of demo facts. */
function demoInsertRows(string $table,array $records):void{
 $allowed=['orders','order_preparations','preparation_revisions','plan_workspaces','plan_workspace_revisions','plan_versions','tasks','production_events','economy_observations'];
 if(!in_array($table,$allowed,true)||!$records)return;
 $columns=array_keys($records[0]);$fields=implode(',',array_map(fn($c)=>'`'.$c.'`',$columns));$slot='('.implode(',',array_fill(0,count($columns),'?')).')';
 foreach(array_chunk($records,32) as $chunk){$values=[];foreach($chunk as $row)foreach($columns as $column)$values[]=$row[$column];query('INSERT INTO `'.$table.'` ('.$fields.') VALUES '.implode(',',array_fill(0,count($chunk),$slot)),$values);}
}

function demoRemap(string $value,array $map):string{
 if(isset($map[$value]))return $map[$value];
 // Also remap JSON object keys and embedded job names such as task-<UUID>.
 return preg_replace_callback('/(?<![0-9a-f])(?:[0-9a-f]{64}|[0-9a-f]{32})(?![0-9a-f])/',fn($m)=>$map[$m[0]]??$m[0],$value)??$value;
}

/** New company, new actors and new IDs. The populated source remains untouched. */
function demoCloneTemplate(array $template,string $owner):string{
 $company=companyNew($template['name'],$owner);$actors=demoActors($owner,$company);$source=$template['id'];
 query('UPDATE companies SET is_demo=1,passport=?,layout=?,revision=? WHERE id=?',[$template['passport'],$template['layout'],(int)$template['revision'],$company]);
 $tables=['orders','order_preparations','preparation_revisions','plan_workspaces','plan_workspace_revisions','plan_versions','tasks','production_events','economy_observations'];$records=[];$map=[$source=>$company];
 foreach(rows('SELECT user_id,role FROM memberships WHERE company_id=?',[$source]) as $member)$map[$member['user_id']]=$actors[$member['role']]??$owner;
 foreach($tables as $table){$records[$table]=rows('SELECT * FROM `'.$table.'` WHERE company_id=?',[$source]);foreach($records[$table] as $row)$map[$row['id']]=uid();}
 foreach($records['plan_versions'] as $plan)$map[$plan['input_hash']]=hash('sha256',$plan['input_hash'].':'.$company.':'.$map[$plan['id']]);
 foreach($tables as $table){
  $batch=[];
  foreach($records[$table] as $row){
   foreach($row as $key=>$value){
    if($value===null)continue;
    if(in_array($key,['actor_id','creator_id'],true))$row[$key]=$map[(string)$value]??$owner;
    elseif(is_string($value))$row[$key]=demoRemap($value,$map);
   }
   if($table==='plan_versions'){
    $snapshot=decode($row['snapshot']);$snapshot['demo_copy']=['synthetic'=>true,'source'=>'easy3-demo-q4-20261023-v2','created_at'=>date('c')];$row['snapshot']=j($snapshot);
   }
   $batch[]=$row;
   if(count($batch)===32){demoInsertRows($table,$batch);$batch=[];}
  }
  demoInsertRows($table,$batch);
 }
 // The latest copied snapshot refers to the identities of this private copy.
 require_once __DIR__.'/engine.php';$c=$template;$c['id']=$company;$c['passport']=decode($template['passport']);$c['layout']=decode($template['layout']);$c=productionCompanyView($c);
 [$orders,$tasks]=engineInputs($c);$hash=engineHash($c,$orders,$tasks);$latest=one('SELECT * FROM plan_versions WHERE company_id=? ORDER BY version_no DESC LIMIT 1',[$company]);
 if($latest){$snapshot=decode($latest['snapshot']);$snapshot['input_hash']=$hash;$approvals=decode($latest['approvals']);foreach($approvals as &$approval)$approval['input_hash']=$hash;unset($approval);query('UPDATE plan_versions SET snapshot=?,input_hash=?,approvals=? WHERE id=? AND company_id=?',[j($snapshot),$hash,j($approvals),$latest['id'],$company]);}
 query('INSERT INTO audit_log (id,company_id,actor_id,action,details) VALUES (?,?,?,?,?)',[uid(),$company,$owner,'demo_template_cloned',j(['source'=>'easy3-demo-q4-20261023-v2','synthetic'=>true,'goals'=>count($records['orders']),'notice'=>'Изолированная учебная копия: исходная фабрика и работа других сеансов не изменяются.'])]);
 return $company;
}

function demoEnter():array{
 global $config;
 if(empty($config['demo_enabled']))fail('Демо-сеанс отключён',403);
 $bucket=authLimit('demo-session');query('UPDATE auth_limits SET failures=failures+1 WHERE bucket=?',[$bucket]);$db=db();
 $db->beginTransaction();
 try{
  $owner=uid();query('INSERT INTO users (id,email,name,password_hash) VALUES (?,?,?,?)',[$owner,'session-'.$owner.'@demo.easy3.local','Владелец',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
  $template=demoTemplateSource();
  if($template)$company=demoCloneTemplate($template,$owner);
  else{$company=companyNew('Ателье Север',$owner,true);demoActors($owner,$company);require_once __DIR__.'/engine.php';seedInitialPlan($db,$company,$owner);}
  $db->commit();
 }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
 establish($owner,$company);$_SESSION['demo_company']=$company;$_SESSION['worker_resource']='p-sew-1';return stateData();
}
