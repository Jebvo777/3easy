<?php
declare(strict_types=1);
/** Calendar interpretation is local and reviewable; the model never chooses its own 'today'. */
function simulationCalendarDate(int $year,int $month,int $day,DateTimeZone $zone):?DateTimeImmutable{
 return checkdate($month,$day,$year)?new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day),$zone):null;
}
function simulationIntentPreview(string $intent,array $company,array $settings,?int $now=null):array{
 $zone=new DateTimeZone($company['passport']['timezone']??'Europe/Moscow');$from=(new DateTimeImmutable('@'.($now??time())))->setTimezone($zone)->setTime(0,0);$text=mb_strtolower(str_replace("\xC2\xA0",' ',$intent),'UTF-8');$questions=[];$deadline=null;$origin='selected';
 if(preg_match('/(?:за|через|в течение|на)\s*(\d{1,3})\s*(день|дня|дней|недел[юьиь]|месяц(?:а|ев)?)/u',$text,$m)){
  $n=(int)$m[1];if($n<1)fail('Укажите положительный срок.');
  if(str_starts_with($m[2],'месяц')){$first=$from->modify('first day of this month')->modify('+'.$n.' months');$deadline=$first->setDate((int)$first->format('Y'),(int)$first->format('m'),min((int)$from->format('d'),(int)$first->format('t')));}
  else $deadline=$from->modify('+'.($n*(str_starts_with($m[2],'недел')?7:1)).' days');$origin='relative';
 }
 $dateText=$text;if(preg_match('/(?:к|до|не позднее)\s+(\d[^\n,;]{0,45})/u',$text,$m))$dateText=$m[1];
 if(preg_match('/(?<!\d)(\d{4})-(\d{2})-(\d{2})(?!\d)/u',$dateText,$m)){$deadline=simulationCalendarDate((int)$m[1],(int)$m[2],(int)$m[3],$zone);if(!$deadline)fail('В запросе указана несуществующая дата.');$origin='explicit';}
 elseif(preg_match('/(?<!\d)(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})(?!\d)/u',$dateText,$m)){$deadline=simulationCalendarDate((int)$m[3],(int)$m[2],(int)$m[1],$zone);if(!$deadline)fail('В запросе указана несуществующая дата.');$origin='explicit';}
 elseif(preg_match('/(?<!\d)(\d{1,2})\s+(января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря)(?:\s+(20\d{2}))?/u',$dateText,$m)){
  $months=['января'=>1,'февраля'=>2,'марта'=>3,'апреля'=>4,'мая'=>5,'июня'=>6,'июля'=>7,'августа'=>8,'сентября'=>9,'октября'=>10,'ноября'=>11,'декабря'=>12];$year=isset($m[3])?(int)$m[3]:(int)$from->format('Y');$deadline=simulationCalendarDate($year,$months[$m[2]],(int)$m[1],$zone);if(!$deadline)fail('В запросе указана несуществующая дата.');
  if(!isset($m[3])){if($deadline<$from)$deadline=simulationCalendarDate($year+1,$months[$m[2]],(int)$m[1],$zone);$questions[]='Год не указан: предложена ближайшая дата. Подтвердите год в поле срока.';}$origin='explicit';
 }
 if(!$deadline){$deadline=$from->modify('+'.max(1,min(180,(int)($settings['horizon_days']??30))).' days');$questions[]='Однозначный срок в тексте не найден. Использован выбранный горизонт — его можно изменить.';}
 $days=(int)$from->diff($deadline)->format('%r%a');if($days<1)$questions[]='Выбранный срок уже наступил. Укажите будущую дату.';if($days>180)$questions[]='Этот срок больше 180 дней. Выберите дату в пределах доступного горизонта.';
 $cents=null;if(preg_match('/(?<![\d.])(\d(?:[\d ]*\d)?(?:[,.]\d{1,2})?)\s*(миллион(?:а|ов)?|млн\.?|тысяч(?:а|и)?|тыс\.?|руб(?:лей|ля|ль)?\.?|₽)/u',$text,$m)){$v=(float)str_replace([' ', ','],['','.'],$m[1]);$factor=preg_match('/^(миллион|млн)/u',$m[2])?1000000:(preg_match('/^(тысяч|тыс)/u',$m[2])?1000:1);$value=round($v*$factor*100);if($value>=0&&$value<=1000000000000)$cents=(int)$value;else $questions[]='Сумма вне поддерживаемого диапазона.';}
 $quantity=null;if(preg_match('/(?<!\d)(\d[\d ]*)\s*(?:шт\.?|издели[йяе]|единиц)/u',$text,$m)){$n=(int)str_replace(' ','',$m[1]);if($n>=1&&$n<=1000000)$quantity=$n;}
 return ['intent_hash'=>hash('sha256',j([$intent,$from->format('Y-m-d'),$settings['scope']??'portfolio',(int)($settings['horizon_days']??30)])),'from'=>$from->format('Y-m-d'),'deadline'=>$deadline->format('Y-m-d'),'max_deadline'=>$from->modify('+180 days')->format('Y-m-d'),'timezone'=>$zone->getName(),'horizon_days'=>$days,'source'=>$origin,'target'=>['result_cents'=>$cents,'quantity'=>$quantity,'deadline'=>$deadline->format('Y-m-d')],'questions'=>$questions];
}
function simulationConfirmIntent(array $preview,array $confirmation):array{
 if(!isset($confirmation['intent_hash'])||!is_string($confirmation['intent_hash'])||!hash_equals($preview['intent_hash'],$confirmation['intent_hash']))fail('Задача или дата изменились. Сначала проверьте понимание запроса ещё раз.',409);
 $deadline=dateField($confirmation,'deadline');$zone=new DateTimeZone($preview['timezone']);$days=(int)(new DateTimeImmutable($preview['from'],$zone))->diff(new DateTimeImmutable($deadline,$zone))->format('%r%a');if($days<1||$days>180)fail('Выберите срок от завтрашнего дня до 180 дней вперёд.');
 return ['deadline'=>$deadline,'horizon_days'=>$days,'result_cents'=>aiInteger($confirmation['result_cents']??null,0,1000000000000,true),'quantity'=>aiInteger($confirmation['quantity']??null,1,1000000,true)];
}
