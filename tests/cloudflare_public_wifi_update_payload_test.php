<?php
declare(strict_types=1);

function failPublicWifiPayload(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectPublicWifiPayload(bool $condition, string $message): void { if (!$condition) { failPublicWifiPayload($message); } }
function removePublicWifiPayloadTree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removePublicWifiPayloadTree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}
function copyPublicWifiPayloadTree(string $source, string $target): void
{
    @mkdir($target, 0700, true);
    foreach (scandir($source) ?: [] as $item) {
        if ($item === '.' || $item === '..') { continue; }
        $from = $source . '/' . $item;
        $to = $target . '/' . $item;
        if (is_dir($from)) { copyPublicWifiPayloadTree($from, $to); }
        elseif (!@copy($from, $to)) { failPublicWifiPayload('Không thể chuẩn bị source V17.0.3 để kiểm thử.'); }
    }
}

$root = realpath(dirname(__DIR__));
$baseline = $root . '/.build/cloudflare-resolver-hotfix-v17.0.3/source';
$release = $root . '/.build/v17.0.4/release';
$zipSource = $release . '/TMS_OS_LATEST.zip';
$metadata = $release . '/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-public-wifi-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';

try {
    expectPublicWifiPayload(is_file($zipSource) && is_file($metadata), 'Thiếu ZIP hoặc RELEASE.json V17.0.4.');
    $releaseData = json_decode((string)file_get_contents($metadata), true);
    expectPublicWifiPayload(is_array($releaseData), 'RELEASE.json không hợp lệ.');
    expectPublicWifiPayload(($releaseData['version'] ?? '') === '17.0.4', 'RELEASE.json phải khai báo V17.0.4.');
    expectPublicWifiPayload(hash_file('sha256', $zipSource) === ($releaseData['checksum_sha256'] ?? ''), 'Checksum RELEASE.json không khớp ZIP.');

    foreach (['app', 'config', 'public', 'routes', 'scripts', 'storage'] as $part) {
        copyPublicWifiPayloadTree($baseline . '/' . $part, $target . '/' . $part);
    }
    file_put_contents($target . '/storage/public-wifi-persist.txt', 'persistent-storage-marker');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"preserve-config\",\n  \"unrelated_setting\": true\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $stagedZip = $updates . '/tms-update-v17.0.4.zip';
    expectPublicWifiPayload(copy($zipSource, $stagedZip), 'Không thể stage ZIP V17.0.4.');

    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($stagedZip));

    expectPublicWifiPayload(!empty($result['ok']), 'UpdateService V17.0.3 không áp dụng được ZIP V17.0.4.');
    expectPublicWifiPayload($service->currentVersion() === 'V17.0.4', 'Payload không đổi version thực tế sang V17.0.4.');
    expectPublicWifiPayload((string)file_get_contents($target . '/storage/public-wifi-persist.txt') === 'persistent-storage-marker', 'Update đã chạm dữ liệu storage.');
    expectPublicWifiPayload((string)file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã làm thay đổi cấu hình Cloudflare ngoài source.');
    expectPublicWifiPayload(is_dir($target . '.previous/config'), 'Không tạo vùng khôi phục source trước đó.');
    expectPublicWifiPayload(str_contains((string)file_get_contents($target . '.previous/config/app.php'), 'Platform V17.0.3'), 'Vùng khôi phục không giữ source V17.0.3.');

    expectPublicWifiPayload(str_contains((string)file_get_contents($target . '/app/Services/CloudflareDomainService.php'), 'setPublicWifiDnsCompatibility'), 'Payload thiếu dịch vụ tương thích Wi-Fi.');
    expectPublicWifiPayload(str_contains((string)file_get_contents($target . '/app/Controllers/CloudflareDomainController.php'), 'publicWifiDns'), 'Payload thiếu endpoint tương thích Wi-Fi.');
    expectPublicWifiPayload(str_contains((string)file_get_contents($target . '/public/assets/cfdomain.js'), '/api/cloudflare-domain/public-wifi-dns'), 'Payload thiếu điều khiển giao diện tương thích Wi-Fi.');
    expectPublicWifiPayload(str_contains((string)file_get_contents($target . '/routes/web.php'), '/api/cloudflare-domain/public-wifi-dns'), 'Payload thiếu route tương thích Wi-Fi.');

    echo "PASS: ZIP V17.0.4 áp dụng từ V17.0.3, đổi version thực tế và giữ nguyên cấu hình Cloudflare cùng storage.\n";
} finally {
    removePublicWifiPayloadTree($temp);
}
