<?php
declare(strict_types=1);

const MANUFACTURING_VERSION='easy3-manufacturing/7.4';

function manufacturingAvailable():bool{
 static $available=null;if($available!==null)return $available;
 try{db()->query('SELECT id FROM cutting_plans LIMIT 0');return $available=true;}catch(Throwable){return $available=false;}
}
function manufacturingRequireStorage():void{if(!manufacturingAvailable())fail('Для новых расчётов импортируйте database/update_20261025_74.sql в действующую БД.',503);}
function manufacturingRevision(array $c,string $kind,string $id,int $revision,array $state):void{
 manufacturingRequireStorage();query('INSERT INTO manufacturing_revisions (id,company_id,entity_kind,entity_id,revision,state_json,actor_id) VALUES (?,?,?,?,?,?,?)',[uid(),$c['id'],$kind,$id,$revision,j($state),$_SESSION['user_id']]);
}
function manufacturingId(array $data,string $field='id',int $max=96):string{
 $id=textField($data,$field,$max,true);if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$id))fail('Некорректный идентификатор: '.$field);return $id;
}
/** Decimal user input -> scaled integer. Never parse monetary decimals through float. */
function manufacturingDecimal(mixed $value,int $scale,int $max,bool $nullable=false):?int{
 if($value===null||$value===''){if($nullable)return null;fail('Укажите значение');}
 if(is_bool($value)||!is_scalar($value))fail('Некорректное число');$raw=str_replace(',','.',trim((string)$value));$digits=strlen((string)$scale)-1;
 if(!preg_match('/^\d+(?:\.\d{1,'.$digits.'})?$/D',$raw))fail('Укажите неотрицательное число с точностью до '.$digits.' знаков');
 [$whole,$fraction]=array_pad(explode('.',$raw,2),2,'');if(strlen($whole)>12)fail('Слишком большое число');$result=(int)$whole*$scale+(int)str_pad($fraction,$digits,'0');if($result>$max)fail('Число вне допустимого диапазона');return $result;
}
function manufacturingNorm(array $op):array{
 $raw=$op['norm']??null;if(!is_array($raw))return ['schema'=>1,'mode'=>'legacy','base_millis'=>null,'repeats_milli'=>1000,'operation_factor_ppm'=>1000000,'complexity_ppm'=>1000000,'applied_millis'=>isset($op['seconds'])?(int)$op['seconds']*1000:null,'status'=>!empty($op['ai_origin'])&&empty($op['norm_reviewed'])?'estimate':(isset($op['seconds'])?'confirmed':'unknown'),'reason'=>''];
 $mode=textField($raw,'mode',20,true);if(!in_array($mode,['legacy','direct','repeated'],true))fail('Выберите способ нормирования');
 $base=numberField($raw,'base_millis',1,86400000,true);$repeats=$mode==='repeated'?numberField($raw,'repeats_milli',1,100000000):1000;
 $factor=numberField($raw,'operation_factor_ppm',10000,100000000);$complexity=numberField($raw,'complexity_ppm',10000,100000000);$status=textField($raw,'status',20,true);if(!in_array($status,['unknown','estimate','confirmed'],true))fail('Укажите статус нормы');
 if($mode==='legacy'){$base=null;$applied=isset($op['seconds'])?(int)$op['seconds']*1000:null;$repeats=1000;$factor=1000000;$complexity=1000000;}
 else{$applied=$base===null?null:roundedRatio($base,$repeats,1000);foreach([$factor,$complexity] as $f)$applied=$applied===null?null:roundedRatio($applied,$f,1000000);}
 if($applied!==null&&($applied<1||$applied>86400000))fail('Применяемая норма должна быть в пределах суток на изделие');if($applied===null)$status='unknown';
 return ['schema'=>1,'mode'=>$mode,'base_millis'=>$base,'repeats_milli'=>$repeats,'operation_factor_ppm'=>$factor,'complexity_ppm'=>$complexity,'applied_millis'=>$applied,'status'=>$status,'reason'=>textField($raw,'reason',600),'unit'=>$mode==='repeated'?'действие':'изделие'];
}
function manufacturingMillis(array $op,int $quantity=1,bool $setup=false):?int{
 $n=manufacturingNorm($op);return $n['applied_millis']===null?null:$n['applied_millis']*$quantity+($setup?(int)($op['setup_minutes']??0)*60000:0);
}
function manufacturingWorkSeconds(array $op,int $quantity,bool $setup=true):int{
 $ms=manufacturingMillis($op,$quantity,$setup);return $ms===null?0:intdiv($ms+999,1000);
}
function manufacturingNormFields(array $op):array{
 $norm=manufacturingNorm($op);return ['norm'=>$norm,'seconds'=>$norm['applied_millis']===null?null:intdiv($norm['applied_millis']+999,1000)];
}
function manufacturingWage(array $p,array $op,int $quantity,string $mode,string $day,bool $setup=false):?array{
 $ms=manufacturingMillis($op,$quantity,$setup);if($ms===null)return null;$ec=$p['economy']??[];
 if((int)($ec['schema']??0)<2){$rate=$ec['labor_hour_cents']??null;$cents=$rate===null?null:roundedRatio($ms,(int)$rate,3600000);return $cents===null?null:['cents'=>$cents,'tariff'=>['legacy'=>true,'hour_cents'=>$rate]];}
 $grade=isset($op['grade'])?(int)$op['grade']:null;if($mode!=='whole'&&!empty($op['ai_origin'])&&empty($op['grade_reviewed']))return null;$rate=companyTariff($ec,$mode,$grade,$day);if(!$rate)return null;
 $cents=roundedRatio($ms,(int)$rate['rate_micros'],10000000*$rate['unit_seconds']);return $cents===null?null:['cents'=>$cents,'tariff'=>$rate];
}
function manufacturingTechnologySummary(array $route,array $p,int $quantity,string $mode,string $day,bool $financial):array{
 $rows=[];$groups=[];$known=0;$complete=true;$moneyComplete=true;$unitCents=0;$volumeCents=0;$setup=0;
 foreach($route as $op){$n=manufacturingNorm($op);$ms=$n['applied_millis'];if($ms===null)$complete=false;else $known+=$ms;$setup+=(int)($op['setup_minutes']??0)*60;
  $pay=$financial?manufacturingWage($p,$op,1,$mode,$day):null;$volumePay=$financial?manufacturingWage($p,$op,$quantity,$mode,$day):null;
  if($pay===null||$volumePay===null)$moneyComplete=false;else{$unitCents+=$pay['cents'];$volumeCents+=$volumePay['cents'];}
  $node=$op['node_id']??'node-main';$groups[$node]??=['name'=>$op['node_name']??'Основные работы','known_millis'=>0,'complete'=>true,'count'=>0,'wage_cents'=>0,'money_complete'=>true];$groups[$node]['count']++;$groups[$node]['known_millis']+=$ms??0;if($ms===null)$groups[$node]['complete']=false;if($pay===null)$groups[$node]['money_complete']=false;else $groups[$node]['wage_cents']+=$pay['cents'];
  $row=['id'=>$op['id'],'norm'=>$n];if($financial)$row+=['wage_cents'=>$pay['cents']??null,'volume_wage_cents'=>$volumePay['cents']??null,'tariff'=>$pay['tariff']??null];$rows[]=$row;
 }
 if(!$route)$complete=false;$out=['version'=>MANUFACTURING_VERSION,'quantity'=>$quantity,'mode'=>$mode,'operations'=>count($route),'known_millis'=>$known,'unit_millis'=>$complete?$known:null,'volume_millis'=>$complete?$known*$quantity:null,'setup_seconds'=>$setup,'complete'=>$complete,'rows'=>$rows,'groups'=>$groups];
 if($financial)$out+=['unit_wage_cents'=>$moneyComplete?$unitCents:null,'volume_wage_cents'=>$moneyComplete?$volumeCents:null,'known_wage_cents'=>$unitCents,'money_complete'=>$moneyComplete];else foreach($out['groups'] as &$group)unset($group['wage_cents'],$group['money_complete']);unset($group);return $out;
}
function manufacturingTimestamp(string $value,string $zone):int{
 try{$d=new DateTimeImmutable($value,new DateTimeZone($zone));return $d->getTimestamp();}catch(Throwable){fail('Укажите корректные дату и время');}
}
