<?php
declare(strict_types=1);
function organizationId(array $value,string $field='id',bool $required=true):string{
 $id=textField($value,$field,60,$required);if($id&&!preg_match('/^[a-zA-Z0-9_-]+$/',$id))fail('Некорректный код структуры');return $id;
}
function organizationStructure(array $passport):array{
 $raw=$passport['structure']??null;if($raw===null)return ['workshops'=>[['id'=>'w-main','name'=>'Основной цех']],'brigades'=>[]];
 if(!is_array($raw))fail('Некорректная структура предприятия');$workshops=$raw['workshops']??[];$brigades=$raw['brigades']??[];
 if(!is_array($workshops)||!count($workshops)||count($workshops)>20||!is_array($brigades)||count($brigades)>40)fail('Укажите от одного до 20 цехов и до 40 бригад');
 $clean=['workshops'=>[],'brigades'=>[]];$ids=[];$names=[];
 foreach($workshops as $w){if(!is_array($w))fail('Некорректный цех');$id=organizationId($w);$name=textField($w,'name',100,true);if(isset($ids[$id])||isset($names[$name]))fail('Цех должен иметь уникальный код и название');$ids[$id]=true;$names[$name]=true;$clean['workshops'][]=['id'=>$id,'name'=>$name];}
 $bids=[];$leads=[];$bnames=[];foreach($brigades as $b){if(!is_array($b))fail('Некорректная бригада');$id=organizationId($b);$workshop=organizationId($b,'workshop');$lead=organizationId($b,'lead');$name=textField($b,'name',100,true);if(isset($bids[$id])||isset($leads[$lead])||isset($bnames[$workshop.'|'.$name]))fail('Бригада и её бригадир должны быть уникальными');if(!isset($ids[$workshop]))fail('Цех бригады отсутствует');$bids[$id]=true;$leads[$lead]=true;$bnames[$workshop.'|'.$name]=true;$clean['brigades'][]=['id'=>$id,'name'=>$name,'workshop'=>$workshop,'lead'=>$lead];}
 return $clean;
}
function normalizedWorkshop(array $item,array $structure):string{
 $id=organizationId($item,'workshop',false);if(!$id)$id=$structure['workshops'][0]['id'];if(!in_array($id,array_column($structure['workshops'],'id'),true))fail('Выбранный цех отсутствует');return $id;
}
function validateOrganization(array $passport):void{
 $people=array_column($passport['people'],null,'id');$brigades=array_column($passport['structure']['brigades'],null,'id');
 foreach($passport['people'] as $person){$bid=$person['brigade'];if($bid&&(!isset($brigades[$bid])||$brigades[$bid]['workshop']!==$person['workshop']))fail('Бригада человека должна относиться к его цеху');}
 foreach($brigades as $b){$p=$people[$b['lead']]??null;if(!$p||!in_array('foreman',$p['roles'],true)||$p['workshop']!==$b['workshop']||$p['brigade']!==$b['id'])fail('Бригаде нужен бригадир с соответствующей ролью в этом цехе');}
 foreach($passport['resources'] as $r){if(!$r['worker'])continue;$p=$people[$r['worker']];if($p['workshop']!==$r['workshop'])fail('Исполнитель и центр должны относиться к одному цеху. Переназначьте центр.');if(!in_array($r['type'],$p['skills'],true))fail('У исполнителя нет навыка для назначенного центра');}
}
