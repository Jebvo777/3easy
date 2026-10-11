<?php
declare(strict_types=1);
require_once __DIR__.'/../finance.php';
function financeAction(string $action,array $input):array{
 owner();if(!financeAvailable())fail('Расчётный контур ещё подготавливается',503);
 return command($action,$input,function(array $c)use($input){
  $id=textField($input,'order_id',32,true);$order=one('SELECT * FROM orders WHERE id=? AND company_id=?',[$id,$c['id']]);if(!$order||$order['status']==='archived')fail('Цель недоступна',404);if($order['mode']!=='CONTRACT')fail('Для себя считается себестоимость, а не результат продажи');
  $previous=one('SELECT * FROM economy_observations WHERE company_id=? AND order_id=? ORDER BY revision DESC LIMIT 1',[$c['id'],$id]);$revision=(int)($previous['revision']??0);checkRevision($revision,$input);
  $accepted=(int)(one("SELECT COALESCE(SUM(accepted),0) AS n FROM tasks WHERE company_id=? AND order_id=? AND is_final=1 AND state<>'cancelled'",[$c['id'],$id])['n']);
  if(!$accepted)fail('Сначала нужен принятый выпуск');$quantity=numberField($input,'accepted_quantity',1,min($accepted,(int)$order['quantity']));
  $direct=numberField($input,'direct_cents',0,DEMO_MONEY_LIMIT,true);$labor=numberField($input,'labor_cents',0,DEMO_MONEY_LIMIT,true);$common=numberField($input,'common_cents',0,DEMO_MONEY_LIMIT,true);
  if(($direct??0)+($labor??0)+($common??0)>DEMO_MONEY_LIMIT)fail('Сумма превышает масштаб демо');$source=textField($input,'source',300,true);$record=uid();$plans=rows('SELECT * FROM plan_versions WHERE company_id=?',[$c['id']]);foreach($plans as &$p)$p['snapshot']=decode($p['snapshot']);unset($p);$tasks=rows("SELECT * FROM tasks WHERE company_id=? AND order_id=? AND state<>'cancelled'",[$c['id'],$id]);$value=recognizedValue($id,$tasks,$plans);$recognized=moneyShare($value,$quantity,$accepted);if($recognized===null)fail('Не установлена цена принятой части по опубликованному плану');
  query('INSERT INTO economy_observations (id,company_id,order_id,actor_id,revision,accepted_quantity,recognized_cents,direct_cents,labor_cents,common_cents,source) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$record,$c['id'],$id,$_SESSION['user_id'],$revision+1,$quantity,$recognized,$direct,$labor,$common,$source]);
  audit('economy_observed',['id'=>$record,'order_id'=>$id,'quantity'=>$quantity,'revision'=>$revision+1]);return ['saved'=>true,'id'=>$record,'revision'=>$revision+1];
 });
}
