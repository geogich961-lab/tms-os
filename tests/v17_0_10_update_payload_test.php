<?php
declare(strict_types=1);

function failV1710(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1710(bool $condition, string $message): void { if (!$condition) { failV1710($message); } }
function removeV1710Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1710Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.9/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.10/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.10/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1710-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1710(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.9 hoặc V17.0.10 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1710(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.10', 'RELEASE.json phải khai báo V17.0.10.');
    expectV1710(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.10 không khớp RELEASE.json.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1710($zip->open($baseZip) === true, 'Không mở được payload V17.0.9.');
    expectV1710($zip->extractTo($target), 'Không giải nén được payload V17.0.9.');
    $zip->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1710-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\",\n  \"public_wifi_dns\": true\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.10.zip';
    expectV1710(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.10.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1710(!empty($result['ok']), 'UpdateService V17.0.9 không áp dụng được V17.0.10.');
    expectV1710($service->currentVersion() === 'V17.0.10', 'Payload không đổi version thực tế sang V17.0.10.');
    expectV1710((string) file_get_contents($target . '/storage/v1710-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1710((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $reportService = (string) file_get_contents($target . '/app/Services/AccessReportService.php');
    expectV1710(str_contains($reportService, 'Asia/Ho_Chi_Minh'), 'Payload phải định dạng mốc báo cáo theo Asia/Ho_Chi_Minh.');
    $serviceWorker = (string) file_get_contents($target . '/public/service-worker.js');
    expectV1710(str_contains($serviceWorker, "const VERSION='tms-os-v17.0.10';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.9 → V17.0.10 giữ storage/Cloudflare và dùng mốc giờ Việt Nam cho báo cáo Telegram.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1710Tree($temp);
}
