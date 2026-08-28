<?php
declare(strict_types=1);

function failV1713(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1713(bool $condition, string $message): void { if (!$condition) { failV1713($message); } }
function removeV1713Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1713Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.12/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.13/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.13/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1713-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1713(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.12 hoặc V17.0.13 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1713(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.13', 'RELEASE.json phải khai báo V17.0.13.');
    expectV1713(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.13 không khớp RELEASE.json.');

    $releaseArchive = new ZipArchive();
    expectV1713($releaseArchive->open($releaseZip) === true, 'Không mở được payload V17.0.13.');
    $releaseEntries = [];
    for ($index = 0; $index < $releaseArchive->numFiles; $index++) { $releaseEntries[] = (string) $releaseArchive->getNameIndex($index); }
    $releaseArchive->close();
    expectV1713(!in_array('scripts/verify-uci-payload.sh', $releaseEntries, true), 'Payload thiết bị không được kèm script xác minh nội bộ.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1713($zip->open($baseZip) === true && $zip->extractTo($target), 'Không giải nén được payload V17.0.12.');
    $zip->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1713-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\"\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.13.zip';
    expectV1713(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.13.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1713(!empty($result['ok']) && $service->currentVersion() === 'V17.0.13', 'UpdateService V17.0.12 không áp dụng được V17.0.13.');
    expectV1713((string) file_get_contents($target . '/storage/v1713-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1713((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $view = (string) file_get_contents($target . '/app/Views/updates/index.php');
    expectV1713(str_contains($view, 'id="current-version-card"') && str_contains($view, 'id="online-update-action" hidden'), 'Khung cập nhật phải nằm trong Phiên bản hiện tại và ẩn trước khi kiểm tra.');
    expectV1713(str_contains($view, '<button type="submit" class="btn btn-primary" id="apply-github-btn">Cập nhật ngay</button>'), 'Nút Cập nhật ngay phải là submit chuẩn để có dự phòng không cần JavaScript.');
    expectV1713(str_contains($view, "document.getElementById('github-update-form')?.addEventListener('submit', function(event)"), 'Form cập nhật phải xử lý submit thay vì phụ thuộc riêng vào click.');
    expectV1713(!str_contains($view, 'online-update-card') && !str_contains($view, '>Cập nhật nhanh<'), 'Payload không được tạo card Cập nhật nhanh riêng.');
    $serviceCode = (string) file_get_contents($target . '/app/Services/UpdateService.php');
    expectV1713(str_contains($serviceCode, 'private function uploadErrorMessage(int $error): string'), 'Payload cần có chẩn đoán upload an toàn.');
    $serviceWorker = (string) file_get_contents($target . '/public/service-worker.js');
    expectV1713(str_contains($serviceWorker, "const VERSION='tms-os-v17.0.13';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.12 → V17.0.13 giữ đường cập nhật submit chuẩn, chẩn đoán upload và bảo toàn storage/Cloudflare.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1713Tree($temp);
}
