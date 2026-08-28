<?php
declare(strict_types=1);

function failV1716(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1716(bool $condition, string $message): void { if (!$condition) { failV1716($message); } }
function removeV1716Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1716Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.15/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.16/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.16/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1716-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1716(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.15 hoặc V17.0.16 để mô phỏng nâng cấp.');
    $metadata = json_decode((string)file_get_contents($metadataFile), true);
    expectV1716(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.16', 'RELEASE.json phải khai báo V17.0.16.');
    expectV1716(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.16 không khớp RELEASE.json.');

    $archive = new ZipArchive();
    expectV1716($archive->open($releaseZip) === true, 'Không mở được payload V17.0.16.');
    $entries = [];
    for ($index = 0; $index < $archive->numFiles; $index++) { $entries[] = (string)$archive->getNameIndex($index); }
    $archive->close();
    foreach ($entries as $entry) { expectV1716((bool)preg_match('#^(app|config|public|routes|scripts)/#', $entry), 'Payload chỉ được chứa các source root cho phép.'); }
    expectV1716(!in_array('scripts/verify-uci-payload.sh', $entries, true), 'Payload thiết bị không được kèm verifier nội bộ.');
    expectV1716(!in_array('RELEASE.json', $entries, true) && !in_array('storage/', $entries, true), 'Payload không được kèm metadata tự tham chiếu hoặc dữ liệu runtime.');

    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    expectV1716($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.0.15.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1716-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\"\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.16.zip';
    expectV1716(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.16.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1716(!empty($result['ok']) && $service->currentVersion() === 'V17.0.16', 'UpdateService V17.0.15 không áp dụng được V17.0.16.');
    expectV1716((string)file_get_contents($target . '/storage/v1716-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1716((string)file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $updateService = (string)file_get_contents($target . '/app/Services/UpdateService.php');
    $worker = (string)file_get_contents($target . '/public/service-worker.js');
    expectV1716(str_contains($updateService, 'releases/latest/download/RELEASE.json'), 'Payload thiếu fallback GitHub Releases trực tiếp.');
    expectV1716(str_contains($worker, "const VERSION='tms-os-v17.0.16';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.15 → V17.0.16 có fallback GitHub, bảo toàn storage/Cloudflare và làm mới cache.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1716Tree($temp);
}

?>
