<?php
declare(strict_types=1);
require_once __DIR__.'/../catalog.php';
function technologyMetadata(array $op,array $company,array $order):array{
 $extra=['equipment'=>textField($op,'equipment',300),'grade'=>numberField($op,'grade',1,6,true),'notes'=>textField($op,'notes',1200),'node_id'=>textField($op,'node_id',60)?:'node-main','node_name'=>textField($op,'node_name',180)?:'Основные работы','estimated_seconds'=>numberField($op,'estimated_seconds',1,86400,true)];
 if(isset($op['catalog_ref'])&&is_array($op['catalog_ref'])){$v=catalogVariant(textField($op['catalog_ref'],'variant',60));if($v)$extra['catalog_ref']=['operation'=>$v['operation'],'variant'=>$v['id'],'condition'=>$v['condition'],'construction'=>$v['construction'],'source'=>'1С ТехОперации 27.09.2026'];}
 if(!isset($op['ai_origin']))return $extra;
 $origin=$op['ai_origin'];if(!is_array($origin))fail('Некорректное происхождение технологии');$id=textField($origin,'generation_id',32,true);if(!preg_match('/^[a-f0-9]{32}$/',$id))fail('Некорректный код AI');
 static $cache=[];if(!isset($cache[$id])){$row=one('SELECT * FROM ai_generations WHERE id=? AND company_id=? AND order_id=?'.lockSuffix(),[$id,$company['id'],$order['id']]);if(!$row||!in_array($row['state'],['ready','applied'],true))fail('Предложение AI недоступно для заказа',409);$cache[$id]=['row'=>$row,'ops'=>array_column(decode($row['response_json'])['route']??[],null,'id')];}
 $row=$cache[$id]['row'];if($row['state']==='ready'&&(int)$row['order_revision']!==(int)$order['revision'])fail('Заказ изменился после генерации',409);$original=$cache[$id]['ops'][$op['id']??'']??null;if(!$original)fail('Операция отсутствует в исходном предложении');$reviewed=$op['norm_reviewed']??false;if(!is_bool($reviewed))fail('Некорректное подтверждение нормы');if(($op['seconds']??null)!==null&&($op['seconds']??'')!==''&&!$reviewed)fail('Проверьте время операции AI перед расчётом');
 $extra['estimated_seconds']=$original['estimated_seconds'];return $extra+['norm_reviewed'=>$reviewed,'ai_origin'=>['generation_id'=>$row['id'],'model'=>$row['model'],'prompt_version'=>$row['prompt_version']]];
}
