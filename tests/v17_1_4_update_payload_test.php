<?php
declare(strict_types=1);

function failP1714(string $m): never { fwrite(STDERR,"FAIL: {$m}\n"); exit(1); }
function okP1714(bool $v,string $m): void { if(!$v) failP1714($m); }

$root=realpath(dirname(__DIR__));
$zipFile=$root.'/.build/v17.1.4/release/TMS_OS_LATEST.zip';
$metaFile=$root.'/.build/v17.1.4/release/RELEASE.json';
okP1714(is_file($zipFile)&&is_file($metaFile),'Thiếu artifact V17.1.4.');
$meta=json_decode((string)file_get_contents($metaFile),true);
okP1714(($meta['version']??'')==='17.1.4','Sai version metadata.');
okP1714(($meta['channel']??'')==='stable','V17.1.4 phải là bản stable chính thức.');
okP1714(hash_file('sha256',$zipFile)===($meta['checksum_sha256']??''),'Sai checksum.');

$z=new ZipArchive();
okP1714($z->open($zipFile)===true,'Không mở ZIP.');
foreach(['config/app.php','public/index.php','public/service-worker.js','routes/web.php','app/Services/BackupService.php','app/Services/AutoBackupService.php','app/Controllers/BackupController.php','app/Views/backups/index.php','scripts/tms-auto-backup.php'] as $name){
    okP1714($z->locateName($name)!==false,'Thiếu '.$name);
}

// Tâm điểm V17.1.4: nút chạy ngay đi qua requestRun() (chạy nền), runNow()
// có khóa flock chống chạy trùng + kết quả skipped cho CLI.
$auto=(string)$z->getFromName('app/Services/AutoBackupService.php');
okP1714(str_contains($auto,'function requestRun'),'Payload thiếu requestRun() — nút chạy ngay chưa chuyển sang chạy nền.');
okP1714(str_contains($auto,'auto-backup-run.lock'),'Payload thiếu khóa chạy auto-backup-run.lock.');
okP1714(str_contains($auto,'function launchBackgroundWorker'),'Payload thiếu launchBackgroundWorker().');
okP1714(str_contains($auto,"'skipped' => true"),'Payload thiếu xử lý vòng trùng (skipped).');
okP1714(str_contains($auto,'function pushOffsite'),'Payload thiếu pushOffsite (di sản V17.1.2/17.1.3).');
$backupCtl=(string)$z->getFromName('app/Controllers/BackupController.php');
okP1714(str_contains($backupCtl,'requestRun'),'BackupController::autoRun phải gọi requestRun().');
okP1714(!str_contains($backupCtl,'auto->runNow()'),'BackupController không được gọi runNow() đồng bộ trong request web nữa.');
$cli=(string)$z->getFromName('scripts/tms-auto-backup.php');
okP1714(str_contains($cli,'SKIP:'),'CLI phải in SKIP và thoát 0 khi trùng vòng.');
$view=(string)$z->getFromName('app/Views/backups/index.php');
okP1714(str_contains($view,'Đang chạy backup ở chế độ nền'),'Trang Backup phải hiển thị trạng thái đang chạy nền.');
// Di sản V17.1.3: create() trả ID
$backup=(string)$z->getFromName('app/Services/BackupService.php');
okP1714(str_contains($backup,'return $id;'),'Payload thiếu bản vá create() trả ID của V17.1.3.');

$app=(string)$z->getFromName('config/app.php');
okP1714(str_contains($app,'Platform V17.1.4'),'Payload chưa bump V17.1.4.');
okP1714(str_contains($app,"'channel' => 'stable'"),'Payload V17.1.4 phải thuộc kênh stable.');
$sw=(string)$z->getFromName('public/service-worker.js');
okP1714(str_contains($sw,'tms-os-v17.1.4'),'Service worker chưa bump V17.1.4.');
$z->close();
echo "OK: payload V17.1.4\n";
