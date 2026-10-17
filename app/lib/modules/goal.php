<?php
declare(strict_types=1);
function orderRecord(string $id,bool $lock=false):array{$c=context();$o=one('SELECT * FROM orders WHERE id=? AND company_id=?'.($lock?lockSuffix():''),[$id,$c['id']]);if(!$o)fail('Заказ не найден в этом предприятии',404);return $o;}
function goalAction(string $action,array $input):array{
 owner();return command($action,$input,function(array $c)use($action,$input){
 $id=textField($input,'id',32);$old=$id?orderRecord($id,true):null;if($old)checkRevision((int)$old['revision'],$input);
 $importId=textField($input,'import_id',32);$candidate=null;if($importId){$candidate=numberField($input,'import_candidate',0,10000);$source=one('SELECT id,job_id FROM document_imports WHERE id=? AND company_id=? AND actor_id=?'.lockSuffix(),[$importId,$c['id'],$_SESSION['user_id']]);if(!$source)fail('Исходный документ недоступен',403);$job=one('SELECT state,result_json FROM assistant_jobs WHERE id=? AND company_id=?',[$source['job_id'],$c['id']]);$items=decode($job['result_json']??null)['goals']??[];if(!$job||!in_array($job['state'],['ready','applied'],true)||!isset($items[$candidate]))fail('Сначала завершите разбор документа',409);$applied=one('SELECT order_id FROM document_import_applications WHERE import_id=? AND candidate_index=? AND company_id=?',[$importId,$candidate,$c['id']]);if($applied&&!$old)return ['saved'=>true,'id'=>$applied['order_id'],'already_applied'=>true];}
 if($action==='order_archive'){
  if(!$old)fail('Заказ не найден');$active=one("SELECT id FROM tasks WHERE order_id=? AND company_id=? AND state NOT IN ('completed','cancelled') LIMIT 1",[$id,$c['id']]);if($active)fail('Сначала завершите активные задания заказа');query("UPDATE orders SET status='archived',revision=revision+1 WHERE id=? AND company_id=?",[$id,$c['id']]);audit('order_archived',['id'=>$id]);return ['saved'=>true];
 }
 $title=textField($input,'title',180,true);$product=textField($input,'product',180,true);$customer=textField($input,'customer',180);$mode=textField($input,'mode',12,true);if(!in_array($mode,['OWN','CONTRACT'],true))fail('Выберите основание производства');
 $qty=numberField($input,'quantity',1,100000);$deadline=dateField($input,'deadline');$start=dateField($input,'start_date');if($deadline<$start)fail('Срок не может быть раньше начала');
 $price=numberField($input,'price_cents',0,100000000000,true);$target=numberField($input,'target_cents',0,1000000000,true);$basis=textField($input,'price_basis',12,true);if(!in_array($basis,['unit','total'],true))fail('Выберите основание цены');
 if($mode==='OWN')$price=null;else $target=null;
 if($mode==='CONTRACT'&&$price!==null&&$price*($basis==='unit'?$qty:1)>1000000000000)fail('Сумма заказа превышает предел демонстрации: 10 млрд ₽');
 $material=textField($input,'material_owner',12,true);if(!in_array($material,['company','customer'],true))fail('Укажите владельца материала');
 $expenses=$input['expenses']??[];if(!is_array($expenses)||count($expenses)>40)fail('Слишком много статей');$clean=[];$ids=[];
 foreach($expenses as $ex){$eid=textField($ex,'id',60,true);if(isset($ids[$eid]))fail('Статья расхода повторяется');$ids[$eid]=1;$clean[]=['id'=>$eid,'name'=>textField($ex,'name',100,true),'kind'=>'direct','cents'=>numberField($ex,'cents',0,100000000000)];}
 if($old){
  $running=one('SELECT id FROM tasks WHERE company_id=? AND order_id=? LIMIT 1',[$c['id'],$id]);
  if($running&&$qty!==(int)$old['quantity'])fail('Объём уже опубликован. Для другого объёма создайте отдельную цель.');
  query('UPDATE orders SET title=?,product=?,customer=?,mode=?,quantity=?,deadline=?,start_date=?,price_cents=?,price_basis=?,target_cents=?,material_owner=?,expenses=?,revision=revision+1 WHERE id=? AND company_id=?',[$title,$product,$customer,$mode,$qty,$deadline,$start,$price,$basis,$target,$material,j($clean),$id,$c['id']]);
  audit('order_saved',['id'=>$id,'revision'=>(int)$old['revision']+1,'previous_conditions'=>['quantity'=>(int)$old['quantity'],'deadline'=>$old['deadline'],'mode'=>$old['mode'],'price_cents'=>$old['price_cents'],'target_cents'=>$old['target_cents'],'expenses'=>decode($old['expenses'])]]);
 }else{$id=uid();query('INSERT INTO orders (id,company_id,title,product,customer,mode,quantity,deadline,start_date,price_cents,price_basis,target_cents,material_owner,expenses,route) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$c['id'],$title,$product,$customer,$mode,$qty,$deadline,$start,$price,$basis,$target,$material,j($clean),'[]']);audit('order_saved',['id'=>$id,'revision'=>1]);}
 if($importId&&!$old){query('INSERT INTO document_import_applications (import_id,candidate_index,company_id,order_id,actor_id) VALUES (?,?,?,?,?)',[$importId,$candidate,$c['id'],$id,$_SESSION['user_id']]);audit('document_goal_applied',['document_id'=>$importId,'candidate'=>$candidate,'order_id'=>$id]);}
 return ['saved'=>true,'id'=>$id];
 });
}
