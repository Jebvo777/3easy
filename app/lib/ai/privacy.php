<?php
declare(strict_types=1);
/** Only the local server sees original identifiers. Sanitized text is reviewable before sending. */
function aiPrivacyNames(array $company):array{
 $known=[$company['name'],user()['name'],...array_column($company['passport']['people']??[],'name')];foreach(rows('SELECT customer FROM orders WHERE company_id=?',[$company['id']]) as $r)if($r['customer'])$known[]=$r['customer'];return array_unique($known);
}
function aiSanitizeText(string $text,array $company,array $aliases=[],?array $knownNames=null):array{
 $count=0;$known=$knownNames??aiPrivacyNames($company);
 foreach($aliases as $name=>$alias)if(strlen($name)>=3)$text=preg_replace('~'.preg_quote($name,'~').'~iu',$alias,$text,-1,$n)??$text;
 foreach(array_unique($known) as $name)if(strlen(trim((string)$name))>=3){$text=preg_replace('~'.preg_quote(trim((string)$name),'~').'~iu','[идентификатор скрыт]',$text,-1,$n)??$text;$count+=$n;}
 $patterns=[
  '~(?:https?://|www\.)\S+|[\w.+-]+@[\w.-]+\.[a-z]{2,}|\bsk-[\w-]+~iu',
  '~(?:\+7|8)[\s(.-]*\d{3}[\s).\-]*\d{3}[\s.\-]*\d{2}[\s.\-]*\d{2}\b~u',
  '~\b\d{10,20}\b~u',
  '~\b[А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+\b~u',
  '~\b[А-ЯЁ][а-яё]+\s+[А-ЯЁ]\.\s*[А-ЯЁ]\.~u',
  '~^.*(?:ФИО|Ф\.И\.О|подпись|телефон|e-mail|email|СНИЛС|паспортные|реквизиты|расч[её]тный сч[её]т|корреспондентский|адрес регистрации|юридический адрес|дата рождения|получатель платежа|контактное лицо|ИНН\s*[:№]|БИК\s*[:№]).*$~imu',
  '~\b(?:ООО|ОАО|ПАО|ЗАО|АО|ИП)\s*[«"„][^»"“\r\n]+[»"“]~u'
 ];foreach($patterns as $pattern){$text=preg_replace($pattern,'[личные реквизиты скрыты]',$text,-1,$n)??$text;$count+=$n;}
 return ['text'=>$text,'redactions'=>$count];
}
function aiWorkflowSafeReply(string $text):void{if(preg_match('~https?://|www\.|\bsk-[\w-]+|[\w.+-]+@[\w.-]+\.[a-z]{2,}~iu',$text))fail('Ответ AI содержит недопустимые реквизиты. Повторите запрос.',502);}
function aiWorkflowTexts(mixed $values):array{if(!is_array($values)||!array_is_list($values)||count($values)>60)fail('Некорректные пояснения AI',502);return array_map(function($v){$s=aiString($v,600,true);aiWorkflowSafeReply($s);return $s;},$values);}
