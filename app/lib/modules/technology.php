<?php
declare(strict_types=1);
require_once __DIR__.'/../ai/gateway.php';require_once __DIR__.'/goal.php';
function technologyAction(string $action,array $input):array{
 $c=permit(['owner','technologist']);$cfg=aiConfiguration();if(!aiStorageAvailable())fail('Для AI импортируйте 004_ai_technology.sql. Остальные модули доступны.',503);
 $technical=aiTechnicalInput($input);$requestKey=textField($input,'key',100,true);$orderId=textField($input,'order_id',32,true);$hash=hash('sha256',j(['order_id'=>$orderId,'revision'=>$input['revision']??null,'technical'=>$technical]));$db=db();$id=null;
 $db->beginTransaction();try{
  $c=context(true);$o=orderRecord($orderId,true);aiTechnicalPrivacy($technical,$c,$o);
  $existing=one('SELECT *,TIMESTAMPDIFF(SECOND,created_at,CURRENT_TIMESTAMP) age_seconds FROM ai_generations WHERE company_id=? AND actor_id=? AND request_key=?'.lockSuffix(),[$c['id'],$_SESSION['user_id'],$requestKey]);
  if($existing){
   if(!hash_equals($existing['request_hash'],$hash))fail('Ключ генерации уже использован с другим описанием',409);
   if(in_array($existing['state'],['ready','applied'],true)){$cached=decode($existing['response_json']);$db->commit();return array_replace($cached,['replayed'=>true]);}
   if($existing['state']==='pending'&&(int)$existing['age_seconds']<90)fail('AI ещё готовит эту последовательность. Повторите запрос чуть позже.',409);fail('Предыдущая генерация не завершена. Запустите новую попытку.',409);
  }
  checkRevision((int)$o['revision'],$input);if($o['status']==='archived')fail('Выберите активную цель');if(one("SELECT id FROM tasks WHERE company_id=? AND order_id=? AND state<>'cancelled' LIMIT 1",[$c['id'],$o['id']]))fail('Технология уже опубликована. Генерация доступна для новой цели.');
  if(!aiCapability()['configured'])fail('AI не подключён: проверьте серверный ключ и PHP cURL.',503);
  // Serialize budget reservations, then release every DB lock before the external HTTP call.
  $bucket=hash('sha256','easy3-ai-global-budget');query('INSERT IGNORE INTO auth_limits (bucket,failures) VALUES (?,0)',[$bucket]);one('SELECT bucket FROM auth_limits WHERE bucket=?'.lockSuffix(),[$bucket]);
  $daily=(int)one('SELECT COUNT(*) n FROM ai_generations WHERE company_id=? AND created_at>=CURRENT_DATE()',[$c['id']])['n'];$global=(int)one('SELECT COUNT(*) n FROM ai_generations WHERE created_at>=CURRENT_DATE()')['n'];
  if($daily>=(int)$cfg['daily_limit']||$global>=(int)$cfg['global_daily_limit'])fail('Дневной лимит AI исчерпан. Продолжите вручную или повторите завтра.',429);
  $recent=one('SELECT TIMESTAMPDIFF(SECOND,created_at,CURRENT_TIMESTAMP) age_seconds FROM ai_generations WHERE company_id=? AND actor_id=? ORDER BY created_at DESC LIMIT 1',[$c['id'],$_SESSION['user_id']]);if($recent&&(int)$recent['age_seconds']<(int)$cfg['min_interval'])fail('Подождите несколько секунд перед новой генерацией.',429);
  $id=uid();query('INSERT INTO ai_generations (id,company_id,actor_id,order_id,order_revision,request_key,request_hash,prompt_version) VALUES (?,?,?,?,?,?,?,?)',[$id,$c['id'],$_SESSION['user_id'],$o['id'],(int)$o['revision'],$requestKey,$hash,AI_TECHNOLOGY_PROMPT_VERSION]);$db->commit();
 }catch(Throwable $err){if($db->inTransaction())$db->rollBack();throw $err;}
 try{
  $result=aiGateway($technical);$proposal=$result['proposal'];foreach($proposal['route'] as &$op)$op['ai_origin']=['generation_id'=>$id,'model'=>$result['model'],'prompt_version'=>$result['prompt_version']];unset($op);
  $out=$proposal+['generation_id'=>$id,'order_id'=>$orderId,'order_revision'=>(int)$o['revision'],'model'=>$result['model'],'prompt_version'=>$result['prompt_version'],'usage'=>$result['usage'],'replayed'=>false];
  $db->beginTransaction();query("UPDATE ai_generations SET state='ready',response_json=?,model=?,usage_json=?,latency_ms=? WHERE id=?",[j($out),$result['model'],j($result['usage']),$result['latency_ms'],$id]);audit('ai_technology_generated',['generation_id'=>$id,'order_id'=>$orderId,'operations'=>count($proposal['route']),'model'=>$result['model'],'prompt_version'=>$result['prompt_version']]);$db->commit();return $out;
 }catch(Throwable $err){if($db->inTransaction())$db->rollBack();query("UPDATE ai_generations SET state='failed',error_code=? WHERE id=?",[$err instanceof AppError?'generation_failed':'server_failed',$id]);throw $err;}
}
