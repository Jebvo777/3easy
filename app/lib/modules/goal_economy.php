<?php
declare(strict_types=1);
require_once __DIR__.'/goal.php';require_once __DIR__.'/../goal_economy.php';require_once __DIR__.'/../ai/insights.php';
const GOAL_ECONOMY_METRICS=['revenue_cents','cost_cents','result_cents','direct_limit_cents','break_even_unit_cents','reserve_cents'];
function goal_economyAction(string $action,array $input):array{
 $c=owner();
 if(in_array($action,['goal_economy_preview','goal_economy_ai_start'],true)){
  $stored=null;$id=textField($input,'order_id',32);if($id){$stored=orderRecord($id);$stored['route']=decode($stored['route']);}
  $draft=goalEconomyDraft($input,$c,$stored);$result=goalEconomyCalculate($draft['goal'],$c['passport'],$draft['assumptions']);
  if($action==='goal_economy_preview')return $result;
  // Free labels and customer/company/person identifiers are intentionally absent.
  $context=array_intersect_key($result,array_flip(['status','mode','quantity','revenue_cents','direct_cents','labor_cents','common_cents','cost_cents','reserve_cents','result_cents','direct_limit_cents','break_even_unit_cents']));
  $job=workflowCreate('goal_economy',['calculation'=>$result,'ai_context'=>$context],['part'=>0,'total'=>1],textField($input,'key',100,true));return workflowPublic($job);
 }
 if($action==='goal_economy_ai_step'){
  $id=textField($input,'id',32,true);if(workflowRecord($id)['kind']!=='goal_economy')fail('Неверный тип задания',409);
  return workflowStep($id,function($r){$input=decode($r['input_json']);$response=aiGateway([],['_messages'=>insightMessages('Объясни потенциальный производственный результат и предложи, какие затраты проверить. Предел прямых расходов — бюджет для выбранной маржи, а не рыночная цена или обещанная экономия. При OWN речь только о гипотезе продажи; неизвестный спрос не подтверждён.',$input['ai_context'],GOAL_ECONOMY_METRICS),'_validator'=>fn($text)=>insightReply($text,GOAL_ECONOMY_METRICS),'_prompt_version'=>'goal-economy-20261018.1']);return ['ready'=>true,'progress'=>['part'=>1,'total'=>1],'model'=>$response['model'],'result'=>['calculation'=>$input['calculation'],'insights'=>$response['data']]];});
 }
 fail('Неизвестное действие экономики цели',404);
}
