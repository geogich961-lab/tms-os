<?php
declare(strict_types=1);

function failV1709(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1709(bool $condition, string $message): void { if (!$condition) { failV1709($message); } }
function removeV1709Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1709Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.8/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.9/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.9/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1709-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1709(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.8 hoặc V17.0.9 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1709(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.9', 'RELEASE.json phải khai báo V17.0.9.');
    expectV1709(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.9 không khớp RELEASE.json.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1709($zip->open($baseZip) === true, 'Không mở được payload V17.0.8.');
    expectV1709($zip->extractTo($target), 'Không giải nén được payload V17.0.8.');
    $zip->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1709-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\",\n  \"public_wifi_dns\": true\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.9.zip';
    expectV1709(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.9.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1709(!empty($result['ok']), 'UpdateService V17.0.8 không áp dụng được V17.0.9.');
    expectV1709($service->currentVersion() === 'V17.0.9', 'Payload không đổi version thực tế sang V17.0.9.');
    expectV1709((string) file_get_contents($target . '/storage/v1709-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1709((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $view = (string) file_get_contents($target . '/app/Views/updates/index.php');
    expectV1709(str_contains($view, 'id="online-update-card" hidden'), 'Khối Cập nhật nhanh phải ẩn khi vừa mở Update Center.');
    expectV1709(str_contains($view, 'function setOnlineUpdateVisibility(visible)'), 'Payload thiếu điều khiển ẩn/hiện khối cập nhật.');
    expectV1709(str_contains($view, 'showAvailableUpdate(d.available);'), 'Payload chỉ được hiện khối cập nhật sau khi phát hiện release mới.');
    expectV1709(str_contains($view, "setOnlineUpdateVisibility(false);"), 'Payload phải ẩn lại Cập nhật nhanh khi kiểm tra không có release mới.');
    $serviceWorker = (string) file_get_contents($target . '/public/service-worker.js');
    expectV1709(str_contains($serviceWorker, "const VERSION='tms-os-v17.0.9';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.8 → V17.0.9 giữ storage/Cloudflare và ẩn Cập nhật nhanh cho đến khi có release mới.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1709Tree($temp);
}
