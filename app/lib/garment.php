<?php
declare(strict_types=1);
require_once __DIR__.'/ai/contract.php';require_once __DIR__.'/ai/illustration.php';
function technologyIllustration(array $order):?array{
 $ids=[];foreach($order['route'] as $op)if(isset($op['ai_origin']['generation_id']))$ids[$op['ai_origin']['generation_id']]=true;
 if(count($ids)!==1)return null;$id=array_key_first($ids);if(!preg_match('/^[a-f0-9]{32}$/',$id))return null;
 $r=one("SELECT response_json FROM ai_generations WHERE id=? AND company_id=? AND order_id=? AND state IN ('ready','applied')",[$id,$order['company_id'],$order['id']]);$illustration=$r?decode($r['response_json'])['illustration']??null:null;if(!$illustration)return null;
 try{return aiIllustrationReply(j($illustration));}catch(AppError){return null;}
}
