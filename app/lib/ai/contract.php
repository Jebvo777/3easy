<?php
declare(strict_types=1);
const AI_TECHNOLOGY_PROMPT_VERSION='garment-route-20261011.1';
const AI_TECHNOLOGY_TYPES=['cut','sew','overlock','iron','qc','pack'];
function aiKeys(array $data,array $allowed,array $required=[]):void{
 if(array_diff(array_keys($data),$allowed)||array_diff($required,array_keys($data)))fail('Ответ AI не соответствует формату. Повторите генерацию или продолжите вручную.',502);
}
function aiString(mixed $v,int $limit,bool $required=false):string{
 if(!is_string($v)||!preg_match('//u',$v)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$v))fail('Некорректный текст в ответе AI',502);
 $v=trim($v);$count=preg_match_all('/./us',$v);if($count>$limit||($required&&$v===''))fail('Некорректная длина текста в ответе AI',502);return $v;
}
function aiInteger(mixed $v,int $min,int $max,bool $nullable=false):?int{
 if($nullable&&$v===null)return null;if(!is_int($v)||$v<$min||$v>$max)fail('Некорректное число в ответе AI',502);return $v;
}
function aiNoIdentifiers(string $text,int $status=422):void{
 if(preg_match('~(*UCP)(?:https?://|www\.|[\w.+-]+@[\w.-]+\.[a-z]{2,}|\bsk-[\w-]+|\+?\d[\d ()-]{8,}\d|\b(?:ФИО|ИНН|СНИЛС|паспорт|телефон|email|e-mail|заказчик|клиент|сотрудник)\b)~iu',$text))fail('Укажите только изделие, материал и конструкцию, без имён, контактов и данных заказчика.',$status);
 if(preg_match('/\b[А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+\b/u',$text))fail('Уберите личные имена из технического описания.',$status);
}
function aiTechnicalInput(array $input):array{
 $product=textField($input,'product',160,true);$description=textField($input,'description',1400);
 foreach([$product,$description] as $s){if(!preg_match('//u',$s)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$s))fail('Некорректное описание изделия');aiNoIdentifiers($s);}
 return ['product'=>$product,'description'=>$description,'max_operations'=>numberField($input,'max_operations',1,50)];
}
function aiTechnicalPrivacy(array $technical,array $company,array $order):void{
 $text=$technical['product'].' '.$technical['description'];$known=[$company['name'],$order['customer']??'',user()['name'],...array_column($company['passport']['people']??[],'name')];
 foreach($known as $name){$name=trim((string)$name);if(strlen($name)<5)continue;if(preg_match('~'.preg_quote($name,'~').'~iu',$text))fail('Уберите названия предприятия, заказчика и имена из описания изделия.');}
}
function aiProposalParse(string $reply,int $limit):array{
 if(strlen($reply)>120000)fail('Ответ AI слишком большой',502);
 $reply=trim($reply);if(preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i',$reply,$m))$reply=$m[1];
 try{$data=json_decode($reply,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){fail('AI вернул незавершённый JSON. Повторите генерацию или продолжите вручную.',502);}
 if(!is_array($data)||array_is_list($data))fail('AI вернул неверный формат',502);
 aiKeys($data,['schema_version','product','assumptions','questions','operations'],['schema_version','product','assumptions','questions','operations']);if($data['schema_version']!==1)fail('Версия формата AI не поддерживается',502);
 $product=aiString($data['product'],160,true);aiNoIdentifiers($product,502);
 $textList=function(mixed $list):array{if(!is_array($list)||!array_is_list($list)||count($list)>8)fail('Слишком много уточнений AI',502);return array_map(function($s){$s=aiString($s,350,true);aiNoIdentifiers($s,502);return $s;},$list);};
 $assumptions=$textList($data['assumptions']);$questions=$textList($data['questions']);$ops=$data['operations'];
 if(!is_array($ops)||!array_is_list($ops)||!count($ops)||count($ops)>min(50,$limit))fail('AI должен вернуть от 1 до '.$limit.' операций',502);
 $route=[];$ids=[];
 foreach($ops as $op){
  if(!is_array($op)||array_is_list($op))fail('Некорректная операция AI',502);
  $fields=['id','name','type','estimated_seconds','setup_minutes','dependencies','equipment','grade','notes'];aiKeys($op,$fields,$fields);
  $id=aiString($op['id'],20,true);if(!preg_match('/^op-[1-9][0-9]?$/',$id)||isset($ids[$id]))fail('AI вернул повторяющийся или неверный код операции',502);$ids[$id]=true;
  $type=aiString($op['type'],20,true);if(!in_array($type,AI_TECHNOLOGY_TYPES,true))fail('Неизвестный тип рабочего центра AI',502);
  $deps=$op['dependencies'];if(!is_array($deps)||!array_is_list($deps)||count($deps)>10||count(array_unique($deps,SORT_REGULAR))!==count($deps))fail('Некорректные связи AI',502);
  foreach($deps as $dep)if(!is_string($dep)||!preg_match('/^op-[1-9][0-9]?$/',$dep)||$dep===$id||!isset($ids[$dep]))fail('AI должен ссылаться только на предыдущие операции',502);
  $name=aiString($op['name'],180,true);$equipment=aiString($op['equipment'],160);$notes=aiString($op['notes'],400);aiNoIdentifiers($name.' '.$equipment.' '.$notes,502);
  $route[]=['id'=>$id,'name'=>$name,'type'=>$type,'seconds'=>null,'estimated_seconds'=>aiInteger($op['estimated_seconds'],1,36000,true),'setup_minutes'=>aiInteger($op['setup_minutes'],0,480),'dependencies'=>$deps,'equipment'=>$equipment,'grade'=>aiInteger($op['grade'],1,6,true),'notes'=>$notes,'source'=>'AI: предложенная операция; норма требует проверки','batch'=>1,'norm_reviewed'=>false];
 }
 require_once __DIR__.'/../engine.php';foreach(routeCheck($route) as $error)if(!str_starts_with($error,'Нет нормы')&&!str_starts_with($error,'Нет источника'))fail('AI вернул неполную структуру: '.$error,502);
 return ['schema_version'=>1,'product'=>$product,'assumptions'=>$assumptions,'questions'=>$questions,'route'=>$route];
}
