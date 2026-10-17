<?php
declare(strict_types=1);
const DOCUMENT_MAX_BYTES=12582912;
const DOCUMENT_MAX_TEXT=1200000;
/** Limited OOXML ZIP reader: no extraction, paths, executable content, or ZIP64. */
final class DocumentZip {
 private string $bytes;private array $entries=[];
 public function __construct(string $path){
  $this->bytes=(string)file_get_contents($path);$end=strrpos($this->bytes,"PK\x05\x06");if($end===false)fail('Повреждённый DOCX/XLSX: не найден каталог ZIP.');
  $e=unpack('vdisk/vstart/von_disk/vcount/Vsize/Voffset/vcomment',substr($this->bytes,$end+4,18));if(!$e||$e['disk']||$e['start']||$e['count']>15000||$e['offset']+$e['size']>$end)fail('Этот формат ZIP не поддерживается. Пересохраните документ.');
  $pos=$e['offset'];$total=0;for($i=0;$i<$e['count'];$i++){
   if(substr($this->bytes,$pos,4)!=="PK\x01\x02")fail('Повреждённый каталог документа.');
   $f=unpack('vflags/vmethod',substr($this->bytes,$pos+8,4));$s=unpack('Vcrc/Vcompressed/Vsize',substr($this->bytes,$pos+16,12));$l=unpack('vname/vextra/vcomment',substr($this->bytes,$pos+28,6));$o=unpack('Voffset',substr($this->bytes,$pos+42,4));$name=substr($this->bytes,$pos+46,$l['name']);
   if(str_contains($name,'..')||str_starts_with($name,'/')||str_contains($name,"\0")||isset($this->entries[$name]))fail('Некорректное содержимое архива документа.');$total+=$s['size'];
   if($s['size']>50331648||$total>100663296||$s['compressed']===0xFFFFFFFF||$o['offset']===0xFFFFFFFF)fail('Распакованный документ слишком велик. Удалите пустые листы или разделите файл.');
   $this->entries[$name]=$f+$s+$o;$pos+=46+$l['name']+$l['extra']+$l['comment'];
  }
 }
 public function names():array{return array_keys($this->entries);}
 public function has(string $name):bool{return isset($this->entries[$name]);}
 public function read(string $name):string{
  $e=$this->entries[$name]??null;if(!$e)fail('В документе отсутствует обязательная часть.');if($e['flags']&1)fail('Документ защищён паролем. Сохраните незашифрованную копию.');$p=$e['offset'];if(substr($this->bytes,$p,4)!=="PK\x03\x04")fail('Повреждённая часть документа.');$l=unpack('vname/vextra',substr($this->bytes,$p+26,4));$start=$p+30+$l['name']+$l['extra'];if($start+$e['compressed']>strlen($this->bytes))fail('Файл документа обрезан.');$data=substr($this->bytes,$start,$e['compressed']);
  $text=match($e['method']){0=>$data,8=>@gzinflate($data,max(1,$e['size'])),default=>false};if($text===false||strlen($text)!==$e['size']||hash('crc32b',$text)!==sprintf('%08x',$e['crc']))fail('Не удалось прочитать целую часть документа.');return $text;
 }
}
function documentXml(string $xml):DOMDocument{
 if(!class_exists(DOMDocument::class))fail('Включите расширение PHP DOM/XML для документов.',503);if(stripos($xml,'<!DOCTYPE')!==false||stripos($xml,'<!ENTITY')!==false)fail('Документ содержит недопустимые XML-сущности.');
 $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);$ok=$dom->loadXML($xml,LIBXML_NONET|LIBXML_COMPACT);libxml_clear_errors();libxml_use_internal_errors($previous);if(!$ok)fail('Некорректная XML-структура документа.');return $dom;
}
function documentCleanText(string $text):string{
 $text=str_replace(["\r\n","\r","\xC2\xA0"],["\n","\n",' '],$text);$text=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',$text)??'';$text=preg_replace('/[ \t]+\n/u',"\n",$text)??'';$text=trim($text);if(strlen($text)>DOCUMENT_MAX_TEXT)fail('Извлечённый текст слишком велик. Разделите документ; текст не был обрезан.',413);return $text;
}
function documentDecode(string $bytes):string{
 if(str_starts_with($bytes,"\xEF\xBB\xBF"))$bytes=substr($bytes,3);
 if(str_starts_with($bytes,"\xFF\xFE"))return (string)iconv('UTF-16LE','UTF-8',substr($bytes,2));if(str_starts_with($bytes,"\xFE\xFF"))return (string)iconv('UTF-16BE','UTF-8',substr($bytes,2));
 if(preg_match('//u',$bytes))return $bytes;if(str_contains($bytes,"\0"))fail('Это двоичный файл, а не текстовый документ.');$converted=@iconv('Windows-1251','UTF-8',$bytes);if($converted===false)fail('Не удалось определить кодировку. Сохраните TXT в UTF-8.');return $converted;
}
function extractDocument(string $path,string $extension):array{
 $size=filesize($path);if(!$size||$size>DOCUMENT_MAX_BYTES)fail('Файл должен быть не больше 12 МБ.',413);$head=(string)file_get_contents($path,false,null,0,8);$parts=[];$warnings=[];$pages=0;$sheets=0;
 if($extension==='txt'||$extension==='csv'){$parts[]=documentDecode((string)file_get_contents($path));}
 elseif(in_array($extension,['docx','xlsx'],true)){
  if(substr($head,0,2)!=='PK')fail('Расширение не соответствует формату документа.');$zip=new DocumentZip($path);
  if($extension==='docx'){
   if(!$zip->has('word/document.xml'))fail('Это не документ Word DOCX.');$names=array_values(array_filter($zip->names(),fn($n)=>$n==='word/document.xml'||preg_match('~^word/(header\d+|footer\d+|footnotes|endnotes)\.xml$~',$n)));usort($names,fn($a,$b)=>($a==='word/document.xml'?-1:($b==='word/document.xml'?1:strcmp($a,$b))));
   foreach($names as $name){$dom=documentXml($zip->read($name));$xp=new DOMXPath($dom);$lines=[];foreach($xp->query('//*[local-name()="p"]') as $p){$line='';foreach($xp->query('.//*[local-name()="t" or local-name()="tab" or local-name()="br"]',$p) as $n)$line.=$n->localName==='t'?$n->textContent:($n->localName==='tab'?"\t":"\n");if(trim($line)!=='')$lines[]=$line;}$parts[]=implode("\n",$lines);}
  }else{
   if(!$zip->has('xl/workbook.xml'))fail('Это не книга Excel XLSX.');$shared=[];if($zip->has('xl/sharedStrings.xml')){$dom=documentXml($zip->read('xl/sharedStrings.xml'));$xp=new DOMXPath($dom);foreach($xp->query('//*[local-name()="si"]') as $si){$text='';foreach($xp->query('.//*[local-name()="t"]',$si) as $t)$text.=$t->textContent;$shared[]=$text;}}
   $styles=[];$formats=[];if($zip->has('xl/styles.xml')){$dom=documentXml($zip->read('xl/styles.xml'));$xp=new DOMXPath($dom);foreach($xp->query('//*[local-name()="numFmt"]') as $n)$formats[(int)$n->getAttribute('numFmtId')]=$n->getAttribute('formatCode');foreach($xp->query('//*[local-name()="cellXfs"]/*[local-name()="xf"]') as $n){$id=(int)$n->getAttribute('numFmtId');$styles[]=($id>=14&&$id<=22)||($id>=45&&$id<=47)||isset($formats[$id])&&preg_match('/[dy]/i',preg_replace('/"[^"]*"|\\\\./','',$formats[$id]));}}
   $book=documentXml($zip->read('xl/workbook.xml'));$bx=new DOMXPath($book);$pr=$bx->query('//*[local-name()="workbookPr"]')->item(0);$date1904=$pr&&in_array($pr->getAttribute('date1904'),['1','true'],true);$relations=[];$rels=documentXml($zip->read('xl/_rels/workbook.xml.rels'));foreach($rels->documentElement->childNodes as $n)if($n instanceof DOMElement&&$n->getAttribute('TargetMode')!=='External')$relations[$n->getAttribute('Id')]=ltrim($n->getAttribute('Target'),'/');
   foreach($bx->query('//*[local-name()="sheet"]') as $sheet){$id=$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$target=$relations[$id]??'';$target=str_starts_with($target,'xl/')?$target:'xl/'.$target;if(!$zip->has($target))continue;$sheets++;$lines=['Лист: '.$sheet->getAttribute('name')];$dom=documentXml($zip->read($target));$xp=new DOMXPath($dom);
    foreach($xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row){$cells=[];foreach($xp->query('./*[local-name()="c"]',$row) as $c){$type=$c->getAttribute('t');$v=$xp->query('./*[local-name()="v"]',$c)->item(0);$value=$v?$v->textContent:'';if($type==='s')$value=$shared[(int)$value]??'';elseif($type==='inlineStr'){$value='';foreach($xp->query('.//*[local-name()="t"]',$c) as $t)$value.=$t->textContent;}elseif($type==='b')$value=$value==='1'?'Да':'Нет';elseif($value!==''&&is_numeric($value)&&!empty($styles[(int)$c->getAttribute('s')]))$value=gmdate('Y-m-d',(int)round(((float)$value-($date1904?24107:25569))*86400));elseif($value===''&&$xp->query('./*[local-name()="f"]',$c)->length)$value='[формула без сохранённого значения]';if(trim($value)!=='')$cells[]=$c->getAttribute('r').': '.$value;}
     if($cells)$lines[]=implode("\t",$cells);
    }$parts[]=implode("\n",$lines);if(strlen(implode("\n",$parts))>DOCUMENT_MAX_TEXT)fail('В книге слишком много текста. Разделите файл.',413);
   }$warnings[]='Формулы и макросы не выполнялись; использованы сохранённые значения ячеек.';
  }
 }elseif($extension==='xls'){
  if($head!=="\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")fail('Это не двоичная книга Excel XLS.');if(!function_exists('mb_convert_encoding'))fail('Включите PHP mbstring для файлов XLS.',503);require_once dirname(__DIR__).'/vendor/simplexls/SimpleXLS.php';$xls=\Shuchkin\SimpleXLS::parseFile($path);if(!$xls)fail('Не удалось прочитать XLS. Пересохраните книгу как XLSX.');foreach($xls->sheetNames() as $i=>$name){$sheets++;$lines=['Лист: '.$name];foreach($xls->rows($i) as $r=>$row){$values=[];foreach($row as $col=>$value)if($value!==null&&trim((string)$value)!=='')$values[]='C'.($col+1).': '.(string)$value;if($values)$lines[]='Строка '.($r+1).' · '.implode("\t",$values);}$parts[]=implode("\n",$lines);if(strlen(implode("\n",$parts))>DOCUMENT_MAX_TEXT)fail('В книге слишком много текста. Разделите файл.',413);}
 }elseif($extension==='pdf'){
  if(!str_starts_with($head,'%PDF-'))fail('Это не PDF-файл.');if(!function_exists('mb_convert_encoding')||!function_exists('iconv'))fail('Включите PHP mbstring и iconv для PDF.',503);
  spl_autoload_register(function($class){$prefix='Smalot\\PdfParser\\';if(str_starts_with($class,$prefix)){$file=dirname(__DIR__).'/vendor/pdfparser/src/'.str_replace('\\','/',$class).'.php';if(is_file($file))require_once $file;}});
  try{$cfg=new \Smalot\PdfParser\Config();$cfg->setRetainImageContent(false);$cfg->setDecodeMemoryLimit(33554432);$cfg->setMaxNestingDepth(100);$parser=new \Smalot\PdfParser\Parser([],$cfg);$pdf=$parser->parseFile($path);foreach($pdf->getPages() as $page){$pages++;$text=trim($page->getText());$parts[]='Страница '.$pages."\n".$text;if($text==='')$warnings[]='Страница '.$pages.' не содержит извлекаемого текста; изображения не распознаются.';}}catch(Throwable){fail('Не удалось прочитать текстовый слой PDF. Снимите пароль или сохраните текстовую копию.');}
  $body=implode('',array_map(fn($p)=>preg_replace('/^Страница \d+\s*/u','',$p),$parts));if(!preg_match('/[\p{L}\p{N}]/u',$body))fail('В PDF нет текстового слоя. Скан и изображения не распознаются; загрузите текстовый PDF, DOCX или XLSX.');
 }else fail('Поддерживаются TXT, DOCX, XLSX, XLS и PDF с текстовым слоем.');
 $text=documentCleanText(implode("\n\n",$parts));if($text==='')fail('В документе не найден текст.');return ['text'=>$text,'format'=>$extension,'pages'=>$pages,'sheets'=>$sheets,'warnings'=>array_values(array_unique($warnings)),'sha256'=>hash_file('sha256',$path),'bytes'=>$size];
}
