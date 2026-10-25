<?php
declare(strict_types=1);
require_once __DIR__.'/../organization.php';

function workplaceRecord(array $c,string $id):array{
 foreach(companyWorkplaces($c['passport']) as $w)if($w['id']===$id)return $w;fail('Рабочее место не найдено в этой компании',404);
}
function workplaceWorker(array $c,array $w):?array{
 $id=companyWorkerId($c);$people=array_column($c['passport']['people'],null,'id');$p=$id?($people[$id]??null):null;
 if(!empty($c['is_demo'])&&($_SESSION['demo_company']??null)===$c['id']){
  $current=companyStorage('workplace_sessions')?one('SELECT person_id FROM workplace_sessions WHERE company_id=? AND workplace_id=? AND user_id=? AND finished_at IS NULL',[$c['id'],$w['id'],$_SESSION['user_id']]):null;
  if($current&&isset($people[$current['person_id']]))$p=$people[$current['person_id']];
  if(!$p||$p['group']!=='executors'||empty($p['active'])||$p['workshop']!==$w['workshop']){
   $preferred=[];foreach($c['passport']['resources'] as $resource)if(in_array($resource['id'],$w['equipment'],true)&&!empty($resource['worker']))$preferred[]=$resource['worker'];
   $pool=array_values(array_filter($people,fn($person)=>$person['group']==='executors'&&!empty($person['active'])&&$person['workshop']===$w['workshop']));usort($pool,fn($a,$b)=>(in_array($b['id'],$preferred,true)<=>in_array($a['id'],$preferred,true))?:strcmp($a['id'],$b['id']));$p=$pool[0]??null;
  }
  if($p)$_SESSION['worker_resource']=$p['id'];
 }

 return $p&&$p['group']==='executors'&&!empty($p['active'])&&$p['workshop']===$w['workshop']?$p:null;
}
function workplaceView(array $c,array $w):array{
 $tracking=companyStorage('workplace_sessions');
 $worker=workplaceWorker($c,$w);$manager=in_array($c['role'],['owner','chief','technologist','foreman'],true);
 if(!$manager&&!$worker)fail('Это рабочее место не относится к вашему подразделению',403);
 $tz=new DateTimeZone($c['passport']['timezone']);$from=new DateTimeImmutable('today',$tz);$until=$from->modify('+1 day');$utc=new DateTimeZone('UTC');$params=[$c['id'],$w['id'],$until->setTimezone($utc)->format('Y-m-d H:i:s'),$from->setTimezone($utc)->format('Y-m-d H:i:s')];$scope='';if(!$manager){$scope=' AND user_id=?';$params[]=$_SESSION['user_id'];}
 $sessions=$tracking?rows('SELECT id,person_id,workplace_name,department_name,timezone,started_at,finished_at FROM workplace_sessions WHERE company_id=? AND workplace_id=? AND started_at<? AND (finished_at IS NULL OR finished_at>=?)'.$scope.' ORDER BY started_at DESC LIMIT 100',$params):[];$names=array_column($c['passport']['people'],'name','id');foreach($sessions as &$s)$s['person_name']=$names[$s['person_id']]??'Участник';unset($s);
 $active=$tracking?one('SELECT id,workplace_id,workplace_name,started_at FROM workplace_sessions WHERE company_id=? AND user_id=? AND finished_at IS NULL',[$c['id'],$_SESSION['user_id']]):null;
 $tasks=[];if($worker)foreach(rows("SELECT id,title,order_id,resource_id,quantity,released,accepted,state,planned_start,planned_end FROM tasks WHERE company_id=? AND worker_id=? AND state<>'cancelled' ORDER BY planned_start",[$c['id'],$worker['id']]) as $t)if(in_array($t['resource_id'],$w['equipment'],true)&&substr($t['planned_start'],0,10)<=$from->format('Y-m-d')&&substr($t['planned_end'],0,10)>=$from->format('Y-m-d'))$tasks[]=$t;
 $departments=array_column($c['passport']['structure']['workshops'],'name','id');return ['company_id'=>$c['id'],'company_name'=>$c['name'],'workplace'=>$w,'department_name'=>$departments[$w['workshop']]??'','timezone'=>$tz->getName(),'date'=>$from->format('Y-m-d'),'can_start'=>$worker!==null&&$tracking&&!empty($w['active']),'start_reason'=>!$tracking?'Для учёта времени импортируйте SQL обновления QR/демо.':(empty($w['active'])?'Место приостановлено. Включите его в информации о компании.':($worker===null?'Для начала работы войдите под аккаунтом исполнителя этого подразделения.':'')),'person_name'=>$worker['name']??user()['name'],'active'=>$active,'sessions'=>$sessions,'tasks'=>$tasks];
}
function resourcesAction(string $action,array $input):array{
 if($action==='workplace_open'){$u=user();$requested=textField($input,'company_id',32);if($requested&&$requested!==($_SESSION['company_id']??'')){if(!one('SELECT user_id FROM memberships WHERE company_id=? AND user_id=?',[$requested,$u['id']]))fail('Нет доступа к компании рабочего места',403);$_SESSION['company_id']=$requested;}$c=context();return workplaceView($c,workplaceRecord($c,organizationId($input,'workplace_id')));}
 if(!in_array($action,['workplace_start','workplace_finish'],true))fail('Неизвестное действие рабочего места',404);
 return command($action,$input,function(array $c)use($action,$input){
  $w=workplaceRecord($c,organizationId($input,'workplace_id'));$p=workplaceWorker($c,$w);if(!$p)fail('Начать или закончить работу может исполнитель этого подразделения',403);if(!companyStorage('workplace_sessions'))fail('Нужен SQL обновления 25.10.2026',503);
  $active=one('SELECT * FROM workplace_sessions WHERE company_id=? AND user_id=? AND finished_at IS NULL'.lockSuffix(),[$c['id'],$_SESSION['user_id']]);
  if($action==='workplace_start'){
   if(empty($w['active']))fail('Место приостановлено. Включите его в информации о компании.',409);
   if($active&&$active['workplace_id']!==$w['id'])fail('Сначала завершите работу на месте «'.$active['workplace_name'].'»',409);
   if(!$active){$names=array_column($c['passport']['structure']['workshops'],'name','id');query('INSERT INTO workplace_sessions (id,company_id,person_id,user_id,workplace_id,workplace_name,department_name,timezone,started_at,active_key) VALUES (?,?,?,?,?,?,?,?,?,?)',[uid(),$c['id'],$p['id'],$_SESSION['user_id'],$w['id'],$w['name'],$names[$w['workshop']]??'',$c['passport']['timezone'],gmdate('Y-m-d H:i:s'),hash('sha256',$c['id'].':'.$_SESSION['user_id'])]);audit('workplace_started',['workplace_id'=>$w['id'],'person_id'=>$p['id']]);}
  }elseif($active){if($active['workplace_id']!==$w['id'])fail('Активный сеанс относится к другому рабочему месту',409);query('UPDATE workplace_sessions SET finished_at=?,active_key=NULL WHERE id=? AND company_id=?',[gmdate('Y-m-d H:i:s'),$active['id'],$c['id']]);audit('workplace_finished',['workplace_id'=>$w['id'],'person_id'=>$p['id']]);}
  return workplaceView($c,$w);
 });
}
