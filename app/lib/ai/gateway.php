<?php
declare(strict_types=1);
require_once __DIR__.'/prompt.php';
function aiConfiguration():array{
 static $cfg=null;if($cfg!==null)return $cfg;$root=dirname(__DIR__,2);$cfg=require $root.'/config.ai.example.php';
 if(is_file($root.'/config.ai.local.php'))$cfg=array_replace($cfg,require $root.'/config.ai.local.php');if(getenv('EASY3_AI_KEY'))$cfg['key']=getenv('EASY3_AI_KEY');return $cfg;
}
function aiStorageAvailable():bool{try{db()->query('SELECT id FROM ai_generations LIMIT 0');return true;}catch(Throwable){return false;}}
function aiCapability():array{
 $cfg=aiConfiguration();$storage=aiStorageAvailable();$configured=!empty($cfg['enabled'])&&trim((string)$cfg['key'])!==''&&function_exists('curl_init')&&$storage;
 return ['enabled'=>(bool)$cfg['enabled'],'configured'=>$configured,'max_operations'=>50,'reason'=>$configured?null:(!$storage?'migration_required':(!function_exists('curl_init')?'curl_required':(empty($cfg['key'])?'key_required':'disabled')))];
}
function aiHttp(array $cfg,array $payload,int $timeout):array{
 $url=rtrim((string)$cfg['base_url'],'/').'/chat/completions';$parts=parse_url($url);
 if(!$parts||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))return ['http'=>0,'error'=>'configuration','body'=>''];
 $body='';$ch=curl_init($url);$options=[CURLOPT_POST=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$cfg['key']],CURLOPT_POSTFIELDS=>j($payload),CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>min(6,$timeout),CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>180000)return 0;$body.=$chunk;return strlen($chunk);}];
 if(!empty($cfg['ca_file']))$options[CURLOPT_CAINFO]=$cfg['ca_file'];curl_setopt_array($ch,$options);$ok=curl_exec($ch);$errno=curl_errno($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return ['http'=>$http,'body'=>$body,'error'=>$ok===false?($errno===28?'timeout':'transport'):null];
}
function aiGateway(array $technical,?array $cfg=null,?callable $transport=null):array{
 $cfg??=aiConfiguration();$transport??='aiHttp';$messages=aiTechnologyMessages($technical);
 if(empty($cfg['enabled'])||trim((string)$cfg['key'])==='')fail('AI не подключён на сервере. Ручной редактор доступен.',503);
 if($transport==='aiHttp'&&!function_exists('curl_init'))fail('Для AI включите расширение PHP cURL.',503);
 $normalize=fn($m)=>preg_replace('/^(gpt-5-(?:nano|mini)|gpt-5\.4-mini)-\d{4}.*$/','$1',trim((string)$m));$models=array_slice(array_values(array_unique(array_filter(array_map($normalize,array_merge([$cfg['model']],$cfg['fallback_models']??[]))))),0,3);
 $started=microtime(true);$budget=max(8,min(55,(int)$cfg['total_timeout']));$last='unavailable';$attempts=0;
 foreach($models as $model){
  $remaining=$budget-(microtime(true)-$started);if($remaining<4){$last='timeout';break;}$timeout=max(4,min((int)$cfg['attempt_timeout'],(int)floor($remaining)));$attempts++;
  $payload=['model'=>$model,'messages'=>$messages,'temperature'=>(float)$cfg['temperature'],'max_tokens'=>max(4000,min(12000,(int)$cfg['max_tokens']))];$response=$transport($cfg,$payload,$timeout);$code=(int)$response['http'];
  if(in_array($code,[401,403],true))fail('Провайдер отклонил серверный ключ AI. Проверьте настройки подключения.',503);if($code===429)fail('Провайдер временно ограничил запросы. Повторите позже.',429);
  if($response['error']){$last=$response['error'];continue;}if($code<200||$code>=300){$last='provider';continue;}
  try{$json=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR);}catch(JsonException){$last='invalid_json';continue;}
  $choice=$json['choices'][0]??null;$reply=$choice['message']['content']??null;if(!is_string($reply)||!trim($reply)||($choice['finish_reason']??'')==='length'){$last='truncated';continue;}
  try{$proposal=aiProposalParse($reply,$technical['max_operations']);}catch(AppError){$last='invalid_proposal';continue;}
  $usage=[];foreach(['prompt_tokens','completion_tokens','total_tokens'] as $field)$usage[$field]=isset($json['usage'][$field])&&is_int($json['usage'][$field])&&$json['usage'][$field]>=0?$json['usage'][$field]:null;
  return ['proposal'=>$proposal,'model'=>$model,'prompt_version'=>AI_TECHNOLOGY_PROMPT_VERSION,'usage'=>$usage,'latency_ms'=>(int)round((microtime(true)-$started)*1000),'attempts'=>$attempts];
 }
 $message=match($last){'timeout'=>'AI не успел ответить. Описание сохранено в окне; можно повторить.', 'truncated','invalid_json','invalid_proposal'=>'AI не вернул полную корректную последовательность. Повторите генерацию или продолжите вручную.',default=>'AI временно недоступен. Ручной редактор работает.'};fail($message,502);
}
