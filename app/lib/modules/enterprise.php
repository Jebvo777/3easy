<?php
declare(strict_types=1);
require_once __DIR__.'/documents.php';require_once __DIR__.'/passport.php';require_once __DIR__.'/../ai/enterprise.php';
function enterpriseAction(string $action,array $input):array{
 $c=owner();
 if($action==='enterprise_prepare'){
  $r=documentRecord(textField($input,'id',32,true));$meta=decode($r['metadata_json']);$safe=enterprisePrivateText($r['raw_text'],$c,$meta['enterprise_aliases']??[]);
  $meta['enterprise_aliases']=$safe['aliases'];query('UPDATE document_imports SET metadata_json=? WHERE id=? AND company_id=?',[j($meta),$r['id'],$c['id']]);
  return ['id'=>$r['id'],'name'=>$r['display_name'],'format'=>$r['extension'],'text'=>$safe['text'],'private_people'=>count($safe['aliases']),'job_id'=>$meta['enterprise_job_id']??null];
 }
 if($action==='enterprise_start'){
  $r=documentRecord(textField($input,'id',32,true));$meta=decode($r['metadata_json']);
  if(!empty($meta['enterprise_job_id']))return workflowPublic(workflowRecord($meta['enterprise_job_id']));
  $safe=enterprisePrivateText(documentCleanText(textField($input,'text',300000,true)),$c,$meta['enterprise_aliases']??[]);$chunks=documentChunks($safe['text']);if(!$chunks)fail('Не осталось данных предприятия.');if(empty($input['privacy_reviewed']))fail('Проверьте разрешённый для AI текст.');
  $job=workflowCreate('enterprise',['document_id'=>$r['id'],'chunks'=>$chunks,'aliases'=>$safe['aliases'],'timezone'=>$c['passport']['timezone']??'Europe/Moscow'],['part'=>0,'total'=>count($chunks),'proposal'=>[]],textField($input,'key',100,true));
  $meta['enterprise_job_id']=$job['id'];query('UPDATE document_imports SET metadata_json=? WHERE id=? AND company_id=?',[j($meta),$r['id'],$c['id']]);return workflowPublic($job);
 }
 if($action==='enterprise_step'){
  $id=textField($input,'id',32,true);if(workflowRecord($id)['kind']!=='enterprise')fail('Неверный тип задания',409);
  return workflowStep($id,function($r){$in=decode($r['input_json']);documentRecord($in['document_id']);$p=decode($r['progress_json']);$chunk=$in['chunks'][$p['part']]??null;if($chunk===null)fail('Нет части документа',409);
   $reply=aiGateway([],['_messages'=>enterpriseMessages($chunk,$in['aliases']),'_validator'=>fn($text)=>enterpriseReply($text,$chunk,$in['aliases']),'_prompt_version'=>'enterprise-import-20261018.1','preferred_model'=>$p['preferred_model']??null]);
   $p['proposal'][]=$reply['data'];$p['part']++;$p['preferred_model']=$reply['model'];$ready=$p['part']>=count($in['chunks']);return ['ready'=>$ready,'progress'=>$p,'model'=>$reply['model'],'result'=>$ready?enterpriseProposal($p['proposal'],$in['aliases'],$in['timezone']):null];
  });
 }
 if($action==='enterprise_apply')return command($action,$input,function($c)use($input){
  $r=workflowRecord(textField($input,'id',32,true),true);if($r['kind']!=='enterprise'||!in_array($r['state'],['ready','applied'],true))fail('Сначала завершите разбор предприятия',409);$result=decode($r['result_json']);if(!empty($result['company_id']))return ['company_id'=>$result['company_id'],'already_applied'=>true];
  if(empty($input['reviewed']))fail('Подтвердите проверку структуры и графика.');$name=textField($input,'name',120,true);$passport=validatedPassport($input['passport']??$result['passport'],['passport'=>['structure'=>['workshops'=>[['id'=>'w-main','name'=>'Основной цех']],'brigades'=>[]]],'layout'=>['objects'=>[]]]);
  $id=companyNew($name,$_SESSION['user_id']);query('UPDATE companies SET passport=? WHERE id=?',[j($passport),$id]);$result['company_id']=$id;query("UPDATE assistant_jobs SET state='applied',result_json=? WHERE id=? AND company_id=?",[j($result),$r['id'],$c['id']]);audit('enterprise_imported',['source_job'=>$r['id'],'created_company'=>$id]);return ['company_id'=>$id];
 });
 fail('Неизвестное действие импорта предприятия',404);
}
