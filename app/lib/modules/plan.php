<?php
declare(strict_types=1);
require_once __DIR__.'/../engine.php';require_once __DIR__.'/../technology.php';require_once __DIR__.'/goal.php';require_once __DIR__.'/../ai/route.php';
function planRecord(string $id,bool $lock=false):array{$c=context();$p=one('SELECT * FROM plan_versions WHERE id=? AND company_id=?'.($lock?lockSuffix():''),[$id,$c['id']]);if(!$p)fail('Вариант не найден',404);return $p;}
function planAction(string $action,array $input):array{
 if($action==='route_save')return saveTechnology($input);
 if($action==='route_save')permit(['owner','technologist']);elseif($action==='plan_publish')permit(['owner','chief']);else permit(['owner','foreman','technologist','chief']);
 return command($action,$input,function(array $c)use($action,$input){
  [$orders,$tasks]=engineInputs($c);$hash=engineHash($c,$orders,$tasks);
  if($action==='plan_calculate'){
   $strategy=$input['strategy']??'deadline';if(!in_array($strategy,['deadline','compact'],true))fail('Выберите вариант расчёта');$snapshot=calculatePlan($c,$orders,$tasks,$strategy);$snapshot['source_event_count']=(int)one('SELECT COUNT(*) AS n FROM production_events WHERE company_id=?',[$c['id']])['n'];$snapshot['input_hash']=$hash;$snapshot['company_revision']=$c['revision'];
   $row=one('SELECT MAX(version_no) AS n FROM plan_versions WHERE company_id=?',[$c['id']]);$version=(int)($row['n']??0)+1;$id=uid();query('INSERT INTO plan_versions (id,company_id,version_no,snapshot,input_hash,approvals,creator_id) VALUES (?,?,?,?,?,?,?)',[$id,$c['id'],$version,j($snapshot),$hash,'[]',$_SESSION['user_id']]);audit('plan_calculated',['id'=>$id,'version'=>$version,'status'=>$snapshot['status']]);return ['saved'=>true,'id'=>$id,'status'=>$snapshot['status']];
  }
  $p=planRecord(textField($input,'id',32,true),true);if($p['state']==='published')fail('Опубликованный план неизменяем. Рассчитайте новый вариант.');if(!hash_equals($p['input_hash'],$hash))fail('Основа изменилась. Рассчитайте новый вариант и согласуйте его.',409);$snapshot=decode($p['snapshot']);if($snapshot['status']!=='feasible')fail('Сначала устраните замечания и получите допустимое расписание');
  if($action==='plan_approve'){
   $approvals=decode($p['approvals']);$stages=['foreman','technologist','chief'];$next=$stages[count($approvals)]??null;if(!$next)fail('План уже прошёл согласование');$stage=textField($input,'stage',20,true);if($stage!==$next)fail('Сначала нужен предыдущий этап согласования');if($c['role']!=='owner'&&$c['role']!==$stage)fail('Этот этап согласует другая роль',403);
   $approvals[]=['stage'=>$stage,'actor_id'=>$_SESSION['user_id'],'at'=>date('c'),'input_hash'=>$hash];$state=count($approvals)===3?'approved':'review';query('UPDATE plan_versions SET approvals=?,state=? WHERE id=? AND company_id=?',[j($approvals),$state,$p['id'],$c['id']]);audit('plan_approved',['id'=>$p['id'],'stage'=>$stage]);return ['saved'=>true];
  }
  if($action==='plan_publish'){if($p['state']!=='approved')fail('Пройдите три этапа согласования');publishSchedule($c,$p);audit('plan_published',['id'=>$p['id'],'version'=>(int)$p['version_no']]);return ['saved'=>true];}
  fail('Действие не найдено');
 });
}
