<?php
declare(strict_types=1);

function failP1718(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP1718(bool $value, string $message): void { if (!$value) failP1718($message); }
function removeP1718(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP1718($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.7/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.8/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.8/release/RELEASE.json';
if (!is_file($baseZip)) {
    // Dựng lại payload từ tag đã phát hành v17.1.7 để mô phỏng nâng cấp.
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    $archiveCommand = 'git -C ' . escapeshellarg($root)
        . ' archive v17.1.7 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload);
    exec($archiveCommand, $output, $code);
    okP1718($code === 0, 'Không dựng được source V17.1.7 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    $buildCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php')
        . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip);
    exec($buildCommand, $output, $code);
    okP1718($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.7 để mô phỏng nâng cấp.');
}
okP1718(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.8.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP1718(($meta['version'] ?? '') === '17.1.8', 'Sai version metadata.');
okP1718(($meta['channel'] ?? '') === 'stable', 'V17.1.8 phải thuộc kênh stable.');
okP1718(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP1718($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'app/Views/updates/index.php'] as $name) {
    okP1718($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$view = (string)$zip->getFromName('app/Views/updates/index.php');
okP1718(str_contains($view, 'class="package-checkbox" form="batch-delete-form"'), 'Payload thiếu liên kết form= cho checkbox gói.');
$batchStart = strpos($view, '<form id="batch-delete-form"');
okP1718($batchStart !== false, 'Payload thiếu form batch-delete-form.');
$batchEnd = strpos($view, '</form>', (int)$batchStart);
$batchSpan = substr($view, (int)$batchStart, (int)$batchEnd - (int)$batchStart);
okP1718(!str_contains($batchSpan, '<table') && !str_contains($batchSpan, 'staged/apply'), 'Payload còn lỗi form lồng nhau ở Gói đã lưu trữ.');
okP1718(str_contains($view, 'action="/updates/staged/apply"'), 'Payload thiếu form Áp dụng gói.');

$app = (string)$zip->getFromName('config/app.php');
okP1718(str_contains($app, 'Platform V17.1.8'), 'Payload chưa bump V17.1.8.');
okP1718(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.8 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP1718(str_contains($sw, 'tms-os-v17.1.8'), 'Service worker chưa bump V17.1.8.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v1718-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP1718($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.7.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.8.zip';
    okP1718(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.8.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP1718(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.8', 'UpdateService không áp dụng được V17.1.8.');
    okP1718((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP1718((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $viewAfter = (string)file_get_contents($target . '/app/Views/updates/index.php');
    okP1718(str_contains($viewAfter, 'form="batch-delete-form"'), 'Source sau nâng cấp chưa có vá form Gói đã lưu trữ.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP1718($temp);
}

echo "PASS: V17.1.7 → V17.1.8 bảo toàn storage/Cloudflare và sửa form lồng nhau ở Gói đã lưu trữ.\n";
