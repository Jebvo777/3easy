<?php
declare(strict_types=1);
// Only an isolated local QA server; never uses the application's private config.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$dsn=getenv('EASY3_QA_DSN') ?: 'mysql:host=127.0.0.1;port=3307;charset=utf8mb4';
if (!preg_match('/^mysql:host=(127\.0\.0\.1|localhost);/', $dsn)) throw new RuntimeException('Use a local QA database');
$db=new PDO($dsn,getenv('EASY3_QA_USER') ?: 'root',getenv('EASY3_QA_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='easy3_install_qa_'.bin2hex(random_bytes(5));$checks=[];
$check=function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks[]=$label;};
try {
 $sql=str_replace('system404_easy3',$name,file_get_contents(__DIR__.'/../../database/install.sql'));
 $db->exec($sql);
 $count=fn(string $table)=>(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
 $check($count('users')===6 && $count('memberships')===6,'Учебные аккаунты и роли установлены');
 $check($count('companies')===1 && $count('orders')===3,'Компания и три цели установлены');
 $check($count('tasks')===18 && $count('plan_versions')===1,'Опубликованный план и 18 заданий установлены');
 $passport=json_decode($db->query('SELECT passport FROM companies')->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
 $check(count($passport['structure']['workshops'])===1 && count($passport['structure']['brigades'])===1,'Цех и бригада установлены');
 $roles=array_merge(...array_column($passport['people'],'roles'));
 $check(!array_diff(['chief','technologist','foreman','worker'],$roles),'Учебная структура содержит руководство и работников');
 $check(count(array_filter($passport['resources'],fn($r)=>$r['workshop']==='w-main'))===count($passport['resources']),'Все центры отнесены к существующему цеху');
 $plan=$db->query('SELECT snapshot,input_hash,approvals FROM plan_versions')->fetch(PDO::FETCH_ASSOC);
 $snapshot=json_decode($plan['snapshot'],true,512,JSON_THROW_ON_ERROR);
 $check(($snapshot['structure_snapshot']??null)===$passport['structure'],'Новый снимок плана включает структуру предприятия');
 $approvals=json_decode($plan['approvals'],true,512,JSON_THROW_ON_ERROR);
 $check($snapshot['input_hash']===$plan['input_hash'] && count($approvals)===3 && count(array_filter($approvals,fn($a)=>$a['input_hash']===$plan['input_hash']))===3,'Снимок и согласования ссылаются на одну основу');
 $check($count('economy_observations')===1 && $count('production_events')===2,'Финансовый факт и производственные события установлены');
 $tables=['users','companies','memberships','orders','plan_versions','tasks','production_events','economy_observations'];
 $before=array_map($count,$tables);$db->exec($sql);
 $check($before===array_map($count,$tables),'Повторный импорт не дублирует учебные сущности');
 echo json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
} finally { $db->exec('DROP DATABASE `'.$name.'`'); }
