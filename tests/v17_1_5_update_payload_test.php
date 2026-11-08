<?php
declare(strict_types=1);

function failP1715(string $m): never { fwrite(STDERR,"FAIL: {$m}\n"); exit(1); }
function okP1715(bool $v,string $m): void { if(!$v) failP1715($m); }

$root=realpath(dirname(__DIR__));
$zipFile=$root.'/.build/v17.1.5/release/TMS_OS_LATEST.zip';
$metaFile=$root.'/.build/v17.1.5/release/RELEASE.json';
okP1715(is_file($zipFile)&&is_file($metaFile),'Thiếu artifact V17.1.5.');
$meta=json_decode((string)file_get_contents($metaFile),true);
okP1715(($meta['version']??'')==='17.1.5','Sai version metadata.');
okP1715(($meta['channel']??'')==='stable','V17.1.5 phải là bản stable chính thức.');
okP1715(hash_file('sha256',$zipFile)===($meta['checksum_sha256']??''),'Sai checksum.');

$z=new ZipArchive();
okP1715($z->open($zipFile)===true,'Không mở ZIP.');
foreach(['config/app.php','public/index.php','public/service-worker.js','routes/web.php','app/Services/BackupService.php','app/Services/AutoBackupService.php','scripts/tms-auto-backup.php'] as $name){
    okP1715($z->locateName($name)!==false,'Thiếu '.$name);
}

// Tâm điểm V17.1.5: tar trong create() phải loại trừ thư mục archives.
$backup=(string)$z->getFromName('app/Services/BackupService.php');
okP1715(str_contains($backup,'--exclude='),'Payload thiếu --exclude trong lệnh tar của BackupService.');
okP1715(str_contains($backup,'archiveDir'),'Bản vá phải tính đường loại trừ từ archiveDir.');
okP1715(str_contains($backup,'return $id;'),'Payload thiếu bản vá create() trả ID của V17.1.3.');
// Di sản V17.1.4: chạy nền + khóa chống trùng
$auto=(string)$z->getFromName('app/Services/AutoBackupService.php');
okP1715(str_contains($auto,'function requestRun'),'Payload thiếu requestRun() của V17.1.4.');
okP1715(str_contains($auto,'auto-backup-run.lock'),'Payload thiếu khóa chạy của V17.1.4.');

$app=(string)$z->getFromName('config/app.php');
okP1715(str_contains($app,'Platform V17.1.5'),'Payload chưa bump V17.1.5.');
okP1715(str_contains($app,"'channel' => 'stable'"),'Payload V17.1.5 phải thuộc kênh stable.');
$sw=(string)$z->getFromName('public/service-worker.js');
okP1715(str_contains($sw,'tms-os-v17.1.5'),'Service worker chưa bump V17.1.5.');
$z->close();
echo "OK: payload V17.1.5\n";
