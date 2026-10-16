<?php
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/api.php';
if(isset($_GET['api'])){
 header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 try{
  $action=(string)$_GET['api'];$method=$_SERVER['REQUEST_METHOD']??'GET';
  if(!in_array($action,['bootstrap','state'],true)&&$method!=='POST')fail('Нужен POST-запрос',405);
  $input=[];if($method==='POST'){
   if((int)($_SERVER['CONTENT_LENGTH']??0)>16777216)fail('Слишком большой запрос',413);
   $raw=file_get_contents('php://input');$input=json_decode($raw?:'{}',true,64,JSON_THROW_ON_ERROR);if(!is_array($input))fail('Некорректные данные');
   $token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($token)||!hash_equals($_SESSION['csrf'],$token))fail('Сессия обновилась. Перезагрузите страницу.',419);
  }
  $data=apiRun($action,$input);echo j(['ok'=>true,'data'=>$data]);
 }catch(AppError $e){http_response_code($e->getCode()?:422);$extra=$e instanceof AiGatewayError?['code'=>$e->reason,'retryable'=>$e->retryable]:[];echo j(['ok'=>false,'error'=>$e->getMessage()]+$extra);}
 catch(JsonException){http_response_code(400);echo j(['ok'=>false,'error'=>'Не удалось прочитать данные запроса']);}
 catch(Throwable $e){$reference=bin2hex(random_bytes(4));$driver=$e instanceof PDOException?(int)($e->errorInfo[1]??0):0;error_log('EASY3 ref='.$reference.' action='.preg_replace('/[^a-z_]/','',(string)($_GET['api']??'')).' '.get_class($e).' code='.$e->getCode().' driver='.$driver.' at='.basename($e->getFile()).':'.$e->getLine());$message=match($driver){1045=>'Проверьте подключение к БД в config.local.php: сервер отклонил доступ.',1049=>'База данных из серверной конфигурации не найдена.',1054,1146=>'В БД не хватает таблицы или поля. Импортируйте SQL обновления и загрузите все файлы архива.',1142=>'Пользователю БД не хватает прав для обновления схемы. Проверьте права на таблицы в панели хостинга.',default=>'Сервер не закончил действие. Повторите позже; код для журнала: '.$reference};http_response_code(503);echo j(['ok'=>false,'error'=>$message,'code'=>'server_'.$reference,'retryable'=>$driver===0]);}exit;
}
require_once __DIR__.'/lib/assets.php';$assets=assetManifest();
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');header('Expires: 0');
if(($_SESSION['asset_build']??'')!==$assets['build']){header('Clear-Site-Data: "cache"');$_SESSION['asset_build']=$assets['build'];}
function assetUrl(string $name):string{return htmlspecialchars($name,ENT_QUOTES,'UTF-8');}
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#173f3d"><meta name="description" content="EASY3 — спокойное рабочее пространство производства. Цель, план и действие в одном цикле."><title>EASY3 — пространство производства</title><link rel="icon" href="assets/media/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="<?=assetUrl($assets['css'])?>"><link rel="preload" href="assets/media/alpine.webp" as="image"></head>
<body data-build="<?=assetUrl($assets['build'])?>"><div class="wallpaper" aria-hidden="true"></div><div id="app"></div><div class="toast-stack" id="toasts" role="status" aria-live="polite"></div><div class="loading-screen" id="loading"><div class="stack" style="justify-items:center"><img class="logo" src="assets/media/logo.png" alt="EASY3"><div class="loading-ring" aria-label="Загрузка"></div></div></div><noscript><p style="padding:40px;background:white">Для рабочего пространства EASY3 нужен JavaScript.</p></noscript><script src="assets/js/startup.js?v=<?=assetUrl($assets['build'])?>"></script><script type="module" src="<?=assetUrl($assets['js'])?>"></script></body></html>
