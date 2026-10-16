<?php
declare(strict_types=1);
function operationCatalog():array{static $data=null;return $data??=json_decode(file_get_contents(dirname(__DIR__).'/data/operations.json'),true,128,JSON_THROW_ON_ERROR);}
function catalogTokens(string $s):array{$s=function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);preg_match_all('/[\p{L}\p{N}]{3,}/u',$s,$m);return array_values(array_unique($m[0]));}
function catalogFind(string $term,int $limit=30):array{
 $tokens=catalogTokens($term);$rank=[];foreach(operationCatalog()['operations'] as $op){$s=$op['node'].' '.$op['name'].' '.$op['action'];$score=0;foreach($tokens as $token)if(preg_match('~'.preg_quote($token,'~').'~iu',$s))$score++;if($score||!$tokens)$rank[]=['score'=>$score,'op'=>$op];}
 usort($rank,fn($a,$b)=>$b['score']<=>$a['score']?:strcmp($a['op']['id'],$b['op']['id']));return array_column(array_slice($rank,0,$limit),'op');
}
function catalogContext(string $term):array{return array_map(function($op){return ['code'=>$op['id'],'node'=>$op['node'],'name'=>$op['name'],'variants'=>array_map(fn($v)=>array_intersect_key($v,array_flip(['id','condition','construction','seconds'])),array_slice($op['variants'],0,2))];},catalogFind($term,8));}
function catalogVariant(string $id):?array{if(!$id)return null;foreach(operationCatalog()['operations'] as $op)foreach($op['variants'] as $v)if($v['id']===$id)return ['operation'=>$op['id'],'name'=>$op['name'],'node'=>$op['node']]+$v;return null;}
function technologyExamples():array{static $data=null;return $data??=json_decode(file_get_contents(dirname(__DIR__).'/data/examples.json'),true,128,JSON_THROW_ON_ERROR);}
