<?php
declare(strict_types=1);

function failV1707(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1707(bool $condition, string $message): void { if (!$condition) { failV1707($message); } }
function removeV1707Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1707Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.6/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.7/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.7/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1707-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1707(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.6 hoặc V17.0.7 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1707(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.7', 'RELEASE.json phải khai báo V17.0.7.');
    expectV1707(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.7 không khớp RELEASE.json.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1707($zip->open($baseZip) === true, 'Không mở được payload V17.0.6.');
    expectV1707($zip->extractTo($target), 'Không giải nén được payload V17.0.6.');
    $zip->close();
    file_put_contents($target . '/storage/v1707-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\",\n  \"public_wifi_dns\": true\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.7.zip';
    expectV1707(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.7.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1707(!empty($result['ok']), 'UpdateService V17.0.6 không áp dụng được V17.0.7.');
    expectV1707($service->currentVersion() === 'V17.0.7', 'Payload không đổi version thực tế sang V17.0.7.');
    expectV1707((string) file_get_contents($target . '/storage/v1707-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1707((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $login = (string) file_get_contents($target . '/app/Views/auth/login.php');
    expectV1707(str_contains($login, "require dirname(__DIR__) . '/layouts/header.php';"), 'Payload chưa nạp layout header cho trang đăng nhập.');
    expectV1707(str_contains($login, "require dirname(__DIR__) . '/layouts/footer.php';"), 'Payload chưa nạp layout footer cho trang đăng nhập.');
    $worker = (string) file_get_contents($target . '/public/service-worker.js');
    expectV1707(str_contains($worker, "const VERSION='tms-os-v17.0.7';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.6 → V17.0.7 giữ storage/Cloudflare và nạp layout login sau re-authentication.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1707Tree($temp);
}
