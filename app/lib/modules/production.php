<?php
declare(strict_types=1);require_once __DIR__.'/../preparation.php';
function taskRecord(string $id,bool $lock=false):array{$c=context();$t=one('SELECT * FROM tasks WHERE id=? AND company_id=?'.($lock?lockSuffix():''),[$id,$c['id']]);if(!$t||$t['state']==='cancelled')fail('Задание не найдено',404);return $t;}
function availableInput(array $t):int{
 $rawOrder=one('SELECT route FROM orders WHERE id=? AND company_id=?',[$t['order_id'],$t['company_id']]);$prepIds=array_column(productionPreparationRoute(decode($rawOrder['route']??null)),'id');$remaining=(int)$t['quantity']-(int)$t['released'];foreach(decode($t['dependencies']) as $id){$dep=one('SELECT * FROM tasks WHERE id=? AND company_id=?',[$id,$t['company_id']]);if(!$dep||$dep['order_id']!==$t['order_id'])fail('Источник относится к другой цели или отсутствует');if(in_array($dep['operation_id'],$prepIds,true)||!empty(decode($dep['planning_meta']??null)['cutting']))continue;$remaining=min($remaining,(int)$dep['transferred']-(int)$t['released']);}$prepared=preparationTaskAllowance($t);if($prepared!==null)$remaining=min($remaining,$prepared);return max(0,$remaining);
}
function productionAction(string $action,array $input):array{
 permit(['owner','foreman','chief','worker','qc']);return command($action,$input,function(array $c)use($input){
 $t=taskRecord(textField($input,'task_id',32,true),true);$kind=textField($input,'kind',30,true);$rawOrder=one('SELECT route FROM orders WHERE id=? AND company_id=?',[$t['order_id'],$c['id']]);if(in_array($t['operation_id'],array_column(productionPreparationRoute(decode($rawOrder['route']??null)),'id'),true))fail('Это историческая работа подготовки. Готовность компонентов подтверждается в отдельной комплектации.');$details=[];$qty=null;
 $quality=in_array($kind,['quality','rework_quality'],true);if($quality){if(!in_array($c['role'],['owner','qc'],true))fail('Качество подтверждает ОТК',403);}else{
  if($c['role']==='qc')fail('Эта роль подтверждает качество',403);
  if($c['role']==='worker'&&$t['worker_id']!==companyWorkerId($c))fail('Это задание другого исполнителя',403);
 }
 checkRevision((int)$t['revision'],$input);
 $state=$t['state'];$released=(int)$t['released'];$transferred=(int)$t['transferred'];$accepted=(int)$t['accepted'];$rework=(int)$t['rework'];$reworkReady=(int)$t['rework_ready'];$scrap=(int)$t['scrap'];$total=(int)$t['quantity'];
 if($kind==='start'){
  if(!in_array($state,['ready','paused'],true))fail('Задание уже начато или завершено');if(availableInput($t)<=0)fail('Сначала нужна передача партии из предыдущей операции');
  $busy=one("SELECT id,title FROM tasks WHERE company_id=? AND id<>? AND state='running' AND (resource_id=? OR worker_id=?) LIMIT 1",[$c['id'],$t['id'],$t['resource_id'],$t['worker_id']]);if($busy)fail('Центр или исполнитель занят: '.$busy['title']);$state='running';
 }elseif($kind==='pause'){
  if($state!=='running')fail('Приостановить можно только начатую работу');$details['reason']=textField($input,'reason',200,true);$state='paused';
 }elseif($kind==='release'){
  if($state!=='running')fail('Сначала начните задание');$qty=numberField($input,'quantity',1,100000);if($qty>availableInput($t))fail('Количество превышает доступную переданную партию или остаток');$released+=$qty;if($released===$total)$state='awaiting_transfer';
 }elseif($kind==='transfer'){
  $qty=numberField($input,'quantity',1,100000);if($qty>$released-$transferred)fail('Можно передать только выпущенный остаток');$transferred+=$qty;if($transferred===$total)$state=$t['is_final']?'quality':'completed';
 }elseif($kind==='quality'||$kind==='rework_quality'){
  if(!$t['is_final'])fail('Итоговый ОТК проводится после конечной операции');$qty=numberField($input,'quantity',1,100000);$a=numberField($input,'accepted',0,100000);$r=numberField($input,'rework',0,100000);$s=numberField($input,'scrap',0,100000);if($a+$r+$s!==$qty)fail('Проверено должно равняться: принято + доработка + брак');
  $pending=$kind==='quality'?$transferred-$accepted-$rework-$scrap:$reworkReady;if($qty>$pending)fail('Проверяемый объём превышает очередь ОТК');
  $reason=textField($input,'reason',200);if(($r||$s)&&$reason==='')fail('Укажите причину доработки или брака');
  $accepted+=$a;$scrap+=$s;if($kind==='quality')$rework+=$r;else{$rework-=($a+$s);$reworkReady-=$qty;}
  $details=['accepted'=>$a,'rework'=>$r,'scrap'=>$s,'reason'=>$reason];
 }elseif($kind==='rework_release'){
  $qty=numberField($input,'quantity',1,100000);if($qty>$rework-$reworkReady)fail('Количество превышает остаток доработки');$reworkReady+=$qty;$details['reason']=textField($input,'reason',200,true);
 }else fail('Неизвестное производственное действие');
 if($released>$total||$transferred>$released||$accepted+$rework+$scrap>$transferred||$reworkReady>$rework)fail('Нарушен баланс партии');
 if($t['is_final']&&$transferred===$total){if($accepted+$scrap===$total&&$rework===0)$state='completed';elseif($rework>0)$state='rework';else $state='quality';}
 query('UPDATE tasks SET state=?,released=?,transferred=?,accepted=?,rework=?,rework_ready=?,scrap=?,revision=revision+1 WHERE id=? AND company_id=?',[$state,$released,$transferred,$accepted,$rework,$reworkReady,$scrap,$t['id'],$c['id']]);
 $details['_task_revision']=(int)$t['revision']+1;$eventId=uid();query('INSERT INTO production_events (id,company_id,task_id,actor_id,kind,quantity,details) VALUES (?,?,?,?,?,?,?)',[$eventId,$c['id'],$t['id'],$_SESSION['user_id'],$kind,$qty,j($details)]);audit('production_event',['task_id'=>$t['id'],'kind'=>$kind,'quantity'=>$qty]);return ['saved'=>true,'event_id'=>$eventId,'revision'=>(int)$t['revision']+1];
 });
}
