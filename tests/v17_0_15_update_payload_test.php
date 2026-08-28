<?php
declare(strict_types=1);

function failV1715(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1715(bool $condition, string $message): void { if (!$condition) { failV1715($message); } }
function removeV1715Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1715Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.14/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.15/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.15/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1715-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1715(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.14 hoặc V17.0.15 để mô phỏng nâng cấp.');
    $metadata = json_decode((string)file_get_contents($metadataFile), true);
    expectV1715(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.15', 'RELEASE.json phải khai báo V17.0.15.');
    expectV1715(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.15 không khớp RELEASE.json.');

    $archive = new ZipArchive();
    expectV1715($archive->open($releaseZip) === true, 'Không mở được payload V17.0.15.');
    $entries = [];
    for ($index = 0; $index < $archive->numFiles; $index++) { $entries[] = (string)$archive->getNameIndex($index); }
    $archive->close();
    foreach ($entries as $entry) { expectV1715((bool)preg_match('#^(app|config|public|routes|scripts)/#', $entry), 'Payload chỉ được chứa các source root cho phép.'); }
    expectV1715(!in_array('scripts/verify-uci-payload.sh', $entries, true), 'Payload thiết bị không được kèm verifier nội bộ.');
    expectV1715(!in_array('RELEASE.json', $entries, true) && !in_array('storage/', $entries, true), 'Payload không được kèm metadata tự tham chiếu hoặc dữ liệu runtime.');

    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    expectV1715($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.0.14.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1715-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\"\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.15.zip';
    expectV1715(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.15.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1715(!empty($result['ok']) && $service->currentVersion() === 'V17.0.15', 'UpdateService V17.0.14 không áp dụng được V17.0.15.');
    expectV1715((string)file_get_contents($target . '/storage/v1715-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1715((string)file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $core = (string)file_get_contents($target . '/scripts/tms-service-core.sh');
    $worker = (string)file_get_contents($target . '/public/service-worker.js');
    expectV1715(str_contains($core, 'ensure_php_up_for_nginx') && str_contains($core, 'không khởi động Nginx để tránh 502'), 'Payload thiếu chốt bảo vệ PHP runtime trước Nginx.');
    expectV1715(str_contains($worker, "const VERSION='tms-os-v17.0.15';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.14 → V17.0.15 chặn Nginx gây 502, bảo toàn storage/Cloudflare và làm mới cache.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1715Tree($temp);
}
