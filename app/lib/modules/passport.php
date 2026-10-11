<?php
declare(strict_types=1);
require_once __DIR__.'/../organization.php';
function validTime(mixed $v):string{if(!is_string($v)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$v))fail('Время указывается в формате ЧЧ:ММ');return $v;}
function passportAction(string $action,array $input):array{
 owner();return command($action,$input,function(array $c)use($input){
 checkRevision($c['revision'],$input);$name=textField($input,'name',120,true);$p=$input['passport']??null;if(!is_array($p))fail('Паспорт не заполнен');
 $clean=['industry'=>textField($p,'industry',100,true),'city'=>textField($p,'city',100),'description'=>textField($p,'description',1000),'area'=>numberField($p,'area',1,100000,true),'timezone'=>textField($p,'timezone',60,true)];
 if(!in_array($clean['timezone'],DateTimeZone::listIdentifiers(),true))fail('Выберите корректный часовой пояс');
 $people=$p['people']??[];$resources=$p['resources']??[];if(!is_array($people)||count($people)>80||!is_array($resources)||count($resources)>120)fail('Слишком много ресурсов для демонстрации');
 $clean['structure']=organizationStructure($p);$types=['cut','sew','overlock','iron','qc','pack'];$ids=[];$clean['people']=[];
 foreach($people as $person){if(!is_array($person))fail('Некорректный участник');$id=textField($person,'id',60,true);if(isset($ids[$id])||!preg_match('/^[a-zA-Z0-9_-]+$/',$id))fail('Участник должен иметь уникальный код');$ids[$id]=true;$skills=$person['skills']??[];if(!is_array($skills)||array_diff($skills,$types))fail('Неизвестная квалификация');$roles=$person['roles']??['worker'];if(!is_array($roles)||!$roles||array_diff($roles,['owner','chief','technologist','foreman','worker','qc']))fail('Выберите корректные роли человека');$clean['people'][]=['roles'=>array_values(array_unique($roles)),'workshop'=>normalizedWorkshop($person,$clean['structure']),'brigade'=>organizationId($person,'brigade',false),'id'=>$id,'name'=>textField($person,'name',100,true),'role'=>textField($person,'role',60),'skills'=>array_values(array_unique($skills)),'active'=>(bool)($person['active']??true)];}
 $rids=[];$clean['resources']=[];foreach($resources as $r){$id=textField($r,'id',60,true);if(isset($rids[$id])||!preg_match('/^[a-zA-Z0-9_-]+$/',$id))fail('Ресурс должен иметь уникальный код');$rids[$id]=true;$type=textField($r,'type',20,true);if(!in_array($type,$types,true))fail('Неизвестный тип оборудования');$worker=textField($r,'worker',60);if($worker&&!isset($ids[$worker]))fail('Выбранный сотрудник отсутствует в паспорте');$clean['resources'][]=['workshop'=>normalizedWorkshop($r,$clean['structure']),'id'=>$id,'name'=>textField($r,'name',100,true),'type'=>$type,'worker'=>$worker,'active'=>(bool)($r['active']??true)];}
 validateOrganization($clean);
 $validWorkshops=array_column($clean['structure']['workshops'],'id');foreach(($c['layout']['workshops']??[]) as $wid=>$map)if(!in_array($wid,$validWorkshops,true)&&!empty($map['objects']))fail('Сначала очистите схему удаляемого цеха');
 $sh=$p['shift']??[];$days=$sh['days']??[];if(!is_array($days)||!$days||array_diff($days,[1,2,3,4,5,6,7]))fail('Выберите рабочие дни');
 $clean['shift']=['start'=>validTime($sh['start']??null),'end'=>validTime($sh['end']??null),'break_start'=>validTime($sh['break_start']??null),'break_minutes'=>numberField($sh,'break_minutes',0,180),'days'=>array_values(array_unique(array_map('intval',$days)))];
 if($clean['shift']['start']===$clean['shift']['end'])fail('Начало и конец смены должны различаться');
 $ec=$p['economy']??[];$clean['economy']=['labor_hour_cents'=>numberField($ec,'labor_hour_cents',0,100000000,true),'monthly_common_cents'=>numberField($ec,'monthly_common_cents',0,100000000000,true),'monthly_hours'=>numberField($ec,'monthly_hours',1,100000,true),'currency'=>'RUB'];
 foreach(['excluded','unknown'] as $list){$values=$ec[$list]??[];if(!is_array($values)||count($values)>30)fail('Слишком много статей');$clean['economy'][$list]=[];foreach($values as $v){$s=textField(['value'=>$v],'value',180);if($s)$clean['economy'][$list][]=$s;}}
 query('UPDATE companies SET name=?,passport=?,revision=revision+1 WHERE id=?',[$name,j($clean),$c['id']]);audit('passport_saved',['section'=>$input['section']??'all']);return ['saved'=>true,'revision'=>$c['revision']+1];
 });
}
