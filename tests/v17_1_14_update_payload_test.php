<?php
declare(strict_types=1);

function failP17114(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP17114(bool $value, string $message): void { if (!$value) failP17114($message); }
function removeP17114(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP17114($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.13/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.14/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.14/release/RELEASE.json';
if (!is_file($baseZip)) {
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    exec('git -C ' . escapeshellarg($root) . ' archive v17.1.13 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload), $output, $code);
    okP17114($code === 0, 'Không dựng được source V17.1.13 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php') . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip), $output, $code);
    okP17114($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.13 để mô phỏng nâng cấp.');
}
okP17114(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.14.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP17114(($meta['version'] ?? '') === '17.1.14', 'Sai version metadata.');
okP17114(($meta['channel'] ?? '') === 'stable', 'V17.1.14 phải thuộc kênh stable.');
okP17114(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP17114($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'app/Services/OperationalAlertsService.php', 'app/Views/notifications/index.php', 'app/Controllers/NotificationController.php', 'scripts/tms-alerts-check.php', 'scripts/install.sh'] as $name) {
    okP17114($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$svc = (string)$zip->getFromName('app/Services/OperationalAlertsService.php');
okP17114(str_contains($svc, "'battery_low_percent'"), 'Payload: service thiếu cấu hình ngưỡng pin thấp.');
okP17114(str_contains($svc, "'battery_critical_percent'"), 'Payload: service thiếu cấu hình ngưỡng pin nguy kịch.');
okP17114(str_contains($svc, "'check_battery_low'"), 'Payload: service thiếu công tắc theo dõi pin thấp.');
okP17114(str_contains($svc, "'battery_critical'"), 'Payload: service thiếu cảnh báo pin nguy kịch.');
okP17114(str_contains($svc, 'plugged'), 'Payload: service chưa đọc trạng thái cắm sạc (plugged).');
okP17114(str_contains($svc, "'recovered'"), 'Payload: service thiếu cơ chế báo hồi phục.');
okP17114(str_contains($svc, "'battery_full_max_minutes'"), 'Payload: service mất cảnh báo pin đầy quá lâu của bản cũ.');
okP17114(str_contains($svc, 'sendTelegramNotification'), 'Payload: service mất kênh gửi Telegram.');
okP17114(str_contains($svc, 'tms-alerts-check'), 'Payload: service mất đăng ký cron kiểm tra.');
$view = (string)$zip->getFromName('app/Views/notifications/index.php');
okP17114(str_contains($view, 'name="battery_low_percent"'), 'Payload: view thiếu ô ngưỡng pin thấp.');
okP17114(str_contains($view, 'name="battery_critical_percent"'), 'Payload: view thiếu ô ngưỡng pin nguy kịch.');
okP17114(str_contains($view, 'name="check_battery_low"'), 'Payload: view thiếu công tắc pin thấp.');

$app = (string)$zip->getFromName('config/app.php');
okP17114(str_contains($app, 'Platform V17.1.14'), 'Payload chưa bump V17.1.14.');
okP17114(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.14 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP17114(str_contains($sw, 'tms-os-v17.1.14'), 'Service worker chưa bump V17.1.14.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v17114-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP17114($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.13.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.14.zip';
    okP17114(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.14.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP17114(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.14', 'UpdateService không áp dụng được V17.1.14.');
    okP17114((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP17114((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $svcAfter = (string)file_get_contents($target . '/app/Services/OperationalAlertsService.php');
    okP17114(str_contains($svcAfter, "'battery_critical'"), 'Source sau nâng cấp chưa có cảnh báo pin nguy kịch.');
    okP17114(str_contains($svcAfter, "'battery_low_percent'"), 'Source sau nâng cấp chưa có ngưỡng pin thấp.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP17114($temp);
}

echo "PASS: V17.1.13 → V17.1.14 bảo toàn storage/Cloudflare và bổ sung cảnh báo pin cạn khi không có điện vào + tin hồi phục cho Cảnh báo vận hành Telegram.\n";
