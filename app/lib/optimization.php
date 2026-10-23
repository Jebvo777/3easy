<?php
declare(strict_types=1);
require_once __DIR__.'/simulation.php';
const OPTIMIZATION_VERSION='portfolio-search/1';
function optimizationSettings(array $raw,array $source):array{
 $focus=textField($raw+['focus'=>'deadline'],'focus',20,true);if(!in_array($focus,['deadline','load','cost'],true))fail('Выберите цель оптимизации');
 $s=['focus'=>$focus,'horizon_days'=>numberField($raw+['horizon_days'=>30],'horizon_days',1,180),'max_batch_percent'=>numberField($raw+['max_batch_percent'=>200],'max_batch_percent',20,500),'allow_reassign'=>($raw['allow_reassign']??true)===true,'protect_lateness'=>($raw['protect_lateness']??true)===true,'use_estimates'=>($raw['use_estimates']??false)===true,'power_watts'=>[],'electricity_cents'=>numberField($raw,'electricity_cents',0,100000,true)];
 $ids=array_column($source['company']['passport']['resources'],'id');if(!is_array($raw['power_watts']??[]))fail('Некорректные мощности');foreach($raw['power_watts']??[] as $id=>$power){if(!in_array($id,$ids,true))fail('Ресурс другого предприятия',403);$s['power_watts'][$id]=numberField(['v'=>$power],'v',0,1000000,true);}
 return $s;
}
function optimizationCandidates(array $settings):array{
 $out=[];$batches=array_values(array_unique([min(50,$settings['max_batch_percent']),min(100,$settings['max_batch_percent']),$settings['max_batch_percent']]));
 foreach(['flow','nodes','whole'] as $mode)foreach($batches as $batch)$out[]=['mode'=>$mode,'batch_percent'=>$batch,'release_assignments'=>$settings['allow_reassign']];
 return $out;
}
function optimizationPlan(array $source,array $settings,array $change):array{
 $copy=$source;$c=$copy['company'];$c['_simulation_now']=$source['as_of'];$c['_optimization']=true;$c['_preparation_records']=$source['preparations']??[];$overrides=[];
 if($change['release_assignments'])foreach($c['passport']['resources'] as &$r)$r['worker']='';unset($r);
 foreach($copy['orders'] as &$o){
  if($settings['use_estimates'])foreach($o['route'] as &$op)if($op['seconds']===null&&($op['estimated_seconds']??null)!==null){$op['seconds']=$op['estimated_seconds'];$op['norm_reviewed']=true;$op['source']='Предварительная оценка; оптимизация условная';}unset($op);
  $w=$copy['workspaces'][$o['id']]['state']??workspaceDefault($o);
  if($change['mode']!=='unchanged')$w['mode']=$change['mode'];$w['transfer_batch']=max(1,(int)round($w['transfer_batch']*$change['batch_percent']/100));
  if($change['release_assignments']){$w['allocations']=array_filter($w['allocations'],fn($a)=>!empty($a['unassigned']));$w['whole_workers']=[];$w['node_workers']=[];}
  $overrides[$o['id']]=$w;
 }
 unset($o);$c['_workspace_records']=$copy['workspaces'];$plan=calculateWorkspacePlan($c,$copy['orders'],$copy['tasks'],'deadline',$overrides);
 $plan['_optimization_conditional']=$settings['use_estimates'];return $plan;
}
function optimizationMetrics(array $plan,array $source,array $settings):array{
 $zone=$source['company']['passport']['timezone'];$from=$source['as_of'];$day=(new DateTimeImmutable('@'.$from))->setTimezone(new DateTimeZone($zone))->setTime(0,0);$until=$day->modify('+'.$settings['horizon_days'].' days')->setTime(23,59,59)->getTimestamp();
 $capacity=0;for($d=$day->modify('-1 day');$d->getTimestamp()<=$until;$d=$d->modify('+1 day'))foreach(dayWindows($d,$source['company']['passport']['shift']) as [$a,$b])$capacity+=max(0,min($until,$b)-max($from,$a));
 $workers=[];$resources=[];$futureSeconds=0;$energy=0;$unknownPower=[];$counts=0;
 foreach($plan['schedule'] as $t){
  $seconds=0;$period=0;foreach($t['slots']??[[$t['start'],$t['end']]] as [$a,$b]){$a=plannerTime($a,$zone);$b=plannerTime($b,$zone);$seconds+=max(0,$b-max($from,$a));$period+=max(0,min($until,$b)-max($from,$a));}
  $futureSeconds+=$seconds;$workers[$t['worker_id']]=($workers[$t['worker_id']]??0)+$period;$resources[$t['resource_id']]=($resources[$t['resource_id']]??0)+$seconds;
  if($seconds>0){$power=$settings['power_watts'][$t['resource_id']]??null;if($power===null)$unknownPower[$t['resource_id']]=true;else $energy+=$seconds*$power/3600000;}
  if($t['is_final']&&plannerTime($t['end'],$zone)<=$until)$counts+=$t['quantity'];
 }
 $active=array_values(array_filter($source['company']['passport']['people'],fn($p)=>$p['active']));$loads=[];$people=[];
 foreach($active as $p){$used=$workers[$p['id']]??0;$load=$capacity?round($used/$capacity*100,1):null;if($load!==null)$loads[]=$load;$people[]=['id'=>$p['id'],'name'=>$p['name'],'hours'=>round($used/3600,2),'available_hours'=>round($capacity/3600,2),'load_percent'=>$load];}
 $average=$loads?array_sum($loads)/count($loads):0;$dispersion=$loads?sqrt(array_sum(array_map(fn($x)=>($x-$average)**2,$loads))/count($loads)):null;
 $costs=array_column($plan['economy'],'cost_cents');$cost=$costs&&!in_array(null,$costs,true)?array_sum($costs):null;
 $tardiness=0;foreach($source['orders'] as $o){$finish=$plan['order_finishes'][$o['id']]??null;if($finish)$tardiness+=max(0,plannerTime($finish,$zone)-plannerTime($o['deadline'].' 23:59:59',$zone));}
 $meters=0;$metersKnown=true;foreach($plan['decision_summary'] as $summary){if(($summary['cutting']['meters']??null)===null)$metersKnown=false;else $meters+=$summary['cutting']['meters'];}
 return ['status'=>$plan['status']==='feasible'?($settings['use_estimates']?'conditional':'feasible'):'incomplete','finish'=>$plan['finish'],'late_orders'=>$plan['late_orders'],'tardiness_hours'=>round($tardiness/3600,2),'labor_hours'=>round($futureSeconds/3600,2),'center_hours'=>round(array_sum($resources)/3600,2),'cost_cents'=>$cost,'energy_kwh'=>$unknownPower?null:round($energy,3),'known_energy_kwh'=>round($energy,3),'energy_reference_cents'=>$unknownPower||$settings['electricity_cents']===null?null:(int)round($energy*$settings['electricity_cents']),'unknown_power_resources'=>array_keys($unknownPower),'new_lays_meters'=>$metersKnown?round($meters,3):null,'load_spread'=>is_null($dispersion)?null:round($dispersion,2),'max_load_percent'=>$loads?max($loads):null,'by_horizon'=>$plan['status']==='feasible'?$counts:null,'people'=>$people,'resources'=>array_map(fn($id,$seconds)=>['id'=>$id,'hours'=>round($seconds/3600,2)],array_keys($resources),array_values($resources)),'errors'=>$plan['errors'],'warnings'=>$plan['warnings'],'horizon_until'=>simulationDate($until,$zone)];
}
function optimizationScore(array $v,array $settings):array{
 $m=$v['metrics'];$bad=$m['status']==='incomplete'?1:0;$late=$m['tardiness_hours'];$finish=$m['finish']?strtotime($m['finish']):PHP_INT_MAX;$labor=$m['labor_hours'];$spread=$m['load_spread']??PHP_INT_MAX;$cost=$m['cost_cents']??PHP_INT_MAX;
 return match($settings['focus']){'cost'=>[$bad,$cost,$late,$labor,$finish],'load'=>[$bad,$spread,$late,$labor,$finish],default=>[$bad,$late,$finish,$labor,$spread]};
}
function optimizationAllowed(array $base,array $candidate,array $settings):bool{
 if(!$settings['protect_lateness'])return true;
 foreach($base['order_finishes'] as $id=>$finish){$next=$candidate['order_finishes'][$id]??null;if(!$next)return false;$deadline=null;foreach($base['optimization_deadlines']??[] as $oid=>$date)if($oid===$id)$deadline=$date;if($deadline&&$next>max($finish,$deadline.' 23:59:59'))return false;}
 return true;
}
function optimizationSelect(array $variants,array $settings):array{
 usort($variants,fn($a,$b)=>optimizationScore($a,$settings)<=>optimizationScore($b,$settings)?:($a['candidate_index']<=>$b['candidate_index']));return array_slice($variants,0,3);
}
