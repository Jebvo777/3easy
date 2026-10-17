<?php
declare(strict_types=1);
require_once __DIR__.'/prompt.php';

class AiGatewayError extends AppError {
 public function __construct(string $message,int $status,public string $reason,public array $attempts=[],public bool $retryable=false,public ?string $nextModel=null,public int $retryAfter=0) {
  parent::__construct($message,$status);
 }
}
function aiConfiguration():array {
 static $cfg=null;if($cfg!==null)return $cfg;$root=dirname(__DIR__,2);
 $cfg=require $root.'/config.ai.example.php';
 $local=is_file($root.'/config.ai.local.php')?require $root.'/config.ai.local.php':[];
 if(!is_array($local))$local=[];$oldModel=$local['model']??null;$cfg=array_replace($cfg,$local);
 // Update the primary even if an older local file names the previous model.
 // Credentials and provider remain local; an explicit environment override wins.
 $cfg['model']=getenv('EASY3_AI_MODEL')?:'gpt-6-luna';
 $cfg['fallback_models']=array_values(array_unique(array_filter(array_merge([$oldModel],$cfg['fallback_models']??[],['gpt-5-nano','gpt-5.4-mini','gpt-4o-mini']),fn($m)=>is_string($m)&&$m!==$cfg['model']&&trim($m)!=='')));
 foreach(['EASY3_AI_KEY'=>'key','EASY3_AI_BASE_URL'=>'base_url'] as $env=>$field)if(getenv($env))$cfg[$field]=getenv($env);
 $cfg['key']=trim((string)($cfg['key']??''));
 // Keep each synchronous HTTP step within ordinary shared-hosting limits.
 $cfg['total_timeout']=max(8,min(24,(int)($cfg['total_timeout']??24)));
 $cfg['attempt_timeout']=max(4,min(18,(int)($cfg['attempt_timeout']??18)));
 $cfg['max_tokens']=max(1500,min(5000,(int)($cfg['max_tokens']??5000)));return $cfg;
}
function aiStorageAvailable():bool {
 try{db()->query('SELECT id,job_json,request_json,lease_token,lease_until,call_count FROM ai_generations LIMIT 0');db()->query('SELECT bucket FROM ai_provider_limits LIMIT 0');return true;}catch(Throwable){return false;}
}
function aiCapabilityMessage(?string $reason):?string {
 return match($reason){
  'disabled'=>'Генерация отключена в серверной конфигурации AI.',
  'migration_required'=>'Импортируйте update_20261017.sql из текущего архива: он добавляет хранение и очередь AI.',
  'curl_required'=>'На хостинге не включено расширение PHP cURL. Включите его для серверных запросов AI.',
  'key_required'=>'Сервер не видит ключ AI. Сохраните прежний ключ в config.ai.local.php или EASY3_AI_KEY.',
  'configuration'=>'Проверьте HTTPS-адрес провайдера и формат серверного ключа AI.',default=>null};
}
function aiCapability():array {
 $cfg=aiConfiguration();$storage=aiStorageAvailable();$url=parse_url((string)($cfg['base_url']??''));
 $validUrl=$url&&($url['scheme']??'')==='https'&&!empty($url['host'])&&!isset($url['user'])&&!isset($url['pass'])&&!isset($url['query'])&&!isset($url['fragment']);
 $reason=empty($cfg['enabled'])?'disabled':(!$storage?'migration_required':(!function_exists('curl_init')?'curl_required':($cfg['key']===''?'key_required':(!$validUrl||preg_match('/[\r\n]/',$cfg['key'])?'configuration':null))));
 return ['enabled'=>(bool)$cfg['enabled'],'configured'=>$reason===null,'grouped'=>true,'max_operations'=>null,'model'=>$cfg['model'],'reason'=>$reason,'message'=>aiCapabilityMessage($reason)];
}
function aiModelNames(array $cfg):array {
 return array_values(array_unique(array_filter(array_merge([$cfg['model']??'gpt-6-luna'],$cfg['fallback_models']??[]),fn($m)=>is_string($m)&&preg_match('~^[a-zA-Z0-9][a-zA-Z0-9_./:-]{0,119}$~',$m))));
}
function aiHttp(array $cfg,array $payload,int $timeout):array {
 $url=rtrim((string)$cfg['base_url'],'/').'/chat/completions';$parts=parse_url($url);
 if(!$parts||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||preg_match('/[\r\n]/',(string)$cfg['key']))return ['http'=>0,'error'=>'configuration','body'=>''];
 $body='';$retryAfter=0;$ch=curl_init($url);$options=[CURLOPT_POST=>true,CURLOPT_USERAGENT=>(string)($cfg['user_agent']??'EASY3/6'),CURLOPT_FOLLOWLOCATION=>false,
  CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: Bearer '.$cfg['key']],CURLOPT_POSTFIELDS=>j($payload),
  CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>min(4,$timeout),CURLOPT_NOSIGNAL=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
  CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$retryAfter){if(preg_match('/^Retry-After:\s*(.+)/i',$line,$m))$retryAfter=is_numeric(trim($m[1]))?(int)trim($m[1]):max(1,(int)strtotime(trim($m[1]))-time());return strlen($line);},
  CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>2097152)return 0;$body.=$chunk;return strlen($chunk);}];
 if(!empty($cfg['ca_file']))$options[CURLOPT_CAINFO]=$cfg['ca_file'];curl_setopt_array($ch,$options);$ok=curl_exec($ch);$errno=curl_errno($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 return ['http'=>$http,'body'=>$body,'error'=>$ok===false?($errno===28?'timeout':'transport'):null,'retry_after'=>max(0,min(3600,$retryAfter))];
}
/** Serialize calls sharing a provider key; a 429 applies to all modules. */
function aiProviderGate(array $cfg,bool $reserve=true,int $cooldown=0):void{
 $bucket=hash('sha256',(string)$cfg['base_url'].'|'.(string)$cfg['key']);$db=db();$db->beginTransaction();
 try{
  query('INSERT IGNORE INTO ai_provider_limits (bucket,next_allowed) VALUES (?,0)',[$bucket]);
  $row=one('SELECT next_allowed FROM ai_provider_limits WHERE bucket=?'.lockSuffix(),[$bucket]);$wait=max(0,(int)($row['next_allowed']??0)-time());
  if($cooldown>0){query('UPDATE ai_provider_limits SET next_allowed=? WHERE bucket=?',[max((int)($row['next_allowed']??0),time()+$cooldown),$bucket]);$db->commit();return;}
  if($wait>0){$db->commit();throw new AiGatewayError('Провайдер просит паузу. Прогресс сохранён; продолжение через '.$wait.' сек.',429,'rate_limit',[],true,null,$wait);}
  if($reserve)query('UPDATE ai_provider_limits SET next_allowed=? WHERE bucket=?',[time()+max(3,min(60,(int)($cfg['min_interval_seconds']??6))),$bucket]);$db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function aiProviderPayload(string $model,array $messages,array $cfg):array {
 $reasoning=(bool)preg_match('~^(?:gpt-[56](?:[.-]|$)|o[134](?:[.-]|$))~',$model);
 $payload=['model'=>$model,'messages'=>$messages,'response_format'=>['type'=>'json_object']];$payload[$reasoning?'max_completion_tokens':'max_tokens']=(int)$cfg['max_tokens'];
 if($reasoning)$payload['reasoning_effort']=str_starts_with($model,'gpt-5-nano')?'minimal':(str_starts_with($model,'gpt-6')?'none':'low');else $payload['temperature']=(float)($cfg['temperature']??0.3);return $payload;
}
/** Retry a different payload only for an explicit parameter incompatibility. */
function aiCompatiblePayload(array $payload,array $error):?array {
 $message=strtolower((string)($error['message']??''));$param=(string)($error['param']??'');
 foreach(['max_completion_tokens','max_tokens','reasoning_effort','response_format','temperature'] as $name){
  if(!array_key_exists($name,$payload)||($param!==$name&&!str_contains($message,$name)))continue;
  if(!preg_match('/unsupported|not supported|unknown|unrecognized|not allowed|invalid parameter|invalid value/',$message))continue;
  if($name==='max_completion_tokens'){$payload['max_tokens']=$payload[$name];unset($payload[$name]);}
  elseif($name==='max_tokens'){$payload['max_completion_tokens']=$payload[$name];unset($payload[$name]);}else unset($payload[$name]);return $payload;
 }return null;
}
function aiGateway(array $technical,array $job,?array $cfg=null,?callable $transport=null):array {
 $cfg??=aiConfiguration();$transport??='aiHttp';
 if(empty($cfg['enabled'])||trim((string)$cfg['key'])==='')throw new AiGatewayError(aiCapabilityMessage(empty($cfg['enabled'])?'disabled':'key_required'),503,'configuration');
 if($transport==='aiHttp'&&!function_exists('curl_init'))throw new AiGatewayError(aiCapabilityMessage('curl_required'),503,'curl_required');
 $models=aiModelNames($cfg);$preferred=$job['preferred_model']??null;
 // A working fallback is preferred for this generation; new jobs start with Luna.
 if($preferred&&in_array($preferred,$models,true))$models=array_merge([$preferred],array_values(array_diff($models,[$preferred])));
 $started=microtime(true);$budget=max(8,min(24,(int)$cfg['total_timeout']));$phpLimit=(int)ini_get('max_execution_time');if($phpLimit>0)$budget=max(4,min($budget,$phpLimit-5));
 $attempts=[];$last='unavailable';$lastIndex=0;$messages=$job['_messages']??aiTechnologyMessages($technical,$job);$retryAfter=0;
 foreach($models as $index=>$model){
  if($budget-(microtime(true)-$started)<3)break;$lastIndex=$index;$payload=aiProviderPayload($model,$messages,$cfg);
  for($variant=0;$variant<2;$variant++){
   $remaining=$budget-(microtime(true)-$started);if($remaining<3)break;$timeout=max(2,min((int)$cfg['attempt_timeout'],(int)floor($remaining)));
   if($transport==='aiHttp')aiProviderGate($cfg);$response=$transport($cfg,$payload,$timeout);$code=(int)$response['http'];$body=(string)$response['body'];$envelope=json_decode($body,true);$providerError=is_array($envelope['error']??null)?$envelope['error']:[];
   $providerCode=strtolower((string)($providerError['code']??$providerError['type']??''));$providerMessage=strtolower((string)($providerError['message']??''));
   $last=$response['error']??($code>=200&&$code<300?'invalid':'provider');
   if($code===401)$last='key_rejected';
   elseif($code===403)$last=str_contains($body,'1010')||str_contains($body,'Cloudflare')?'provider_blocked':(str_contains($providerMessage,'model')||str_contains($providerCode,'model')?'model_unavailable':'access_denied');
   elseif($code===402||str_contains($providerCode,'insufficient_quota')||str_contains($providerCode,'balance'))$last='balance';
   elseif($code===404)$last='model_unavailable';elseif($code===429)$last='rate_limit';elseif($code===413||str_contains($providerCode,'context_length'))$last='request_size';
   $attempts[]=['model'=>$model,'http'=>$code,'reason'=>$last];
   if($last==='rate_limit'){$retryAfter=max(15,(int)($response['retry_after']??0));if($transport==='aiHttp')aiProviderGate($cfg,false,$retryAfter);break 2;}
   if(in_array($last,['configuration','key_rejected','provider_blocked','access_denied','balance','request_size'],true))break 2;
   if(in_array($code,[400,422],true)&&$variant===0&&($compatible=aiCompatiblePayload($payload,$providerError))){$payload=$compatible;continue;}
   if($response['error']||$code<200||$code>=300)break;
   try{
    if(!is_array($envelope))throw new JsonException('Invalid response');$choice=$envelope['choices'][0]??[];$reply=$choice['message']['content']??'';
    if(($choice['finish_reason']??'')==='length'){$last='truncated';break;}if(!is_string($reply)||!trim($reply)){$last='invalid';break;}
    $data=isset($job['_validator'])?($job['_validator'])($reply):(($job['stage']==='outline')?aiOutlinePage($reply):aiOperationsPage($reply));
   }catch(JsonException|AppError){$last='invalid';break;}
   $usage=[];foreach(['prompt_tokens','completion_tokens','total_tokens'] as $k)$usage[$k]=isset($envelope['usage'][$k])&&is_numeric($envelope['usage'][$k])?max(0,(int)$envelope['usage'][$k]):null;
   $attempts[array_key_last($attempts)]['reason']='ok';return ['data'=>$data,'model'=>$model,'prompt_version'=>$job['_prompt_version']??AI_TECHNOLOGY_PROMPT_VERSION,'usage'=>$usage,'attempts'=>$attempts,'latency_ms'=>(int)round((microtime(true)-$started)*1000)];
  }
  if($attempts)$attempts[array_key_last($attempts)]['reason']=$last;
 }
 $next=$models?($models[($lastIndex+1)%count($models)]):null;$retryable=in_array($last,['rate_limit','timeout','transport','provider','unavailable','truncated','invalid'],true);
 $message=match($last){
  'key_rejected'=>'Провайдер отклонил серверный ключ AI (401). Проверьте прежний ключ в config.ai.local.php.',
  'provider_blocked'=>'Провайдер AI заблокировал подключение с сервера (403 / Cloudflare). Разрешите IP хостинга в настройках провайдера или обратитесь в его поддержку.',
  'access_denied'=>'Провайдер AI запретил доступ (403). Проверьте разрешения ключа и доступ с хостинга.',
  'balance'=>'На аккаунте провайдера AI недостаточно средств или исчерпана квота.',
  'rate_limit'=>'Провайдер ограничил частоту запросов (429). Прогресс сохранён; продолжите немного позже.',
  'model_unavailable'=>'Основная и доступные резервные модели не приняли запрос. Проверьте список моделей у провайдера.',
  'request_size'=>'Провайдер не принял размер технического описания. Сократите описание текущего изделия; число операций не ограничивается.',
  'timeout'=>'Провайдер не ответил за время одной части. Прогресс сохранён; продолжим с резервной моделью.',
  'truncated','invalid'=>'Модель не вернула целую корректную часть. Готовые узлы сохранены; повторим эту часть.',
  'configuration'=>aiCapabilityMessage('configuration'),default=>'Провайдер AI временно недоступен. Готовые части сохранены; можно продолжить генерацию.'};
 error_log('EASY3 AI '.j(['reason'=>$last,'attempts'=>$attempts]));throw new AiGatewayError($message,$last==='rate_limit'?429:502,$last,$attempts,$retryable,$next,$retryAfter?:($retryable?6:0));
}
