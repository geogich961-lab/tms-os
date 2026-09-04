<?php
declare(strict_types=1);

function failV17021(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV17021(bool $condition, string $message): void { if (!$condition) failV17021($message); }
function removeV17021Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') removeV17021Tree($path . '/' . $item);
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.20/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.21/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.21/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v17021-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV17021(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.20 hoặc V17.0.21.');
    $metadata = json_decode((string)file_get_contents($metadataFile), true);
    expectV17021(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.21', 'RELEASE.json phải khai báo V17.0.21.');
    expectV17021(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.21 không khớp RELEASE.json.');

    $archive = new ZipArchive();
    expectV17021($archive->open($releaseZip) === true, 'Không mở được payload V17.0.21.');
    $entries = [];
    $textExtensions = ['php', 'sh', 'js', 'css', 'json', 'html', 'htm', 'txt', 'md', 'conf', 'ini', 'xml', 'svg', 'yml', 'yaml'];
    for ($i = 0; $i < $archive->numFiles; $i++) $entries[] = (string)$archive->getNameIndex($i);
    foreach ($entries as $entry) {
        expectV17021((bool)preg_match('#^(app|config|public|routes|scripts)/#', $entry), 'Payload chứa root không được phép: ' . $entry);
        if (!str_ends_with($entry, '/') && in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $textExtensions, true)) {
            expectV17021(!str_contains((string)$archive->getFromName($entry), "\r\n"), 'Payload text còn CRLF: ' . $entry);
        }
    }
    expectV17021(!in_array('scripts/verify-uci-payload.sh', $entries, true), 'Payload không được kèm verifier nội bộ.');
    expectV17021($archive->locateName('app/Core/NginxCompatibility.php') !== false, 'Payload thiếu NginxCompatibility.php.');
    expectV17021($archive->locateName('scripts/tms-nginx-compat.php') !== false, 'Payload thiếu CLI repair Nginx.');

    $compat = (string)$archive->getFromName('app/Core/NginxCompatibility.php');
    $restart = (string)$archive->getFromName('scripts/tms-update-restart.sh');
    $index = (string)$archive->getFromName('public/index.php');
    $view = (string)$archive->getFromName('app/Views/websites/index.php');
    $worker = (string)$archive->getFromName('public/service-worker.js');
    $archive->close();

    expectV17021(str_contains($compat, 'server_names_hash_bucket_size 128;') && str_contains($compat, 'server_names_hash_max_size 4096;'), 'Payload thiếu giá trị server_names_hash mới.');
    expectV17021(str_contains($restart, 'tms-nginx-compat.php') && str_contains($restart, 'nginx -t'), 'Restart worker thiếu repair/test Nginx.');
    expectV17021(str_contains($index, 'tms_repair_nginx_server_names_hash'), 'Panel thiếu auto-repair nginx.conf.');
    expectV17021(str_contains($view, 'pattern="[A-Za-z0-9_\\-]{2,40}"'), 'View thiếu pattern tương thích Chrome mới.');
    expectV17021(str_contains($worker, "const VERSION='tms-os-v17.0.21';"), 'Service Worker chưa bump V17.0.21.');

    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    expectV17021($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.0.20.');
    $base->close();

    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\"\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.21.zip';
    expectV17021(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.21.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV17021(!empty($result['ok']) && $service->currentVersion() === 'V17.0.21', 'UpdateService V17.0.20 không áp dụng được V17.0.21.');
    expectV17021((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update làm thay đổi storage.');
    expectV17021((string)file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update làm thay đổi cấu hình Cloudflare.');

    echo "PASS: V17.0.20 → V17.0.21 bảo toàn storage/Cloudflare và chứa bản sửa Website/Nginx.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV17021Tree($temp);
}
