<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// A copy of the attachment after seed import; never loads private hosting configs.
ini_set('display_errors','1');error_reporting(E_ALL);date_default_timezone_set('Europe/Moscow');
$root=dirname(__DIR__,2);$database=$argv[1]??'';$out=$argv[2]??'';
if(!$database||!$out||!is_file($database))throw new RuntimeException('Use: php demo_q4_2026.php ISOLATED_COPY.sqlite OUTPUT_DIRECTORY');
class DemoQaPDO extends PDO{
 public function prepare(string $query,array $options=[]):PDOStatement|false{
  if(str_contains($query,'information_schema.tables'))$query="SELECT COUNT(*) n FROM sqlite_master WHERE name='economy_observations'";
  return parent::prepare($query,$options);
 }
}
$qaDb=new DemoQaPDO('sqlite:'.$database);$qaDb->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$qaDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$qaDb->exec('PRAGMA foreign_keys=ON');
$code=file_get_contents($root.'/lib/bootstrap.php');$code=substr($code,strpos($code,'function uid()'));$begin=strpos($code,'function db():PDO{');$end=strpos($code,'function query(',$begin);$code=substr($code,0,$begin).'function db():PDO{return $GLOBALS["qaDb"];} '.substr($code,$end);$code=str_replace("require_once __DIR__.'/seed.php';",'',$code);eval($code);
$_SESSION=['company_id'=>'96941cfaf7e78052df60fbc06d541d9d','csrf'=>'isolated-qa'];
$_SESSION['user_id']=one("SELECT user_id FROM memberships WHERE company_id=? AND role='owner'",[$_SESSION['company_id']])['user_id'];$config=['demo_enabled'=>false];
require_once $root.'/lib/seed.php';require_once $root.'/lib/api.php';require_once $root.'/lib/modules/passport.php';require_once $root.'/lib/technology.php';require_once $root.'/lib/modules/production.php';require_once $root.'/lib/simulation.php';require_once $root.'/lib/analytics.php';require_once $root.'/lib/optimization.php';
$checks=[];$errors=[];
function verify(string $name,callable $run):void{global $checks,$errors;try{if(!$run())throw new RuntimeException('Assertion failed');$checks[]=$name;}catch(Throwable $e){$errors[]=['check'=>$name,'error'=>$e->getMessage(),'line'=>$e->getLine()];}}
$c=context();[$orders,$tasks]=engineInputs($c);$byOrder=array_column($orders,null,'id');$new=array_values(array_filter($orders,fn($o)=>str_starts_with($o['title'],'D26-')));$latest=one('SELECT * FROM plan_versions WHERE company_id=? ORDER BY version_no DESC LIMIT 1',[$c['id']]);$snapshot=decode($latest['snapshot']);
verify('Actual passport validator accepts all people, brigades, resources and both maps',fn()=>count(validatedPassport($c['passport'],$c)['people'])===46);
verify('Actual route and technology validators accept all 480 operations',function()use($new,$c){$count=0;foreach($new as $o){if(routeCheck($o['route']))return false;$count+=count(cleanTechnology($o['route'],$c,$o));}return $count===480;});
verify('Actual workspace validator accepts every saved new cutting/flow workspace',function()use($new,$c){foreach($new as $o){$r=workspaceRecord($c,$o);$s=workspaceNormalize($r['state'],$c,$o);if(count($s['input']['variants'])!==3)return false;$positions=array_map(fn($p)=>$p['x'].'/'.$p['y'],$s['positions']);if(count(array_unique($positions))!==count($positions))return false;}return true;});
verify('Published input hash matches the exact current company, orders and task revisions',fn()=>hash_equals($latest['input_hash'],engineHash($c,$orders,$tasks)));
verify('Published snapshot event count and current company revision are consistent',fn()=>(int)$snapshot['company_revision']===$c['revision']&&(int)$snapshot['source_event_count']===(int)one('SELECT COUNT(*) n FROM production_events WHERE company_id=?',[$c['id']])['n']);
$plans=rows('SELECT * FROM plan_versions WHERE company_id=? ORDER BY version_no',[$c['id']]);foreach($plans as &$p)$p['snapshot']=decode($p['snapshot']);unset($p);
$observations=rows('SELECT * FROM economy_observations WHERE company_id=?',[$c['id']]);
foreach($observations as &$o)foreach(['revision','accepted_quantity','recognized_cents','direct_cents','labor_cents','common_cents'] as $key)$o[$key]=$o[$key]===null?null:(int)$o[$key];unset($o);
$finance=financeRollup($orders,$plans,$tasks,$observations,$snapshot['economy']);
verify('Expected, actual and forecast portfolio economics are all known and nonzero',fn()=>is_int($finance['expected_cents'])&&$finance['expected_cents']>0&&is_int($finance['actual_cents'])&&$finance['actual_cents']!==0&&is_int($finance['forecast_cents'])&&$finance['forecast_cents']>0);
verify('Every Q4 month has nonzero contract economics and preserves OWN separation',function()use($orders,$plans,$tasks,$observations,$snapshot){foreach(['2026-10','2026-11','2026-12'] as $month){$group=array_values(array_filter($orders,fn($o)=>str_starts_with($o['deadline'],$month)));$f=financeRollup($group,$plans,$tasks,$observations,$snapshot['economy']);if($f['expected_cents']<=0||$f['forecast_cents']<=0||$f['contract_count']<6||$f['own_count']<2)return false;}return true;});
$analytics=analyticsSnapshot($c,['from'=>'2026-10-01','to'=>'2026-10-18']);
verify('Actual period analytics counts final output and rework without duplicating operations',function()use($analytics,$tasks){$accepted=array_sum(array_column(array_filter($tasks,fn($t)=>$t['is_final']),'accepted'));return $analytics['facts']['accepted']===$accepted&&$analytics['facts']['first_pass_percent']>0&&$analytics['facts']['sent_to_rework']>=8&&count($analytics['facts']['daily'])===18;});
verify('Future November/December analytics contains no accepted production facts',function()use($c){foreach([['2026-11-01','2026-11-30'],['2026-12-01','2026-12-31']] as [$a,$b])if(analyticsSnapshot($c,['from'=>$a,'to'=>$b])['facts']['accepted']!==0)return false;return true;});
$state=stateData();
verify('Actual state API opens the full dataset and snapshot without stale-source warnings',fn()=>count($state['orders'])===27&&count($state['tasks'])===2307&&$state['forecast_stale']===false&&$state['finance']['available']===true);
file_put_contents($out.'/state.json',j($state));
$source=simulationCapture($c);$source['as_of']=(new DateTimeImmutable('2026-10-19 00:00:00',new DateTimeZone('Europe/Moscow')))->getTimestamp();
$base=null;
verify('Actual planner can recalculate the expanded portfolio with preserved factual batches',function()use($source,&$base){$base=simulationCalculate($source,simulationSettings([], $source));return $base['status']==='feasible'&&count($base['order_finishes'])===27;});
if($base){file_put_contents($out.'/recalculation.json',j(['status'=>$base['status'],'errors'=>$base['errors'],'late_orders'=>$base['late_orders'],'schedule_count'=>count($base['schedule']),'order_finishes'=>$base['order_finishes']]));}
$incident=one("SELECT t.* FROM tasks t JOIN orders o ON o.id=t.order_id WHERE o.company_id=? AND o.title LIKE 'D26-10-04%' AND t.rework=8",[$c['id']]);
verify('Normal production actions resolve the new QC incident and preserve quantity',function()use($incident){
 $r=productionAction('production_event',['key'=>uid(),'task_id'=>$incident['id'],'revision'=>(int)$incident['revision'],'kind'=>'rework_quality','quantity'=>2,'accepted'=>2,'rework'=>0,'scrap'=>0,'reason'=>'Изолированная проверка: повторный ОТК пройден']);
 $r=productionAction('production_event',['key'=>uid(),'task_id'=>$incident['id'],'revision'=>$r['revision'],'kind'=>'rework_release','quantity'=>6,'reason'=>'Изолированная проверка: оставшиеся шесть исправлены']);
 productionAction('production_event',['key'=>uid(),'task_id'=>$incident['id'],'revision'=>$r['revision'],'kind'=>'rework_quality','quantity'=>6,'accepted'=>6,'rework'=>0,'scrap'=>0,'reason'=>'Изолированная проверка: вся партия соответствует']);
 $after=taskRecord($incident['id']);return $after['state']==='completed'&&(int)$after['accepted']===(int)$after['quantity']&&(int)$after['rework']===0&&(int)$after['rework_ready']===0;
});
$report=['passed'=>count($checks),'checks'=>$checks,'errors'=>$errors,'finance'=>array_intersect_key($finance,array_flip(['expected_cents','actual_cents','forecast_cents','deviation_cents','accepted','covered','quantity','contract_count','own_count'])),'facts'=>$analytics['facts'],'native_mysql'=>false];file_put_contents($out.'/app-checks.json',j($report));echo j(['passed'=>count($checks),'checks'=>$checks,'errors'=>$errors,'finance'=>$report['finance'],'accepted_fact'=>$analytics['facts']['accepted'],'recalculated_status'=>$base['status']??null]);if($errors)exit(1);
