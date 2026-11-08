<?php
declare(strict_types=1);

function failP1716(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okP1716(bool $value, string $message): void { if (!$value) failP1716($message); }
function removeP1716(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeP1716($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.5/release/TMS_OS_LATEST.zip';
$zipFile = $root . '/.build/v17.1.6/release/TMS_OS_LATEST.zip';
$metaFile = $root . '/.build/v17.1.6/release/RELEASE.json';
if (!is_file($baseZip)) {
    // GitHub Actions chặn tải chính asset release từ job phát hành của cùng
    // repository. Dựng lại payload từ tag đã phát hành v17.1.5 để mô phỏng
    // chính xác source release trước mà không phụ thuộc mạng ngoài.
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    $archiveCommand = 'git -C ' . escapeshellarg($root)
        . ' archive v17.1.5 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload);
    exec($archiveCommand, $output, $code);
    okP1716($code === 0, 'Không dựng được source V17.1.5 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    $buildCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php')
        . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip);
    exec($buildCommand, $output, $code);
    okP1716($code === 0 && is_file($baseZip), 'Không dựng được payload V17.1.5 để mô phỏng nâng cấp.');
}
okP1716(is_file($zipFile) && is_file($metaFile), 'Thiếu artifact V17.1.6.');
$meta = json_decode((string)file_get_contents($metaFile), true);
okP1716(($meta['version'] ?? '') === '17.1.6', 'Sai version metadata.');
okP1716(($meta['channel'] ?? '') === 'stable', 'V17.1.6 phải thuộc kênh stable.');
okP1716(hash_file('sha256', $zipFile) === ($meta['checksum_sha256'] ?? ''), 'Sai checksum.');

$zip = new ZipArchive();
okP1716($zip->open($zipFile) === true, 'Không mở ZIP.');
foreach (['config/app.php', 'public/index.php', 'public/service-worker.js', 'app/Services/WebsiteService.php', 'scripts/tms-php-engine.sh'] as $name) {
    okP1716($zip->locateName($name) !== false, 'Thiếu ' . $name);
}

$website = (string)$zip->getFromName('app/Services/WebsiteService.php');
okP1716(str_contains($website, 'usesPhpHttpEngine'), 'Payload thiếu nhận diện php-http.');
okP1716(str_contains($website, 'proxy_set_header X-TMS-Root'), 'Vhost php-http phải truyền document root cho router.');
okP1716(str_contains($website, 'Không thể cập nhật tương thích PHP cho website.'), 'Payload thiếu đường chuyển an toàn cho website cũ.');
$engine = (string)$zip->getFromName('scripts/tms-php-engine.sh');
okP1716(str_contains($engine, 'PHP built-in server chỉ có một document root'), 'Router php-http chưa xử lý document root website riêng.');
okP1716(str_contains($engine, "require \$file;"), 'Router php-http phải thực thi file PHP của website.');

$app = (string)$zip->getFromName('config/app.php');
okP1716(str_contains($app, 'Platform V17.1.6'), 'Payload chưa bump V17.1.6.');
okP1716(str_contains($app, "'channel' => 'stable'"), 'Payload V17.1.6 phải thuộc kênh stable.');
$sw = (string)$zip->getFromName('public/service-worker.js');
okP1716(str_contains($sw, 'tms-os-v17.1.6'), 'Service worker chưa bump V17.1.6.');
$zip->close();

$temp = sys_get_temp_dir() . '/tms-v1716-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okP1716($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.5.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    file_put_contents($cloudflareDir . '/config.json', "{\"tunnel_id\":\"unchanged\"}\n");
    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.1.6.zip';
    okP1716(copy($zipFile, $staged), 'Không thể stage ZIP V17.1.6.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $updater = new UpdateService();
    $result = $updater->apply(basename($staged));
    okP1716(!empty($result['ok']) && $updater->currentVersion() === 'V17.1.6', 'UpdateService không áp dụng được V17.1.6.');
    okP1716((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    okP1716((string)file_get_contents($cloudflareDir . '/config.json') === "{\"tunnel_id\":\"unchanged\"}\n", 'Update đã thay đổi cấu hình Cloudflare.');
    $engineAfter = (string)file_get_contents($target . '/scripts/tms-php-engine.sh');
    okP1716(str_contains($engineAfter, 'require $file;'), 'Source sau nâng cấp chưa có router PHP website.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeP1716($temp);
}

echo "PASS: V17.1.5 → V17.1.6 bảo toàn storage/Cloudflare và sửa handler php-http cho website.\n";
