<?php
declare(strict_types=1);

function failP1719(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP1719(bool $value, string $message): void { if (!$value) failP1719($message); }
function removeP1719(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP1719($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.8/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.9/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.9/release/RELEASE.json';
if (!is_file($baseZip)) {
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    exec('git -C ' . escapeshellarg($root) . ' archive v17.1.8 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload), $output, $code);
    okP1719($code === 0, 'Không dựng được source V17.1.8 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php') . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip), $output, $code);
    okP1719($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.8 để mô phỏng nâng cấp.');
}
okP1719(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.9.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP1719(($meta['version'] ?? '') === '17.1.9', 'Sai version metadata.');
okP1719(($meta['channel'] ?? '') === 'stable', 'V17.1.9 phải thuộc kênh stable.');
okP1719(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP1719($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'app/Services/UpdateService.php'] as $name) {
    okP1719($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$svc = (string)$zip->getFromName('app/Services/UpdateService.php');
okP1719(str_contains($svc, 'httpGetWithFallback'), 'Payload thiếu cơ chế tải dự phòng httpGetWithFallback.');
okP1719(str_contains($svc, 'zip_api_url'), 'Payload thiếu trường zip_api_url (đường API asset).');
okP1719(str_contains($svc, 'application/octet-stream'), 'Payload thiếu quy tắc Accept: application/octet-stream cho API asset.');

$app = (string)$zip->getFromName('config/app.php');
okP1719(str_contains($app, 'Platform V17.1.9'), 'Payload chưa bump V17.1.9.');
okP1719(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.9 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP1719(str_contains($sw, 'tms-os-v17.1.9'), 'Service worker chưa bump V17.1.9.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v1719-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP1719($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.8.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.9.zip';
    okP1719(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.9.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP1719(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.9', 'UpdateService không áp dụng được V17.1.9.');
    okP1719((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP1719((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $svcAfter = (string)file_get_contents($target . '/app/Services/UpdateService.php');
    okP1719(str_contains($svcAfter, 'httpGetWithFallback'), 'Source sau nâng cấp chưa có cơ chế tải dự phòng.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP1719($temp);
}

echo "PASS: V17.1.8 → V17.1.9 bảo toàn storage/Cloudflare và có đường tải dự phòng qua GitHub API.\n";
