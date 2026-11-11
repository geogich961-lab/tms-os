<?php
declare(strict_types=1);

function failP17113(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP17113(bool $value, string $message): void { if (!$value) failP17113($message); }
function removeP17113(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP17113($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.12/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.13/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.13/release/RELEASE.json';
if (!is_file($baseZip)) {
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    exec('git -C ' . escapeshellarg($root) . ' archive v17.1.12 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload), $output, $code);
    okP17113($code === 0, 'Không dựng được source V17.1.12 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php') . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip), $output, $code);
    okP17113($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.12 để mô phỏng nâng cấp.');
}
okP17113(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.13.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP17113(($meta['version'] ?? '') === '17.1.13', 'Sai version metadata.');
okP17113(($meta['channel'] ?? '') === 'stable', 'V17.1.13 phải thuộc kênh stable.');
okP17113(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP17113($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'public/assets/app.js', 'app/Services/AutoBackupService.php', 'app/Services/BackupService.php', 'app/Controllers/BackupController.php', 'app/Views/backups/index.php', 'scripts/install.sh', 'scripts/tms-manual-backup.php', 'scripts/tms-auto-backup.php'] as $name) {
    okP17113($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$svc = (string)$zip->getFromName('app/Services/BackupService.php');
okP17113(str_contains($svc, 'function downloadUrl'), 'Payload: BackupService thiếu downloadUrl.');
okP17113(str_contains($svc, '_backup_downloads'), 'Payload: downloadUrl chưa dùng thư mục liên kết tĩnh.');
okP17113(str_contains($svc, 'symlink('), 'Payload: downloadUrl chưa tạo symlink.');
$controller = (string)$zip->getFromName('app/Controllers/BackupController.php');
okP17113(str_contains($controller, 'downloadUrl($id)'), 'Payload: controller chưa ưu tiên liên kết tĩnh khi tải.');
okP17113(str_contains($controller, "header('Location: '.\$url)"), 'Payload: controller chưa chuyển hướng sang liên kết tĩnh.');
okP17113(str_contains($controller, 'readfile($p)'), 'Payload: controller mất fallback readfile.');
okP17113(str_contains($controller, 'requestManualCreate'), 'Payload: controller mất tạo backup nền của V17.1.12.');
okP17113(str_contains($controller, 'Vui lòng chọn website cần sao lưu.'), 'Payload: controller mất chốt chặn chọn website của V17.1.11.');
$auto = (string)$zip->getFromName('app/Services/AutoBackupService.php');
okP17113(str_contains($auto, 'function requestManualCreate'), 'Payload: AutoBackupService mất requestManualCreate của V17.1.12.');
okP17113(str_contains($auto, 'TMS Backup thủ công'), 'Payload: mất báo cáo Telegram của V17.1.12.');
$view = (string)$zip->getFromName('app/Views/backups/index.php');
okP17113(str_contains($view, 'Trạng thái tạo backup thủ công'), 'Payload: view mất khối trạng thái của V17.1.12.');
okP17113(str_contains($view, '<option value="">-- Chọn website --</option>'), 'Payload: view mất option trống chọn website của V17.1.11.');

$app = (string)$zip->getFromName('config/app.php');
okP17113(str_contains($app, 'Platform V17.1.13'), 'Payload chưa bump V17.1.13.');
okP17113(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.13 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP17113(str_contains($sw, 'tms-os-v17.1.13'), 'Service worker chưa bump V17.1.13.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v17113-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP17113($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.12.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.13.zip';
    okP17113(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.13.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP17113(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.13', 'UpdateService không áp dụng được V17.1.13.');
    okP17113((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP17113((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $svcAfter = (string)file_get_contents($target . '/app/Services/BackupService.php');
    okP17113(str_contains($svcAfter, 'function downloadUrl'), 'Source sau nâng cấp chưa có cơ chế tải qua liên kết tĩnh.');
    $ctlAfter = (string)file_get_contents($target . '/app/Controllers/BackupController.php');
    okP17113(str_contains($ctlAfter, 'downloadUrl($id)'), 'Controller sau nâng cấp chưa chuyển sang liên kết tĩnh.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP17113($temp);
}

echo "PASS: V17.1.12 → V17.1.13 bảo toàn storage/Cloudflare và đổi cơ chế tải backup sang liên kết tĩnh để nginx phục vụ trực tiếp (hết 502/520 với file ~1 GB).\n";
