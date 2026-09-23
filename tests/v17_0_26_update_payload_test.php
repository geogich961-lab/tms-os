<?php
declare(strict_types=1);

function failP26(string $m): never { fwrite(STDERR,"FAIL: {$m}\n"); exit(1); }
function okP26(bool $v,string $m): void { if(!$v) failP26($m); }

$root=realpath(dirname(__DIR__));
$zipFile=$root.'/.build/v17.0.26/release/TMS_OS_LATEST.zip';
$metaFile=$root.'/.build/v17.0.26/release/RELEASE.json';
okP26(is_file($zipFile)&&is_file($metaFile),'Thiếu artifact V17.0.26.');
$meta=json_decode((string)file_get_contents($metaFile),true);
okP26(($meta['version']??'')==='17.0.26','Sai version metadata.');
okP26(($meta['channel']??'')==='stable','V17.0.26 phải là stable bootstrap.');
okP26(hash_file('sha256',$zipFile)===($meta['checksum_sha256']??''),'Sai checksum.');

$z=new ZipArchive();
okP26($z->open($zipFile)===true,'Không mở ZIP.');
foreach(['config/app.php','public/index.php','routes/web.php','app/Services/UpdateService.php','app/Controllers/UpdateController.php','app/Views/updates/index.php','scripts/tms-update-worker.php'] as $name){
    okP26($z->locateName($name)!==false,'Thiếu '.$name);
}
$service=(string)$z->getFromName('app/Services/UpdateService.php');
foreach([
    'public function updateChannel(): string',
    'public function setUpdateChannel(string $channel): array',
    'public function releases(int $limit = 20): array',
    'public function releaseByTag(string $tag): array',
    'public function enqueueReleaseApply(string $tag): array',
    'private function stageResolvedRelease(array $release): array',
    "version_compare($a, $b, '>')",
] as $needle){
    okP26(str_contains($service,$needle),'Payload thiếu update-channel guard: '.$needle);
}
okP26(str_contains($service,"hash_equals(strtolower($expectedHash), strtolower($actualHash))"),'Release cụ thể phải bắt buộc checksum chính xác.');

$routes=(string)$z->getFromName('routes/web.php');
foreach(["'/api/updates/releases'","'/updates/channel'","'/updates/release/apply'"] as $needle){
    okP26(str_contains($routes,$needle),'Payload thiếu route '.$needle);
}
$view=(string)$z->getFromName('app/Views/updates/index.php');
foreach(['id="update-channel-card"','id="release-history-card"','Beta / Test — thử nghiệm','Chuyển về bản này'] as $needle){
    okP26(str_contains($view,$needle),'Payload thiếu UI '.$needle);
}
$app=(string)$z->getFromName('config/app.php');
okP26(str_contains($app,"Platform V17.0.26"),'Payload chưa bump V17.0.26.');
$sw=(string)$z->getFromName('public/service-worker.js');
okP26(str_contains($sw,"tms-os-v17.0.26"),'Service Worker chưa bump V17.0.26.');
$z->close();

echo "PASS: V17.0.26 payload hỗ trợ Stable/Beta và rollback release an toàn.\n";
