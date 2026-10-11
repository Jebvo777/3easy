<?php
/** Build the synthetic fixture; CLI only. Does not read a real database or any secret. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('Europe/Moscow');
function uid():string{return bin2hex(random_bytes(16));}
function j(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function fail(string $m,int $code=422):never{throw new RuntimeException($m,$code);}
require __DIR__.'/../lib/seed.php';require __DIR__.'/../lib/engine.php';
$c=['id'=>md5('easy3-demo-company'),'name'=>'Ателье Север','revision'=>1,'passport'=>demoPassport(),'layout'=>demoLayout()];
$today=new DateTimeImmutable('today');$orders=[];
$input=[['Худи «Север»','Худи из футера','CONTRACT',600,125000,null,14,'mint','hoodie'],['Футболка «Линия»','Футболка базовая','OWN',350,null,56000,10,'gold','tee'],['Свитшот «Туман»','Свитшот без капюшона','CONTRACT',280,145000,null,18,'clay','hoodie']];
foreach($input as $i=>$v){$expenses=[['id'=>'e-1','name'=>'Лекала и подготовка','kind'=>'direct','cents'=>1800000],['id'=>'e-2','name'=>'Логистика','kind'=>'direct','cents'=>650000]];if($v[2]==='OWN')$expenses[]=['id'=>'e-3','name'=>'Материал для партии','kind'=>'direct','cents'=>4200000];$orders[]=['id'=>md5('easy3-order-'.$i),'company_id'=>$c['id'],'title'=>$v[0],'product'=>$v[1],'customer'=>$v[2]==='OWN'?'Внутренний заказ':'Заказчик 0'.($i+1),'mode'=>$v[2],'quantity'=>$v[3],'price_cents'=>$v[4],'target_cents'=>$v[5],'deadline'=>$today->modify('+'.$v[6].' days')->format('Y-m-d'),'start_date'=>$today->format('Y-m-d'),'price_basis'=>'unit','material_owner'=>$v[2]==='OWN'?'company':'customer','expenses'=>$expenses,'route'=>demoRoute($v[8]),'revision'=>1,'color'=>$v[7],'status'=>'active'];}
$snapshot=calculatePlan($c,$orders,[]);$hash=engineHash($c,$orders,[]);$snapshot['input_hash']=$hash;$snapshot['company_revision']=1;$snapshot['source_event_count']=0;
$users=[];foreach(['owner'=>'Собственник','foreman'=>'Мастер','technologist'=>'Технолог','chief'=>'Начальник производства','worker'=>'Швея 01','qc'=>'Контролёр ОТК'] as $role=>$name)$users[]=['id'=>md5('easy3-user-'.$role),'name'=>$name,'email'=>$role.'@demo.easy3.local','role'=>$role];
echo j(['company'=>$c,'orders'=>$orders,'snapshot'=>$snapshot,'users'=>$users,'plan_id'=>md5('easy3-seed-plan'),'password_hash'=>password_hash('Easy3_demo_2026!',PASSWORD_DEFAULT)]);
