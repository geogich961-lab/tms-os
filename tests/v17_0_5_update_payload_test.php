<?php
declare(strict_types=1);

function failV1705(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1705(bool $condition, string $message): void { if (!$condition) { failV1705($message); } }
function removeV1705Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1705Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.5/download/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.5/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.5/release/RELEASE.json';
$repairScript = $root . '/scripts/tms-update-repair.sh';
$temp = sys_get_temp_dir() . '/tms-v1705-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$bin = $temp . '/bin';

try {
    expectV1705(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.4 hoặc V17.0.5 để mô phỏng nâng cấp.');
    $metadata = json_decode((string) file_get_contents($metadataFile), true);
    expectV1705(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.5', 'RELEASE.json phải khai báo V17.0.5.');
    expectV1705(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.5 không khớp RELEASE.json.');

    @mkdir($target, 0700, true);
    $zip = new ZipArchive();
    expectV1705($zip->open($baseZip) === true, 'Không mở được payload V17.0.4.');
    expectV1705($zip->extractTo($target), 'Không giải nén được payload V17.0.4.');
    $zip->close();
    file_put_contents($target . '/storage/v1705-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\",\n  \"public_wifi_dns\": true\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    // Mô phỏng một lần bootstrap repair mà người dùng V17.0.4 sẽ chạy trước Update Center.
    @mkdir($bin, 0700, true);
    copy($repairScript, $target . '/scripts/tms-update-repair.sh');
    $curlStub = <<<'SH'
#!/usr/bin/env sh
set -eu
out=''
url=''
while [ "$#" -gt 0 ]; do
  case "$1" in
    -o) out=$2; shift 2 ;;
    http*) url=$1; shift ;;
    *) shift ;;
  esac
done
rel=${url#https://raw.githubusercontent.com/geogich961-lab/tms-os/}
rel=${rel#*/}
mkdir -p "$(dirname "$out")"
cat "$TMS_REPAIR_FIXTURE_ROOT/$rel" > "$out"
SH;
    file_put_contents($bin . '/curl', $curlStub);
    chmod($bin . '/curl', 0700);
    $repairCommand = 'PATH=' . escapeshellarg($bin . ':' . getenv('PATH'))
        . ' TMS_OS_TARGET=' . escapeshellarg($target)
        . ' TMS_REPAIR_FIXTURE_ROOT=' . escapeshellarg($root)
        . ' TMS_UPDATE_REPAIR_SKIP_RESTART=1 sh ' . escapeshellarg($target . '/scripts/tms-update-repair.sh') . ' --apply 2>&1';
    exec($repairCommand, $repairOutput, $repairCode);
    expectV1705($repairCode === 0, 'Bootstrap repair V17.0.4 thất bại: ' . implode("\n", $repairOutput));
    expectV1705(str_contains((string) file_get_contents($target . '/app/Services/UpdateService.php'), 'private function scheduleRestart(): bool'), 'Repair không đặt UpdateService worker mới.');

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.5.zip';
    expectV1705(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.5.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1705(!empty($result['ok']), 'UpdateService sau repair không áp dụng được V17.0.5.');
    expectV1705($service->currentVersion() === 'V17.0.5', 'Payload không đổi version thực tế sang V17.0.5.');
    expectV1705((string) file_get_contents($target . '/storage/v1705-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1705((string) file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    expectV1705(is_file($target . '/scripts/tms-update-restart.sh'), 'Payload thiếu worker restart mới.');
    expectV1705(is_file($target . '/scripts/tms-update-repair.sh'), 'Payload thiếu bootstrap repair có thể hoàn tác.');
    foreach ([
        '/app/Controllers/MarketplaceController.php',
        '/app/Controllers/AppInstallerController.php',
        '/app/Services/AppInstallerService.php',
        '/app/Views/marketplace/index.php',
        '/app/Views/apps/index.php',
        '/public/assets/marketplace.css',
        '/app/Modules/app-installer',
    ] as $removed) {
        expectV1705(!file_exists($target . $removed), 'Payload vẫn chứa thành phần Marketplace: ' . $removed);
    }

    echo "PASS: V17.0.4 repair → V17.0.5 đổi version, giữ dữ liệu/Cloudflare và loại bỏ Marketplace.\n";
} finally {
    removeV1705Tree($temp);
}
