<?php
declare(strict_types=1);

function failP1713(string $m): never { fwrite(STDERR,"FAIL: {$m}\n"); exit(1); }
function okP1713(bool $v,string $m): void { if(!$v) failP1713($m); }

$root=realpath(dirname(__DIR__));
$zipFile=$root.'/.build/v17.1.3/release/TMS_OS_LATEST.zip';
$metaFile=$root.'/.build/v17.1.3/release/RELEASE.json';
okP1713(is_file($zipFile)&&is_file($metaFile),'Thiếu artifact V17.1.3.');
$meta=json_decode((string)file_get_contents($metaFile),true);
okP1713(($meta['version']??'')==='17.1.3','Sai version metadata.');
okP1713(($meta['channel']??'')==='stable','V17.1.3 phải là bản stable chính thức.');
okP1713(hash_file('sha256',$zipFile)===($meta['checksum_sha256']??''),'Sai checksum.');

$z=new ZipArchive();
okP1713($z->open($zipFile)===true,'Không mở ZIP.');
foreach(['config/app.php','public/index.php','public/service-worker.js','routes/web.php','app/Services/BackupService.php','app/Services/AutoBackupService.php','app/Controllers/BackupController.php','app/Controllers/WebsiteController.php','app/Services/UpdateService.php','scripts/tms-auto-backup.php'] as $name){
    okP1713($z->locateName($name)!==false,'Thiếu '.$name);
}

// Tâm điểm V17.1.3: BackupService::create() trả ID cho vòng backup tự động,
// không còn trả câu thông báo; controller tự dựng thông báo như cũ.
$backup=(string)$z->getFromName('app/Services/BackupService.php');
okP1713(str_contains($backup,'return $id;'),'Payload thiếu bản vá create() trả ID.');
okP1713(!str_contains($backup,"return 'Đã tạo '"),'Payload vẫn còn create() trả câu thông báo cũ.');
$backupCtl=(string)$z->getFromName('app/Controllers/BackupController.php');
okP1713(str_contains($backupCtl,'Đã tạo bản sao lưu'),'BackupController phải tự dựng thông báo tạo backup.');
okP1713(str_contains($backupCtl,'Đã tạo snapshot website'),'BackupController phải tự dựng thông báo snapshot.');
$webCtl=(string)$z->getFromName('app/Controllers/WebsiteController.php');
okP1713(str_contains($webCtl,'Đã tạo snapshot website'),'WebsiteController phải tự dựng thông báo snapshot.');
$auto=(string)$z->getFromName('app/Services/AutoBackupService.php');
okP1713(str_contains($auto,'function pushOffsite'),'Payload thiếu pushOffsite trong AutoBackupService.');
okP1713(str_contains($auto,'function checkRemote'),'Payload thiếu kiểm tra kết nối rclone (di sản V17.1.2).');

$app=(string)$z->getFromName('config/app.php');
okP1713(str_contains($app,'Platform V17.1.3'),'Payload chưa bump V17.1.3.');
okP1713(str_contains($app,"'channel' => 'stable'"),'Payload V17.1.3 phải thuộc kênh stable.');
$sw=(string)$z->getFromName('public/service-worker.js');
okP1713(str_contains($sw,'tms-os-v17.1.3'),'Service worker chưa bump V17.1.3.');
$z->close();
echo "OK: payload V17.1.3\n";
