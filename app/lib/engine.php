<?php
declare(strict_types=1);
require_once __DIR__.'/preparation.php';
const ENGINE_VERSION='easy3-assembly-preparation/3.0';
function routeCheck(array $route):array{
 if(!$route)return ['Технология ещё не собрана'];$errors=[];$map=[];$used=[];$degree=[];$next=[];
 foreach($route as $op){$id=$op['id'];if(isset($map[$id]))$errors[]='Повторяется код операции';$map[$id]=$op;$degree[$id]=0;if(($op['seconds']??null)===null)$errors[]='Нет нормы: '.$op['name'];if(!empty($op['ai_origin'])&&empty($op['norm_reviewed']))$errors[]='Нет подтверждения нормы AI: '.$op['name'];if(empty($op['source']))$errors[]='Нет источника нормы: '.$op['name'];}
 foreach($route as $op)foreach($op['dependencies']??[] as $d){if(!isset($map[$d])||$d===$op['id']){$errors[]='Некорректная зависимость: '.$op['name'];continue;}$degree[$op['id']]++;$next[$d][]=$op['id'];$used[$d]=true;}
 $queue=[];foreach($degree as $id=>$n)if(!$n)$queue[]=$id;$seen=0;for($i=0;$i<count($queue);$i++){$seen++;foreach($next[$queue[$i]]??[] as $id)if(--$degree[$id]===0)$queue[]=$id;}if($seen!==count($map))$errors[]='В технологии обнаружен цикл';if(count(array_diff_key($map,$used))!==1)$errors[]='Нужна одна конечная операция выпуска';return array_values(array_unique($errors));
}
function sortedRoute(array $route):array{$map=array_column($route,null,'id');$degree=[];$next=[];$queue=[];foreach($route as $op){$degree[$op['id']]=count($op['dependencies']??[]);foreach($op['dependencies']??[] as $d)$next[$d][]=$op['id'];if(!$degree[$op['id']])$queue[]=$op['id'];}$out=[];for($i=0;$i<count($queue);$i++){$id=$queue[$i];$out[]=$map[$id];foreach($next[$id]??[] as $n)if(--$degree[$n]===0)$queue[]=$n;}if(count($out)!==count($route))fail('Технология содержит цикл');return $out;}
function minuteOfDay(string $s):int{[$h,$m]=array_map('intval',explode(':',$s));return $h*60+$m;}
function dayWindows(DateTimeImmutable $day,array $shift):array{
 if(!in_array((int)$day->format('N'),$shift['days']??[],true))return [];
 $start=minuteOfDay($shift['start']);$end=minuteOfDay($shift['end']);if($end<=$start)$end+=1440;$break=minuteOfDay($shift['break_start']);if($break<$start)$break+=1440;$breakEnd=$break+(int)$shift['break_minutes'];$midnight=$day->setTime(0,0)->getTimestamp();
 if((int)$shift['break_minutes']<=0||$break>=$end||$breakEnd<=$start)return [[$midnight+$start*60,$midnight+$end*60]];
 $out=[];if($break>$start)$out[]=[$midnight+$start*60,$midnight+min($break,$end)*60];if($breakEnd<$end)$out[]=[$midnight+max($breakEnd,$start)*60,$midnight+$end*60];return $out;
}
function fitWork(int $after,int $seconds,array $shift,array $busy,string $timezone):array|null{
 $zone=new DateTimeZone($timezone);$day=(new DateTimeImmutable('@'.$after))->setTimezone($zone)->setTime(0,0)->modify('-1 day');$slots=[];$remaining=$seconds;
 usort($busy,fn($a,$b)=>$a[0]<=>$b[0]);
 for($i=0;$i<370&&$remaining>0;$i++,$day=$day->modify('+1 day')){
  foreach(dayWindows($day,$shift) as [$s,$end]){
   $s=max($s,$after);if($s>=$end)continue;$free=[[$s,$end]];
   foreach($busy as [$bs,$be]){if($be<=$s||$bs>=$end)continue;$next=[];foreach($free as [$fs,$fe]){if($be<=$fs||$bs>=$fe){$next[]=[$fs,$fe];continue;}if($bs>$fs)$next[]=[$fs,min($bs,$fe)];if($be<$fe)$next[]=[max($be,$fs),$fe];}$free=$next;}
   foreach($free as [$fs,$fe]){if($remaining<=0)break;$work=min($remaining,$fe-$fs);if($work>0){$slots[]=[$fs,$fs+$work];$remaining-=$work;}}
  }
 }
 return $remaining>0||!$slots?null:['slots'=>$slots,'start'=>$slots[0][0],'end'=>$slots[count($slots)-1][1]];
}
function engineHash(array $c,array $orders,array $tasks):string{
 usort($orders,fn($a,$b)=>strcmp($a['id'],$b['id']));usort($tasks,fn($a,$b)=>strcmp($a['id'],$b['id']));
 return hash('sha256',j(['version'=>ENGINE_VERSION,'company_revision'=>(int)$c['revision'],'orders'=>array_map(fn($o)=>[$o['id'],(int)$o['revision']],$orders),'tasks'=>array_map(fn($t)=>[$t['id'],(int)$t['revision'],$t['state']],$tasks)]));
}
function engineInputs(array $c):array{
 $orders=rows("SELECT * FROM orders WHERE company_id=? AND status<>'archived' ORDER BY deadline,id",[$c['id']]);foreach($orders as &$o){$raw=decode($o['route']);$o['preparation_route']=productionPreparationRoute($raw);$o['route']=productionAssemblyRoute($raw);$o['expenses']=decode($o['expenses']);$o['preparation']=preparationState($c,$o);$o['_preparation_cost_unknown']=($o['preparation']['cost_mode']??'unknown')==='unknown';}unset($o);
 $tasks=rows("SELECT * FROM tasks WHERE company_id=? AND state<>'cancelled' ORDER BY id",[$c['id']]);return [$orders,productionAssemblyTasks($orders,$tasks)];
}
// Exact integer cents, rounded half up; no floating-point money arithmetic.
const DEMO_MONEY_LIMIT=1000000000000;
function gcdInt(int $a,int $b):int{while($b){[$a,$b]=[$b,$a%$b];}return max(1,$a);}
function roundedRatio(int $a,int $b,int $denominator):?int{
 if($denominator<=0||$a<0||$b<0)return null;if($a===0||$b===0)return 0;
 $g=gcdInt($a,$denominator);$a=intdiv($a,$g);$denominator=intdiv($denominator,$g);
 $g=gcdInt($b,$denominator);$b=intdiv($b,$g);$denominator=intdiv($denominator,$g);
 if($a>intdiv(PHP_INT_MAX,$b))return null;$product=$a*$b;$q=intdiv($product,$denominator);$r=$product%$denominator;
 if($r>=intdiv($denominator,2)+$denominator%2)$q++;return $q>DEMO_MONEY_LIMIT?null:$q;
}
function orderEconomy(array $order,array $passport,?int $plannedSeconds=null):array{
 $ec=$passport['economy']??[];$seconds=$plannedSeconds??0;$missing=[];if(!empty($order['_preparation_cost_unknown']))$missing[]='Уточните стоимость подготовки и комплектации отдельно от пошива';foreach($order['route'] as $op){if(($op['seconds']??null)===null)$missing[]='Нет нормы: '.$op['name'];elseif($plannedSeconds===null)$seconds+=(int)$op['seconds']*(int)$order['quantity']+(int)($op['setup_minutes']??0)*60;}
 $labor=isset($ec['labor_hour_cents'])?roundedRatio($seconds,(int)$ec['labor_hour_cents'],3600):null;
 $common=isset($ec['monthly_common_cents'])&&!empty($ec['monthly_hours'])?roundedRatio($seconds,(int)$ec['monthly_common_cents'],3600*(int)$ec['monthly_hours']):null;
 $direct=array_sum(array_column($order['expenses'],'cents'));
 $cost=$labor===null||$common===null||$missing||!$order['route']?null:$labor+$common+$direct;if($cost!==null&&$cost>DEMO_MONEY_LIMIT){$cost=null;$missing[]='Масштаб суммы превышает границу демо';}
 if($labor===null)$missing[]=isset($ec['labor_hour_cents'])?'Масштаб труда превышает границу демо':'Неизвестна ставка труда';if($common===null)$missing[]=isset($ec['monthly_common_cents'])&&!empty($ec['monthly_hours'])?'Масштаб общих расходов превышает границу демо':'Неизвестны общие расходы';$missing=array_merge($missing,$ec['unknown']??[]);
 $price=$order['price_cents'];$value=$order['mode']==='CONTRACT'&&$price!==null?(int)$price*($order['price_basis']==='total'?1:(int)$order['quantity']):null;
 return ['quantity'=>(int)$order['quantity'],'norm_seconds'=>$seconds,'scope'=>'Производственный контур','status'=>'partial','cost_cents'=>$cost,'labor_cents'=>$labor,'common_cents'=>$common,'direct_cents'=>$direct,'unit_cents'=>$cost===null?null:roundedRatio($cost,1,(int)$order['quantity']),'value_cents'=>$value,'result_cents'=>$cost===null||$value===null?null:$value-$cost,'unknown'=>$missing,'excluded'=>$ec['excluded']??[],'method'=>'Прямые статьи + нормированный труд + доля общих расходов по трудочасам'];
}
function calculateLegacyPlan(array $c,array $orders,array $tasks,string $strategy='deadline'):array{
 $p=$c['passport'];$zone=$p['timezone']??'Europe/Moscow';$tz=new DateTimeZone($zone);$shift=$p['shift']??[];$now=time();$errors=[];$warnings=[];$schedule=[];$busy=[];$done=[];
 if(!$orders)$errors[]='Создайте хотя бы одну цель';if(empty($shift['days']))$errors[]='Не заданы рабочие дни';
 $resources=array_values(array_filter($p['resources']??[],fn($r)=>$r['active']??false));$people=array_column($p['people']??[],null,'id');
 foreach($orders as $o)foreach(routeCheck($o['route']) as $error)$errors[]=$o['title'].': '.$error;
 if($errors)return ['engine'=>ENGINE_VERSION,'status'=>'incomplete','errors'=>$errors,'warnings'=>[],'schedule'=>[],'strategy'=>$strategy,'calculated_at'=>date('c'),'timezone'=>$zone];
 $pinned=[];foreach($tasks as $t)if((int)$t['released']>0||in_array($t['state'],['running','paused','completed'],true))$pinned[$t['order_id'].'/'.$t['operation_id']]=$t;
 usort($orders,$strategy==='compact'?fn($a,$b)=>(int)$a['quantity']<=>(int)$b['quantity']?:strcmp($a['deadline'],$b['deadline']):fn($a,$b)=>strcmp($a['deadline'],$b['deadline'])?:strcmp($a['id'],$b['id']));
 // Reserve already started/factual work before choosing any new placement.
 foreach($pinned as $key=>$t){$s=(new DateTimeImmutable($t['planned_start'],$tz))->getTimestamp();$end=(new DateTimeImmutable($t['planned_end'],$tz))->getTimestamp();if($t['state']!=='completed'&&$end<$now)$end=$now+3600;if($end>$now&&$t['state']!=='completed'){foreach(['r:'.$t['resource_id'],'p:'.$t['worker_id']] as $bucket)$busy[$bucket][]= [max($s,$now),$end];}$done[$key]=$end;}
 foreach($orders as $o){$route=sortedRoute($o['route']);$sink=$route[count($route)-1]['id'];$start=(new DateTimeImmutable($o['start_date'].' 00:00:00',$tz))->getTimestamp();
  foreach($route as $op){$key=$o['id'].'/'.$op['id'];$quantity=(int)$o['quantity'];$earliest=max($now,$start);foreach($op['dependencies'] as $dep)$earliest=max($earliest,$done[$o['id'].'/'.$dep]??$earliest);
   if(isset($pinned[$key])){$t=$pinned[$key];$schedule[]=['order_id'=>$o['id'],'operation_id'=>$op['id'],'title'=>$t['title'],'resource_id'=>$t['resource_id'],'worker_id'=>$t['worker_id'],'quantity'=>(int)$t['quantity'],'start'=>$t['planned_start'],'end'=>date('Y-m-d H:i:s',$done[$key]),'slots'=>[],'seconds'=>(int)$op['seconds']*max(0,(int)$t['quantity']-(int)$t['released']),'dependencies'=>$op['dependencies'],'is_final'=>$op['id']===$sink,'pinned'=>true,'task_id'=>$t['id']];continue;}
   $seconds=(int)$op['seconds']*$quantity+(int)$op['setup_minutes']*60;$best=null;
   foreach($resources as $r){if($r['type']!==$op['type'])continue;$person=$people[$r['worker']??'']??null;if(!$person||empty($person['active'])||!in_array($op['type'],$person['skills']??[],true))continue;$blocked=array_merge($busy['r:'.$r['id']]??[],$busy['p:'.$person['id']]??[]);$fit=fitWork($earliest,$seconds,$shift,$blocked,$zone);if(!$fit)continue;if(!$best||$fit['end']<$best['end'])$best=$fit+['resource'=>$r,'person'=>$person];}
   if(!$best){$errors[]=$o['title'].': нет доступного центра и исполнителя для «'.$op['name'].'»';break;}
   foreach($best['slots'] as $slot){$busy['r:'.$best['resource']['id']][]=$slot;$busy['p:'.$best['person']['id']][]=$slot;}
   $done[$key]=$best['end'];$schedule[]=['order_id'=>$o['id'],'operation_id'=>$op['id'],'title'=>$op['name'],'resource_id'=>$best['resource']['id'],'worker_id'=>$best['person']['id'],'quantity'=>$quantity,'start'=>date('Y-m-d H:i:s',$best['start']),'end'=>date('Y-m-d H:i:s',$best['end']),'slots'=>array_map(fn($slot)=>[date('Y-m-d H:i:s',$slot[0]),date('Y-m-d H:i:s',$slot[1])],$best['slots']),'seconds'=>$seconds,'dependencies'=>$op['dependencies'],'is_final'=>$op['id']===$sink,'pinned'=>false];
  }
 }
 $late=0;$ends=[];foreach($orders as $o){$matches=array_filter($schedule,fn($x)=>$x['order_id']===$o['id']);$end=$matches?max(array_column($matches,'end')):null;if($end){$ends[$o['id']]=$end;if(substr($end,0,10)>$o['deadline']){$late++;$warnings[]=$o['title'].': прогноз позже коммерческого срока';}}}
 $economy=[];foreach($orders as $o)$economy[$o['id']]=orderEconomy($o,$p);
 return ['engine'=>ENGINE_VERSION,'status'=>$errors?'incomplete':'feasible','errors'=>$errors,'warnings'=>$warnings,'schedule'=>$schedule,'strategy'=>$strategy,'calculated_at'=>date('c'),'timezone'=>$zone,'order_finishes'=>$ends,'late_orders'=>$late,'finish'=>$schedule?max(array_column($schedule,'end')):null,'economy'=>$economy,'layout'=>$c['layout'],'resource_snapshot'=>$p['resources']??[],'structure_snapshot'=>$p['structure']??null,'people_snapshot'=>$p['people']??[],'shift_snapshot'=>$shift,'order_revisions'=>array_column($orders,'revision','id'),'routes'=>array_column($orders,'route','id'),'assumptions'=>['Учебные нормы, указанные в операциях','Эвристика списка работ; оптимальность не доказана','Операция планируется целым объёмом с разрывом на смены','Параллельные ветви допустимы; количественные коэффициенты узлов 1:1','Материалы предполагаются готовыми; склад и поступления не моделируются','Начатые задания сохраняют факты и назначения. Просроченный начатый интервал получает допущение +1 час']];
}
function publishSchedule(array $c,array $plan):void{
 $snapshot=decode($plan['snapshot']);$entries=$snapshot['schedule'];$modern=isset($snapshot['workspace_snapshot']);$ids=[];$retained=[];
 foreach($entries as $t){$key=$modern?$t['job_key']:$t['order_id'].'/'.$t['operation_id'];$ids[$key]=!empty($t['task_id'])?$t['task_id']:uid();if(!empty($t['retained'])||!empty($t['pinned']))$retained[$ids[$key]]=true;}
 foreach(rows("SELECT id FROM tasks WHERE company_id=? AND state='ready' AND released=0",[$c['id']]) as $t)if(!isset($retained[$t['id']]))query("UPDATE tasks SET state='cancelled',revision=revision+1 WHERE id=? AND company_id=?",[$t['id'],$c['id']]);
 foreach($entries as $t){if(!empty($t['pinned']))continue;$key=$modern?$t['job_key']:$t['order_id'].'/'.$t['operation_id'];$dependencies=array_map(fn($dep)=>$ids[$modern?$dep:$t['order_id'].'/'.$dep],$t['dependencies']);$meta=$t['meta']??[];$meta['slots']=$t['slots'];$meta['workspace_revision']=$snapshot['workspace_snapshot'][$t['order_id']]['revision']??null;
  if(!empty($t['retained'])){query('UPDATE tasks SET plan_id=?,resource_id=?,worker_id=?,dependencies=?,planned_start=?,planned_end=?,planning_meta=?,revision=revision+1 WHERE id=? AND company_id=?',[$plan['id'],$t['resource_id'],$t['worker_id'],j($dependencies),$t['start'],$t['end'],j($meta),$ids[$key],$c['id']]);continue;}
  if($modern)query('INSERT INTO tasks (id,company_id,order_id,plan_id,operation_id,title,resource_id,worker_id,quantity,is_final,dependencies,planned_start,planned_end,planning_meta) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$ids[$key],$c['id'],$t['order_id'],$plan['id'],$t['operation_id'],$t['title'],$t['resource_id'],$t['worker_id'],$t['quantity'],$t['is_final']?1:0,j($dependencies),$t['start'],$t['end'],j($meta)]);
  else query('INSERT INTO tasks (id,company_id,order_id,plan_id,operation_id,title,resource_id,worker_id,quantity,is_final,dependencies,planned_start,planned_end) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$ids[$key],$c['id'],$t['order_id'],$plan['id'],$t['operation_id'],$t['title'],$t['resource_id'],$t['worker_id'],$t['quantity'],$t['is_final']?1:0,j($dependencies),$t['start'],$t['end']]);
 }
 query("UPDATE plan_versions SET state='published',published_at=CURRENT_TIMESTAMP WHERE id=? AND company_id=?",[$plan['id'],$c['id']]);foreach(array_keys($snapshot['order_revisions']) as $orderId)query("UPDATE orders SET status='active' WHERE id=? AND company_id=?",[$orderId,$c['id']]);
}

require_once __DIR__.'/planning.php';
