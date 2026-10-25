<?php
declare(strict_types=1);
require_once __DIR__.'/../engine.php';
require_once __DIR__.'/goal.php';
function manufacturingAction(string $action,array $input):array{
 $c=permit(['owner','technologist','chief','foreman']);$o=orderRecord(textField($input,'order_id',32,true));$saved=productionAssemblyRoute(decode($o['route']));
 if($action==='technology_history'){
  manufacturingRequireStorage();$history=rows("SELECT revision,state_json,created_at FROM manufacturing_revisions WHERE company_id=? AND entity_kind='technology' AND entity_id=? ORDER BY revision DESC LIMIT 100",[$c['id'],$o['id']]);foreach($history as &$r){$r['state']=decode($r['state_json']);unset($r['state_json']);}unset($r);return ['history'=>$history];
 }
 if((int)($input['order_revision']??-1)!==(int)$o['revision'])fail('Технология изменилась. Откройте её заново.',409);
 $raw=$input['route']??$saved;if(!is_array($raw)||!array_is_list($raw)||count($raw)>15000)fail('Проверьте список операций');$route=[];
 foreach($raw as $op){if(!is_array($op))fail('Некорректная операция');$op['id']=manufacturingId($op);$op['name']=textField($op,'name',300,true);$op['setup_minutes']=numberField($op,'setup_minutes',0,1440);$op['grade']=numberField($op,'grade',1,99,true);$fields=manufacturingNormFields($op);$route[]=array_replace($op,$fields);}
 $mode=textField($input,'mode',20,true);if(!in_array($mode,['whole','nodes','flow'],true))fail('Выберите тариф организации');$quantity=numberField($input,'quantity',1,(int)$o['quantity']);$financial=$c['role']==='owner';
 $summary=manufacturingTechnologySummary($route,$c['passport'],$quantity,$mode,$o['start_date'],$financial);$prior=manufacturingTechnologySummary($saved,$c['passport'],$quantity,$mode,$o['start_date'],$financial);
 $summary['delta_millis']=$summary['unit_millis']===null||$prior['unit_millis']===null?null:($summary['unit_millis']-$prior['unit_millis'])*$quantity;
 $summary['delta_wage_cents']=$financial&&$summary['volume_wage_cents']!==null&&$prior['volume_wage_cents']!==null?$summary['volume_wage_cents']-$prior['volume_wage_cents']:null;
 if($financial){$direct=array_sum(array_column(decode($o['expenses']),'cents'));$summary['known_cost_cents']=($summary['volume_wage_cents']??0)+(roundedRatio($direct,$quantity,max(1,(int)$o['quantity']))??0);$summary['cost_complete']=false;$summary['cost_scope']='Известный труд пошива и прямые статьи выбранного объёма; постоянные расходы — в экономике цели';}
 return ['summary'=>$summary,'order_revision'=>(int)$o['revision']];
}
