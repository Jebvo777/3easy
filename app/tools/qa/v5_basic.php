<?php
// Small final smoke check. No HTTP server, external AI, or working database.
declare(strict_types=1);
date_default_timezone_set('Europe/Moscow');
class AppError extends RuntimeException{}
function fail(string $message,int $status=422):never{throw new AppError($message,$status);}
function j(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function decode(?string $v):array{return $v?json_decode($v,true,128,JSON_THROW_ON_ERROR):[];}
class SmokeDB{function query($sql){return new stdClass();}}
function db():SmokeDB{return new SmokeDB();}
function one(string $sql,array $p=[]):?array{global $smokeDependency;return str_contains($sql,'FROM tasks')?($smokeDependency??null):null;}
function rows(string $sql,array $p=[]):array{return [];}
function check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo 'OK '.$name."\n";}
$root=dirname(__DIR__,2);$count=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $f){if(!$f->isFile()||$f->getExtension()!=='php'||str_contains($f->getPathname(),'/node_modules/'))continue;token_get_all(file_get_contents($f->getPathname()),TOKEN_PARSE);$count++;}
echo 'PHP syntax: '.$count." files\n";
require_once $root.'/lib/engine.php';require_once $root.'/lib/ai/contract.php';require_once $root.'/lib/finance.php';
$route=[];for($i=1;$i<=200;$i++)$route[]=['id'=>'op-'.$i,'name'=>'Операция '.$i,'type'=>'sew','seconds'=>1,'setup_minutes'=>0,'dependencies'=>$i>1?['op-'.($i-1)]:[],'source'=>'Контрольный норматив','batch'=>5,'node_id'=>'node-'.(int)ceil($i/20),'node_name'=>'Узел'];
check(routeCheck($route)===[]&&count(sortedRoute($route))===200,'Route with 200 operations');
$page=['operations'=>[],'has_more'=>false,'continuation'=>'','questions'=>[]];for($i=0;$i<120;$i++)$page['operations'][]=['name'=>'Стачать деталь '.$i,'type'=>'sew','estimated_seconds'=>null,'setup_minutes'=>0,'equipment'=>'Прямострочная машина','grade'=>null,'notes'=>'','catalog_variant'=>''];
check(count(aiOperationsPage(j($page))['operations'])===120,'AI JSON has no 50-operation restriction');
$p=['timezone'=>'Europe/Moscow','shift'=>['days'=>[1,2,3,4,5],'start'=>'09:00','end'=>'18:00','break_start'=>'13:00','break_minutes'=>60],'people'=>[['id'=>'u1','name'=>'Учебный 01','active'=>true,'skills'=>['cut','sew','pack','hand']],['id'=>'u2','name'=>'Учебный 02','active'=>true,'skills'=>['cut','sew','pack','hand']]],'resources'=>[['id'=>'cut','type'=>'cut','name'=>'Раскройный стол','worker'=>'u2','active'=>true],['id'=>'sew','type'=>'sew','name'=>'Прямострочная машина','worker'=>'u1','active'=>true],['id'=>'pack','type'=>'pack','name'=>'Упаковка','worker'=>'u2','active'=>true]],'economy'=>['labor_hour_cents'=>360000,'monthly_common_cents'=>0,'monthly_hours'=>160,'unknown'=>[]]];
$c=['id'=>'company','revision'=>1,'passport'=>$p,'layout'=>[]];$o=['id'=>'order','revision'=>1,'title'=>'Контрольный заказ','product'=>'Изделие','quantity'=>10,'start_date'=>date('Y-m-d'),'deadline'=>date('Y-m-d',time()+30*86400),'mode'=>'CONTRACT','price_cents'=>1000,'price_basis'=>'unit','expenses'=>[],'route'=>[
 ['id'=>'sew-op','name'=>'Сборка','type'=>'sew','seconds'=>60,'setup_minutes'=>0,'dependencies'=>[],'source'=>'Контрольная норма','node_id'=>'assembly','node_name'=>'Сборка'],
 ['id'=>'pack-op','name'=>'Упаковка','type'=>'pack','seconds'=>30,'setup_minutes'=>0,'dependencies'=>['sew-op'],'source'=>'Контрольная норма','node_id'=>'finish','node_name'=>'Отделка']]];
$s=workspaceDefault($o);$s['input']['variants'][0]['ready']=10;$s['transfer_batch']=5;
foreach(['whole','nodes','flow'] as $mode){$s['mode']=$mode;$r=calculateWorkspacePlan($c,[$o],[],'deadline',[$o['id']=>$s]);check($r['status']==='feasible'&&$r['decision_summary']['order']['scope']===10,'Feasible '.$mode.' with shared resources');$busy=[];foreach($r['schedule'] as $t)foreach($t['slots'] as [$a,$b])foreach(['r:'.$t['resource_id'],'p:'.$t['worker_id']] as $id)$busy[$id][]=[$a,$b];foreach($busy as $intervals){usort($intervals,fn($a,$b)=>strcmp($a[0],$b[0]));for($i=1;$i<count($intervals);$i++)check($intervals[$i-1][1]<=$intervals[$i][0],'No resource/person overlap '.$mode);}}
$s['mode']='flow';$s['input']['mode']='cut';$s['input']['variants'][0]['ready']=0;$s['input']['max_layers']=4;$s['input']['material_width_cm']=150;$s['input']['markers'][0]['width_cm']=140;$s['input']['markers'][0]['length_m']=2;$s['input']['timing']=['setup_seconds'=>60,'layer_seconds'=>5,'kit_seconds'=>10,'source'=>'Контрольный пример'];
$r=calculateWorkspacePlan($c,[$o],[],'deadline',['order'=>$s]);$cut=$r['decision_summary']['order']['cutting'];check($r['status']==='feasible'&&$cut['deficit']===0&&$cut['planned']===10&&abs($cut['meters']-20)<.001,'Cutting quantity and metres');
$s['input']['auto']=false;$s['input']['lays']=proposeLays($s);$first=$s['input']['lays'][0];$s['input']['lays'][0]['layers']=2;$first['id']='split';$first['layers']=2;$s['input']['lays'][]=$first;
$r2=calculateWorkspacePlan($c,[$o],[],'deadline',['order'=>$s]);$cut2=$r2['decision_summary']['order']['cutting'];check($cut2['planned']===10&&abs($cut2['meters']-20)<.001,'Split preserves quantity and metres');
$tasks=[['order_id'=>'o','is_final'=>1,'state'=>'completed','accepted'=>5,'quantity'=>5,'plan_id'=>'p'],['order_id'=>'o','is_final'=>1,'state'=>'completed','accepted'=>5,'quantity'=>5,'plan_id'=>'p']];$plans=[['id'=>'p','snapshot'=>['order_quantities'=>['o'=>10],'economy'=>['o'=>['value_cents'=>10000]]]]];check(recognizedValue('o',$tasks,$plans)===10000,'Batch revenue is not multiplied');
check(count(json_decode(file_get_contents($root.'/data/examples.json'),true)['examples'])===9,'Nine technical examples');
require_once $root.'/lib/modules/production.php';$smokeDependency=['quantity'=>10,'transferred'=>5,'planning_meta'=>j(['cutting'=>true])];$child=['quantity'=>5,'released'=>0,'dependencies'=>j(['cut']),'company_id'=>'company'];check(availableInput($child)===0,'A partial lay cannot feed two full child batches');$smokeDependency['transferred']=10;check(availableInput($child)===5,'A transferred lay unlocks its planned child batch');
echo "BASIC CHECKS PASSED\n";
