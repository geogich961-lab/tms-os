<?php
declare(strict_types=1);

function failV1712(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1712(bool $condition, string $message): void { if (!$condition) { failV1712($message); } }
function removeV1712Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1712Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.9/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.12/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.12/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1712-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1712(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.9 hoặc V17.0.12 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1712(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.12', 'RELEASE.json phải khai báo V17.0.12.');
    expectV1712(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.12 không khớp RELEASE.json.');

    $releaseArchive = new ZipArchive();
    expectV1712($releaseArchive->open($releaseZip) === true, 'Không mở được payload V17.0.12.');
    $releaseEntries = [];
    for ($index = 0; $index < $releaseArchive->numFiles; $index++) { $releaseEntries[] = (string) $releaseArchive->getNameIndex($index); }
    $releaseArchive->close();
    expectV1712(!in_array('scripts/verify-uci-payload.sh', $releaseEntries, true), 'Payload thiết bị không được kèm script xác minh nội bộ.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1712($zip->open($baseZip) === true && $zip->extractTo($target), 'Không giải nén được payload V17.0.9.');
    $zip->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1712-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\"\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.12.zip';
    expectV1712(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.12.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1712(!empty($result['ok']) && $service->currentVersion() === 'V17.0.12', 'UpdateService V17.0.9 không áp dụng được V17.0.12.');
    expectV1712((string) file_get_contents($target . '/storage/v1712-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1712((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $view = (string) file_get_contents($target . '/app/Views/updates/index.php');
    expectV1712(str_contains($view, 'id="current-version-card"') && str_contains($view, 'id="online-update-action" hidden'), 'Khung cập nhật phải nằm trong Phiên bản hiện tại và ẩn trước khi kiểm tra.');
    expectV1712(str_contains($view, 'name="csrf" value="<?=tms_h($csrf)?>"><button type="button" class="btn btn-primary" id="apply-github-btn">Cập nhật ngay</button>'), 'Nút Cập nhật ngay phải nằm sau trường CSRF đã đóng đúng.');
    expectV1712(!str_contains($view, 'name="csrf" value="<?=tms_h($csrf)?><button') && !str_contains($view, 'online-update-card') && !str_contains($view, '>Cập nhật nhanh<'), 'Payload không được có markup CSRF lỗi hoặc card Cập nhật nhanh riêng.');
    $serviceWorker = (string) file_get_contents($target . '/public/service-worker.js');
    expectV1712(str_contains($serviceWorker, "const VERSION='tms-os-v17.0.12';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.9 → V17.0.12 có nút cập nhật hợp lệ trong khung hiện tại và giữ storage/Cloudflare.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1712Tree($temp);
}
