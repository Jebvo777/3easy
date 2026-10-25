<?php
declare(strict_types=1);

const DEMO_ENVIRONMENT_VERSION=1;

/** Current demo configuration only; published plans and production facts are untouched. */
function demoBuildEnvironment(array $company):array{
 $company=productionCompanyView($company);$p=$company['passport'];$structure=$p['structure'];$workshops=array_column($structure['workshops'],null,'id');$first=array_key_first($workshops);
 $people=[];$counts=array_fill_keys(array_keys($workshops),0);
 foreach($p['people']??[] as $person){
  $person=companyPerson($person);if(!isset($workshops[$person['workshop']??'']))$person['workshop']=$first;
  if($person['group']==='executors'){$person['duties']=[];$person['role']='';$person['universal']=true;$person['roles']=['worker'];$person['skills']=['cut','sew','special','overlock','iron','hand','qc','pack','other'];$person['specializations']=[];$person['active']=true;$counts[$person['workshop']]++;}
  $people[$person['id']]=$person;
 }
 $resources=[];$needed=[];
 foreach($p['resources']??[] as $i=>$resource){
  $resource=productionItem($resource);if(!isset($workshops[$resource['workshop']??'']))$resource['workshop']=$first;
  $resource['active']=true;if(empty($resource['inventory_number']))$resource['inventory_number']='DEMO-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT);
  $resources[$resource['id']]=$resource;$needed[$resource['workshop']]=true;
 }
 // Every occupied department gets an executor when there are enough people.
 foreach(array_keys($needed) as $department)if(!$counts[$department]){
  arsort($counts);$donor=array_key_first($counts);if(($counts[$donor]??0)<=1)continue;
  foreach($people as &$person)if($person['group']==='executors'&&$person['workshop']===$donor){$person['workshop']=$department;$person['brigade']='';$counts[$donor]--;$counts[$department]++;break;}unset($person);
 }
 $brigades=[];$leads=[];
 foreach($structure['brigades']??[] as $brigade){$lead=$people[$brigade['lead']]??null;if(!isset($workshops[$brigade['workshop']])||!$lead||!in_array('foreman',$lead['roles'],true)||!in_array($brigade['workshop'],companyPersonWorkshops($lead),true)||isset($leads[$lead['id']]))continue;$brigades[$brigade['id']]=$brigade;$leads[$lead['id']]=true;}
 foreach($people as $person)if(in_array('foreman',$person['roles'],true)&&!isset($leads[$person['id']])){$id='b-demo-'.substr(hash('sha256',$person['id']),0,16);$brigades[$id]=['id'=>$id,'name'=>'Бригада · '.$person['name'],'workshop'=>$person['workshop'],'lead'=>$person['id']];$leads[$person['id']]=true;}
 foreach($brigades as $brigade)$people[$brigade['lead']]['brigade']=$brigade['id'];
 foreach($people as &$person)if($person['group']!=='executors'&&!isset($leads[$person['id']])&&!isset($brigades[$person['brigade']??'']))$person['brigade']='';unset($person);
 $brigadeCounts=array_fill_keys(array_keys($brigades),0);
 foreach($people as &$person){
  if($person['group']!=='executors')continue;$available=array_filter($brigades,fn($b)=>$b['workshop']===$person['workshop']);
  if(!isset($available[$person['brigade']??''])){if($available){$ids=array_keys($available);usort($ids,fn($a,$b)=>$brigadeCounts[$a]<=>$brigadeCounts[$b]?:strcmp($a,$b));$person['brigade']=$ids[0];}else $person['brigade']='';}
  if($person['brigade']!=='')$brigadeCounts[$person['brigade']]++;
 }unset($person);
 $load=array_fill_keys(array_keys($people),0);
 foreach($resources as &$resource){
  $pool=array_filter($people,fn($person)=>$person['group']==='executors'&&$person['active']&&$person['workshop']===$resource['workshop']);$preferred=$resource['worker']??'';
  if(!isset($pool[$preferred])){if($pool){$ids=array_keys($pool);usort($ids,fn($a,$b)=>$load[$a]<=>$load[$b]?:strcmp($a,$b));$preferred=$ids[0];}else $preferred='';}
  $resource['worker']=$preferred;if($preferred!=='')$load[$preferred]++;
 }unset($resource);
 $places=[];$attached=[];$placeNames=[];
 foreach(companyWorkplaces($p) as $place){
  if(!isset($workshops[$place['workshop']??'']))continue;$items=[];
  foreach($place['equipment']??[] as $id)if(isset($resources[$id])&&!isset($attached[$id])&&$resources[$id]['workshop']===$place['workshop']){$items[]=$id;$attached[$id]=true;}
  $place['equipment']=$items;$place['active']=true;$places[$place['id']]=$place;$placeNames[$place['workshop'].'|'.mb_strtolower($place['name'])]=true;
 }
 foreach($resources as $resource)if(!isset($attached[$resource['id']])){
  $id=strlen($resource['id'])<=57?'wp-'.$resource['id']:'wp-'.substr(hash('sha256',$resource['id']),0,24);if(isset($places[$id]))$id='wp-demo-'.substr(hash('sha256',$resource['id']),0,24);
  $name=$resource['name'];$suffix=1;while(isset($placeNames[$resource['workshop'].'|'.mb_strtolower($name)]))$name=$resource['name'].' · '.(++$suffix);
  $places[$id]=['id'=>$id,'name'=>$name,'workshop'=>$resource['workshop'],'equipment'=>[$resource['id']],'active'=>true];$placeNames[$resource['workshop'].'|'.mb_strtolower($name)]=true;$attached[$resource['id']]=true;
 }
 $maps=$company['layout']['workshops']??[$first=>$company['layout']];$rank=['cut'=>0,'sew'=>1,'special'=>2,'hand'=>3,'iron'=>4,'qc'=>5,'pack'=>6,'other'=>7];
 foreach($workshops as $wid=>$workshop){
  $equipment=array_filter($resources,fn($r)=>$r['workshop']===$wid);$room=$maps[$wid]??['width'=>18,'depth'=>12,'objects'=>[],'links'=>[]];$objects=[];$seen=[];$objectIds=[];
  foreach($room['objects']??[] as $object){$rid=$object['resource']??'';if($rid!==''&&(!isset($equipment[$rid])||isset($seen[$rid])))continue;if(isset($objectIds[$object['id']]))continue;if($rid!==''){$seen[$rid]=true;$object['type']=$equipment[$rid]['type'];}$objects[]=$object;$objectIds[$object['id']]=true;}
  $columns=max(3,min(6,(int)ceil(sqrt(max(1,count($equipment))))));$width=max((float)($room['width']??18),$columns*3.8+4);$depth=max((float)($room['depth']??12),ceil(max(1,count($equipment))/$columns)*3.2+4);$slot=0;
  foreach($equipment as $resource)if(!isset($seen[$resource['id']])){
   do{$x=-$width/2+2+($slot%$columns)*3.8;$z=-$depth/2+2+intdiv($slot,$columns)*3.2;$slot++;$occupied=false;foreach($objects as $old)if(abs((float)$old['x']-$x)<2.5&&abs((float)$old['z']-$z)<2.2){$occupied=true;break;}}while($occupied);
   $oid='f-demo-'.substr(hash('sha256',$resource['id']),0,20);$objects[]=['id'=>$oid,'resource'=>$resource['id'],'type'=>$resource['type'],'x'=>$x,'z'=>$z,'rotation'=>0,'zone'=>$workshop['name']];$objectIds[$oid]=true;$depth=max($depth,$z*2+4);
  }
  $links=array_values(array_filter($room['links']??[],fn($link)=>isset($objectIds[$link[0]],$objectIds[$link[1]])));$degree=[];foreach($links as [$a,$b]){$degree[$a]=1;$degree[$b]=1;}
  $flow=array_values(array_filter($objects,fn($o)=>isset($equipment[$o['resource']??''])));usort($flow,fn($a,$b)=>($rank[$a['type']]??9)<=>($rank[$b['type']]??9)?:strcmp($a['id'],$b['id']));
  $previous=null;foreach($flow as $object){if($previous!==null&&!isset($degree[$object['id']])){$links[]=[$previous,$object['id']];$degree[$previous]=1;$degree[$object['id']]=1;}$previous=$object['id'];}
  $maps[$wid]=['width'=>$width,'depth'=>$depth,'objects'=>$objects,'links'=>$links];
 }
 $p['structure']['brigades']=array_values($brigades);$p['people']=array_values($people);$p['resources']=array_values($resources);$p['workplaces']=array_values($places);$p['_demo_environment_version']=DEMO_ENVIRONMENT_VERSION;
 $company['passport']=$p;$company['layout']=$maps[$first];$company['layout']['workshops']=$maps;return $company;
}

/** Apply once under a company lock, keeping the previous demo configuration. */
function demoEnsureEnvironment(array $company):array{
 if(empty($company['is_demo'])||(int)($company['passport']['_demo_environment_version']??0)>=DEMO_ENVIRONMENT_VERSION||!companyStorage('demo_environment_revisions'))return $company;
 $db=db();$ownTransaction=!$db->inTransaction();if($ownTransaction)$db->beginTransaction();
 try{
  $row=one('SELECT * FROM companies WHERE id=? AND is_demo=1'.lockSuffix(),[$company['id']]);
  if(!$row){if($ownTransaction)$db->commit();return $company;}
  $current=$row;$current['passport']=decode($row['passport']);$current['layout']=decode($row['layout']);
  if((int)($current['passport']['_demo_environment_version']??0)<DEMO_ENVIRONMENT_VERSION){
   $updated=demoBuildEnvironment($current);
   query('INSERT IGNORE INTO demo_environment_revisions (company_id,migration_version,passport_before,layout_before,company_revision) VALUES (?,?,?,?,?)',[$row['id'],DEMO_ENVIRONMENT_VERSION,$row['passport'],$row['layout'],(int)$row['revision']]);
   $current['passport']=$updated['passport'];$current['layout']=$updated['layout'];$current['revision']=(int)$row['revision']+1;
   query('UPDATE companies SET passport=?,layout=?,revision=? WHERE id=? AND is_demo=1',[j($current['passport']),j($current['layout']),$current['revision'],$row['id']]);
   $owner=one("SELECT user_id FROM memberships WHERE company_id=? AND role='owner' LIMIT 1",[$row['id']]);
   if($owner)query('INSERT INTO audit_log (id,company_id,actor_id,action,details) VALUES (?,?,?,?,?)',[uid(),$row['id'],$owner['user_id'],'demo_environment_prepared',j(['version'=>DEMO_ENVIRONMENT_VERSION,'universal_executors'=>true,'workplaces'=>count($current['passport']['workplaces']),'resources'=>count($current['passport']['resources']),'history_preserved'=>true])]);
  }
  if($ownTransaction)$db->commit();return array_replace($company,['passport'=>$current['passport'],'layout'=>$current['layout'],'revision'=>(int)$current['revision']]);
 }catch(Throwable $error){if($ownTransaction&&$db->inTransaction())$db->rollBack();throw $error;}
}
