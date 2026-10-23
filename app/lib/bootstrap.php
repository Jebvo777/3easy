<?php
declare(strict_types=1);
ini_set('display_errors','0');
$local=__DIR__.'/../config.local.php';
$config=require (is_file($local)?$local:__DIR__.'/../config.example.php');
date_default_timezone_set($config['timezone']??'Europe/Moscow');
$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
session_name('EASY3_SESSION');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');
session_start();
if(isset($_SESSION['touched'])&&time()-$_SESSION['touched']>(int)($config['session_minutes']??240)*60){$_SESSION=[];session_regenerate_id(true);}
$_SESSION['touched']=time();
$_SESSION['csrf']??=bin2hex(random_bytes(24));
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: blob:; font-src \'self\'; connect-src \'self\'; object-src \'none\'; base-uri \'self\'; frame-ancestors \'none\'');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
function uid():string{return bin2hex(random_bytes(16));}
function j(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function decode(string|null $v):array{return $v?json_decode($v,true,128,JSON_THROW_ON_ERROR):[];}
function fail(string $message,int $status=422):never{throw new AppError($message,$status);}
class AppError extends RuntimeException{}
function db():PDO{
 static $db=null;global $config;if($db)return $db;
 if(!empty($config['sqlite_path'])){$db=new PDO('sqlite:'.$config['sqlite_path']);$db->exec('PRAGMA foreign_keys=ON');}
 else{$dsn='mysql:host='.$config['host'].';port='.($config['port']??3306).';dbname='.$config['database'].';charset=utf8mb4';$db=new PDO($dsn,$config['username'],$config['password'],[PDO::ATTR_EMULATE_PREPARES=>false]);}
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
 require_once __DIR__.'/schema.php';easy3EnsureOptionalColumns($db);return $db;
}
function query(string $sql,array $params=[]):PDOStatement{$q=db()->prepare($sql);$q->execute($params);return $q;}
function one(string $sql,array $params=[]):array|null{$r=query($sql,$params)->fetch();return $r?:null;}
function rows(string $sql,array $params=[]):array{return query($sql,$params)->fetchAll();}
function lockSuffix():string{return db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
function user():array{if(empty($_SESSION['user_id']))fail('Войдите в рабочее пространство',401);$u=one('SELECT id,email,name FROM users WHERE id=?',[$_SESSION['user_id']]);if(!$u)fail('Сессия завершена',401);return $u;}
function context(bool $lock=false):array{
 $u=user();$id=$_SESSION['company_id']??'';
 $r=one('SELECT c.*,m.role FROM companies c JOIN memberships m ON m.company_id=c.id WHERE c.id=? AND m.user_id=?'.($lock?lockSuffix():''),[$id,$u['id']]);
 if(!$r)fail('Нет доступа к предприятию',403);
 $r['passport']=decode($r['passport']);$r['layout']=decode($r['layout']);$r['revision']=(int)$r['revision'];return productionCompanyView($r);
}
function permit(array $roles):array{$c=context();if(!in_array($c['role'],$roles,true))fail('Для этого действия нужна другая роль',403);return $c;}
function owner():array{return permit(['owner']);}
function audit(string $action,array $details=[]):void{query('INSERT INTO audit_log (id,company_id,actor_id,action,details) VALUES (?,?,?,?,?)',[uid(),$_SESSION['company_id'],$_SESSION['user_id'],$action,j($details)]);}
function textField(array $a,string $k,int $max,bool $required=false):string{
 $raw=$a[$k]??'';if(!is_scalar($raw))fail('Некорректный текст: '.$k);$v=trim((string)$raw);if(strlen($v)>$max*4)fail('Слишком длинное значение: '.$k);if($required&&$v==='')fail('Заполните обязательное поле: '.$k);return $v;
}
function numberField(array $a,string $k,int $min=0,int $max=1000000,bool $nullable=false):?int{
 if(!array_key_exists($k,$a)||$a[$k]===''||$a[$k]===null){if($nullable)return null;fail('Укажите число: '.$k);}
 if(is_bool($a[$k])||!is_scalar($a[$k])||filter_var($a[$k],FILTER_VALIDATE_INT)===false)fail('Требуется целое число: '.$k);
 $v=(int)$a[$k];if($v<$min||$v>$max)fail('Значение вне допустимого диапазона: '.$k);return $v;
}
function dateField(array $a,string $k):string{$s=(string)($a[$k]??'');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)fail('Укажите корректную дату');return $s;}
function checkRevision(int $actual,array $input):void{if((int)($input['revision']??-1)!==$actual)fail('Данные изменились. Обновите экран и повторите действие.',409);}
function command(string $action,array $input,callable $run):array{
 $db=db();$db->beginTransaction();try{
 $c=context(true);$key=textField($input,'key',100,true);
 $hash=hash('sha256',j(['action'=>$action,'input'=>$input]));
 $old=one('SELECT * FROM idempotency WHERE company_id=? AND actor_id=? AND command_key=?',[$c['id'],$_SESSION['user_id'],$key]);
 if($old){if(!hash_equals($old['request_hash'],$hash))fail('Ключ повторной отправки уже использован с другими данными',409);$db->commit();return decode($old['response']);}
 $out=$run($c);query('INSERT INTO idempotency (company_id,actor_id,command_key,request_hash,response) VALUES (?,?,?,?,?)',[$c['id'],$_SESSION['user_id'],$key,$hash,j($out)]);$db->commit();return $out;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
require_once __DIR__.'/production_domain.php';require_once __DIR__.'/seed.php';
