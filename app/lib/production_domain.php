<?php
declare(strict_types=1);
const SPECIAL_MACHINE_NAMES=['overlock'=>'Оверлок','coverstitch'=>'Распошивальная','bar_tack'=>'Закрепочная','buttonhole'=>'Петельная','button'=>'Пуговичная','double_needle'=>'Двухигольная','zigzag'=>'Зигзаг','other'=>'Другая спецмашина'];
function specialMachineKind(array $item):string{
 if(!empty($item['specialization']))return (string)$item['specialization'];
 if(($item['type']??'')==='overlock')return 'overlock';
 $name=mb_strtolower(($item['name']??'').' '.($item['equipment']??''));
 foreach(['распош'=>'coverstitch','плоскош'=>'coverstitch','закрепоч'=>'bar_tack','петельн'=>'buttonhole','пугович'=>'button','двухиг'=>'double_needle','зигзаг'=>'zigzag','оверлок'=>'overlock','обмёт'=>'overlock'] as $stem=>$kind)if(str_contains($name,$stem))return $kind;
 return 'other';
}
function productionItem(array $item):array{
 $kind=specialMachineKind($item);if(($item['type']??'')==='overlock'||(($item['type']??'')==='sew'&&$kind!=='other')){$item['type']='special';$item['specialization']=$kind;}
 if(($item['type']??'')==='special')$item['specialization']=$kind;
 return $item;
}
function productionPerson(array $person):array{
 if(in_array('overlock',$person['skills']??[],true)){$person['skills']=array_values(array_unique(array_map(fn($s)=>$s==='overlock'?'special':$s,$person['skills'])));$person['specializations']=array_values(array_unique([...($person['specializations']??[]),'overlock']));}
 return $person;
}
function productionCanWork(array $person,array $op):bool{
 $person=productionPerson($person);$op=productionItem($op);if(empty($person['active'])||!in_array($op['type'],$person['skills']??[],true))return false;
 if($op['type']!=='special')return true;$known=$person['specializations']??[];$kind=$op['specialization']??'other';return $kind!=='other'&&in_array($kind,$known,true);
}
function productionAssemblyRoute(array $route):array{
 $map=array_column($route,null,'id');$roots=function(string $id,array $seen=[])use(&$roots,$map):array{if(isset($seen[$id])||!isset($map[$id]))return [];$seen[$id]=true;$op=$map[$id];if(($op['type']??'')!=='cut'&&($op['domain']??'assembly')!=='preparation')return [$id];$out=[];foreach($op['dependencies']??[] as $d)$out=[...$out,...$roots($d,$seen)];return $out;};
 $out=[];foreach($route as $op){if(($op['type']??'')==='cut'||($op['domain']??'assembly')==='preparation')continue;$op=productionItem($op);$deps=[];foreach($op['dependencies']??[] as $d)$deps=[...$deps,...$roots($d)];$op['dependencies']=array_values(array_unique($deps));$out[]=$op;}return $out;
}
function productionPreparationRoute(array $route):array{$out=array_values(array_filter($route,fn($op)=>($op['type']??'')==='cut'||($op['domain']??'assembly')==='preparation'));$ids=array_column($out,'id');foreach($out as &$op)$op['dependencies']=array_values(array_intersect($op['dependencies']??[],$ids));unset($op);return $out;}
/** Read compatibility: old mixed workshops become two departments without changing historical snapshots. */
function productionCompanyView(array $company):array{
 $p=$company['passport'];$layout=$company['layout'];$structure=$p['structure']??['workshops'=>[['id'=>'w-main','name'=>'Пошив']],'brigades'=>[]];
 foreach($structure['workshops'] as &$w)$w['kind']??='assembly';unset($w);
 $cut=array_values(array_filter($p['resources']??[],fn($r)=>$r['type']==='cut'));$needsPrep=(bool)$cut;$prep=null;
 foreach($structure['workshops'] as $w)if($w['kind']==='preparation'){$prep=$w['id'];break;}
 if($needsPrep&&!$prep){$prep='w-preparation';$ids=array_column($structure['workshops'],'id');if(in_array($prep,$ids,true))$prep='w-preparation-legacy';$structure['workshops'][]=['id'=>$prep,'name'=>'Подготовка · раскрой и комплектация','kind'=>'preparation'];}
 $resourcePeople=[];foreach($p['resources']??[] as $r)if(!empty($r['worker'])&&$r['type']!=='cut')$resourcePeople[$r['worker']]=true;
 $prepPeople=[];foreach($cut as $r)if(!empty($r['worker'])&&!isset($resourcePeople[$r['worker']]))$prepPeople[$r['worker']]=true;
 foreach($p['people'] as &$person){$person=productionPerson($person);if(isset($prepPeople[$person['id']])){$person['workshop']=$prep;$person['brigade']='';}}unset($person);
 foreach($p['resources'] as &$r){$r=productionItem($r);if($r['type']==='cut'){$r['workshop']=$prep;if(!empty($r['worker'])&&!isset($prepPeople[$r['worker']]))$r['worker']='';}}unset($r);
 // A named machine documents a worker's existing assignment, not new qualifications.
 foreach($p['resources'] as $r)if($r['type']==='special'&&!empty($r['worker']))foreach($p['people'] as &$person)if($person['id']===$r['worker']){$person['skills']=array_values(array_unique([...$person['skills'],'special']));$person['specializations']=array_values(array_unique([...($person['specializations']??[]),$r['specialization']]));}unset($person);
 $first=$structure['workshops'][0]['id'];$maps=$layout['workshops']??[$first=>$layout];
 foreach($structure['workshops'] as $w)$maps[$w['id']]??=['width'=>18,'depth'=>12,'objects'=>[],'links'=>[]];
 $resources=array_column($p['resources'],null,'id');$moved=[];
 foreach($maps as $wid=>&$room){$keep=[];foreach($room['objects']??[] as $o){$r=$resources[$o['resource']??'']??null;if($r&&$r['workshop']!==$wid){$moved[$r['workshop']][]=$o;continue;}$o['type']=($r['type']??$o['type'])==='special'?'special':$o['type'];$keep[]=$o;}$room['objects']=$keep;$ids=array_column($keep,'id');$room['links']=array_values(array_filter($room['links']??[],fn($e)=>in_array($e[0],$ids,true)&&in_array($e[1],$ids,true)));}unset($room);
 foreach($moved as $wid=>$objects)foreach($objects as $o){$n=count($maps[$wid]['objects']);$o['x']=-5+($n%3)*4;$o['z']=-3+intdiv($n,3)*3;$maps[$wid]['objects'][]=$o;}
 $p['structure']=$structure;$layout=$maps[$first];$layout['workshops']=$maps;$company['passport']=$p;$company['layout']=$layout;return $company;
}
function productionAssemblyPeople(array $passport):array{$kinds=array_column($passport['structure']['workshops']??[], 'kind','id');$ids=[];foreach($passport['resources']??[] as $r)if($r['type']!=='cut'&&($kinds[$r['workshop']??'']??'assembly')==='assembly'&&!empty($r['worker']))$ids[$r['worker']]=true;return array_values(array_filter($passport['people']??[],fn($p)=>!empty($p['active'])&&isset($ids[$p['id']])));}
function productionAssemblyTasks(array $orders,array $tasks):array{
 $allowed=[];foreach($orders as $o)$allowed[$o['id']]=array_column($o['route'],'id');$remove=[];foreach($tasks as $t)if(!in_array($t['operation_id'],$allowed[$t['order_id']]??[],true)||!empty(decode($t['planning_meta']??null)['cutting']))$remove[$t['id']]=true;
 $out=[];foreach($tasks as $t)if(!isset($remove[$t['id']])){$t['dependencies']=j(array_values(array_filter(decode($t['dependencies']),fn($d)=>!isset($remove[$d]))));$out[]=$t;}return $out;
}
