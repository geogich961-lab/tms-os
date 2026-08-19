<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/helpers.php';
$ui=tms_ui_settings();
header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode([
 'id'=>'/','name'=>'TMS OS by THCGaming','short_name'=>'TMS OS',
 'description'=>'Nền tảng quản trị server Android/Termux.','lang'=>'vi',
 'start_url'=>'/','scope'=>'/','display'=>'standalone','display_override'=>['standalone'],
 'orientation'=>'portrait-primary','background_color'=>'#0a1220','theme_color'=>'#0a1220',
 'categories'=>['utilities','developer tools'],
 'icons'=>[
  ['src'=>'/assets/icons/icon-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
  ['src'=>'/assets/icons/icon-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any'],
 ],
 'shortcuts'=>[
  ['name'=>'Dashboard','short_name'=>'Dashboard','url'=>'/','icons'=>[['src'=>'/assets/icons/icon-192.png','sizes'=>'192x192']]],
  ['name'=>'Website','short_name'=>'Website','url'=>'/websites','icons'=>[['src'=>'/assets/icons/icon-192.png','sizes'=>'192x192']]],
  ['name'=>'App Installer','short_name'=>'Apps','url'=>'/apps','icons'=>[['src'=>'/assets/icons/icon-192.png','sizes'=>'192x192']]],
 ]
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
