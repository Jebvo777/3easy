<?php
declare(strict_types=1);
function technologyMetadata(array $op,array $company,array $order):array{
 $extra=['equipment'=>textField($op,'equipment',160),'grade'=>numberField($op,'grade',1,6,true),'notes'=>textField($op,'notes',400)];
 if(!isset($op['ai_origin']))return $extra;
 $origin=$op['ai_origin'];if(!is_array($origin))fail('Некорректное происхождение технологии');$id=textField($origin,'generation_id',32,true);if(!preg_match('/^[a-f0-9]{32}$/',$id))fail('Некорректный код предложения AI');
 static $cache=[];$row=$cache[$id]??=one('SELECT * FROM ai_generations WHERE id=? AND company_id=? AND order_id=?'.lockSuffix(),[$id,$company['id'],$order['id']]);if(!$row||!in_array($row['state'],['ready','applied'],true))fail('Предложение AI недоступно для этой цели',409);
 if($row['state']==='ready'&&(int)$row['order_revision']!==(int)$order['revision'])fail('Цель изменилась после генерации. Соберите новую последовательность.',409);
 $proposal=decode($row['response_json']);$original=null;foreach($proposal['route'] as $candidate)if($candidate['id']===($op['id']??null)){$original=$candidate;break;}if(!$original)fail('Операция отсутствует в исходном предложении AI');
 if(isset($op['norm_reviewed'])&&!is_bool($op['norm_reviewed']))fail('Некорректное подтверждение нормы');$reviewed=$op['norm_reviewed']??false;
 if(($op['seconds']??null)!==null&&($op['seconds']??'')!==''&&!$reviewed)fail('Сначала проверьте норму и подготовку операции AI');
 return $extra+['estimated_seconds'=>$original['estimated_seconds'],'norm_reviewed'=>$reviewed,'ai_origin'=>['generation_id'=>$row['id'],'model'=>$row['model'],'prompt_version'=>$row['prompt_version']]];
}
