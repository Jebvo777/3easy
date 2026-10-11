<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('Europe/Moscow');
function j(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function fail(string $message,int $code=422):never{throw new RuntimeException($message,$code);}
require __DIR__.'/../../lib/engine.php';
$checks=[];function test(string $name,bool $result):void{global $checks;if(!$result)throw new RuntimeException($name);$checks[]=$name;echo 'OK '.$name."\n";}
$day=['start'=>'09:00','end'=>'18:00','break_start'=>'13:00','break_minutes'=>60,'days'=>[1,2,3,4,5]];
$after=strtotime('2026-10-12 09:00');$fit=fitWork($after,5*3600,$day,[],'Europe/Moscow');test('Перерыв исключается из рабочего времени',count($fit['slots'])===2&&date('H:i',$fit['end'])==='15:00');
$busy=[[$after,$after+3600]];$fit=fitWork($after,5*3600,$day,$busy,'Europe/Moscow');test('Занятое окно не используется',date('H:i',$fit['start'])==='10:00'&&date('H:i',$fit['end'])==='16:00');
$night=['start'=>'22:00','end'=>'06:00','break_start'=>'02:00','break_minutes'=>30,'days'=>[1,2,3,4,5,6,7]];
$after=strtotime('2026-10-13 03:00');$fit=fitWork($after,3600,$night,[],'Europe/Moscow');test('Текущая ночная смена используется после полуночи',$fit['start']===$after&&date('H:i',$fit['end'])==='04:00');
$fit=fitWork(strtotime('2026-10-16 17:30'),7200,$day,[],'Europe/Moscow');test('Остаток переносится через выходные',date('Y-m-d H:i',$fit['end'])==='2026-10-19 10:30');
test('Полкопейки округляется вверх',roundedRatio(1,1,2)===1);test('Меньше полкопейки округляется вниз',roundedRatio(1,1,3)===0);test('Подтверждённый ноль сохраняется',roundedRatio(0,99,3)===0);test('Слишком большой расчёт не создаёт неточное число',roundedRatio(PHP_INT_MAX,PHP_INT_MAX,3)===null);
$passport=['economy'=>['labor_hour_cents'=>60000,'monthly_common_cents'=>2000000,'monthly_hours'=>100,'unknown'=>[],'excluded'=>[]]];
$order=['quantity'=>30,'mode'=>'CONTRACT','price_cents'=>100000,'price_basis'=>'unit','expenses'=>[['cents'=>10000]],'route'=>[['name'=>'Учебная операция','seconds'=>60,'setup_minutes'=>0]]];
$econ=orderEconomy($order,$passport);test('Затраты в целых копейках без повторного включения',$econ['labor_cents']===30000&&$econ['common_cents']===10000&&$econ['direct_cents']===10000&&$econ['cost_cents']===50000);
$order['price_basis']='total';$econ=orderEconomy($order,$passport);test('Цена всего заказа не умножается на объём',$econ['value_cents']===100000&&$econ['result_cents']===50000);
$order['mode']='OWN';$econ=orderEconomy($order,$passport);test('OWN не выдумывает результат продажи',$econ['value_cents']===null&&$econ['result_cents']===null);
$passport['economy']['labor_hour_cents']=null;$econ=orderEconomy($order,$passport);test('Неизвестная ставка не превращается в ноль',$econ['labor_cents']===null&&$econ['cost_cents']===null);
echo j(['passed'=>count($checks),'checks'=>$checks])."\n";
