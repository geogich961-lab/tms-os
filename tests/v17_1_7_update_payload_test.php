<?php
declare(strict_types=1);

function failP1717(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP1717(bool $value, string $message): void { if (!$value) failP1717($message); }
function removeP1717(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP1717($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.6/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.7/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.7/release/RELEASE.json';
if (!is_file($baseZip)) {
    // GitHub Actions chặn tải chính asset release từ job phát hành của cùng
    // repository. Dựng lại payload từ tag đã phát hành v17.1.6 để mô phỏng
    // chính xác source release trước mà không phụ thuộc mạng ngoài.
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    $archiveCommand = 'git -C ' . escapeshellarg($root)
        . ' archive v17.1.6 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload);
    exec($archiveCommand, $output, $code);
    okP1717($code === 0, 'Không dựng được source V17.1.6 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    $buildCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php')
        . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip);
    exec($buildCommand, $output, $code);
    okP1717($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.6 để mô phỏng nâng cấp.');
}
okP1717(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.7.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP1717(($meta['version'] ?? '') === '17.1.7', 'Sai version metadata.');
okP1717(($meta['channel'] ?? '') === 'stable', 'V17.1.7 phải thuộc kênh stable.');
okP1717(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP1717($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'scripts/tms-php-engine.sh', 'scripts/tms-watchdog.sh', 'scripts/tms-guardian.sh'] as $name) {
    okP1717($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$engine = (string)$zip->getFromName('scripts/tms-php-engine.sh');
okP1717(str_contains($engine, 'PHP_FCGI_CHILDREN="$cgi_children"'), 'Payload thiếu pool PHP_FCGI_CHILDREN cho php-cgi.');
okP1717(str_contains($engine, 'PHP_FCGI_MAX_REQUESTS=0'), 'Payload thiếu tắt giới hạn tự thoát 500 request của php-cgi.');
$watchdog = (string)$zip->getFromName('scripts/tms-watchdog.sh');
okP1717(str_contains($watchdog, 'repair_count_get'), 'Payload thiếu đếm hạn mức tự sửa theo dịch vụ ở watchdog.');
$guardian = (string)$zip->getFromName('scripts/tms-guardian.sh');
okP1717(str_contains($guardian, 'repair_allowed php'), 'Payload thiếu hạn mức theo dịch vụ php ở Guardian.');
okP1717(str_contains($guardian, 'repair_allowed tunnel'), 'Payload thiếu hạn mức theo dịch vụ tunnel ở Guardian.');

$app = (string)$zip->getFromName('config/app.php');
okP1717(str_contains($app, 'Platform V17.1.7'), 'Payload chưa bump V17.1.7.');
okP1717(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.7 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP1717(str_contains($sw, 'tms-os-v17.1.7'), 'Service worker chưa bump V17.1.7.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v1717-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP1717($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.6.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.7.zip';
    okP1717(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.7.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP1717(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.7', 'UpdateService không áp dụng được V17.1.7.');
    okP1717((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP1717((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $engineAfter = (string)file_get_contents($target . '/scripts/tms-php-engine.sh');
    okP1717(str_contains($engineAfter, 'PHP_FCGI_MAX_REQUESTS=0'), 'Source sau nâng cấp chưa có vá php-cgi.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP1717($temp);
}

echo "PASS: V17.1.6 → V17.1.7 bảo toàn storage/Cloudflare và vá php-cgi pool + hạn mức tự sửa theo dịch vụ.\n";
