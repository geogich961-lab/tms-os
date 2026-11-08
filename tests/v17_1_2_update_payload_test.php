<?php
declare(strict_types=1);

function failP1712(string $m): never { fwrite(STDERR,"FAIL: {$m}\n"); exit(1); }
function okP1712(bool $v,string $m): void { if(!$v) failP1712($m); }

$root=realpath(dirname(__DIR__));
$zipFile=$root.'/.build/v17.1.2/release/TMS_OS_LATEST.zip';
$metaFile=$root.'/.build/v17.1.2/release/RELEASE.json';
okP1712(is_file($zipFile)&&is_file($metaFile),'Thiếu artifact V17.1.2.');
$meta=json_decode((string)file_get_contents($metaFile),true);
okP1712(($meta['version']??'')==='17.1.2','Sai version metadata.');
okP1712(($meta['channel']??'')==='stable','V17.1.2 phải là bản stable chính thức.');
okP1712(hash_file('sha256',$zipFile)===($meta['checksum_sha256']??''),'Sai checksum.');

$z=new ZipArchive();
okP1712($z->open($zipFile)===true,'Không mở ZIP.');
foreach(['config/app.php','public/index.php','routes/web.php','app/Services/UpdateService.php','app/Services/TerminalService.php','app/Controllers/TerminalController.php','app/Services/AutoBackupService.php','app/Controllers/BackupController.php','app/Views/backups/index.php','app/Views/terminal/index.php','app/Views/monitoring/index.php','app/Views/files/index.php','app/Services/PluginService.php','app/Services/TelegramCommandService.php','scripts/tms-telegram-poll.php','scripts/tms-watchdog.sh'] as $name){
    okP1712($z->locateName($name)!==false,'Thiếu '.$name);
}
$service=(string)$z->getFromName('app/Services/UpdateService.php');
foreach([
    'public function updateChannel(): string',
    'public function setUpdateChannel(string $channel): array',
    'public function releases(int $limit = 20): array',
    'public function releaseByTag(string $tag): array',
    'public function enqueueReleaseApply(string $tag): array',
    'private function stageResolvedRelease(array $release): array',
    "version_compare(\$a, \$b, '>')",
] as $needle){
    okP1712(str_contains($service,$needle),'Payload thiếu update-channel guard: '.$needle);
}
okP1712(str_contains($service,'hash_equals(strtolower($expectedHash), strtolower($actualHash))'),'Release cụ thể phải bắt buộc checksum chính xác.');

// 1. Terminal lệnh thủ công
$terminal=(string)$z->getFromName('app/Services/TerminalService.php');
foreach(['public function runCustom(string $command): array','terminal-audit.log','cd "$HOME"; '] as $needle){
    okP1712(str_contains($terminal,$needle),'Payload thiếu Terminal lệnh thủ công: '.$needle);
}
$routes=(string)$z->getFromName('routes/web.php');
foreach(["'/terminal/exec'","'/backups/auto/check'","'/api/telegram-commands/enable-status'","'/api/updates/releases'"] as $needle){
    okP1712(str_contains($routes,$needle),'Payload thiếu route '.$needle);
}
$terminalView=(string)$z->getFromName('app/Views/terminal/index.php');
foreach(['Lệnh thủ công','/terminal/exec','custom_command'] as $needle){
    okP1712(str_contains($terminalView,$needle),'Payload thiếu UI Terminal thủ công: '.$needle);
}

// 2. Nhãn Thùng rác có chữ
$filesView=(string)$z->getFromName('app/Views/files/index.php');
okP1712(str_contains($filesView,'🗑 Thùng rác'),'Payload thiếu nhãn chữ Thùng rác ở File Manager.');

// 3. Gộp hàng Pin: chỉ còn đúng 1 hàng <strong>Pin</strong>, giữ hook cập nhật trực tiếp
$mon=(string)$z->getFromName('app/Views/monitoring/index.php');
okP1712(substr_count($mon,'<strong>Pin</strong>')===1,'Resource Monitor phải còn đúng 1 hàng Pin.');
foreach(['id="battery-bar-row"','data-monitor-bar="battery"','data-monitor-value="battery_label"'] as $needle){
    okP1712(str_contains($mon,$needle),'Payload thiếu hook Pin đã gộp: '.$needle);
}

// 4. AutoBackup: không chặn lưu + kiểm tra rclone
$auto=(string)$z->getFromName('app/Services/AutoBackupService.php');
foreach(['public function offsiteIssue(array $config): string','public function checkRemote(): array',"'offsite_ready'"] as $needle){
    okP1712(str_contains($auto,$needle),'Payload thiếu cải thiện AutoBackup: '.$needle);
}
okP1712(!str_contains($auto,'tính năng offsite sẽ bị chặn khi lưu'),'Payload vẫn còn hành vi chặn lưu offsite cũ.');
okP1712(!str_contains($auto,"throw new RuntimeException('Chưa cài rclone."),'saveConfig vẫn còn chặn lưu khi chưa cài rclone.');
$backupsView=(string)$z->getFromName('app/Views/backups/index.php');
foreach(['Kiểm tra kết nối rclone','không dán link thư mục Google Drive','/backups/auto/check'] as $needle){
    okP1712(str_contains($backupsView,$needle),'Payload thiếu UI Backup offsite: '.$needle);
}

// 5. Gói rclone trong Runtime Packages
$plugins=(string)$z->getFromName('app/Services/PluginService.php');
okP1712(str_contains($plugins,"'id'=>'rclone'"),'Payload thiếu gói rclone trong Runtime Packages.');
okP1712(str_contains($plugins,"'package'=>'rclone'"),'Payload thiếu pkg rclone.');

$app=(string)$z->getFromName('config/app.php');
okP1712(str_contains($app,"Platform V17.1.2"),'Payload chưa bump V17.1.2.');
okP1712(str_contains($app,"'channel' => 'stable'"),'Payload V17.1.2 phải thuộc kênh stable.');
$sw=(string)$z->getFromName('public/service-worker.js');
okP1712(str_contains($sw,"tms-os-v17.1.2"),'Service Worker chưa bump V17.1.2.');
$z->close();

echo "PASS: V17.1.2 payload stable — Terminal thủ công, gói rclone, Backup offsite không chặn lưu, nhãn Thùng rác, gộp hàng Pin.\n";
