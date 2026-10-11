<?php
declare(strict_types=1);
function demoPassport(): array {
 return [
 'industry'=>'Швейное производство','city'=>'Санкт-Петербург','area'=>216,'timezone'=>'Europe/Moscow',
 'description'=>'Небольшая фабрика трикотажных изделий. Тестовые данные EASY3.',
 'structure'=>['workshops'=>[['id'=>'w-main','name'=>'Швейный цех']],'brigades'=>[['id'=>'b-sew','name'=>'Бригада пошива','workshop'=>'w-main','lead'=>'p-foreman']]],
 'people'=>[
 ['id'=>'p-chief','name'=>'Начальник производства','role'=>'Начальник производства','roles'=>['chief'],'workshop'=>'w-main','brigade'=>'','skills'=>[],'active'=>true],
 ['id'=>'p-tech','name'=>'Технолог','role'=>'Технолог','roles'=>['technologist'],'workshop'=>'w-main','brigade'=>'','skills'=>[],'active'=>true],
 ['id'=>'p-foreman','name'=>'Бригадир','role'=>'Бригадир','roles'=>['foreman'],'workshop'=>'w-main','brigade'=>'b-sew','skills'=>[],'active'=>true],
 ['id'=>'p-cut','name'=>'Раскройщик 01','role'=>'Раскройщик','skills'=>['cut'],'active'=>true],
 ['id'=>'p-sew-1','roles'=>['worker'],'workshop'=>'w-main','brigade'=>'b-sew','name'=>'Швея 01','role'=>'Швея','skills'=>['sew','overlock'],'active'=>true],
 ['id'=>'p-sew-2','roles'=>['worker'],'workshop'=>'w-main','brigade'=>'b-sew','name'=>'Швея 02','role'=>'Швея','skills'=>['sew','overlock'],'active'=>true],
 ['id'=>'p-sew-3','roles'=>['worker'],'workshop'=>'w-main','brigade'=>'b-sew','name'=>'Швея 03','role'=>'Швея','skills'=>['sew','overlock'],'active'=>true],
 ['id'=>'p-iron','name'=>'Оператор ВТО','role'=>'ВТО','skills'=>['iron'],'active'=>true],
 ['id'=>'p-qc','name'=>'Контролёр ОТК','role'=>'ОТК','skills'=>['qc','pack'],'active'=>true]],
 'shift'=>['start'=>'09:00','end'=>'18:00','break_start'=>'13:00','break_minutes'=>60,'days'=>[1,2,3,4,5]],
 'resources'=>[
 ['id'=>'cut-1','name'=>'Раскройный стол','type'=>'cut','worker'=>'p-cut','active'=>true],
 ['id'=>'sew-1','name'=>'Прямострочная 01','type'=>'sew','worker'=>'p-sew-1','active'=>true],
 ['id'=>'sew-2','name'=>'Прямострочная 02','type'=>'sew','worker'=>'p-sew-2','active'=>true],
 ['id'=>'over-1','name'=>'Оверлок 01','type'=>'overlock','worker'=>'p-sew-3','active'=>true],
 ['id'=>'iron-1','name'=>'Стол ВТО','type'=>'iron','worker'=>'p-iron','active'=>true],
 ['id'=>'qc-1','name'=>'Стол ОТК','type'=>'qc','worker'=>'p-qc','active'=>true],
 ['id'=>'pack-1','name'=>'Упаковка','type'=>'pack','worker'=>'p-qc','active'=>true]],
 'economy'=>['labor_hour_cents'=>45000,'monthly_common_cents'=>28500000,'monthly_hours'=>960,
 'excluded'=>['Продажи бренда','Налоги компании','Кредитные платежи','Непроизводственная администрация'],
 'unknown'=>['Расходы на обслуживание оборудования'],'currency'=>'RUB']
 ];
}
function demoLayout(): array {
 return ['width'=>18,'depth'=>12,'objects'=>[
 ['id'=>'f-cut','resource'=>'cut-1','type'=>'cut','x'=>-5.5,'z'=>-2.7,'rotation'=>0,'zone'=>'Раскрой'],
 ['id'=>'f-s1','resource'=>'sew-1','type'=>'sew','x'=>-1.6,'z'=>-2.7,'rotation'=>0,'zone'=>'Пошив'],
 ['id'=>'f-s2','resource'=>'sew-2','type'=>'sew','x'=>1.5,'z'=>-2.7,'rotation'=>0,'zone'=>'Пошив'],
 ['id'=>'f-o1','resource'=>'over-1','type'=>'overlock','x'=>4.8,'z'=>-2.7,'rotation'=>0,'zone'=>'Пошив'],
 ['id'=>'f-iron','resource'=>'iron-1','type'=>'iron','x'=>4.8,'z'=>1.7,'rotation'=>0,'zone'=>'Финиш'],
 ['id'=>'f-qc','resource'=>'qc-1','type'=>'qc','x'=>1.4,'z'=>1.7,'rotation'=>0,'zone'=>'Финиш'],
 ['id'=>'f-pack','resource'=>'pack-1','type'=>'pack','x'=>-1.9,'z'=>1.7,'rotation'=>0,'zone'=>'Финиш'],
 ['id'=>'f-stock','resource'=>'','type'=>'storage','x'=>-6,'z'=>2.2,'rotation'=>0,'zone'=>'Склад']],
 'links'=>[['f-cut','f-s1'],['f-s1','f-o1'],['f-o1','f-iron'],['f-iron','f-qc'],['f-qc','f-pack']]];
}
function demoRoute(string $product='hoodie'): array {
 $norms=$product==='tee'?[65,105,70,35,25,20]:[120,240,150,55,35,25];
 $types=['cut','sew','overlock','iron','qc','pack'];
 $names=['Раскрой деталей','Сборка изделия','Обработка швов','Влажно-тепловая обработка','Контроль качества','Упаковка'];
 $r=[];foreach($types as $i=>$type){$r[]=['id'=>'op-'.($i+1),'name'=>$names[$i],'type'=>$type,
 'seconds'=>$norms[$i],'setup_minutes'=>$i===0?12:5,'dependencies'=>$i?['op-'.$i]:[],
 'source'=>'Учебная норма демо v1; не норматив предприятия','batch'=>50];}return $r;
}
function seedCompany(PDO $db,string $companyId): void {
 $today=new DateTimeImmutable('today');
 $orders=[
 ['Худи «Север»','Худи из футера','Заказчик 01','CONTRACT',600,125000,null,'customer',14,'mint','hoodie'],
 ['Футболка «Линия»','Футболка базовая','Внутренний заказ','OWN',350,null,56000,'company',10,'gold','tee'],
 ['Свитшот «Туман»','Свитшот без капюшона','Заказчик 02','CONTRACT',280,145000,null,'customer',18,'clay','hoodie']];
 $sql='INSERT INTO orders (id,company_id,title,product,customer,mode,quantity,deadline,start_date,price_cents,target_cents,material_owner,expenses,route,status,color) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
 $q=$db->prepare($sql);
 foreach($orders as $o){$expenses=[['id'=>'e-1','name'=>'Лекала и подготовка','kind'=>'direct','cents'=>1800000],['id'=>'e-2','name'=>'Логистика','kind'=>'direct','cents'=>650000]];
 if($o[3]==='OWN')$expenses[]=['id'=>'e-3','name'=>'Материал для партии','kind'=>'direct','cents'=>4200000];
 $q->execute([uid(),$companyId,$o[0],$o[1],$o[2],$o[3],$o[4],$today->modify('+'.$o[8].' days')->format('Y-m-d'),$today->format('Y-m-d'),$o[5],$o[6],$o[7],j($expenses),j(demoRoute($o[10])),'draft',$o[9]]);}
}
function seedInitialPlan(PDO $db,string $companyId,string $ownerId):void{
 $c=one('SELECT * FROM companies WHERE id=?',[$companyId]);$c['passport']=decode($c['passport']);$c['layout']=decode($c['layout']);
 [$orders,$tasks]=engineInputs($c);$snapshot=calculatePlan($c,$orders,$tasks);if($snapshot['status']!=='feasible')return;
 $id=uid();$hash=engineHash($c,$orders,$tasks);$snapshot['input_hash']=$hash;$snapshot['company_revision']=(int)$c['revision'];$snapshot['source_event_count']=0;
 $approvals=[];foreach(['foreman','technologist','chief'] as $stage)$approvals[]=['stage'=>$stage,'actor_id'=>$ownerId,'at'=>date('c'),'input_hash'=>$hash,'source'=>'Учебный план'];
 query('INSERT INTO plan_versions (id,company_id,version_no,state,snapshot,input_hash,approvals,creator_id) VALUES (?,?,?,?,?,?,?,?)',[$id,$companyId,1,'approved',j($snapshot),$hash,j($approvals),$ownerId]);
 publishSchedule($c,['id'=>$id,'snapshot'=>j($snapshot)]);
 $first=array_values(array_filter($orders,fn($o)=>$o['mode']==='CONTRACT'))[0];$all=rows('SELECT * FROM tasks WHERE company_id=? AND order_id=? ORDER BY planned_start',[$companyId,$first['id']]);
 foreach($all as $t){if(!$t['is_final']){query("UPDATE tasks SET released=quantity,transferred=quantity,state='completed',revision=2 WHERE id=?",[$t['id']]);}
 else{query("UPDATE tasks SET released=20,transferred=20,accepted=18,rework=2,state='paused',revision=2 WHERE id=?",[$t['id']]);
 query('INSERT INTO production_events (id,company_id,task_id,actor_id,kind,quantity,details) VALUES (?,?,?,?,?,?,?)',[uid(),$companyId,$t['id'],$ownerId,'quality',20,j(['accepted'=>18,'rework'=>2,'scrap'=>0,'reason'=>'Учебный пример: неровная строчка','source'=>'Тестовые данные'])]);
 query('INSERT INTO production_events (id,company_id,task_id,actor_id,kind,quantity,details) VALUES (?,?,?,?,?,?,?)',[uid(),$companyId,$t['id'],$ownerId,'pause',null,j(['reason'=>'Учебный пример: следующая партия ещё в работе','source'=>'Тестовые данные'])]);}}
 seedFinance($db,$companyId,$first['id'],$ownerId,((int)$first['price_cents'])*18);
}

function seedFinance(PDO $db,string $companyId,string $orderId,string $ownerId,int $recognized):void{
 require_once __DIR__.'/finance.php';if(!financeAvailable())return;
 query('INSERT INTO economy_observations (id,company_id,order_id,actor_id,revision,accepted_quantity,recognized_cents,direct_cents,labor_cents,common_cents,source) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[uid(),$companyId,$orderId,$ownerId,1,18,$recognized,380000,700000,130000,'Учебный факт: вымышленные подтверждённые затраты 18 принятых изделий.']);
}
