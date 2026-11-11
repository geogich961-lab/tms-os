<?php
declare(strict_types=1);

function failP17110(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP17110(bool $value, string $message): void { if (!$value) failP17110($message); }
function removeP17110(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP17110($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.9/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.10/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.10/release/RELEASE.json';
if (!is_file($baseZip)) {
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    exec('git -C ' . escapeshellarg($root) . ' archive v17.1.9 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload), $output, $code);
    okP17110($code === 0, 'Không dựng được source V17.1.9 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php') . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip), $output, $code);
    okP17110($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.9 để mô phỏng nâng cấp.');
}
okP17110(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.10.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP17110(($meta['version'] ?? '') === '17.1.10', 'Sai version metadata.');
okP17110(($meta['channel'] ?? '') === 'stable', 'V17.1.10 phải thuộc kênh stable.');
okP17110(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP17110($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'public/assets/app.js', 'app/Core/NginxCompatibility.php', 'app/Views/layouts/footer.php', 'scripts/install.sh', 'scripts/tms-nginx-compat.php'] as $name) {
    okP17110($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$compat = (string)$zip->getFromName('app/Core/NginxCompatibility.php');
okP17110(str_contains($compat, 'tms_repair_nginx_service_worker_cache'), 'Payload thiếu hàm repair cache service worker.');
okP17110(str_contains($compat, 'location = /service-worker.js'), 'Hàm repair phải chèn location service worker.');
$cli = (string)$zip->getFromName('scripts/tms-nginx-compat.php');
okP17110(str_contains($cli, 'tms_repair_nginx_service_worker_cache'), 'Payload: CLI compat chưa chạy repair service worker.');
$install = (string)$zip->getFromName('scripts/install.sh');
$listenPos = strpos($install, 'listen 127.0.0.1:8888');
okP17110($listenPos !== false && strpos($install, 'location = /service-worker.js', $listenPos) !== false, 'Payload: install.sh thiếu location service worker trong block panel.');
$appJs = (string)$zip->getFromName('public/assets/app.js');
okP17110(str_contains($appJs, 'tmsPwaVersionNewer'), 'Payload: app.js thiếu logic so phiên bản SW.');
okP17110(str_contains($appJs, 'tms-pwa-dismissed'), 'Payload: app.js thiếu ghi nhớ Bỏ qua theo phiên bản.');
$footer = (string)$zip->getFromName('app/Views/layouts/footer.php');
okP17110(str_contains($footer, 'data-pwa-dismiss'), 'Payload: banner thiếu nút Bỏ qua.');

$app = (string)$zip->getFromName('config/app.php');
okP17110(str_contains($app, 'Platform V17.1.10'), 'Payload chưa bump V17.1.10.');
okP17110(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.10 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP17110(str_contains($sw, 'tms-os-v17.1.10'), 'Service worker chưa bump V17.1.10.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v17110-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP17110($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.9.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.10.zip';
    okP17110(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.10.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP17110(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.10', 'UpdateService không áp dụng được V17.1.10.');
    okP17110((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP17110((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $compatAfter = (string)file_get_contents($target . '/app/Core/NginxCompatibility.php');
    okP17110(str_contains($compatAfter, 'tms_repair_nginx_service_worker_cache'), 'Source sau nâng cấp chưa có hàm repair cache service worker.');
    $appJsAfter = (string)file_get_contents($target . '/public/assets/app.js');
    okP17110(str_contains($appJsAfter, 'tmsPwaVersionNewer'), 'Source sau nâng cấp chưa có logic banner mới.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP17110($temp);
}

echo "PASS: V17.1.9 → V17.1.10 bảo toàn storage/Cloudflare và vá dứt banner PWA hiện lặp (no-cache cho service worker + banner chỉ báo bản mới thật).\n";
