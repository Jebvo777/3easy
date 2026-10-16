<?php
declare(strict_types=1);
require_once __DIR__.'/../organization.php';
function layoutWarnings(array $layout):array{
 $warnings=[];$objects=$layout['objects'];$sizes=['cut'=>[2.8,1.6],'sew'=>[1.5,1],'overlock'=>[1.5,1],'iron'=>[1.8,1],'hand'=>[1.8,1],'qc'=>[1.8,1],'pack'=>[1.8,1],'storage'=>[1.7,.8]];
 foreach($objects as $i=>$a){[$w,$d]=$sizes[$a['type']];if(((int)$a['rotation']%180)!==0)[$w,$d]=[$d,$w];if(abs($a['x'])+$w/2>$layout['width']/2||abs($a['z'])+$d/2>$layout['depth']/2)$warnings[]='Центр выходит за границу комнаты: '.$a['id'];for($k=0;$k<$i;$k++){$b=$objects[$k];[$bw,$bd]=$sizes[$b['type']];if(((int)$b['rotation']%180)!==0)[$bw,$bd]=[$bd,$bw];if(abs($a['x']-$b['x'])<($w+$bw)/2+.15&&abs($a['z']-$b['z'])<($d+$bd)/2+.15)$warnings[]='Пересечение рабочих центров: '.$a['id'].' / '.$b['id'];}}
 return array_values(array_unique($warnings));
}
function builderAction(string $action,array $input):array{
 permit(['owner','technologist','chief','foreman']);return command($action,$input,function(array $c)use($input){
 checkRevision($c['revision'],$input);$l=$input['layout']??[];$clean=['width'=>numberField($l,'width',6,60),'depth'=>numberField($l,'depth',6,60),'objects'=>[],'links'=>[]];
 $passport=$c['passport'];$structure=organizationStructure($passport);$workshop=organizationId($input,'workshop_id',false)?:$structure['workshops'][0]['id'];if(!in_array($workshop,array_column($structure['workshops'],'id'),true))fail('Цех отсутствует');$resources=$passport['resources']??[];$new=$input['new_resources']??[];
 if(!is_array($new)||count($new)>30)fail('Слишком много новых центров');if($new&&$c['role']!=='owner')fail('Создание оборудования доступно собственнику',403);
 $ids=array_column($resources,'id');$peopleById=array_column($passport['people']??[],null,'id');
 foreach($new as $r){$id=textField($r,'id',60,true);if(!preg_match('/^[a-zA-Z0-9_-]+$/',$id)||in_array($id,$ids,true))fail('Рабочий центр уже существует');$type=textField($r,'type',20,true);if(!in_array($type,['cut','sew','overlock','iron','hand','qc','pack'],true))fail('Некорректный тип центра');$worker=textField($r,'worker',60);if($worker&&(!isset($peopleById[$worker])||!$peopleById[$worker]['active']||normalizedWorkshop($peopleById[$worker],$structure)!==$workshop||!in_array($type,$peopleById[$worker]['skills'],true)))fail('Исполнитель не имеет активного навыка для центра');$resources[]=['workshop'=>$workshop,'id'=>$id,'name'=>textField($r,'name',100,true),'type'=>$type,'worker'=>$worker,'active'=>true];$ids[]=$id;}
 $assignments=$input['assignments']??[];if(!is_array($assignments)||count($assignments)>80)fail('Слишком много назначений');
 $peopleById=array_column($passport['people']??[],null,'id');
 foreach($assignments as $rid=>$worker){if(!is_string($rid)||!in_array($rid,$ids,true)||!is_string($worker)||strlen($worker)>60)fail('Некорректное назначение');
  foreach($resources as &$resource)if($resource['id']===$rid){if(normalizedWorkshop($resource,$structure)!==$workshop)fail('Назначение относится к другому цеху');if($worker&&(!isset($peopleById[$worker])||!$peopleById[$worker]['active']||normalizedWorkshop($peopleById[$worker],$structure)!==$workshop||!in_array($resource['type'],$peopleById[$worker]['skills'],true)))fail('Исполнитель не имеет активного навыка для центра');$resource['worker']=$worker;}unset($resource);
 }
 $byId=array_column($resources,null,'id');$objects=$l['objects']??[];if(!is_array($objects)||count($objects)>80)fail('Слишком много объектов');$seen=[];$used=[];
 foreach($objects as $obj){$id=textField($obj,'id',60,true);if(!preg_match('/^[a-zA-Z0-9_-]+$/',$id)||isset($seen[$id]))fail('Объект имеет повторяющийся код');$seen[$id]=1;$type=textField($obj,'type',20,true);if(!in_array($type,['cut','sew','overlock','iron','hand','qc','pack','storage'],true))fail('Неизвестный тип объекта');$rid=textField($obj,'resource',60);
 if($type!=='storage'&&!$rid)fail('Свяжите рабочий центр с ресурсом');if($rid){if(!isset($byId[$rid])||$byId[$rid]['type']!==$type||normalizedWorkshop($byId[$rid],$structure)!==$workshop)fail('Ресурс не соответствует рабочему центру');if(isset($used[$rid]))fail('Один ресурс не может создавать два рабочих центра');$used[$rid]=1;}
 $x=$obj['x']??null;$z=$obj['z']??null;if(!is_numeric($x)||!is_numeric($z)||abs((float)$x)>$clean['width']/2||abs((float)$z)>$clean['depth']/2)fail('Объект за пределами комнаты');$rotation=numberField($obj,'rotation',0,270);if($rotation%90)fail('Поворот кратен 90 градусам');$clean['objects'][]=['id'=>$id,'resource'=>$rid,'type'=>$type,'x'=>round((float)$x*2)/2,'z'=>round((float)$z*2)/2,'rotation'=>$rotation,'zone'=>textField($obj,'zone',60)];}
 $links=$l['links']??[];if(!is_array($links)||count($links)>160)fail('Слишком много связей');foreach($links as $link){if(!is_array($link)||count($link)!==2||!isset($seen[$link[0]],$seen[$link[1]])||$link[0]===$link[1])fail('Некорректная связь');if(!in_array($link,$clean['links'],true))$clean['links'][]=$link;}
 $warnings=layoutWarnings($clean);$passport['resources']=$resources;$passport['structure']=$structure;
 $first=$structure['workshops'][0]['id'];$maps=$c['layout']['workshops']??[];if(!is_array($maps))$maps=[];
 if(!isset($maps[$first]))$maps[$first]=['width'=>$c['layout']['width']??18,'depth'=>$c['layout']['depth']??12,'objects'=>$c['layout']['objects']??[],'links'=>$c['layout']['links']??[]];
 $maps[$workshop]=$clean;$stored=$maps[$first];$stored['workshops']=$maps;
 query('UPDATE companies SET layout=?,passport=?,revision=revision+1 WHERE id=?',[j($stored),j($passport),$c['id']]);audit('layout_saved',['workshop'=>$workshop,'objects'=>count($clean['objects']),'links'=>count($clean['links']),'warnings'=>$warnings]);return ['saved'=>true,'warnings'=>$warnings,'revision'=>$c['revision']+1];
 });
}
