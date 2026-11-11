<?php
declare(strict_types=1);

function failP17112(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP17112(bool $value, string $message): void { if (!$value) failP17112($message); }
function removeP17112(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP17112($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.11/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.12/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.12/release/RELEASE.json';
if (!is_file($baseZip)) {
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    exec('git -C ' . escapeshellarg($root) . ' archive v17.1.11 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload), $output, $code);
    okP17112($code === 0, 'Không dựng được source V17.1.11 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php') . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip), $output, $code);
    okP17112($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.11 để mô phỏng nâng cấp.');
}
okP17112(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.12.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP17112(($meta['version'] ?? '') === '17.1.12', 'Sai version metadata.');
okP17112(($meta['channel'] ?? '') === 'stable', 'V17.1.12 phải thuộc kênh stable.');
okP17112(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP17112($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'public/assets/app.js', 'app/Services/AutoBackupService.php', 'app/Services/BackupService.php', 'app/Controllers/BackupController.php', 'app/Views/backups/index.php', 'scripts/install.sh', 'scripts/tms-manual-backup.php', 'scripts/tms-auto-backup.php'] as $name) {
    okP17112($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$auto = (string)$zip->getFromName('app/Services/AutoBackupService.php');
okP17112(str_contains($auto, 'function requestManualCreate'), 'Payload: AutoBackupService thiếu requestManualCreate.');
okP17112(str_contains($auto, 'function runManualJob'), 'Payload: AutoBackupService thiếu runManualJob.');
okP17112(str_contains($auto, 'function manualStatus'), 'Payload: AutoBackupService thiếu manualStatus.');
okP17112(str_contains($auto, 'TMS Backup thủ công'), 'Payload: thiếu báo cáo Telegram cho backup thủ công.');
$worker = (string)$zip->getFromName('scripts/tms-manual-backup.php');
okP17112(str_contains($worker, 'runManualJob'), 'Payload: worker thủ công chưa gọi runManualJob.');
$controller = (string)$zip->getFromName('app/Controllers/BackupController.php');
okP17112(str_contains($controller, 'requestManualCreate'), 'Payload: controller chưa chuyển sang tạo backup nền.');
okP17112(str_contains($controller, 'Vui lòng chọn website cần sao lưu.'), 'Payload: controller mất chốt chặn chọn website của V17.1.11.');
$view = (string)$zip->getFromName('app/Views/backups/index.php');
okP17112(str_contains($view, 'Trạng thái tạo backup thủ công'), 'Payload: view thiếu khối trạng thái tạo backup thủ công.');
okP17112(str_contains($view, '<option value="">-- Chọn website --</option>'), 'Payload: view mất option trống chọn website của V17.1.11.');
$appJs = (string)$zip->getFromName('public/assets/app.js');
okP17112(str_contains($appJs, "querySelectorAll('[data-backup-scope]')"), 'Payload: app.js mất logic chọn website của V17.1.11.');

$app = (string)$zip->getFromName('config/app.php');
okP17112(str_contains($app, 'Platform V17.1.12'), 'Payload chưa bump V17.1.12.');
okP17112(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.12 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP17112(str_contains($sw, 'tms-os-v17.1.12'), 'Service worker chưa bump V17.1.12.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v17112-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP17112($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.11.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.12.zip';
    okP17112(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.12.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP17112(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.12', 'UpdateService không áp dụng được V17.1.12.');
    okP17112((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP17112((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $autoAfter = (string)file_get_contents($target . '/app/Services/AutoBackupService.php');
    okP17112(str_contains($autoAfter, 'function requestManualCreate'), 'Source sau nâng cấp chưa có tạo backup nền.');
    okP17112(is_file($target . '/scripts/tms-manual-backup.php'), 'Source sau nâng cấp thiếu worker tạo backup thủ công.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP17112($temp);
}

echo "PASS: V17.1.11 → V17.1.12 bảo toàn storage/Cloudflare và chuyển tạo backup thủ công sang chạy nền + báo cáo Telegram khi xong (hết 524 với website nặng).\n";
