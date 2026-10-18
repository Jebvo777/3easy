<?php
declare(strict_types=1);
require_once __DIR__.'/engine.php';
function goalEconomyDraft(array $input,array $company,?array $stored=null):array{
 $raw=$input['goal']??[];if(!is_array($raw))fail('Нет условий цели');$mode=textField($raw,'mode',12,true);if(!in_array($mode,['OWN','CONTRACT'],true))fail('Выберите основание производства');
 $goal=['mode'=>$mode,'product'=>textField($raw,'product',180),'quantity'=>numberField($raw,'quantity',1,100000),'price_cents'=>numberField($raw,'price_cents',0,100000000000,true),'price_basis'=>textField($raw,'price_basis',12),'target_cents'=>numberField($raw,'target_cents',0,1000000000,true),'expenses'=>[],'route'=>$stored['route']??[]];
 if(!in_array($goal['price_basis'],['unit','total'],true))$goal['price_basis']='unit';
 $expenses=$raw['expenses']??[];if(!is_array($expenses)||count($expenses)>40)fail('Слишком много статей расхода');foreach($expenses as $i=>$ex)$goal['expenses'][]=['name'=>textField($ex,'name',100,true),'cents'=>numberField($ex,'cents',0,100000000000),'id'=>'cost-'.($i+1)];
 $a=$input['assumptions']??[];if(!is_array($a))fail('Некорректные предположения');
 $assumptions=['minutes_per_unit'=>numberField($a,'minutes_per_unit',1,10000,true),'labor_hour_cents'=>numberField($a,'labor_hour_cents',0,100000000,true),'common_total_cents'=>numberField($a,'common_total_cents',0,100000000000,true),'sale_unit_cents'=>numberField($a,'sale_unit_cents',0,100000000000,true),'reserve_percent'=>numberField($a+['reserve_percent'=>10],'reserve_percent',0,50),'margin_percent'=>numberField($a+['margin_percent'=>20],'margin_percent',0,80),'direct_complete'=>($a['direct_complete']??false)===true];
 return ['goal'=>$goal,'assumptions'=>$assumptions];
}
function goalEconomyCalculate(array $goal,array $passport,array $a):array{
 $q=$goal['quantity'];$missing=[];$seconds=null;$timeSource='Нет подтверждённого времени';$conditional=false;
 if($goal['route']&&!routeCheck($goal['route'])){$seconds=0;foreach($goal['route'] as $op)$seconds+=(int)$op['seconds']*$q+(int)($op['setup_minutes']??0)*60;$timeSource='Сохранённая технология и подтверждённые нормы';}
 if($a['minutes_per_unit']!==null){$seconds=$a['minutes_per_unit']*60*$q;$timeSource='Предположение пользователя о труде; не производственная норма';$conditional=true;}
 if($seconds===null)$missing[]='Укажите предположение о трудоёмкости либо подтвердите маршрут.';
 $ec=$passport['economy']??[];$rate=$a['labor_hour_cents']??$ec['labor_hour_cents']??null;
 $labor=$seconds!==null&&$rate!==null?roundedRatio($seconds,(int)$rate,3600):null;
 if($a['labor_hour_cents']!==null&&$a['labor_hour_cents']!==($ec['labor_hour_cents']??null))$conditional=true;
 $common=$a['common_total_cents'];if($common!==null)$conditional=true;elseif($seconds!==null&&($ec['monthly_common_cents']??null)!==null&&($ec['monthly_hours']??null)>0)$common=roundedRatio($seconds,(int)$ec['monthly_common_cents'],3600*(int)$ec['monthly_hours']);
 $direct=$a['direct_complete']?array_sum(array_column($goal['expenses'],'cents')):null;
 if($direct===null)$missing[]='Подтвердите полноту прямых статей, в том числе явный ноль.';if($labor===null)$missing[]='Не определены труд или ставка.';if($common===null)$missing[]='Не определена доля общих расходов.';
 $cost=$labor===null||$common===null||$direct===null?null:$labor+$common+$direct;
 $revenue=$goal['mode']==='CONTRACT'?($goal['price_cents']===null?null:$goal['price_cents']*($goal['price_basis']==='unit'?$q:1)):($a['sale_unit_cents']===null?null:$a['sale_unit_cents']*$q);
 if($goal['mode']==='OWN')$conditional=true;
 if($cost!==null&&$cost>DEMO_MONEY_LIMIT||$revenue!==null&&$revenue>DEMO_MONEY_LIMIT)fail('Сумма превышает допустимый масштаб расчёта.');
 if($revenue===null)$missing[]=$goal['mode']==='OWN'?'Для потенциальной прибыли укажите предполагаемую цену продажи.':'Не задана цена подряда.';
 $reserve=$cost===null?null:roundedRatio($cost,$a['reserve_percent'],100);$withReserve=$cost===null?null:$cost+$reserve;$result=$revenue===null||$withReserve===null?null:$revenue-$withReserve;
 $budget=$revenue===null?($goal['target_cents']===null?null:$goal['target_cents']*$q):roundedRatio($revenue,100-$a['margin_percent'],100);
 $directLimit=$budget===null||$labor===null||$common===null?null:(int)floor($budget*100/(100+$a['reserve_percent']))-$labor-$common;
 $breakeven=$withReserve===null?null:(int)ceil($withReserve/$q);$margin=$result===null||!$revenue?null:round($result/$revenue*100,1);
 $variants=[];foreach([90,100,110] as $percent){$d=$direct===null?null:roundedRatio($direct,$percent,100);$c=$d===null||$labor===null||$common===null?null:$d+$labor+$common;$total=$c===null?null:$c+roundedRatio($c,$a['reserve_percent'],100);$variants[]=['direct_percent'=>$percent,'cost_cents'=>$total,'result_cents'=>$revenue===null||$total===null?null:$revenue-$total];}
 return ['version'=>'goal-economy/1','status'=>$cost===null||$revenue===null?'incomplete':($conditional?'conditional':'calculated'),'mode'=>$goal['mode'],'quantity'=>$q,'revenue_cents'=>$revenue,'known_direct_cents'=>array_sum(array_column($goal['expenses'],'cents')),'direct_cents'=>$direct,'labor_cents'=>$labor,'common_cents'=>$common,'cost_cents'=>$cost,'reserve_cents'=>$reserve,'cost_with_reserve_cents'=>$withReserve,'result_cents'=>$result,'unit_cost_cents'=>$cost===null?null:roundedRatio($cost,1,$q),'break_even_unit_cents'=>$breakeven,'budget_cents'=>$budget,'direct_limit_cents'=>$directLimit,'margin_percent'=>$margin,'seconds'=>$seconds,'time_source'=>$timeSource,'unknown'=>array_values(array_unique(array_merge($missing,$ec['unknown']??[]))),'excluded'=>$ec['excluded']??[],'assumptions'=>$a,'sensitivity'=>$variants,'scope'=>$goal['mode']==='OWN'?'Гипотеза продажи всей партии; продажи не фиксируются':'Производственный результат подряда','method'=>'Цена × объём − прямые расходы − труд − доля общих расходов − отдельный резерв; без повторного начисления аренды'];
}
