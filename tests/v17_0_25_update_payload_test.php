<?php
declare(strict_types=1);
function failP25(string $m): never { fwrite(STDERR,"FAIL: {$m}\n"); exit(1); }
function okP25(bool $v,string $m): void { if(!$v) failP25($m); }
$root=realpath(dirname(__DIR__));
$zipFile=$root.'/.build/v17.0.25/release/TMS_OS_LATEST.zip';
$metaFile=$root.'/.build/v17.0.25/release/RELEASE.json';
okP25(is_file($zipFile)&&is_file($metaFile),'Thiếu artifact V17.0.25.');
$meta=json_decode((string)file_get_contents($metaFile),true);
okP25(($meta['version']??'')==='17.0.25','Sai version metadata.');
okP25(hash_file('sha256',$zipFile)===($meta['checksum_sha256']??''),'Sai checksum.');
$z=new ZipArchive(); okP25($z->open($zipFile)===true,'Không mở ZIP.');
foreach(['config/app.php','public/index.php','scripts/install.sh','scripts/tms-update-restart.sh','scripts/tms-guardian.sh'] as $name) okP25($z->locateName($name)!==false,'Thiếu '.$name);
$restart=(string)$z->getFromName('scripts/tms-update-restart.sh');
foreach(['tms-php-engine.sh','nginx -s reload','tms-cloudflare-tunnel.sh','start-tms.sh'] as $bad) okP25(!str_contains($restart,$bad),'Update Center không được gọi '.$bad);
$guardian=(string)$z->getFromName('scripts/tms-guardian.sh');
okP25(str_contains($guardian,'external-update.lock'),'Payload Guardian thiếu external update lock.');
okP25(str_contains($guardian,'confirm_upstream_failure'),'Payload Guardian thiếu double probe.');
okP25(!str_contains($guardian,'nginx -s reload'),'Guardian auto repair không được reload Nginx.');
$z->close();
echo "PASS: V17.0.25 payload bảo vệ shared runtime khi ứng dụng ngoài hot update.\n";
