<?php
declare(strict_types=1);

function failP1711(string $m): never { fwrite(STDERR,"FAIL: {$m}\n"); exit(1); }
function okP1711(bool $v,string $m): void { if(!$v) failP1711($m); }

$root=realpath(dirname(__DIR__));
$zipFile=$root.'/.build/v17.1.1/release/TMS_OS_LATEST.zip';
$metaFile=$root.'/.build/v17.1.1/release/RELEASE.json';
okP1711(is_file($zipFile)&&is_file($metaFile),'Thiếu artifact V17.1.1.');
$meta=json_decode((string)file_get_contents($metaFile),true);
okP1711(($meta['version']??'')==='17.1.1','Sai version metadata.');
okP1711(($meta['channel']??'')==='stable','V17.1.1 phải là bản stable chính thức.');
okP1711(hash_file('sha256',$zipFile)===($meta['checksum_sha256']??''),'Sai checksum.');

$z=new ZipArchive();
okP1711($z->open($zipFile)===true,'Không mở ZIP.');
foreach(['config/app.php','public/index.php','routes/web.php','app/Services/UpdateService.php','app/Controllers/UpdateController.php','app/Views/updates/index.php','scripts/tms-update-worker.php','app/Services/TelegramCommandService.php','app/Controllers/TelegramWebhookController.php','app/Views/cron/index.php','scripts/tms-telegram-poll.php','scripts/tms-watchdog.sh','app/Views/dashboard/landing.php'] as $name){
    okP1711($z->locateName($name)!==false,'Thiếu '.$name);
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
    okP1711(str_contains($service,$needle),'Payload thiếu update-channel guard: '.$needle);
}
okP1711(str_contains($service,'hash_equals(strtolower($expectedHash), strtolower($actualHash))'),'Release cụ thể phải bắt buộc checksum chính xác.');

$telegram=(string)$z->getFromName('app/Services/TelegramCommandService.php');
foreach([
    'public function enableCommand(string $command): array',
    'public function disableCommand(string $command): array',
    "'status_enabled'",
    "'restart_enabled'",
    'commandDisabledNotice',
] as $needle){
    okP1711(str_contains($telegram,$needle),'Payload thiếu tách công tắc Telegram: '.$needle);
}
$routes=(string)$z->getFromName('routes/web.php');
foreach(["'/api/telegram-commands/enable-status'","'/api/telegram-commands/disable-status'","'/api/telegram-commands/enable-restart'","'/api/telegram-commands/disable-restart'","'/api/updates/releases'","'/updates/channel'","'/updates/release/apply'"] as $needle){
    okP1711(str_contains($routes,$needle),'Payload thiếu route '.$needle);
}
$view=(string)$z->getFromName('app/Views/cron/index.php');
foreach(['id="telegramStatusToggle"','id="telegramRestartToggle"','Lệnh /restart Telegram','toggleTelegramCommand(\'status\')','toggleTelegramCommand(\'restart\')'] as $needle){
    okP1711(str_contains($view,$needle),'Payload thiếu UI 2 công tắc Telegram: '.$needle);
}
$updatesView=(string)$z->getFromName('app/Views/updates/index.php');
foreach(['id="update-channel-card"','id="release-history-card"','Beta / Test — thử nghiệm','Chuyển về bản này'] as $needle){
    okP1711(str_contains($updatesView,$needle),'Payload thiếu UI '.$needle);
}
$app=(string)$z->getFromName('config/app.php');
okP1711(str_contains($app,"Platform V17.1.1"),'Payload chưa bump V17.1.1.');
okP1711(str_contains($app,"'channel' => 'stable'"),'Payload V17.1.1 phải thuộc kênh stable.');
$sw=(string)$z->getFromName('public/service-worker.js');
okP1711(str_contains($sw,"tms-os-v17.1.1"),'Service Worker chưa bump V17.1.1.');
$landing=(string)$z->getFromName('app/Views/dashboard/landing.php');
okP1711(str_contains($landing,'thành một mini VPS.</p>'),'Payload chưa có câu mô tả landing mới.');
okP1711(str_contains($landing,'sai sót và lỗi không mong muốn'),'Payload chưa có đoạn giới thiệu landing mới.');
okP1711(!str_contains($landing,'để chạy Webserver'),'Payload vẫn còn câu chữ landing cũ.');
$z->close();

echo "PASS: V17.1.1 payload stable, nội dung landing mới, tách 2 công tắc Telegram và rollback release an toàn.\n";
