<?php
declare(strict_types=1);

function failV1708(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1708(bool $condition, string $message): void { if (!$condition) { failV1708($message); } }
function removeV1708Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1708Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.7/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.8/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.8/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1708-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1708(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.7 hoặc V17.0.8 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1708(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.8', 'RELEASE.json phải khai báo V17.0.8.');
    expectV1708(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.8 không khớp RELEASE.json.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1708($zip->open($baseZip) === true, 'Không mở được payload V17.0.7.');
    expectV1708($zip->extractTo($target), 'Không giải nén được payload V17.0.7.');
    $zip->close();
    file_put_contents($target . '/storage/v1708-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\",\n  \"public_wifi_dns\": true\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.8.zip';
    expectV1708(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.8.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1708(!empty($result['ok']), 'UpdateService V17.0.7 không áp dụng được V17.0.8.');
    expectV1708($service->currentVersion() === 'V17.0.8', 'Payload không đổi version thực tế sang V17.0.8.');
    expectV1708((string) file_get_contents($target . '/storage/v1708-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1708((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $worker = (string) file_get_contents($target . '/scripts/tms-update-restart.sh');
    expectV1708(str_contains($worker, 'tms-php-engine.sh" restart'), 'Payload chưa restart PHP engine hẹp sau source swap.');
    expectV1708(!str_contains($worker, 'bash "$SCRIPT_DIR/start-tms.sh"'), 'Payload không được restart Nginx và Cloudflare Tunnel khi update.');
    $view = (string) file_get_contents($target . '/app/Views/updates/index.php');
    expectV1708(str_contains($view, 'Đang kiểm tra phiên bản thực tế sau khi khởi động lại...'), 'Payload chưa xác minh source sau trạng thái restarting kéo dài.');
    expectV1708(!str_contains($view, 'Chưa xác nhận cập nhật:'), 'Payload vẫn dùng cảnh báo false-negative cũ.');
    $serviceWorker = (string) file_get_contents($target . '/public/service-worker.js');
    expectV1708(str_contains($serviceWorker, "const VERSION='tms-os-v17.0.8';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.7 → V17.0.8 giữ storage/Cloudflare và xác minh restart không phụ thuộc Cloudflare.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1708Tree($temp);
}
