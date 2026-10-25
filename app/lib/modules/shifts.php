<?php
declare(strict_types=1);
require_once __DIR__.'/../engine.php';require_once __DIR__.'/goal.php';
function shiftsAction(string $action,array $input):array{
 $c=permit(['owner','chief','foreman','technologist','worker']);$day=dateField($input,'day');$allTasks=rows("SELECT * FROM tasks WHERE company_id=? AND state<>'cancelled' ORDER BY planned_start,id",[$c['id']]);$schedule=[];$orderIds=[];
 foreach($allTasks as $t){$m=decode($t['planning_meta']??null);$op=manufacturingTaskOperation($t);if(!$op||($op['type']??'')==='cut')continue;$orderIds[$t['order_id']]=true;$schedule[]=['order_id'=>$t['order_id'],'operation_id'=>$t['operation_id'],'worker_id'=>$t['worker_id'],'resource_id'=>$t['resource_id'],'quantity'=>(int)$t['quantity'],'is_final'=>(bool)$t['is_final'],'start'=>$t['planned_start'],'end'=>$t['planned_end'],'slots'=>$m['slots']??[[$t['planned_start'],$t['planned_end']]],'meta'=>$m+['operation_snapshot'=>$op],'job_key'=>$t['id']];}
 $items=[];foreach(array_keys($orderIds) as $id){$o=orderRecord($id);$o['route']=productionAssemblyRoute(decode($o['route']));$workspace=workspaceRecord($c,$o)['state']??workspaceDefault($o);$projection=manufacturingShiftProjection($c,$o,$workspace,$schedule,$day);if($c['role']==='worker')$projection['rows']=array_values(array_filter($projection['rows'],fn($row)=>$row['worker_id']===companyWorkerId($c)));if(!$projection['rows'])continue;$items[]=['order_id'=>$id,'title'=>$o['title'],'product'=>$o['product'],'projection'=>$projection];}
 return ['day'=>$day,'timezone'=>$c['passport']['timezone'],'items'=>$items];
}
