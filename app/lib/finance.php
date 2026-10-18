<?php
declare(strict_types=1);
require_once __DIR__.'/engine.php';
function moneyShare(?int $value,int $part,int $whole):?int{
 if($value===null)return null;$r=roundedRatio(abs($value),$part,$whole);return $r===null?null:($value<0?-$r:$r);
}
function moneySum(array $values):?int{
 $sum=0;foreach($values as $v){if($v===null)return null;$sum+=$v;if(abs($sum)>DEMO_MONEY_LIMIT)return null;}return $sum;
}
function recognizedValue(string $orderId,array $tasks,array $plans):?int{
 $byId=array_column($plans,null,'id');$values=[];
 foreach($tasks as $t)if($t['order_id']===$orderId&&!empty($t['is_final'])&&$t['state']!=='cancelled'&&(int)$t['accepted']>0){$p=$byId[$t['plan_id']]??null;$ec=$p['snapshot']['economy'][$orderId]??null;$quantity=(int)($p['snapshot']['order_quantities'][$orderId]??$ec['quantity']??$t['quantity']);$values[]=moneyShare($ec['value_cents']??null,(int)$t['accepted'],$quantity);}
 return moneySum($values);
}
function financeRollup(array $orders,array $plans,array $tasks,array $observations,array $current):array{
 $latest=[];foreach($observations as $o)if(!isset($latest[$o['order_id']])||(int)$o['revision']>(int)$latest[$o['order_id']]['revision'])$latest[$o['order_id']]=$o;
 usort($plans,fn($a,$b)=>(int)$a['version_no']<=>(int)$b['version_no']);$rows=[];$own=0;
 foreach($orders as $o){if($o['status']==='archived')continue;if($o['mode']!=='CONTRACT'){$own++;continue;}
  $id=$o['id'];$quantity=(int)$o['quantity'];$accepted=0;foreach($tasks as $t)if($t['order_id']===$id&&!empty($t['is_final'])&&$t['state']!=='cancelled')$accepted+=(int)$t['accepted'];
  $accepted=min($quantity,$accepted);$now=$current[$id];$baseline=$now;$version=null;$basis='Текущая оценка цели';
  foreach($plans as $p)if($p['state']==='published'&&isset($p['snapshot']['economy'][$id])){$baseline=$p['snapshot']['economy'][$id];$version=(int)$p['version_no'];$basis='Первый опубликованный план v'.$version;
   $plannedQuantity=0;foreach($p['snapshot']['schedule']??[] as $t)if($t['order_id']===$id&&!empty($t['is_final']))$plannedQuantity+=(int)$t['quantity'];if($plannedQuantity!==$quantity){$baseline['result_cents']=null;$basis='Объём изменился: база несопоставима';}break;
  }
  $observation=$latest[$id]??null;$cost=null;$covered=0;
  if($observation){$cost=moneySum([$observation['direct_cents'],$observation['labor_cents'],$observation['common_cents']]);if($cost!==null&&(int)$observation['accepted_quantity']<=$accepted)$covered=(int)$observation['accepted_quantity'];else $cost=null;}
  $recognized=recognizedValue($id,$tasks,$plans);$observedValue=$observation['recognized_cents']??null;$actual=$accepted===0?0:($covered===$accepted&&$cost!==null&&$observedValue!==null?$observedValue-$cost:null);
  $known=$covered?($observedValue===null?null:$observedValue-$cost):0;
  $remaining=moneyShare($now['cost_cents'],$quantity-$covered,$quantity);$futureRevenue=moneyShare($now['value_cents'],$quantity-$accepted,$quantity);$revenue=moneySum([$recognized,$futureRevenue]);$forecast=$remaining===null||$revenue===null?null:$revenue-($cost??0)-$remaining;
  $expected=$baseline['result_cents'];$expectedPart=moneyShare($expected,$accepted,$quantity);
  $rows[]=['order_id'=>$id,'title'=>$o['title'],'quantity'=>$quantity,'accepted'=>$accepted,'covered'=>$covered,'expected_cents'=>$expected,'actual_cents'=>$actual,'known_actual_cents'=>$known,'expected_part_cents'=>$expectedPart,'forecast_cents'=>$forecast,'baseline_version'=>$version,'baseline_source'=>$basis,'observation'=>$observation];
 }
 $expected=moneySum(array_column($rows,'expected_cents'));$forecast=moneySum(array_column($rows,'forecast_cents'));
 return ['scope'=>'Подрядные цели · производственный результат','expected_cents'=>$expected,'actual_cents'=>moneySum(array_column($rows,'actual_cents')),'known_actual_cents'=>moneySum(array_column($rows,'known_actual_cents')),'expected_part_cents'=>moneySum(array_column($rows,'expected_part_cents')),'forecast_cents'=>$forecast,'deviation_cents'=>$expected===null||$forecast===null?null:$forecast-$expected,'accepted'=>array_sum(array_column($rows,'accepted')),'covered'=>array_sum(array_column($rows,'covered')),'quantity'=>array_sum(array_column($rows,'quantity')),'own_count'=>$own,'contract_count'=>count($rows),'goal_count'=>count($rows)+$own,'rows'=>$rows,'calculated_at'=>date('c'),'method'=>'Цена принятой части минус подтверждённые затраты; оставшийся объём по текущей расчётной себестоимости. Отклонение: прогноз полного портфеля минус первоначальное ожидание.','excluded'=>['НЗП','Продажи собственных изделий','Денежные поступления и платежи','Непроизводственные расходы компании'],'status'=>'limited_scope'];
}
function financeAvailable():bool{
 return (int)(one("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='economy_observations'")['n']??0)>0;
}
function financeState(array $orders,array $plans,array $tasks,array $current,string $company):array{
 $available=financeAvailable();$observations=$available?rows('SELECT * FROM economy_observations WHERE company_id=? ORDER BY revision DESC',[$company]):[];
 foreach($observations as &$o){foreach(['revision','accepted_quantity','recognized_cents','direct_cents','labor_cents','common_cents'] as $k)$o[$k]=$o[$k]===null?null:(int)$o[$k];}unset($o);
 $result=['available'=>$available,...financeRollup($orders,$plans,$tasks,$observations,$current)];
 $zone=new DateTimeZone(context()['passport']['timezone']??'Europe/Moscow');$today=new DateTimeImmutable('today',$zone);$result['periods']=[];
 foreach(['day','week','month'] as $period){
  $from=match($period){'week'=>$today->modify('monday this week'),'month'=>$today->modify('first day of this month'),default=>$today};
  $to=match($period){'week'=>$from->modify('+6 days'),'month'=>$from->modify('last day of this month'),default=>$from};
  $subset=array_values(array_filter($orders,fn($o)=>$o['deadline']>=$from->format('Y-m-d')&&$o['deadline']<=$to->format('Y-m-d')));
  $result['periods'][$period]=financeRollup($subset,$plans,$tasks,$observations,$current)+['from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d'),'cohort'=>'Цели со сроком в выбранном периоде'];
 }
 return $result;
}
