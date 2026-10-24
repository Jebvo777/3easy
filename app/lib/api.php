<?php
declare(strict_types=1);
require_once __DIR__.'/demo.php';
function authLimit(string $email):string{
 $ip=(string)($_SERVER['REMOTE_ADDR']??'cli');$bucket=hash('sha256',$ip.'|'.$email);
 $row=one('SELECT * FROM auth_limits WHERE bucket=?',[$bucket]);
 if($row&&strtotime($row['window_start'])>time()-900&&(int)$row['failures']>=12)fail('Слишком много попыток. Повторите через 15 минут.',429);
 if(!$row)query('INSERT INTO auth_limits (bucket,failures) VALUES (?,0)',[$bucket]);
 elseif(strtotime($row['window_start'])<=time()-900)query('UPDATE auth_limits SET failures=0,window_start=CURRENT_TIMESTAMP WHERE bucket=?',[$bucket]);return $bucket;
}
function establish(string $userId,string $companyId):void{session_regenerate_id(true);unset($_SESSION['active_roles']);$_SESSION['user_id']=$userId;$_SESSION['company_id']=$companyId;$_SESSION['csrf']=bin2hex(random_bytes(24));}
function companyNew(string $name,string $userId,bool $demo=false):string{
 $id=uid();$passport=$demo?demoPassport():['industry'=>'Швейное производство','city'=>'','timezone'=>'Europe/Moscow','people'=>[],'resources'=>[],'shift'=>['start'=>'09:00','end'=>'18:00','break_start'=>'13:00','break_minutes'=>60,'days'=>[1,2,3,4,5]],'economy'=>['labor_hour_cents'=>null,'monthly_common_cents'=>null,'monthly_hours'=>null,'excluded'=>['Продажи и налоги компании'],'unknown'=>['Расходы ещё не заполнены'],'currency'=>'RUB']];
 query('INSERT INTO companies (id,name,is_demo,passport,layout) VALUES (?,?,?,?,?)',[$id,$name,$demo?1:0,j($passport),j($demo?demoLayout():['width'=>18,'depth'=>12,'objects'=>[],'links'=>[]])]);
 query('INSERT INTO memberships (user_id,company_id,role) VALUES (?,?,?)',[$userId,$id,'owner']);if($demo)seedCompany(db(),$id);return $id;
}
function stateData():array{
 $u=user();$c=context();require_once __DIR__.'/engine.php';$economic=$c['role']==='owner';require_once __DIR__.'/preparation.php';
 $companies=rows('SELECT c.id,c.name,m.role,c.is_demo FROM companies c JOIN memberships m ON m.company_id=c.id WHERE m.user_id=? ORDER BY c.created_at',[$u['id']]);
 $economy=[];
 $orders=rows('SELECT * FROM orders WHERE company_id=? ORDER BY created_at,id',[$c['id']]);
 foreach($orders as &$o){$raw=decode($o['route']);$o['preparation_route']=productionPreparationRoute($raw);$o['route']=productionAssemblyRoute($raw);require_once __DIR__.'/garment.php';$o['illustration']=technologyIllustration($o);$o['expenses']=decode($o['expenses']);$o['preparation']=preparationState($c,$o);$o['_preparation_cost_unknown']=($o['preparation']['cost_mode']??'unknown')==='unknown';foreach(['quantity','revision'] as $k)$o[$k]=(int)$o[$k];foreach(['price_cents','target_cents'] as $k)$o[$k]=$o[$k]===null?null:(int)$o[$k];if($economic){$workspace=workspaceRecord($c,$o);$economy[$o['id']]=orderEconomy($o+['_labor_mode'=>$workspace['state']['mode']??'flow'],$c['passport']);}if(!$economic){unset($o['price_cents'],$o['target_cents'],$o['expenses'],$o['preparation']['cost_cents']);}}unset($o);
 $plans=rows('SELECT * FROM plan_versions WHERE company_id=? ORDER BY version_no DESC',[$c['id']]);
 foreach($plans as &$p){$p['snapshot']=decode($p['snapshot']);$p['approvals']=decode($p['approvals']);$p['version_no']=(int)$p['version_no'];if(!$economic){unset($p['snapshot']['economy']);foreach($p['snapshot']['preparation_snapshot']??[] as $oid=>$record)unset($p['snapshot']['preparation_snapshot'][$oid]['state']['cost_cents']);}}unset($p);
 if($economic)foreach($orders as $order)foreach($plans as $plan)if(isset($plan['snapshot']['workspace_snapshot'][$order['id']])&&(int)($plan['snapshot']['order_revisions'][$order['id']]??-1)===(int)$order['revision']&&(int)($plan['snapshot']['company_revision']??-1)===$c['revision']){$economy[$order['id']]=$plan['snapshot']['economy'][$order['id']]??$economy[$order['id']];break;}
 $tasks=rows('SELECT t.*,o.title AS order_title,o.product FROM tasks t JOIN orders o ON o.id=t.order_id WHERE t.company_id=? AND t.state<>\'cancelled\' ORDER BY planned_start,t.id',[$c['id']]);
 $rawTasks=$tasks;$tasks=productionAssemblyTasks($orders,$tasks);$preparationTasks=array_values(array_filter($rawTasks,fn($t)=>!in_array($t['id'],array_column($tasks,'id'),true)));foreach($tasks as &$t){foreach(['quantity','released','transferred','accepted','rework','rework_ready','scrap','revision','is_final'] as $k)$t[$k]=(int)$t[$k];$t['dependencies']=decode($t['dependencies']);$t['planning_meta']=decode($t['planning_meta']??null);}unset($t);
 if($c['role']==='worker'){$tasks=array_values(array_filter($tasks,fn($t)=>$t['worker_id']===companyWorkerId($c)));}
 $events=rows('SELECT e.*,t.title AS task_title FROM production_events e JOIN tasks t ON t.id=e.task_id WHERE e.company_id=? ORDER BY e.created_at DESC,e.id DESC LIMIT 60',[$c['id']]);
 if($c['role']==='worker')$events=array_values(array_filter($events,fn($e)=>$e['actor_id']===$u['id']));
 $count=one('SELECT COUNT(*) AS n FROM production_events WHERE company_id=?',[$c['id']]);$forecastStale=$plans&&((int)$count['n']>(int)($plans[0]['snapshot']['source_event_count']??0));
 $audit=rows('SELECT action,details,created_at FROM audit_log WHERE company_id=? ORDER BY created_at DESC LIMIT 25',[$c['id']]);foreach($audit as &$a)$a['details']=decode($a['details']);unset($a);
 if(!$economic){unset($c['passport']['economy']);$audit=array_values(array_filter($audit,fn($a)=>!in_array($a['action'],['order_saved','passport_saved','economy_observed'],true)));}
 if($c['role']==='worker'){
  $workerId=companyWorkerId($c);$orderIds=array_unique(array_column($tasks,'order_id'));$orders=array_values(array_filter($orders,fn($o)=>in_array($o['id'],$orderIds,true)));unset($c['passport']['structure']);$c['passport']['people']=array_values(array_filter($c['passport']['people']??[],fn($p)=>$p['id']===$workerId));$taskResourceIds=array_unique(array_column($tasks,'resource_id'));$c['passport']['resources']=array_values(array_filter($c['passport']['resources']??[],fn($r)=>in_array($r['id'],$taskResourceIds,true)));$c['layout']=['width'=>18,'depth'=>12,'objects'=>[],'links'=>[]];$audit=[];
  foreach($plans as &$plan){unset($plan['snapshot']['structure_snapshot'],$plan['snapshot']['workspace_snapshot'],$plan['snapshot']['decision_summary']);$plan['snapshot']['schedule']=array_values(array_filter($plan['snapshot']['schedule']??[],fn($t)=>$t['worker_id']===$workerId));$ownResourceIds=array_column($plan['snapshot']['schedule'],'resource_id');$plan['snapshot']['resource_snapshot']=array_values(array_filter($plan['snapshot']['resource_snapshot']??[],fn($r)=>in_array($r['id'],$ownResourceIds,true)));$plan['snapshot']['people_snapshot']=array_values(array_filter($plan['snapshot']['people_snapshot']??[],fn($p)=>$p['id']===$workerId));$plan['snapshot']['layout']=['objects'=>[],'links'=>[]];$plan['snapshot']['routes']=array_intersect_key($plan['snapshot']['routes']??[],array_flip($orderIds));unset($plan['snapshot']['order_revisions'],$plan['snapshot']['order_finishes']);}unset($plan);
 }
 $finance=null;if($economic){require_once __DIR__.'/finance.php';$finance=financeState($orders,$plans,$tasks,$economy,$c['id']);}
 $demoRoles=[];if(!empty($_SESSION['demo_company'])&&$_SESSION['demo_company']===$c['id'])$demoRoles=rows('SELECT u.id,u.name,m.role FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.company_id=?',[$c['id']]);
 require_once __DIR__.'/ai/gateway.php';
 return ['user'=>$u,'company'=>$c,'companies'=>$companies,'orders'=>$orders,'plans'=>$plans,'tasks'=>$tasks,'preparation_tasks'=>$c['role']==='worker'?[]:$preparationTasks,'events'=>$events,'audit'=>$audit,'demoRoles'=>$demoRoles,'personAccounts'=>companyAccountSummaries($c),'demo'=>demoSessionInfo($c,$orders,$plans,$tasks,$audit),'csrf'=>$_SESSION['csrf'],'permissions'=>['economy'=>$economic],'economy'=>(object)$economy,'forecast_stale'=>(bool)$forecastStale,'ai'=>aiCapability(),...($economic?['finance'=>$finance]:[])];
}
function apiRun(string $action,array $input):array{
 global $config;
 if($action==='bootstrap'){
  $available=true;try{db()->query('SELECT 1 FROM users LIMIT 1');}catch(Throwable){$available=false;}
  $out=['csrf'=>$_SESSION['csrf'],'configured'=>$available,'authenticated'=>!empty($_SESSION['user_id']),'demo_enabled'=>$config['demo_enabled']??true];
  if($available&&$out['authenticated'])$out['state']=stateData();return $out;
 }
 if($action==='login'){
  $email=strtolower(textField($input,'email',190,true));$bucket=authLimit($email);$u=one('SELECT * FROM users WHERE email=?',[$email]);
  if(!$u||!password_verify((string)($input['password']??''),$u['password_hash'])){query('UPDATE auth_limits SET failures=failures+1 WHERE bucket=?',[$bucket]);fail('Проверьте почту и пароль',401);}
  $m=one('SELECT company_id FROM memberships WHERE user_id=? ORDER BY company_id LIMIT 1',[$u['id']]);if(!$m)fail('У аккаунта пока нет предприятия',403);
  unset($_SESSION['demo_company']);establish($u['id'],$m['company_id']);query('DELETE FROM auth_limits WHERE bucket=?',[$bucket]);return stateData();
 }
 if($action==='register'){
  $email=strtolower(textField($input,'email',190,true));authLimit($email);if(!filter_var($email,FILTER_VALIDATE_EMAIL))fail('Укажите корректную почту');
  $password=(string)($input['password']??'');if(strlen($password)<10||strlen($password)>128)fail('Пароль: от 10 до 128 символов');$name=textField($input,'name',100,true);$company=textField($input,'company',120,true);
  if(one('SELECT id FROM users WHERE email=?',[$email]))fail('Эта почта уже зарегистрирована',409);
  db()->beginTransaction();try{$id=uid();query('INSERT INTO users (id,email,name,password_hash) VALUES (?,?,?,?)',[$id,$email,$name,password_hash($password,PASSWORD_DEFAULT)]);$cid=companyNew($company,$id);db()->commit();establish($id,$cid);return stateData();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 }
 if($action==='demo_enter')return demoEnter();
 if($action==='logout'){$_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(24));return ['csrf'=>$_SESSION['csrf']];}
 if($action==='state')return stateData();
 if($action==='account_role'){$c=context();$role=textField($input,'role',20,true);if(!in_array($role,$c['account_roles']??[$c['role']],true))fail('Роль недоступна',403);$_SESSION['active_roles'][$c['id']]=$role;return stateData();}
 if($action==='catalog_search'){permit(['owner','technologist','chief']);require_once __DIR__.'/catalog.php';return ['operations'=>catalogFind(textField($input,'query',300),40)];}
 if($action==='technology_examples'){permit(['owner','technologist']);require_once __DIR__.'/catalog.php';return technologyExamples();}
 if($action==='company_switch'){$u=user();$m=one('SELECT company_id FROM memberships WHERE company_id=? AND user_id=?',[$input['id']??'',$u['id']]);if(!$m)fail('Нет доступа к компании',403);$_SESSION['company_id']=$m['company_id'];return stateData();}
 if($action==='company_create'){$u=user();$id=companyNew(textField($input,'name',120,true),$u['id']);$_SESSION['company_id']=$id;return stateData();}
 if($action==='demo_role'){
  $c=context();if(empty($_SESSION['demo_company'])||$_SESSION['demo_company']!==$c['id']||!$c['is_demo'])fail('Переключение доступно только в собственном демо-сеансе',403);
  $m=one('SELECT user_id FROM memberships WHERE company_id=? AND role=?',[$c['id'],$input['role']??'']);if(!$m)fail('Роль недоступна',403);establish($m['user_id'],$c['id']);return stateData();
 }
 $moduleActions=['resources'=>['workplace_open','workplace_start','workplace_finish'],'preparation'=>['preparation_open','preparation_save'],'analytics'=>['analytics_snapshot','analytics_ai_start','analytics_ai_step'],'optimization'=>['optimization_start','optimization_step','optimization_apply','optimization_ai_start','optimization_ai_step'],'goal_economy'=>['goal_economy_preview','goal_economy_ai_start','goal_economy_ai_step'],'enterprise'=>['enterprise_prepare','enterprise_start','enterprise_step','enterprise_apply'],'simulation'=>['simulation_intent','simulation_start','simulation_step','simulation_ai_start','simulation_ai_step','simulation_save','simulation_cases','simulation_case_open'],'documents'=>['document_extract','document_recent','document_open','document_analyze','document_step'],'assistant'=>['assistant_status','assistant_jobs'],'workspace'=>['workspace_open','workspace_save','workspace_preview'],'passport'=>['passport_save'],'goal'=>['order_save','order_archive'],'builder'=>['layout_save'],'plan'=>['route_save','plan_calculate','plan_approve','plan_publish'],'production'=>['production_event'],'finance'=>['economy_observe'],'technology'=>['technology_generate','technology_step','technology_status','technology_jobs']];
 foreach($moduleActions as $module=>$actions)if(in_array($action,$actions,true)){$file=__DIR__.'/modules/'.$module.'.php';if(!is_file($file))fail('Модуль подключается',503);require_once $file;$handler=$module.'Action';return $handler($action,$input);}
 if($action==='ai_request')fail('В разработке. Продолжите вручную.',501);
 fail('Действие не найдено',404);
}
