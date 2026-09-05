<?php
declare(strict_types=1);
function failP(string $m): never { fwrite(STDERR,"FAIL: {$m}\n"); exit(1); }
function okP(bool $v,string $m): void { if(!$v) failP($m); }
$root=realpath(dirname(__DIR__));
$zipFile=$root.'/.build/v17.0.24/release/TMS_OS_LATEST.zip';
$metaFile=$root.'/.build/v17.0.24/release/RELEASE.json';
okP(is_file($zipFile)&&is_file($metaFile),'Thiếu artifact V17.0.24.');
$meta=json_decode((string)file_get_contents($metaFile),true);
okP(($meta['version']??'')==='17.0.24','Sai version metadata.');
okP(hash_file('sha256',$zipFile)===($meta['checksum_sha256']??''),'Sai checksum.');
$z=new ZipArchive(); okP($z->open($zipFile)===true,'Không mở ZIP.');
foreach(['config/app.php','public/index.php','scripts/install.sh','scripts/tms-update-restart.sh'] as $name) okP($z->locateName($name)!==false,'Thiếu '.$name);
$restart=(string)$z->getFromName('scripts/tms-update-restart.sh');
foreach(['tms-php-engine.sh','nginx -s reload','tms-cloudflare-tunnel.sh','start-tms.sh'] as $bad) okP(!str_contains($restart,$bad),'Payload hot update không được gọi '.$bad);
okP(str_contains($restart,'rollback_source'),'Payload thiếu rollback source.');
$z->close();
echo "PASS: V17.0.24 payload zero-downtime contract.\n";
