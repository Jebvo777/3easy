<?php
declare(strict_types=1);
function assetManifest():array{
 $path=__DIR__.'/../assets/manifest.json';$raw=is_file($path)?file_get_contents($path):false;$manifest=$raw?json_decode($raw,true):null;
 if(!is_array($manifest)||!isset($manifest['build'],$manifest['js'],$manifest['css']))throw new RuntimeException('Missing build manifest');
 foreach(['js'=>'js','css'=>'css'] as $key=>$extension){if(!is_string($manifest[$key])||!preg_match('/^assets\/runtime\/(?:app|ui)\.[a-f0-9]{16}\.'. $extension .'$/D',$manifest[$key])||!is_file(__DIR__.'/../'.$manifest[$key]))throw new RuntimeException('Invalid build asset');}
 return $manifest;
}
