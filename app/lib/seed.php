<?php
declare(strict_types=1);
function demoPassport(): array {
 return [
 'industry'=>'Швейное производство','city'=>'Санкт-Петербург','area'=>216,'timezone'=>'Europe/Moscow',
 'description'=>'Небольшая фабрика трикотажных изделий. Тестовые данные EASY3.',
 'people'=>[
 ['id'=>'p-cut','name'=>'Раскройщик 01','role'=>'Раскройщик','skills'=>['cut'],'active'=>true],
 ['id'=>'p-sew-1','name'=>'Швея 01','role'=>'Швея','skills'=>['sew','overlock'],'active'=>true],
 ['id'=>'p-sew-2','name'=>'Швея 02','role'=>'Швея','skills'=>['sew','overlock'],'active'=>true],
 ['id'=>'p-sew-3','name'=>'Швея 03','role'=>'Швея','skills'=>['sew','overlock'],'active'=>true],
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
