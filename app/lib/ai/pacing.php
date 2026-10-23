<?php
declare(strict_types=1);
/** Per-job pacing is stored in existing JSON; another job never inherits this penalty. */
function aiPacingThrottle(array $state,int $retryAfter,array $config=[],?int $now=null):array{
 $now??=time();$base=max(3,min(60,(int)($config['min_interval_seconds']??$config['min_interval']??10)));$old=(int)($state['interval_seconds']??$base);
 $interval=min(240,max(45,$base+15,(int)ceil($old*1.8),min(240,$retryAfter)));
 return ['interval_seconds'=>$interval,'throttle_count'=>(int)($state['throttle_count']??0)+1,'last_rate_at'=>$now,'next_request_at'=>$now+max($interval,min(3600,max(1,$retryAfter)))];
}
function aiPacingSuccess(array $state,bool $ready=false,?int $now=null):array{
 if(!$state)return [];$state['next_request_at']=$ready?0:($now??time())+max(0,(int)($state['interval_seconds']??0));return $state;
}
function aiPacingWait(array $data,?int $now=null):int{return max(0,(int)($data['_pacing']['next_request_at']??0)-( $now??time()),(int)($data['retry_not_before']??0)-( $now??time()));}
function aiPacingGuard(array $data):void{if($wait=aiPacingWait($data))throw new AiGatewayError('Сохранённый интервал между запросами. Продолжим автоматически.',409,'cooldown',[],true,null,$wait);}
