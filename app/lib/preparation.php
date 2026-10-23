<?php
declare(strict_types=1);
require_once __DIR__.'/production_domain.php';
function preparationAvailable():bool{try{db()->query('SELECT id FROM order_preparations LIMIT 0');return true;}catch(Throwable){return false;}}
function preparationRecord(array $c,array $o):?array{if(array_key_exists('_preparation_records',$c))return $c['_preparation_records'][$o['id']]??null;if(!preparationAvailable())return null;$row=one('SELECT * FROM order_preparations WHERE company_id=? AND order_id=?',[$c['id'],$o['id']]);if(!$row)return null;$row['state']=decode($row['state_json']);$row['revision']=(int)$row['revision'];unset($row['state_json']);return $row;}
function preparationDefault(array $c,array $o):array{
 $workspace=function_exists('workspaceRecord')?workspaceRecord($c,$o):null;$old=$workspace['state']['input']??null;$items=[];
 $oldCut=[];foreach($o['preparation_route']??[] as $op)$oldCut[]=$op['id'];
 $completed=0;if($oldCut){$r=one('SELECT MAX(transferred) AS n FROM tasks WHERE company_id=? AND order_id=? AND operation_id IN ('.implode(',',array_fill(0,count($oldCut),'?')).')',[$c['id'],$o['id'],...$oldCut]);$completed=(int)($r['n']??0);}
 foreach($old['variants']??[['id'=>'variant-main','color'=>'Основной','size'=>'Единый','quantity'=>(int)$o['quantity'],'ready'=>0]] as $v)$items[]=['id'=>$v['id'],'color'=>$v['color'],'size'=>$v['size'],'quantity'=>(int)$v['quantity'],'ready'=>min((int)$v['quantity'],max((int)($v['ready']??0),$completed>=(int)$o['quantity']?(int)$v['quantity']:0)),'expected'=>0,'ready_date'=>null];
 $department='';foreach($c['passport']['structure']['workshops']??[] as $w)if(($w['kind']??'assembly')==='preparation'){$department=$w['id'];break;}
 return ['schema'=>1,'department_id'=>$department,'unit'=>'комплект','items'=>$items,'source'=>'Перенос прежних сведений; будущая готовность требует подтверждения.','operations'=>$o['preparation_route']??[],'cost_mode'=>'unknown','cost_cents'=>null];
}
function preparationState(array $c,array $o):array{return preparationRecord($c,$o)['state']??preparationDefault($c,$o);}
function preparationNormalize(array $state,array $c,array $o):array{
 $department=textField($state,'department_id',60);if($department){$w=array_column($c['passport']['structure']['workshops'],null,'id')[$department]??null;if(!$w||($w['kind']??'assembly')!=='preparation')fail('Выберите отдельное подразделение подготовки');}
 $items=[];$ids=[];$total=0;foreach($state['items']??[] as $v){$id=textField($v,'id',60,true);if(!preg_match('/^[a-zA-Z0-9_-]+$/',$id)||isset($ids[$id]))fail('Проверьте коды позиций комплектации');$ids[$id]=true;$q=numberField($v,'quantity',1,100000);$ready=numberField($v,'ready',0,$q);$expected=numberField($v,'expected',0,$q-$ready);$date=!empty($v['ready_date'])?dateField($v,'ready_date'):null;if($expected&&!$date)fail('Для ожидаемых комплектов укажите дату готовности');$total+=$q;$items[]=['id'=>$id,'color'=>textField($v,'color',100,true),'size'=>textField($v,'size',60,true),'quantity'=>$q,'ready'=>$ready,'expected'=>$expected,'ready_date'=>$date];}
 if(!$items||$total!==(int)$o['quantity'])fail('Объём комплектации должен совпадать с количеством готовых изделий в цели');
 $mode=textField($state,'cost_mode',30,true);if(!in_array($mode,['unknown','amount','included','customer'],true))fail('Выберите основание стоимости комплектации');$cost=$mode==='amount'?numberField($state,'cost_cents',0,100000000000):null;
 $ops=$state['operations']??[];if(!is_array($ops)||!array_is_list($ops))fail('Проверьте технологию подготовки');foreach($ops as $op)if(($op['type']??'')!=='cut'&&($op['domain']??'')!=='preparation')fail('Операции подготовки не должны содержать пошив');
 return ['schema'=>1,'department_id'=>$department,'unit'=>textField($state,'unit',40,true),'items'=>$items,'source'=>textField($state,'source',1000,true),'operations'=>$ops,'cost_mode'=>$mode,'cost_cents'=>$cost];
}
function preparationSupply(array $c,array $o,array $covered,int $start):array{
 $state=preparationState($c,$o);$supply=[];$deficit=0;$variants=[];$zone=$c['passport']['timezone'];
 foreach($state['items'] as $v){$q=max(0,(int)$v['quantity']-($covered[$v['id']]??0));$already=$covered[$v['id']]??0;$ready=max(0,(int)$v['ready']-$already);$expected=max(0,(int)$v['expected']-max(0,$already-(int)$v['ready']));$ready=min($q,$ready);$expected=min($q-$ready,$expected);$variants[]=$v;
  foreach([['available',$ready,$start],['expected',$expected,!empty($v['ready_date'])?plannerTime($v['ready_date'].' 00:00:00',$zone):$start]] as [$kind,$quantity,$time])if($quantity)$supply[]=['id'=>'supply-'.$kind.'-'.$v['id'].'-offset-'.$already,'variant_id'=>$v['id'],'color'=>$v['color'],'size'=>$v['size'],'quantity'=>$quantity,'ready'=>max($start,$time),'dependency'=>null];$deficit+=$q-$ready-$expected;
 }
 return ['supply'=>$supply,'deficit'=>$deficit,'state'=>$state,'variants'=>$variants];
}
function preparationTaskAllowance(array $t):?int{
 $order=one('SELECT * FROM orders WHERE id=? AND company_id=?',[$t['order_id'],$t['company_id']]);if(!$order)return null;$order['route']=decode($order['route']);$order['preparation_route']=productionPreparationRoute($order['route']);$route=productionAssemblyRoute($order['route']);$op=array_column($route,null,'id')[$t['operation_id']]??null;if(!$op||$op['dependencies'])return null;
 $c=context();$state=preparationState($c,$order);$meta=decode($t['planning_meta']??null);$variant=$meta['variant_id']??'variant-main';$ready=0;foreach($state['items'] as $v)if($v['id']===$variant||$variant==='variant-main')$ready+=(int)$v['ready'];
 $used=0;foreach(rows("SELECT * FROM tasks WHERE company_id=? AND order_id=? AND operation_id=? AND state<>'cancelled'",[$t['company_id'],$t['order_id'],$t['operation_id']]) as $other){$m=decode($other['planning_meta']??null);if(($m['variant_id']??'variant-main')===$variant)$used+=(int)$other['released'];}
 return max(0,$ready-$used);
}
