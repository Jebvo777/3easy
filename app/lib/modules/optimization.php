<?php
declare(strict_types=1);
require_once __DIR__.'/../optimization.php';require_once __DIR__.'/../ai/insights.php';require_once __DIR__.'/goal.php';
const OPTIMIZATION_METRICS=['labor_hours','cost_cents','late_orders','load_spread','energy_kwh','new_lays_meters'];
function optimizationAction(string $action,array $input):array{
 $c=owner();
 if($action==='optimization_start'){
  $source=simulationCapture($c);if(!$source['orders'])fail('Сначала создайте производственную цель.');$s=optimizationSettings($input['settings']??[],$source);$candidates=optimizationCandidates($s);
  return workflowPublic(workflowCreate('optimization',['source'=>$source,'settings'=>$s,'candidates'=>$candidates],['part'=>0,'total'=>count($candidates)+2,'baseline'=>null,'calculated'=>[],'rejected'=>0],textField($input,'key',100,true)));
 }
 if($action==='optimization_step'){
  $id=textField($input,'id',32,true);if(workflowRecord($id)['kind']!=='optimization')fail('Неверный тип задания',409);
  return workflowStep($id,function($r){$in=decode($r['input_json']);$p=decode($r['progress_json']);$s=$in['settings'];$source=$in['source'];$result=null;$ready=false;
   if($p['part']===0){$p['baseline']=optimizationPlan($source,$s,['mode'=>'unchanged','batch_percent'=>100,'release_assignments'=>false]);$p['baseline']['optimization_deadlines']=array_column($source['orders'],'deadline','id');}
   elseif($p['part']<=count($in['candidates'])){$index=$p['part']-1;$change=$in['candidates'][$index];$plan=optimizationPlan($source,$s,$change);$variant=['candidate_index'=>$index,'changes'=>$change,'metrics'=>optimizationMetrics($plan,$source,$s),'snapshot'=>$plan];if(optimizationAllowed($p['baseline'],$plan,$s))$p['calculated']=optimizationSelect([...$p['calculated'],$variant],$s);else $p['rejected']++;}
   else{$base=optimizationMetrics($p['baseline'],$source,$s);$variants=[];foreach($p['calculated'] as $i=>$v){$m=$v['metrics'];$v['id']=$i;$v['labor_delta_hours']=round($m['labor_hours']-$base['labor_hours'],2);$v['cost_delta_cents']=$m['cost_cents']===null||$base['cost_cents']===null?null:$m['cost_cents']-$base['cost_cents'];$v['same_outcome']=$m['finish']===$base['finish']&&$m['labor_hours']===$base['labor_hours']&&$m['cost_cents']===$base['cost_cents'];$variants[]=$v;}$result=['version'=>OPTIMIZATION_VERSION,'source_hash'=>$source['source_hash'],'as_of'=>date('c',$source['as_of']),'settings'=>$s,'baseline'=>$base,'variants'=>$variants,'evaluated'=>count($in['candidates']),'rejected'=>$p['rejected'],'method'=>'Ограниченный перебор организации и пачек с календарным планировщиком; глобальный оптимум не заявляется','notice'=>'Изменяются только варианты будущего расписания. Снижение распределённых затрат не означает такую же экономию денежных платежей. Энергия справочная и не начисляется повторно поверх общих расходов.'];$ready=true;}
   $p['part']++;return ['ready'=>$ready,'progress'=>$p,'result'=>$result];
  });
 }
 if($action==='optimization_apply')return command($action,$input,function($c)use($input){
  $job=workflowRecord(textField($input,'id',32,true),true);if($job['kind']!=='optimization'||!in_array($job['state'],['ready','applied'],true))fail('Сначала завершите сравнение.',409);$r=decode($job['result_json']);$index=numberField($input,'variant',0,2);
  if(!empty($r['applied_plans'][(string)$index]))return ['plan_id'=>$r['applied_plans'][(string)$index],'saved'=>true,'already_applied'=>true];
  $variant=$r['variants'][$index]??null;if(!$variant||$variant['metrics']['status']!=='feasible')fail('Неполный или условный вариант нельзя передать в план. Подтвердите нормы и пересчитайте.',409);
  $source=simulationCapture($c);if(!hash_equals($r['source_hash'],$source['source_hash'])||time()-$source['as_of']<0)fail('Основа изменилась. Повторите оптимизацию.',409);
  $original=decode($job['input_json'])['source'];if(time()-$original['as_of']>1800)fail('Расчёт старше получаса. Обновите оптимизацию перед созданием плана.',409);
  $snapshot=$variant['snapshot'];$snapshot['input_hash']=engineHash($c,$source['orders'],$source['tasks']);$snapshot['company_revision']=$c['revision'];$snapshot['source_event_count']=(int)(one('SELECT COUNT(*) n FROM production_events WHERE company_id=?',[$c['id']])['n']??0);$snapshot['optimization']=['job_id'=>$job['id'],'variant'=>$index,'source_hash'=>$r['source_hash'],'changes'=>$variant['changes']];unset($snapshot['_optimization_conditional']);
  $version=(int)(one('SELECT MAX(version_no) n FROM plan_versions WHERE company_id=?',[$c['id']])['n']??0)+1;$id=uid();query('INSERT INTO plan_versions (id,company_id,version_no,snapshot,input_hash,approvals,creator_id) VALUES (?,?,?,?,?,?,?)',[$id,$c['id'],$version,j($snapshot),$snapshot['input_hash'],'[]',$_SESSION['user_id']]);$r['applied_plans'][(string)$index]=$id;query('UPDATE assistant_jobs SET result_json=? WHERE id=? AND company_id=?',[j($r),$job['id'],$c['id']]);audit('optimization_draft_created',['plan_id'=>$id,'job_id'=>$job['id'],'variant'=>$index]);return ['plan_id'=>$id,'saved'=>true,'version'=>$version];
 });
 if($action==='optimization_ai_start'){
  $r=workflowRecord(textField($input,'id',32,true));if($r['kind']!=='optimization'||$r['state']!=='ready')fail('Сначала завершите сравнение.',409);$result=decode($r['result_json']);$keys=array_flip(OPTIMIZATION_METRICS);$context=['focus'=>$result['settings']['focus'],'baseline'=>array_intersect_key($result['baseline'],$keys),'variants'=>array_map(fn($v)=>['changes'=>$v['changes'],'metrics'=>array_intersect_key($v['metrics'],$keys)],$result['variants'])];
  return workflowPublic(workflowCreate('optimization_ai',['source_job'=>$r['id'],'context'=>$context],['part'=>0,'total'=>1],textField($input,'key',100,true)));
 }
 if($action==='optimization_ai_step'){
  $id=textField($input,'id',32,true);if(workflowRecord($id)['kind']!=='optimization_ai')fail('Неверный тип задания',409);
  return workflowStep($id,function($r){$in=decode($r['input_json']);$res=aiGateway([],['_messages'=>insightMessages('Объясни найденные варианты оптимизации. Не обещай снижение платежей от сокращения распределённых постоянных расходов. Энергия и новые настилы могут быть неизвестны. Не предлагай сокращать людей или нарушать технологию.',$in['context'],OPTIMIZATION_METRICS),'_validator'=>fn($text)=>insightReply($text,OPTIMIZATION_METRICS),'_prompt_version'=>'optimization-insights-20261018.1']);return ['ready'=>true,'progress'=>['part'=>1,'total'=>1],'result'=>$res['data'],'model'=>$res['model']];});
 }
 fail('Неизвестное действие оптимизации',404);
}
