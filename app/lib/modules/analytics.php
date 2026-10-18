<?php
declare(strict_types=1);
require_once __DIR__.'/../analytics.php';require_once __DIR__.'/../ai/insights.php';
const ANALYTICS_METRICS=['accepted','first_pass_percent','pause_hours','released_final','scrap','sent_to_rework','forecast_cents','deviation_cents','risk_goals'];
function analyticsAction(string $action,array $input):array{
 $c=owner();
 if($action==='analytics_snapshot')return analyticsSnapshot($c,$input);
 if($action==='analytics_ai_start'){
  $snapshot=analyticsSnapshot($c,$input);if(!hash_equals($snapshot['source_hash'],textField($input,'source_hash',64,true)))fail('После загрузки появились новые данные. Обновите показатели перед AI-анализом.',409);
  $facts=array_intersect_key($snapshot['facts'],array_flip(['accepted','first_pass_percent','pause_hours','released_final','scrap','sent_to_rework','first_checked','accepted_delta','events']));
  $safe=['period'=>$snapshot['period'],'facts'=>$facts,'economy_cohort'=>['goals_due'=>$snapshot['goals_due'],'risk_goals'=>$snapshot['risk_goals'],'forecast_cents'=>$snapshot['finance']['forecast_cents'],'deviation_cents'=>$snapshot['finance']['deviation_cents'],'definition'=>'Текущие подрядные цели со сроком в периоде; не денежные движения и не прибыль всей компании']];
  return workflowPublic(workflowCreate('analytics',['snapshot'=>$snapshot,'safe'=>$safe],['part'=>0,'total'=>1],textField($input,'key',100,true)));
 }
 if($action==='analytics_ai_step'){
  $id=textField($input,'id',32,true);if(workflowRecord($id)['kind']!=='analytics')fail('Неверный тип задания',409);
  return workflowStep($id,function($r){$in=decode($r['input_json']);$res=aiGateway([],['_messages'=>insightMessages('Объясни производственные факты выбранного периода. Первичная приёмка исключает повторный ОТК. Не называй паузы потерянным трудом. Экономика относится к целям со сроком в периоде и не является прибылью всей компании или фактическим денежным потоком. Если мало событий, прямо сообщи об ограниченности выводов.',$in['safe'],ANALYTICS_METRICS),'_validator'=>fn($text)=>insightReply($text,ANALYTICS_METRICS),'_prompt_version'=>'period-analytics-20261018.1']);return ['ready'=>true,'progress'=>['part'=>1,'total'=>1],'model'=>$res['model'],'result'=>['snapshot'=>$in['snapshot'],'insights'=>$res['data']]];});
 }
 fail('Неизвестное действие аналитики',404);
}
