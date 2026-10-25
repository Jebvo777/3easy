<?php
declare(strict_types=1);

const COMPANY_DUTIES=['sew'=>'Швея','qc'=>'ОТК','cut'=>'Раскрой','pack'=>'Упаковка','iron'=>'ВТО','hand'=>'Ручная работа'];
function companyPersonWorkshops(array $person):array{
 $roles=$person['roles']??['worker'];$group=$person['group']??(in_array('owner',$roles,true)?'owner':(array_intersect($roles,['chief','technologist','foreman'])?'management':'executors'));
 if($group==='owner')return [];
 $primary=(string)($person['workshop']??'');
 if($group!=='management')return $primary!==''?[$primary]:[];
 $raw=$person['workshops']??[];$ids=is_array($raw)?array_values(array_unique(array_filter($raw,fn($id)=>is_string($id)&&$id!==''))):[];
 return $ids?:($primary!==''?[$primary]:[]);
}
function companyPerson(array $p):array{
 $roles=$p['roles']??['worker'];$p['roles']=$roles;$p['group']=$p['group']??(in_array('owner',$roles,true)?'owner':(array_intersect($roles,['chief','technologist','foreman'])?'management':'executors'));
 if(!isset($p['duties'])){$p['duties']=array_values(array_intersect($p['skills']??[],array_keys(COMPANY_DUTIES)));if(in_array('special',$p['skills']??[],true)&&!in_array('sew',$p['duties'],true))$p['duties'][]='sew';if(in_array('qc',$roles,true))$p['duties'][]='qc';}
 $p['universal']=$p['universal']??true;$p['workshops']=companyPersonWorkshops($p);if($p['group']==='management'&&$p['workshops']&&!in_array($p['workshop']??'', $p['workshops'],true))$p['workshop']=$p['workshops'][0];return $p;
}
function companyWorkplaces(array $p):array{
 if(isset($p['workplaces'])&&is_array($p['workplaces']))return array_map(fn($w)=>array_replace(['equipment'=>[]],$w,['active'=>(bool)($w['active']??true)]),$p['workplaces']);$out=[];
 foreach($p['resources']??[] as $r)$out[]=['id'=>'wp-'.$r['id'],'name'=>$r['name'],'workshop'=>$r['workshop']??'w-main','equipment'=>[$r['id']],'active'=>true];
 return $out;
}
function companyValidateWorkplaces(array $p,array $old):array{
 $raw=$p['workplaces']??companyWorkplaces($old);if(!is_array($raw)||count($raw)>160)fail('Допускается до 160 рабочих мест');$ids=[];$names=[];$assigned=[];$equipment=array_column($p['resources'],null,'id');$departments=array_column($p['structure']['workshops'],null,'id');$out=[];
 foreach($raw as $w){$id=organizationId($w);$name=textField($w,'name',100,true);$dep=organizationId($w,'workshop');$nk=$dep.'|'.mb_strtolower($name);if(isset($ids[$id])||isset($names[$nk])||!isset($departments[$dep]))fail('Проверьте код, название и подразделение рабочего места');$ids[$id]=true;$names[$nk]=true;$items=$w['equipment']??[];if(!is_array($items)||count($items)>30)fail('Проверьте оборудование рабочего места');foreach($items as $eid){if(!is_string($eid)||!isset($equipment[$eid])||isset($assigned[$eid]))fail('Одна единица оборудования закрепляется только за одним рабочим местом');if($equipment[$eid]['workshop']!==$dep)fail('Оборудование и рабочее место должны быть в одном подразделении');$assigned[$eid]=true;}$out[]=['id'=>$id,'name'=>$name,'workshop'=>$dep,'equipment'=>array_values(array_unique($items)),'active'=>(bool)($w['active']??true)];}
 return $out;
}
function companyStorage(string $table):bool{static $ready=[];if(!array_key_exists($table,$ready)){try{db()->query('SELECT 1 FROM `'.$table.'` LIMIT 0');$ready[$table]=true;}catch(Throwable){$ready[$table]=false;}}return $ready[$table];}
function companyWorkerId(array $c):string{
 if(companyStorage('person_accounts')){$r=one('SELECT person_id FROM person_accounts WHERE company_id=? AND user_id=?',[$c['id'],$_SESSION['user_id']]);if($r)return $r['person_id'];}
 return !empty($_SESSION['demo_company'])&&$_SESSION['demo_company']===$c['id']?($_SESSION['worker_resource']??'p-sew-1'):'';
}
function companyAccountSummaries(array $c):array{
 if($c['role']!=='owner'||!companyStorage('person_accounts'))return [];
 return rows('SELECT p.person_id,u.email FROM person_accounts p JOIN users u ON BINARY u.id=BINARY p.user_id WHERE p.company_id=?',[$c['id']]);
}
function companySaveAccounts(array $raw,array $clean,array $c):void{
 foreach($raw['people']??[] as $source){$access=$source['access']??null;if(!is_array($access)||empty($access['email']))continue;if(!companyStorage('person_accounts'))fail('Для доступа участников импортируйте SQL обновления 25.10.2026',503);$person=array_column($clean['people'],null,'id')[$source['id']]??null;if(!$person)fail('Участник не найден');$email=mb_strtolower(textField($access,'email',190,true));if(!filter_var($email,FILTER_VALIDATE_EMAIL))fail('Укажите корректную почту участника');$password=(string)($access['password']??'');if($password!==''&&(strlen($password)<10||strlen($password)>128))fail('Пароль участника: от 10 до 128 символов');$linked=one('SELECT user_id FROM person_accounts WHERE company_id=? AND person_id=?',[$c['id'],$person['id']]);$user=one('SELECT id FROM users WHERE email=?',[$email]);
  if($linked){if($user&&$user['id']!==$linked['user_id'])fail('Эта почта уже используется другим аккаунтом',409);$uid=$linked['user_id'];query('UPDATE users SET email=?,name=? WHERE id=?',[$email,$person['name'],$uid]);if($password!=='')query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$uid]);}
  elseif($user&&$user['id']===$_SESSION['user_id']&&$person['group']==='owner'){$uid=$user['id'];if(one('SELECT person_id FROM person_accounts WHERE company_id=? AND user_id=?',[$c['id'],$uid]))fail('Этот аккаунт уже связан с другим участником');query('INSERT INTO person_accounts (company_id,person_id,user_id) VALUES (?,?,?)',[$c['id'],$person['id'],$uid]);}
  else{if($user)fail('Почта уже зарегистрирована. Используйте отдельный аккаунт участника.',409);if($password==='')fail('Задайте пароль нового участника');$uid=uid();query('INSERT INTO users (id,email,name,password_hash) VALUES (?,?,?,?)',[$uid,$email,$person['name'],password_hash($password,PASSWORD_DEFAULT)]);query('INSERT INTO person_accounts (company_id,person_id,user_id) VALUES (?,?,?)',[$c['id'],$person['id'],$uid]);}
  $role=$person['group']==='owner'?'owner':($person['group']==='management'?(in_array('chief',$person['roles'],true)?'chief':(in_array('technologist',$person['roles'],true)?'technologist':'foreman')):(in_array('qc',$person['duties'],true)&&count($person['duties'])===1?'qc':'worker'));
  $membership=one('SELECT role FROM memberships WHERE company_id=? AND user_id=?',[$c['id'],$uid]);if($membership)query('UPDATE memberships SET role=? WHERE company_id=? AND user_id=?',[$role,$c['id'],$uid]);else query('INSERT INTO memberships (company_id,user_id,role) VALUES (?,?,?)',[$c['id'],$uid,$role]);
 }
 // Structural edits update linked account roles even when credentials were not edited.
 if(companyStorage('person_accounts'))foreach(rows('SELECT person_id,user_id FROM person_accounts WHERE company_id=?',[$c['id']]) as $link){$p=array_column($clean['people'],null,'id')[$link['person_id']]??null;if(!$p)fail('Участника с доступом нельзя удалить: сначала оставьте его недоступным');if($link['user_id']===$_SESSION['user_id']&&$p['group']!=='owner')fail('Нельзя снять свой доступ владельца');}
}
function goalVariants(array $o):array{
 $clean=fn($list)=>array_map(fn($v)=>['id'=>$v['id'],'color'=>$v['color'],'size'=>$v['size'],'quantity'=>(int)$v['quantity']],$list);
 if(isset($o['variants']))return $clean($o['variants']);if(companyStorage('order_variant_revisions')){$r=one('SELECT variants_json FROM order_variant_revisions WHERE company_id=? AND order_id=? AND revision<=? ORDER BY revision DESC LIMIT 1',[$o['company_id'],$o['id'],(int)$o['revision']]);if($r)return $clean(decode($r['variants_json']));}
 if(companyStorage('plan_workspaces')){$r=one('SELECT state_json FROM plan_workspaces WHERE company_id=? AND order_id=?',[$o['company_id'],$o['id']]);$v=$r?decode($r['state_json'])['input']['variants']??[]:[];if($v&&array_sum(array_column($v,'quantity'))===(int)$o['quantity'])return $clean($v);}
 if(companyStorage('order_preparations')){$r=one('SELECT state_json FROM order_preparations WHERE company_id=? AND order_id=?',[$o['company_id'],$o['id']]);$v=$r?decode($r['state_json'])['items']??[]:[];if($v&&array_sum(array_column($v,'quantity'))===(int)$o['quantity'])return $clean($v);}
 return [['id'=>'variant-main','color'=>'Основной','size'=>'Единый','quantity'=>(int)$o['quantity']]];
}
function goalValidateVariants(mixed $raw,int $quantity):array{
 if($raw===null)return [['id'=>'variant-main','color'=>'Основной','size'=>'Единый','quantity'=>$quantity]];
 if(!is_array($raw)||!array_is_list($raw)||!$raw||count($raw)>250)fail('Добавьте цвета и размеры модели');$out=[];$ids=[];$labels=[];$total=0;
 foreach($raw as $v){$id=organizationId($v);$color=textField($v,'color',100,true);$size=textField($v,'size',60,true);$q=numberField($v,'quantity',1,100000);$label=mb_strtolower($color).'|'.mb_strtolower($size);if(isset($ids[$id])||isset($labels[$label]))fail('Цвет и размер не должны повторяться');$ids[$id]=true;$labels[$label]=true;$total+=$q;$out[]=['id'=>$id,'color'=>$color,'size'=>$size,'quantity'=>$q];}
 if($total!==$quantity||$total>100000)fail('Общее количество должно совпадать с суммой цветов и размеров');return $out;
}

function goalSaveVariants(array $c,string $order,int $revision,array $variants,bool $changed):void{
 if(!companyStorage('order_variant_revisions'))fail('Для цветов и размеров импортируйте SQL обновления 25.10.2026',503);
 query('INSERT INTO order_variant_revisions (id,company_id,order_id,revision,variants_json) VALUES (?,?,?,?,?)',[uid(),$c['id'],$order,$revision,j($variants)]);
 if(!$changed)return;$workspace=companyStorage('plan_workspaces')?one('SELECT * FROM plan_workspaces WHERE company_id=? AND order_id=?',[$c['id'],$order]):null;
 if($workspace){$state=decode($workspace['state_json']);$state['input']['variants']=array_map(fn($v)=>$v+['ready'=>0],$variants);$sizes=[];foreach($variants as $v)$sizes[$v['size']]=1;$state['input']['markers']=[['id'=>'marker-main','name'=>'Размеры модели','sizes'=>$sizes,'length_m'=>null,'width_cm'=>null]];$state['input']['lays']=[];$revisionW=(int)$workspace['revision']+1;query('UPDATE plan_workspaces SET state_json=?,revision=?,actor_id=? WHERE id=? AND company_id=?',[j($state),$revisionW,$_SESSION['user_id'],$workspace['id'],$c['id']]);query('INSERT INTO plan_workspace_revisions (id,workspace_id,company_id,revision,state_json,actor_id) VALUES (?,?,?,?,?,?)',[uid(),$workspace['id'],$c['id'],$revisionW,j($state),$_SESSION['user_id']]);}
 $prep=companyStorage('order_preparations')?one('SELECT * FROM order_preparations WHERE company_id=? AND order_id=?',[$c['id'],$order]):null;
 if($prep){$state=decode($prep['state_json']);$state['items']=array_map(fn($v)=>$v+['ready'=>0,'expected'=>0,'ready_date'=>null],$variants);$state['source']='Изменён состав модели. Подтвердите готовность комплектации заново.';$state['cost_mode']='unknown';$state['cost_cents']=null;$revisionP=(int)$prep['revision']+1;query('UPDATE order_preparations SET state_json=?,revision=?,actor_id=? WHERE id=? AND company_id=?',[j($state),$revisionP,$_SESSION['user_id'],$prep['id'],$c['id']]);query('INSERT INTO preparation_revisions (id,preparation_id,company_id,revision,state_json,actor_id) VALUES (?,?,?,?,?,?)',[uid(),$prep['id'],$c['id'],$revisionP,j($state),$_SESSION['user_id']]);}
}
