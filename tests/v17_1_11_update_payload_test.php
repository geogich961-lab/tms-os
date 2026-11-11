<?php
declare(strict_types=1);

function failP17111(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP17111(bool $value, string $message): void { if (!$value) failP17111($message); }
function removeP17111(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP17111($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.10/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.11/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.11/release/RELEASE.json';
if (!is_file($baseZip)) {
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    exec('git -C ' . escapeshellarg($root) . ' archive v17.1.10 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload), $output, $code);
    okP17111($code === 0, 'Không dựng được source V17.1.10 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php') . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip), $output, $code);
    okP17111($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.10 để mô phỏng nâng cấp.');
}
okP17111(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.11.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP17111(($meta['version'] ?? '') === '17.1.11', 'Sai version metadata.');
okP17111(($meta['channel'] ?? '') === 'stable', 'V17.1.11 phải thuộc kênh stable.');
okP17111(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP17111($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'public/assets/app.js', 'app/Views/backups/index.php', 'app/Controllers/BackupController.php', 'scripts/install.sh'] as $name) {
    okP17111($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$appJs = (string)$zip->getFromName('public/assets/app.js');
okP17111(str_contains($appJs, "querySelectorAll('[data-backup-scope]')"), 'Payload: app.js thiếu logic bật ô chọn website backup.');
okP17111(str_contains($appJs, "scopeSelect.value !== 'website'"), 'Payload: app.js thiếu điều kiện ẩn/hiện ô website theo phạm vi.');
$view = (string)$zip->getFromName('app/Views/backups/index.php');
okP17111(str_contains($view, '<option value="">-- Chọn website --</option>'), 'Payload: view backup thiếu option trống chọn website.');
$controller = (string)$zip->getFromName('app/Controllers/BackupController.php');
okP17111(str_contains($controller, 'Vui lòng chọn website cần sao lưu.'), 'Payload: controller chưa chặn tạo backup website khi chưa chọn.');

$app = (string)$zip->getFromName('config/app.php');
okP17111(str_contains($app, 'Platform V17.1.11'), 'Payload chưa bump V17.1.11.');
okP17111(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.11 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP17111(str_contains($sw, 'tms-os-v17.1.11'), 'Service worker chưa bump V17.1.11.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v17111-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP17111($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.10.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.11.zip';
    okP17111(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.11.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP17111(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.11', 'UpdateService không áp dụng được V17.1.11.');
    okP17111((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP17111((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $appJsAfter = (string)file_get_contents($target . '/public/assets/app.js');
    okP17111(str_contains($appJsAfter, "querySelectorAll('[data-backup-scope]')"), 'Source sau nâng cấp chưa có logic chọn website backup.');
    $viewAfter = (string)file_get_contents($target . '/app/Views/backups/index.php');
    okP17111(str_contains($viewAfter, '<option value="">-- Chọn website --</option>'), 'Source sau nâng cấp chưa có option trống chọn website.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP17111($temp);
}

echo "PASS: V17.1.10 → V17.1.11 bảo toàn storage/Cloudflare và vá dứt lỗi không chọn được website khi tạo backup (ô chọn website hiện đúng theo phạm vi + chặn tạo khi chưa chọn).\n";
