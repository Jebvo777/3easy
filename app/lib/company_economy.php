<?php
declare(strict_types=1);

/** Rates are integer millionths of a ruble; conversion rounds only the final cents. */
function companyEconomyValidate(array $raw,array $old):array{
 if((int)($raw['schema']??0)<2)return $raw;
 $out=$raw;$out['schema']=2;$period=$raw['balance_period']??'goal';if(!in_array($period,['goal','shift','month'],true))fail('Выберите период выравнивания заработка');$out['balance_period']=$period;
 foreach(['tariffs','fixed_expenses','variable_expenses'] as $kind){$items=$raw[$kind]??[];if(!is_array($items)||!array_is_list($items)||count($items)>500)fail('Слишком много строк экономики');$clean=[];$ids=[];$oldMap=array_column($old[$kind]??[],null,'id');
  foreach($items as $v){$id=organizationId($v);if(isset($ids[$id]))fail('Строка экономики повторяется');$ids[$id]=true;$from=dateField($v,'from');$until=empty($v['until'])?null:dateField($v,'until');if($until!==null&&$until<$from)fail('Окончание действия раньше начала');$row=['id'=>$id,'from'=>$from,'until'=>$until,'archived'=>(bool)($v['archived']??false)];
   if($kind==='tariffs'){$level=numberField($v,'level',1,3);$row+=['level'=>$level,'grade'=>$level===1?0:numberField($v,'grade',1,99),'rate_micros'=>numberField($v,'rate_micros',0,1000000000000,true)];}
   elseif($kind==='fixed_expenses'){$cycle=textField($v,'period',20,true);if(!in_array($cycle,['month','year','week','day','once'],true))fail('Выберите периодичность расхода');$row+=['category'=>textField($v,'category',100,true),'name'=>textField($v,'name',180,true),'cents'=>numberField($v,'cents',0,1000000000000),'period'=>$cycle];}
   else{$row+=['name'=>textField($v,'name',180,true),'basis'=>textField($v,'basis',60,true),'basis_points'=>numberField($v,'basis_points',0,100000)];if(!in_array($row['basis'],['revenue','payroll'],true))fail('Неизвестная база примера начисления');}
   if(isset($oldMap[$id])){foreach($row as $key=>$value)if(!in_array($key,['until','archived'],true)&&($oldMap[$id][$key]??null)!==$value)fail('Для изменения сохранённой ставки или статьи создайте новую редакцию');if(($oldMap[$id]['until']??null)!==null&&$row['until']!==$oldMap[$id]['until'])fail('Архивная граница уже сохранена');}
   $clean[]=$row;
  }
  foreach($oldMap as $id=>$v)if(!isset($ids[$id]))$clean[]=$v;$out[$kind]=$clean;
 }
 // Two effective rates of the same level/grade must never compete for one date.
 foreach($out['tariffs'] as $i=>$a)foreach(array_slice($out['tariffs'],$i+1) as $b)if(!$a['archived']&&!$b['archived']&&$a['level']===$b['level']&&$a['grade']===$b['grade']&&$a['from']<=($b['until']??'9999-12-31')&&$b['from']<=($a['until']??'9999-12-31'))fail('Периоды ставок одного уровня и разряда пересекаются');
 $out['variable_enabled']=false;return $out;
}
function companyTariff(array $ec,string $mode,?int $grade,string $day):?array{
 $level=['whole'=>1,'nodes'=>2,'flow'=>3][$mode]??3;
 foreach($ec['tariffs']??[] as $r){if(!empty($r['archived'])||(int)$r['level']!==$level||($level!==1&&(int)$r['grade']!==$grade)||$r['from']>$day||(!empty($r['until'])&&$r['until']<$day))continue;if($r['rate_micros']===null)return null;return $r+['unit_seconds'=>[1=>3600,2=>60,3=>1][$level]];}
 return null;
}
function companyOperationWage(array $p,array $op,int $seconds,string $mode,string $day):?array{
 $ec=$p['economy']??[];if((int)($ec['schema']??0)<2){$rate=$ec['labor_hour_cents']??null;return $rate===null?null:['cents'=>roundedRatio($seconds,(int)$rate,3600),'tariff'=>['legacy'=>true,'hour_cents'=>$rate]];}
 $grade=isset($op['grade'])&&is_numeric($op['grade'])?(int)$op['grade']:null;if($mode!=='whole'&&!empty($op['ai_origin'])&&empty($op['grade_reviewed']))return null;
 $r=companyTariff($ec,$mode,$grade,$day);if(!$r)return null;$cents=roundedRatio($seconds,(int)$r['rate_micros'],10000*$r['unit_seconds']);return $cents===null?null:['cents'=>$cents,'tariff'=>$r];
}
function companyMonthlyFixed(array $ec,string $day):?int{
 if((int)($ec['schema']??0)<2)return $ec['monthly_common_cents']??null;$sum=0;$date=new DateTimeImmutable($day);$month=$date->format('Y-m');$days=(int)$date->format('t');
 foreach($ec['fixed_expenses']??[] as $r){if(!empty($r['archived'])||$r['from']>$date->format('Y-m-t')||(!empty($r['until'])&&$r['until']<$date->format('Y-m-01')))continue;$cents=(int)$r['cents'];$amount=match($r['period']){'year'=>roundedRatio($cents,1,12),'week'=>roundedRatio($cents,$days,7),'day'=>$cents*$days,'once'=>substr($r['from'],0,7)===$month?$cents:0,default=>$cents};if($amount===null)return null;$from=max($r['from'],$date->format('Y-m-01'));$until=min($r['until']??$date->format('Y-m-t'),$date->format('Y-m-t'));$active=(int)(new DateTimeImmutable($from))->diff(new DateTimeImmutable($until))->days+1;$sum+=$r['period']==='once'?$amount:(roundedRatio($amount,$active,$days)??0);}
 return $sum;
}
function companyMonthlyHours(array $p,string $day):?int{
 $shift=$p['shift']??[];if(empty($shift['days']))return null;$people=array_filter($p['people']??[],fn($x)=>!empty($x['active'])&&($x['group']??'executors')==='executors'&&in_array($x['workshop']??'',array_column(array_filter($p['structure']['workshops']??[],fn($w)=>($w['kind']??'assembly')==='assembly'),'id'),true));$seconds=0;$d=(new DateTimeImmutable($day))->modify('first day of this month')->setTime(0,0);$month=$d->format('m');while($d->format('m')===$month){foreach(dayWindows($d,$shift) as [$a,$b])$seconds+=$b-$a;$d=$d->modify('+1 day');}return count($people)?max(1,(int)round($seconds*count($people)/3600)):null;
}
