<?php
declare(strict_types=1);
/** Small local OOXML writer for hosting without Composer or ZipArchive. */
final class EasyXlsxDate{public function __construct(public string $value,public bool $time=false){}}
final class EasyXlsxMoney{public function __construct(public ?int $cents){}}
final class EasyXlsxFormula{public function __construct(public string $formula,public int|float|null $value){}}
function xlsxXml(string $s):string{return htmlspecialchars(preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$s)??'',ENT_XML1|ENT_QUOTES,'UTF-8');}
function xlsxColumn(int $n):string{$s='';for($n++;$n>0;$n=intdiv($n-1,26))$s=chr(65+($n-1)%26).$s;return $s;}
function xlsxNumber(int|float $n):string{if(!is_finite((float)$n))fail('В экспорте обнаружено некорректное число.');return is_int($n)?(string)$n:rtrim(rtrim(sprintf('%.10F',$n),'0'),'.');}
function xlsxZip(array $files):string{
 $out='';$central='';$count=0;$unpacked=0;$t=getdate();$time=($t['hours']<<11)|($t['minutes']<<5)|intdiv($t['seconds'],2);$date=(max(0,$t['year']-1980)<<9)|($t['mon']<<5)|$t['mday'];
 foreach($files as $name=>$raw){$unpacked+=strlen($raw);if($unpacked>67108864)fail('Экспорт превышает 64 МБ распакованных данных. Выберите меньший охват.',413);$compressed=gzdeflate($raw,6);$crc=crc32($raw);$offset=strlen($out);$size=strlen($raw);$length=strlen($compressed);$n=strlen($name);
  $out.=pack('VvvvvvVVVvv',0x04034b50,20,0x800,8,$time,$date,$crc,$length,$size,$n,0).$name.$compressed;
  $central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0x800,8,$time,$date,$crc,$length,$size,$n,0,0,0,0,0,$offset).$name;$count++;
 }
 return $out.$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),strlen($out),0);
}
function xlsxStyles():string{
 $fonts='<fonts count="4"><font><sz val="11"/><color rgb="FF294A35"/><name val="Calibri"/></font><font><b/><sz val="20"/><color rgb="FF284F38"/><name val="Calibri"/></font><font><sz val="12"/><color rgb="FF58724B"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>';
 $fills='<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF315B40"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2EDD8"/><bgColor indexed="64"/></patternFill></fill></fills>';
 $borders='<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="hair"><color rgb="FFC8D6BD"/></left><right style="hair"><color rgb="FFC8D6BD"/></right><top style="hair"><color rgb="FFC8D6BD"/></top><bottom style="hair"><color rgb="FFC8D6BD"/></bottom><diagonal/></border></borders>';
 $defs=[[0,0,0,1],[0,1,0,0],[0,2,0,0],[0,3,2,1],[0,0,3,1],[164,0,0,1],[165,0,0,1],[166,0,0,1],[167,0,0,1],[168,0,0,1],[3,0,0,1]];$xf='';
 foreach($defs as [$format,$font,$fill,$border])$xf.='<xf numFmtId="'.$format.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyNumberFormat="1" applyFill="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>';
 return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="5"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="#,##0.00&quot; ₽&quot;"/><numFmt numFmtId="166" formatCode="dd.mm.yyyy"/><numFmt numFmtId="167" formatCode="dd.mm.yyyy hh:mm"/><numFmt numFmtId="168" formatCode="0.0%"/></numFmts>'.$fonts.$fills.$borders.'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="'.count($defs).'">'.$xf.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium4" defaultPivotStyle="PivotStyleLight16"/></styleSheet>';
}
function xlsxCell(string $ref,mixed $value,int $style=0):string{
 if($value instanceof EasyXlsxMoney){$style=6;$value=$value->cents===null?null:$value->cents/100;}
 if($value instanceof EasyXlsxDate){$style=$value->time?8:7;$value=$value->value===''?null:((new DateTimeImmutable($value->value,new DateTimeZone('UTC')))->getTimestamp()/86400+25569);}
 if($value instanceof EasyXlsxFormula)return '<c r="'.$ref.'" s="'.$style.'"><f>'.xlsxXml($value->formula).'</f>'.($value->value===null?'':'<v>'.xlsxNumber($value->value).'</v>').'</c>';
 if($value===null)return '<c r="'.$ref.'" s="'.$style.'"/>';
 if(is_int($value)||is_float($value)){if($style===0)$style=is_int($value)?10:5;return '<c r="'.$ref.'" s="'.$style.'"><v>'.xlsxNumber($value).'</v></c>';}
 if(is_bool($value))$value=$value?'Да':'Нет';
 // inlineStr is intentional: strings beginning with =,+,-,@ never become formulas.
 return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.xlsxXml((string)$value).'</t></is></c>';
}
function xlsxSheet(array $sheet,bool $drawing=false):string{
 $rows=$sheet['rows'];if(count($rows)>50000)fail('Один лист содержит больше 50 000 строк. Сузьте экспорт.',413);$cols='';$widths=$sheet['widths']??[];foreach($widths as $i=>$w)$cols.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.(float)$w.'" customWidth="1"/>';
 $data='';$max=max(1,count($widths));foreach($rows as $r)$max=max($max,count($r['values']??$r));$cells=0;foreach($rows as $i=>$row){$values=$row['values']??$row;$style=$row['style']??0;$max=max($max,count($values));$xml='';$height=$row['height']??24;
  foreach($values as $j=>$value){if(++$cells>1000000)fail('Слишком много ячеек в одном листе.',413);$xml.=xlsxCell(xlsxColumn($j).($i+1),$value,$style);if(is_string($value)){$length=preg_match_all('/./us',$value);$height=max($height,min(409,ceil($length/max(8,($i<2&&$j===0&&$max>1)?array_sum($widths):($widths[$j]??25)))*16));}}
  $data.='<row r="'.($i+1).'" ht="'.$height.'" customHeight="1">'.$xml.'</row>';
 }
 $merged=$max>1?'<mergeCells count="2"><mergeCell ref="A1:'.xlsxColumn($max-1).'1"/><mergeCell ref="A2:'.xlsxColumn($max-1).'2"/></mergeCells>':'';$last=xlsxColumn($max-1).max(1,count($rows));$header=$sheet['header']??4;$freeze=$sheet['freeze']??$header;
 $view='<sheetViews><sheetView workbookViewId="0" showGridLines="0">'.($freeze?'<pane ySplit="'.$freeze.'" topLeftCell="A'.($freeze+1).'" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A'.($freeze+1).'" sqref="A'.($freeze+1).'"/>':'').'</sheetView></sheetViews>';
 return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><dimension ref="A1:'.$last.'"/>'.$view.'<sheetFormatPr defaultRowHeight="24"/><cols>'.$cols.'</cols><sheetData>'.$data.'</sheetData>'.($header&&count($rows)>$header?'<autoFilter ref="A'.$header.':'.$last.'"/>':'').$merged.'<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup paperSize="8" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'.($drawing?'<drawing r:id="rDrawing"/>':'').'</worksheet>';
}
function garmentSvg(array $drawing):string{
 $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" width="640" height="640"><title>'.xlsxXml($drawing['label']).'</title><rect width="1000" height="1000" fill="#f7fbf1"/><path d="M70 500H930M500 70V930" fill="none" stroke="#d6e2ca" stroke-width="2"/>';
 foreach($drawing['contours'] as $c){$d='';foreach($c['points'] as $i=>[$x,$y])$d.=($i?' L':'M').xlsxNumber($x).' '.xlsxNumber($y);if($c['closed'])$d.=' Z';$color=match($c['kind']){'seam'=>'#a18b54','detail'=>'#6a864f',default=>'#315b3d'};$svg.='<path d="'.$d.'" fill="none" stroke="'.$color.'" stroke-width="'.($c['kind']==='outline'?5:3).'" stroke-linecap="round" stroke-linejoin="round"/>';}
 return $svg.'</svg>';
}
/** A real PNG fallback, drawn from the same bounded coordinates, needs no GD. */
function garmentPng(array $drawing,int $size=512):string{
 $rows=array_fill(0,$size,str_repeat("\xF7\xFB\xF1",$size));$dot=function(int $x,int $y,int $radius,string $color)use(&$rows,$size){for($dy=-$radius;$dy<=$radius;$dy++)for($dx=-$radius;$dx<=$radius;$dx++)if($dx*$dx+$dy*$dy<=$radius*$radius){$px=$x+$dx;$py=$y+$dy;if($px<0||$py<0||$px>=$size||$py>=$size)continue;$offset=$px*3;for($k=0;$k<3;$k++)$rows[$py][$offset+$k]=$color[$k];}};
 foreach($drawing['contours'] as $c){$points=$c['points'];if($c['closed'])$points[]=$points[0];$color=match($c['kind']){'seam'=>"\xA1\x8B\x54",'detail'=>"\x6A\x86\x4F",default=>"\x31\x5B\x3D"};
  for($i=1;$i<count($points);$i++){[$x0,$y0]=array_map(fn($v)=>(int)round($v*($size-1)/1000),$points[$i-1]);[$x1,$y1]=array_map(fn($v)=>(int)round($v*($size-1)/1000),$points[$i]);$dx=abs($x1-$x0);$sx=$x0<$x1?1:-1;$dy=-abs($y1-$y0);$sy=$y0<$y1?1:-1;$error=$dx+$dy;
   while(true){$dot($x0,$y0,$c['kind']==='outline'?2:1,$color);if($x0===$x1&&$y0===$y1)break;$twice=2*$error;if($twice>=$dy){$error+=$dy;$x0+=$sx;}if($twice<=$dx){$error+=$dx;$y0+=$sy;}}
  }
 }
 $chunk=fn($kind,$data)=>pack('N',strlen($data)).$kind.$data.pack('N',crc32($kind.$data));return "\x89PNG\r\n\x1A\n".$chunk('IHDR',pack('NNCCCCC',$size,$size,8,2,0,0,0)).$chunk('IDAT',gzcompress("\0".implode("\0",$rows),6)).$chunk('IEND','');
}
function xlsxWorkbook(array $sheets,?array $illustration=null):string{
 $files=[];$relationships='';$sheetXml='';$types='';$names=[];
 foreach($sheets as $i=>$sheet){$name=$sheet['name'];if(isset($names[$name])||preg_match_all('/./us',$name)>31||preg_match('~[\\\\/?*:\[\]]~',$name))fail('Некорректное имя листа.');$names[$name]=true;$id=$i+1;$drawing=($sheet['drawing']??false)&&$illustration!==null;$files['xl/worksheets/sheet'.$id.'.xml']=xlsxSheet($sheet,$drawing);$sheetXml.='<sheet name="'.xlsxXml($name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';$relationships.='<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';$types.='<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
  if($drawing)$files['xl/worksheets/_rels/sheet'.$id.'.xml.rels']='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
 }
 if($illustration){
  $files['xl/media/garment.svg']=garmentSvg($illustration);$files['xl/media/garment.png']=garmentPng($illustration);
  // Microsoft MS-ODRAWXML svgBlip extension, with a raster blip for other readers.
  $files['xl/drawings/drawing1.xml']='<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>3</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:ext cx="5486400" cy="5486400"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Схема изделия" descr="Схематичная иллюстрация, не лекала"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rPng"><a:extLst><a:ext uri="{96DAC541-7B7A-43D3-8B79-37D633B846F1}"><asvg:svgBlip xmlns:asvg="http://schemas.microsoft.com/office/drawing/2016/SVG/main" r:embed="rSvg"/></a:ext></a:extLst></a:blip><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="5486400" cy="5486400"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
  $files['xl/drawings/_rels/drawing1.xml.rels']='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rPng" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/garment.png"/><Relationship Id="rSvg" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/garment.svg"/></Relationships>';$types.='<Default Extension="svg" ContentType="image/svg+xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
 }
 $files['[Content_Types].xml']='<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$types.'</Types>';
 $files['_rels/.rels']='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
 $files['xl/workbook.xml']='<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$sheetXml.'</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>';
 $files['xl/_rels/workbook.xml.rels']='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'<Relationship Id="rStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';$files['xl/styles.xml']=xlsxStyles();return xlsxZip($files);
}
