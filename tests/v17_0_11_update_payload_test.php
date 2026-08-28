<?php
declare(strict_types=1);

function failV1711(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1711(bool $condition, string $message): void { if (!$condition) { failV1711($message); } }
function removeV1711Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1711Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.9/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.11/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.11/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1711-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1711(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.9 hoặc V17.0.11 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1711(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.11', 'RELEASE.json phải khai báo V17.0.11.');
    expectV1711(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.11 không khớp RELEASE.json.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1711($zip->open($baseZip) === true, 'Không mở được payload V17.0.9.');
    expectV1711($zip->extractTo($target), 'Không giải nén được payload V17.0.9.');
    $zip->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1711-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\",\n  \"public_wifi_dns\": true\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.11.zip';
    expectV1711(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.11.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1711(!empty($result['ok']), 'UpdateService V17.0.9 không áp dụng được V17.0.11.');
    expectV1711($service->currentVersion() === 'V17.0.11', 'Payload không đổi version thực tế sang V17.0.11.');
    expectV1711((string) file_get_contents($target . '/storage/v1711-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1711((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $updateView = (string) file_get_contents($target . '/app/Views/updates/index.php');
    expectV1711(str_contains($updateView, 'id="current-version-card"'), 'Payload phải giữ khung Phiên bản hiện tại.');
    expectV1711(str_contains($updateView, 'id="online-update-action" hidden'), 'Nút cập nhật phải ẩn trước khi kiểm tra.');
    expectV1711(str_contains($updateView, 'id="apply-github-btn">Cập nhật ngay</button>'), 'Payload phải có nút Cập nhật ngay trong khung hiện tại.');
    expectV1711(!str_contains($updateView, 'online-update-card') && !str_contains($updateView, '>Cập nhật nhanh<'), 'Payload không được tạo card Cập nhật nhanh riêng.');
    $serviceWorker = (string) file_get_contents($target . '/public/service-worker.js');
    expectV1711(str_contains($serviceWorker, "const VERSION='tms-os-v17.0.11';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.9 → V17.0.11 hiển thị nút cập nhật trong khung hiện tại và giữ storage/Cloudflare.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1711Tree($temp);
}
