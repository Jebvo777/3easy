<?php
declare(strict_types=1);
require_once __DIR__.'/../organization.php';
function validTime(mixed $v):string{if(!is_string($v)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$v))fail('Время указывается в формате ЧЧ:ММ');return $v;}
function passportAction(string $action,array $input):array{
 owner();return command($action,$input,function(array $c)use($input){
 checkRevision($c['revision'],$input);$name=textField($input,'name',120,true);$p=$input['passport']??null;if(!is_array($p))fail('Паспорт не заполнен');
 $clean=validatedPassport($p,$c);companySaveAccounts($p,$clean,$c);
 query('UPDATE companies SET name=?,passport=?,revision=revision+1 WHERE id=?',[$name,j($clean),$c['id']]);audit('passport_saved',['section'=>$input['section']??'all']);return ['saved'=>true,'revision'=>$c['revision']+1];
 });
}

function validatedPassport(array $p,array $c):array{
 $clean=['industry'=>textField($p,'industry',100,true),'city'=>textField($p,'city',100),'description'=>textField($p,'description',1000),'area'=>numberField($p,'area',1,100000,true),'timezone'=>textField($p,'timezone',60,true)];
 if(!in_array($clean['timezone'],DateTimeZone::listIdentifiers(),true))fail('Выберите корректный часовой пояс');
 $people=$p['people']??[];$resources=$p['resources']??[];if(!is_array($people)||count($people)>80||!is_array($resources)||count($resources)>120)fail('Слишком много ресурсов для демонстрации');
 $clean['structure']=organizationStructure($p);$types=['cut','sew','special','overlock','iron','hand','qc','pack','other'];$ids=[];$clean['people']=[];
 foreach($people as $person){
  if(!is_array($person))fail('Некорректный участник');$person=companyPerson($person);$id=organizationId($person);if(isset($ids[$id]))fail('Участник должен иметь уникальный код');$ids[$id]=true;$group=$person['group'];if(!in_array($group,['owner','management','executors'],true))fail('Выберите группу участника');
  $roles=$group==='owner'?['owner']:($person['roles']??['worker']);if(!is_array($roles)||!$roles||array_diff($roles,['owner','chief','technologist','foreman','worker','qc']))fail('Выберите корректную роль');if($group==='management'&&array_diff($roles,['chief','technologist','foreman']))fail('В руководстве выбираются руководящие роли');$duties=$person['duties']??[];if(!is_array($duties)||array_diff($duties,array_keys(COMPANY_DUTIES)))fail('Неизвестная должность');if($group==='executors')$roles=['worker',...(in_array('qc',$duties,true)?['qc']:[])];
  $universal=$group==='executors'&&!$duties;$skills=$group==='executors'?($universal?$types:array_values(array_unique([...$duties,...(in_array('sew',$duties,true)?['special']:[])]))):[];
  $clean['people'][]=['roles'=>array_values(array_unique($roles)),'group'=>$group,'duties'=>$group==='executors'?array_values(array_unique($duties)):[],'universal'=>$universal,'workshop'=>normalizedWorkshop($person,$clean['structure']),'brigade'=>$group==='owner'?'':organizationId($person,'brigade',false),'id'=>$id,'name'=>textField($person,'name',100,true),'role'=>$group==='executors'?implode(', ',array_map(fn($d)=>COMPANY_DUTIES[$d],$duties)):textField($person,'role',100),'skills'=>$skills,'specializations'=>[],'active'=>(bool)($person['active']??true)];
 }

 $rids=[];$clean['resources']=[];foreach($resources as $r){$r=productionItem($r);$id=textField($r,'id',60,true);if(isset($rids[$id])||!preg_match('/^[a-zA-Z0-9_-]+$/',$id))fail('Ресурс должен иметь уникальный код');$rids[$id]=true;$type=textField($r,'type',20,true);if(!in_array($type,$types,true))fail('Неизвестный тип оборудования');if($type==='special'&&!array_key_exists(specialMachineKind($r),SPECIAL_MACHINE_NAMES))fail('Выберите вид спецмашины');$worker=textField($r,'worker',60);if($worker&&!isset($ids[$worker]))fail('Выбранный сотрудник отсутствует в паспорте');$clean['resources'][]=['workshop'=>normalizedWorkshop($r,$clean['structure']),'id'=>$id,'name'=>textField($r,'name',100,true),'type'=>$type,'specialization'=>$type==='special'?specialMachineKind($r):null,'worker'=>$worker,'active'=>(bool)($r['active']??true),'inventory_number'=>textField($r,'inventory_number',60)];}
 $clean['workplaces']=companyValidateWorkplaces($clean+['workplaces'=>$p['workplaces']??companyWorkplaces($c['passport'])],$c['passport']);
 validateOrganization($clean);validateLayoutOrganization($c,$clean);
 $validWorkshops=array_column($clean['structure']['workshops'],'id');foreach(($c['layout']['workshops']??[]) as $wid=>$map)if(!in_array($wid,$validWorkshops,true)&&!empty($map['objects']))fail('Сначала очистите схему удаляемого цеха');
 $sh=$p['shift']??[];$days=$sh['days']??[];if(!is_array($days)||!$days||array_diff($days,[1,2,3,4,5,6,7]))fail('Выберите рабочие дни');
 $clean['shift']=['start'=>validTime($sh['start']??null),'end'=>validTime($sh['end']??null),'break_start'=>validTime($sh['break_start']??null),'break_minutes'=>numberField($sh,'break_minutes',0,180),'days'=>array_values(array_unique(array_map('intval',$days)))];
 if($clean['shift']['start']===$clean['shift']['end'])fail('Начало и конец смены должны различаться');
 $ec=$p['economy']??[];$clean['economy']=['labor_hour_cents'=>numberField($ec,'labor_hour_cents',0,100000000,true),'monthly_common_cents'=>numberField($ec,'monthly_common_cents',0,100000000000,true),'monthly_hours'=>numberField($ec,'monthly_hours',1,100000,true),'currency'=>'RUB','common_breakdown'=>array_values(array_filter(array_map(function($v){return ['name'=>textField($v,'name',180,true),'cents'=>numberField($v,'cents',0,100000000000)];},$ec['common_breakdown']??[])))];if($clean['economy']['common_breakdown'])$clean['economy']['monthly_common_cents']=array_sum(array_column($clean['economy']['common_breakdown'],'cents'));
 $modern=companyEconomyValidate($ec,$c['passport']['economy']??[]);foreach(['schema','tariffs','fixed_expenses','variable_expenses','balance_period','variable_enabled','legacy_labor_hour_cents'] as $field)if(array_key_exists($field,$modern))$clean['economy'][$field]=$modern[$field];
 foreach(['excluded','unknown'] as $list){$values=$ec[$list]??[];if(!is_array($values)||count($values)>30)fail('Слишком много статей');$clean['economy'][$list]=[];foreach($values as $v){$s=textField(['value'=>$v],'value',180);if($s)$clean['economy'][$list][]=$s;}}
 if(!empty($c['is_demo'])&&isset($c['passport']['_demo_environment_version']))$clean['_demo_environment_version']=(int)$c['passport']['_demo_environment_version'];
 return $clean;
}
