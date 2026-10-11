<?php
// Только CLI: веб-установщика с общедоступной записью в БД нет.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$configFile=__DIR__.'/../config.local.php';if(!is_file($configFile)){fwrite(STDERR,"Сначала создайте config.local.php из шаблона.\n");exit(1);}
$c=require $configFile;
try{
 $db=new PDO('mysql:host='.$c['host'].';port='.($c['port']??3306).';charset=utf8mb4',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $sql=file_get_contents(__DIR__.'/../database/install.sql');
 // SQL не принимает пользовательский ввод; имена в поставке фиксированы.
 foreach(preg_split('/;\s*(?:\n|$)/',$sql) as $statement){if(trim($statement)!=='')$db->exec($statement);}
 echo "Схема и тестовые данные установлены.\n";
}catch(Throwable $e){fwrite(STDERR,"Установка не завершена: проверьте права и подключение к БД. Код ".$e->getCode()."\n");exit(1);}
