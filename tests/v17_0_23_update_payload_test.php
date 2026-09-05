<?php
declare(strict_types=1);
function failV17023Payload(string $m): never { fwrite(STDERR,"FAIL: $m\n"); exit(1); }
function expectV17023Payload(bool $c,string $m): void { if(!$c) failV17023Payload($m); }
function rmTree17023(string $p): void { if(is_dir($p)&&!is_link($p)){foreach(scandir($p)?:[] as $i)if($i!=='.'&&$i!=='..')rmTree17023($p.'/'.$i);@rmdir($p);}else @unlink($p); }
$root=realpath(dirname(__DIR__));
$baseZip="$root/.build/v17.0.22/release/TMS_OS_LATEST.zip";
$releaseZip="$root/.build/v17.0.23/release/TMS_OS_LATEST.zip";
$metadataFile="$root/.build/v17.0.23/release/RELEASE.json";
$temp=sys_get_temp_dir().'/tms-v17023-payload-'.bin2hex(random_bytes(5)); $home="$temp/home"; $target="$home/tms-os"; $oldHome=getenv('HOME');
try{
 expectV17023Payload(is_file($baseZip)&&is_file($releaseZip)&&is_file($metadataFile),'Thiếu artifact V17.0.22 hoặc V17.0.23.');
 $metadata=json_decode((string)file_get_contents($metadataFile),true); expectV17023Payload(($metadata['version']??'')==='17.0.23','RELEASE.json sai version.');
 expectV17023Payload(hash_file('sha256',$releaseZip)===($metadata['checksum_sha256']??''),'Checksum không khớp.');
 $z=new ZipArchive(); expectV17023Payload($z->open($releaseZip)===true,'Không mở được ZIP.');
 foreach(range(0,$z->numFiles-1) as $i){$e=(string)$z->getNameIndex($i);expectV17023Payload((bool)preg_match('#^(app|config|public|routes|scripts)/#',$e),'Root không hợp lệ: '.$e);$d=(string)$z->getFromName($e);$ext=strtolower(pathinfo($e,PATHINFO_EXTENSION));if(in_array($ext,['php','js','css','json','sh','md','txt','conf','html'],true))expectV17023Payload(!str_contains($d,"\r\n"),'CRLF: '.$e);}
 $safety=(string)$z->getFromName('scripts/lib/installer-safety.sh'); $worker=(string)$z->getFromName('public/service-worker.js'); $z->close();
 expectV17023Payload(str_contains($safety,'server_names_hash_bucket_size 128;'),'Payload thiếu bucket_size guard.');
 expectV17023Payload(str_contains($safety,'server_names_hash_max_size 4096;'),'Payload thiếu max_size guard.');
 expectV17023Payload(str_contains($safety,'tms_ensure_nginx_server_name_hash'),'Payload thiếu installer repair function.');
 expectV17023Payload(str_contains($worker,"const VERSION='tms-os-v17.0.23';"),'Service Worker sai version.');
 @mkdir($target,0700,true); $base=new ZipArchive(); expectV17023Payload($base->open($baseZip)===true&&$base->extractTo($target),'Không giải nén V17.0.22.'); $base->close();
 @mkdir("$target/storage",0700,true); file_put_contents("$target/storage/persistent.txt",'keep'); @mkdir("$home/.tms-os/cloudflare-hosting",0700,true); $cf='{"tunnel_id":"unchanged"}'; file_put_contents("$home/.tms-os/cloudflare-hosting/config.json",$cf);
 @mkdir("$home/.tms-os/updates",0700,true); $staged="$home/.tms-os/updates/tms-update-v17.0.23.zip"; copy($releaseZip,$staged); putenv('HOME='.$home); putenv('TMS_UPDATE_SKIP_RESTART=1'); require "$target/app/Services/UpdateService.php"; $svc=new UpdateService(); $r=$svc->apply(basename($staged));
 expectV17023Payload(!empty($r['ok'])&&$svc->currentVersion()==='V17.0.23','UpdateService không lên V17.0.23.'); expectV17023Payload(file_get_contents("$target/storage/persistent.txt")==='keep','storage bị thay đổi.'); expectV17023Payload(file_get_contents("$home/.tms-os/cloudflare-hosting/config.json")===$cf,'Cloudflare config bị thay đổi.');
 echo "PASS: V17.0.22 → V17.0.23 giữ storage/Cloudflare và có installer Nginx safety.\n";
} finally { putenv('HOME'.($oldHome===false?'':'='.$oldHome)); rmTree17023($temp); }
