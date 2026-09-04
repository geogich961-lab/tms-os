<?php
declare(strict_types=1);

function failV17022Payload(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV17022Payload(bool $condition, string $message): void { if (!$condition) failV17022Payload($message); }
function removeV17022Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') removeV17022Tree($path . '/' . $item);
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.21/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.22/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.22/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v17022-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV17022Payload(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.21 hoặc V17.0.22.');
    $metadata = json_decode((string)file_get_contents($metadataFile), true);
    expectV17022Payload(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.22', 'RELEASE.json phải khai báo V17.0.22.');
    expectV17022Payload(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.22 không khớp.');

    $archive = new ZipArchive();
    expectV17022Payload($archive->open($releaseZip) === true, 'Không mở được payload V17.0.22.');
    $entries = [];
    for ($i = 0; $i < $archive->numFiles; $i++) $entries[] = (string)$archive->getNameIndex($i);
    foreach ($entries as $entry) {
        expectV17022Payload((bool)preg_match('#^(app|config|public|routes|scripts)/#', $entry), 'Payload chứa root không được phép: ' . $entry);
        $data = (string)$archive->getFromName($entry);
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (in_array($ext, ['php','js','css','json','sh','md','txt','conf','html'], true)) {
            expectV17022Payload(!str_contains($data, "\r\n"), 'Payload text còn CRLF: ' . $entry);
        }
    }
    expectV17022Payload($archive->locateName('config/app.php') !== false, 'Payload thiếu config/app.php.');
    expectV17022Payload($archive->locateName('public/index.php') !== false, 'Payload thiếu public/index.php.');
    expectV17022Payload($archive->locateName('scripts/install.sh') !== false, 'Payload thiếu scripts/install.sh.');
    $index = (string)$archive->getFromName('public/index.php');
    $restart = (string)$archive->getFromName('scripts/tms-update-restart.sh');
    $worker = (string)$archive->getFromName('public/service-worker.js');
    $archive->close();

    expectV17022Payload(str_contains($index, "'/files/upload-chunk'") && str_contains($index, "'/files/upload-complete'"), 'Payload thiếu chunk upload routes.');
    expectV17022Payload(strpos($restart, 'if panel_ok; then') < strpos($restart, 'tms-php-engine.sh\" restart'), 'Update worker phải kiểm tra panel trước khi restart PHP.');
    expectV17022Payload(str_contains($restart, 'ensure_tunnel'), 'Update worker thiếu Cloudflare continuity guard.');
    expectV17022Payload(str_contains($worker, "const VERSION='tms-os-v17.0.22';"), 'Service Worker chưa bump V17.0.22.');

    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    expectV17022Payload($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.0.21.');
    $base->close();

    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\"\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.22.zip';
    expectV17022Payload(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.22.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV17022Payload(!empty($result['ok']) && $service->currentVersion() === 'V17.0.22', 'UpdateService V17.0.21 không áp dụng được V17.0.22.');
    expectV17022Payload((string)file_get_contents($target . '/storage/persistent.txt') === 'storage-must-survive', 'Update làm thay đổi storage.');
    expectV17022Payload((string)file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update làm thay đổi cấu hình Cloudflare.');

    echo "PASS: V17.0.21 → V17.0.22 giữ storage/Cloudflare và chứa bản sửa upload/Update Center.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV17022Tree($temp);
}
