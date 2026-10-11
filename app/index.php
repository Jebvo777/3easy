<?php
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/api.php';
if(isset($_GET['api'])){
 header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 try{
  $action=(string)$_GET['api'];$method=$_SERVER['REQUEST_METHOD']??'GET';
  if(!in_array($action,['bootstrap','state'],true)&&$method!=='POST')fail('Нужен POST-запрос',405);
  $input=[];if($method==='POST'){
   if((int)($_SERVER['CONTENT_LENGTH']??0)>262144)fail('Слишком большой запрос',413);
   $raw=file_get_contents('php://input');$input=json_decode($raw?:'{}',true,64,JSON_THROW_ON_ERROR);if(!is_array($input))fail('Некорректные данные');
   $token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($token)||!hash_equals($_SESSION['csrf'],$token))fail('Сессия обновилась. Перезагрузите страницу.',419);
  }
  $data=apiRun($action,$input);echo j(['ok'=>true,'data'=>$data]);
 }catch(AppError $e){http_response_code($e->getCode()?:422);echo j(['ok'=>false,'error'=>$e->getMessage()]);}
 catch(JsonException){http_response_code(400);echo j(['ok'=>false,'error'=>'Не удалось прочитать данные запроса']);}
 catch(Throwable $e){error_log('EASY3 '.get_class($e).' code='.$e->getCode());http_response_code(503);echo j(['ok'=>false,'error'=>'Сервис пока недоступен. Проверьте установку БД или повторите позже.']);}exit;
}
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#173f3d"><meta name="description" content="EASY3 — спокойное рабочее пространство производства. Цель, план и действие в одном цикле."><title>EASY3 — пространство производства</title><link rel="icon" href="assets/media/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/css/app.css?v=20261011.2"><link rel="stylesheet" href="assets/css/refinement.css?v=20261011.2"><link rel="preload" href="assets/media/alpine.webp" as="image"></head>
<body><div class="wallpaper" aria-hidden="true"></div><div id="app"></div><div class="toast-stack" id="toasts" role="status" aria-live="polite"></div><div class="loading-screen" id="loading"><div class="stack" style="justify-items:center"><img class="logo" src="assets/media/logo.png" alt="EASY3"><div class="loading-ring" aria-label="Загрузка"></div></div></div><noscript><p style="padding:40px;background:white">Для рабочего пространства EASY3 нужен JavaScript.</p></noscript><script type="module" src="assets/js/app.js?v=20261011.2"></script></body></html>
