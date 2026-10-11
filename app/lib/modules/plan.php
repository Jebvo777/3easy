<?php
declare(strict_types=1);
require_once __DIR__.'/../engine.php';require_once __DIR__.'/goal.php';
function planRecord(string $id,bool $lock=false):array{$c=context();$p=one('SELECT * FROM plan_versions WHERE id=? AND company_id=?'.($lock?lockSuffix():''),[$id,$c['id']]);if(!$p)fail('Вариант не найден',404);return $p;}
function planAction(string $action,array $input):array{
 if($action==='route_save')permit(['owner','technologist']);elseif($action==='plan_publish')permit(['owner','chief']);else permit(['owner','foreman','technologist','chief']);
 return command($action,$input,function(array $c)use($action,$input){
  if($action==='route_save'){
   $o=orderRecord(textField($input,'order_id',32,true),true);checkRevision((int)$o['revision'],$input);
   if(one("SELECT id FROM tasks WHERE company_id=? AND order_id=? AND state<>'cancelled' LIMIT 1",[$c['id'],$o['id']]))fail('Технология опубликована. В демо она закреплена за заданиями; новую технологию создайте для новой цели.');
   $route=$input['route']??[];if(!is_array($route)||count($route)>40)fail('Демо поддерживает до 40 операций');$clean=[];
   foreach($route as $op){$id=textField($op,'id',60,true);if(!preg_match('/^[a-zA-Z0-9_-]+$/',$id))fail('Некорректный код операции');$type=textField($op,'type',20,true);if(!in_array($type,['cut','sew','overlock','iron','qc','pack'],true))fail('Неизвестное требование к ресурсу');$dep=$op['dependencies']??[];if(!is_array($dep)||count($dep)>10)fail('Некорректные зависимости');foreach($dep as $d)if(!is_string($d)||strlen($d)>60)fail('Некорректная связь');$clean[]=['id'=>$id,'name'=>textField($op,'name',180,true),'type'=>$type,'seconds'=>numberField($op,'seconds',1,36000,true),'setup_minutes'=>numberField($op,'setup_minutes',0,480),'dependencies'=>array_values(array_unique($dep)),'source'=>textField($op,'source',300),'batch'=>numberField($op,'batch',1,100000)];}
   // Incomplete norms can be saved; structural cycles and broken links cannot.
   foreach(routeCheck($clean) as $err)if(!str_starts_with($err,'Нет нормы')&&!str_starts_with($err,'Нет источника')&&!str_starts_with($err,'Технология ещё'))fail($err);
   query('UPDATE orders SET route=?,revision=revision+1 WHERE id=? AND company_id=?',[j($clean),$o['id'],$c['id']]);audit('route_saved',['order_id'=>$o['id'],'operations'=>count($clean)]);return ['saved'=>true];
  }
  [$orders,$tasks]=engineInputs($c);$hash=engineHash($c,$orders,$tasks);
  if($action==='plan_calculate'){
   $strategy=$input['strategy']??'deadline';if(!in_array($strategy,['deadline','compact'],true))fail('Выберите вариант расчёта');$snapshot=calculatePlan($c,$orders,$tasks,$strategy);$snapshot['input_hash']=$hash;$snapshot['company_revision']=$c['revision'];
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
