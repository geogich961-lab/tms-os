<?php
declare(strict_types=1);

function failV1706(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1706(bool $condition, string $message): void { if (!$condition) { failV1706($message); } }
function removeV1706Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1706Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.6/download/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.6/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.6/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1706-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1706(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.5 hoặc V17.0.6 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1706(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.6', 'RELEASE.json phải khai báo V17.0.6.');
    expectV1706(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.6 không khớp RELEASE.json.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1706($zip->open($baseZip) === true, 'Không mở được payload V17.0.5.');
    expectV1706($zip->extractTo($target), 'Không giải nén được payload V17.0.5.');
    $zip->close();
    file_put_contents($target . '/storage/v1706-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\",\n  \"public_wifi_dns\": true\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.6.zip';
    expectV1706(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.6.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1706(!empty($result['ok']), 'UpdateService V17.0.5 không áp dụng được V17.0.6.');
    expectV1706($service->currentVersion() === 'V17.0.6', 'Payload không đổi version thực tế sang V17.0.6.');
    expectV1706((string) file_get_contents($target . '/storage/v1706-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1706((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    expectV1706(str_contains((string) file_get_contents($target . '/app/Services/UpdateService.php'), 'tms_clear_cache(false)'), 'Payload không bảo toàn session khi worker cập nhật dọn cache.');
    $updateView = (string) file_get_contents($target . '/app/Views/updates/index.php');
    expectV1706(str_contains($updateView, 'AUTH_REQUIRED') && str_contains($updateView, 'requestUpdateReauthentication();'), 'Update Center chưa phân biệt phiên hết hạn và cập nhật chưa xác nhận.');
    $authController = (string) file_get_contents($target . '/app/Controllers/AuthController.php');
    expectV1706(str_contains($updateView, 'next=%2Fupdates') && str_contains($authController, 'safeNextPath') && str_contains($authController, 'str_starts_with($path,'), 'Đăng nhập lại chưa giữ return path nội bộ tới Update Center.');

    echo "PASS: V17.0.5 → V17.0.6 đổi version, giữ storage/Cloudflare và áp dụng sửa session.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1706Tree($temp);
}
