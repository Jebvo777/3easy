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
 foreach($workshops as $w){if(!is_array($w))fail('Некорректный цех');$id=organizationId($w);$name=textField($w,'name',100,true);if(isset($ids[$id])||isset($names[$name]))fail('Цех должен иметь уникальный код и название');$ids[$id]=true;$names[$name]=true;$kind=$w['kind']??'assembly';if(!in_array($kind,['assembly','preparation'],true))fail('Выберите назначение подразделения');$clean['workshops'][]=['id'=>$id,'name'=>$name,'kind'=>$kind];}
 $bids=[];$leads=[];$bnames=[];foreach($brigades as $b){if(!is_array($b))fail('Некорректная бригада');$id=organizationId($b);$workshop=organizationId($b,'workshop');$lead=organizationId($b,'lead');$name=textField($b,'name',100,true);if(isset($bids[$id])||isset($leads[$lead])||isset($bnames[$workshop.'|'.$name]))fail('Бригада и её бригадир должны быть уникальными');if(!isset($ids[$workshop]))fail('Цех бригады отсутствует');$bids[$id]=true;$leads[$lead]=true;$bnames[$workshop.'|'.$name]=true;$clean['brigades'][]=['id'=>$id,'name'=>$name,'workshop'=>$workshop,'lead'=>$lead];}
 return $clean;
}
function normalizedWorkshop(array $item,array $structure):string{
 $id=organizationId($item,'workshop',false);if(!$id)$id=$structure['workshops'][0]['id'];if(!in_array($id,array_column($structure['workshops'],'id'),true))fail('Выбранный цех отсутствует');return $id;
}
function normalizedPersonWorkshops(array $person,array $structure):array{
 if(($person['group']??'executors')==='owner')return [];
 if(($person['group']??'executors')!=='management')return [normalizedWorkshop($person,$structure)];
 $raw=array_key_exists('workshops',$person)?$person['workshops']:[normalizedWorkshop($person,$structure)];
 if(!is_array($raw)||!array_is_list($raw)||!$raw||count($raw)>20)fail('Выберите одно или несколько подразделений руководителя');
 $allowed=array_column($structure['workshops'],'id');$ids=[];
 foreach($raw as $value){$id=organizationId(['id'=>$value]);if(!in_array($id,$allowed,true))fail('Выбранное подразделение руководителя отсутствует');if(in_array($id,$ids,true))fail('Подразделения руководителя не должны повторяться');$ids[]=$id;}
 return $ids;
}
function validateOrganization(array $passport):void{
 $people=array_column($passport['people'],null,'id');$brigades=array_column($passport['structure']['brigades'],null,'id');
 foreach($passport['people'] as $person){$bid=$person['brigade'];if($bid&&(!isset($brigades[$bid])||!in_array($brigades[$bid]['workshop'],companyPersonWorkshops($person),true)))fail('Бригада человека должна относиться к его цеху');}
 foreach($brigades as $b){$p=$people[$b['lead']]??null;if(!$p||!in_array('foreman',$p['roles'],true)||!in_array($b['workshop'],companyPersonWorkshops($p),true)||$p['brigade']!==$b['id'])fail('Бригаде нужен бригадир с соответствующей ролью в этом цехе');}
 foreach($passport['resources'] as $r){$kind=array_column($passport['structure']['workshops'],'kind','id')[$r['workshop']]??'assembly';if(($r['type']==='cut'&&$kind!=='preparation')||($kind==='preparation'&&in_array($r['type'],['sew','special'],true)))fail('Раскрой относится к отдельному подразделению подготовки; пошив и спецмашины — к сборке');if(!$r['worker'])continue;$p=$people[$r['worker']];if($p['workshop']!==$r['workshop'])fail('Исполнитель и центр должны относиться к одному цеху. Переназначьте центр.');if(!productionCanWork(array_replace($p,['active'=>true]),$r))fail('У исполнителя нет навыка для назначенного центра');}
}

function validateLayoutOrganization(array $company,array $passport):void{
 $new=array_column($passport['resources'],null,'id');$oldStructure=organizationStructure($company['passport']);$first=$oldStructure['workshops'][0]['id'];
 $maps=$company['layout']['workshops']??[$first=>$company['layout']];
 foreach($maps as $wid=>$layout)foreach($layout['objects']??[] as $object){$rid=$object['resource']??'';if(!$rid)continue;if(!isset($new[$rid])||$new[$rid]['workshop']!==$wid)fail('Сначала уберите перемещаемый или удаляемый центр с карты его цеха');}
}
