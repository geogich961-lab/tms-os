<?php
declare(strict_types=1);

// V17.1.9 — Test hồi quy sự cố 08/11/2026: đường tải gói qua github.com
// (browser_download_url) trả HTTP 504 diện rộng trong khi api.github.com
// vẫn thông, khiến Update Center không cập nhật được dù release hoàn toàn
// bình thường. UpdateService nay phải tự chuyển sang đường API asset
// (api.github.com/repos/.../releases/assets/<id>, Accept: octet-stream)
// cho cả file ZIP lẫn RELEASE.json.
//
// Kịch bản test dùng httpClient giả lập đúng sự cố: mọi URL github.com
// đều 504, chỉ API asset trả dữ liệu — áp dụng V17.1.8 -> V17.1.9 phải
// thành công trọn vẹn. Kèm ca âm tính: cả hai đường cùng lỗi thì phải
// báo lỗi rõ ràng, không cài đặt gì.

function failF1719(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okF1719(bool $value, string $message): void { if (!$value) failF1719($message); }
function removeF1719(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) if ($item !== '.' && $item !== '..') removeF1719($path . '/' . $item);
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.1.8/release/TMS_OS_LATEST.zip';
$newZip = $root . '/.build/v17.1.9/release/TMS_OS_LATEST.zip';
if (!is_file($baseZip)) {
    $baseBuild = dirname(dirname($baseZip));
    $basePayload = $baseBuild . '/payload';
    @mkdir($basePayload, 0700, true);
    @mkdir(dirname($baseZip), 0700, true);
    exec('git -C ' . escapeshellarg($root) . ' archive v17.1.8 -- app config public routes scripts | tar -x -C ' . escapeshellarg($basePayload), $o, $c);
    okF1719($c === 0, 'Không dựng được source V17.1.8 từ tag phát hành.');
    @unlink($basePayload . '/scripts/verify-uci-payload.sh');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/make_payload_zip.php') . ' ' . escapeshellarg($basePayload) . ' ' . escapeshellarg($baseZip), $o, $c);
    okF1719($c === 0 && is_file($baseZip), 'Không dựng được payload V17.1.8.');
}
okF1719(is_file($newZip), 'Thiếu artifact V17.1.9 — chạy tests/build_v17_1_9_payload.sh trước.');

$zipBytes = (string)file_get_contents($newZip);
$zipSha = hash_file('sha256', $newZip);
$apiBase = 'https://api.github.com/repos/geogich961-lab/tms-os';
$releaseJson = json_encode([
    'tag_name' => 'v17.1.9',
    'body' => '',
    'published_at' => '2026-11-08T00:00:00Z',
    'prerelease' => false,
    'assets' => [
        ['name' => 'TMS_OS_LATEST.zip', 'browser_download_url' => 'https://github.com/geogich961-lab/tms-os/releases/download/v17.1.9/TMS_OS_LATEST.zip', 'url' => $apiBase . '/releases/assets/111'],
        ['name' => 'RELEASE.json', 'browser_download_url' => 'https://github.com/geogich961-lab/tms-os/releases/download/v17.1.9/RELEASE.json', 'url' => $apiBase . '/releases/assets/222'],
    ],
]);
$manifestJson = json_encode(['version' => '17.1.9', 'channel' => 'stable', 'checksum_sha256' => $zipSha]);

require $root . '/app/Services/UpdateService.php';

// --- Kiểm tra quy tắc Accept header ---
$probe = new UpdateService(null, null, static fn() => ['body' => '', 'error' => 'x']);
$acceptMethod = new ReflectionMethod(UpdateService::class, 'acceptHeaderFor');
okF1719($acceptMethod->invoke($probe, $apiBase . '/releases/assets/111') === 'application/octet-stream', 'API asset URL phải dùng Accept: application/octet-stream.');
okF1719($acceptMethod->invoke($probe, 'https://github.com/geogich961-lab/tms-os/releases/download/v17.1.9/TMS_OS_LATEST.zip') === '*/*', 'URL tải thường phải giữ Accept: */*.');
okF1719($acceptMethod->invoke($probe, $apiBase . '/releases/latest') === '*/*', 'API JSON thường phải giữ Accept: */*.');

$temp = sys_get_temp_dir() . '/tms-v1719-fallback-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');
try {
    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    okF1719($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.1.8.');
    $base->close();

    $calls = [];
    $flakyClient = function (string $url, int $timeout, string $ua) use (&$calls, $releaseJson, $manifestJson, $zipBytes, $apiBase) {
        $calls[] = $url;
        if ($url === $apiBase . '/releases/tags/v17.1.9') {
            return ['body' => $releaseJson, 'error' => ''];
        }
        if (str_starts_with($url, 'https://github.com/')) {
            return ['body' => '', 'error' => 'HTTP 504'];
        }
        if ($url === $apiBase . '/releases/assets/111') {
            return ['body' => $zipBytes, 'error' => ''];
        }
        if ($url === $apiBase . '/releases/assets/222') {
            return ['body' => $manifestJson, 'error' => ''];
        }
        return ['body' => '', 'error' => 'URL không mong đợi: ' . $url];
    };

    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');

    // Ca âm tính TRƯỚC (khi máy còn ở V17.1.8): cả hai đường cùng lỗi
    // -> báo lỗi rõ ràng, version không đổi.
    $deadClient = static fn(string $url, int $timeout, string $ua) => $url === $apiBase . '/releases/tags/v17.1.9'
        ? ['body' => $releaseJson, 'error' => '']
        : ['body' => '', 'error' => 'HTTP 504'];
    $svcDead = new UpdateService(null, null, $deadClient);
    okF1719($svcDead->currentVersion() === 'V17.1.8', 'Môi trường test phải bắt đầu ở V17.1.8.');
    $threw = false;
    try {
        $svcDead->applyReleaseTag('v17.1.9');
    } catch (RuntimeException $e) {
        $threw = str_contains($e->getMessage(), 'Tải gói cập nhật thất bại');
    }
    okF1719($threw, 'Cả hai đường cùng lỗi phải ném lỗi "Tải gói cập nhật thất bại".');
    okF1719($svcDead->currentVersion() === 'V17.1.8', 'Cả hai đường cùng lỗi thì version phải giữ nguyên V17.1.8.');

    // Ca dương tính: github.com 504, API asset sống -> áp dụng thành công.
    $svc = new UpdateService(null, null, $flakyClient);
    $result = $svc->applyReleaseTag('v17.1.9');
    okF1719(!empty($result['ok']), 'Áp dụng qua đường dự phòng thất bại: ' . json_encode($result));
    okF1719($svc->currentVersion() === 'V17.1.9', 'Sau áp dụng phải ở V17.1.9.');
    $browserZipAttempts = array_filter($calls, static fn($u) => str_contains($u, 'github.com/geogich961-lab/tms-os/releases/download/v17.1.9/TMS_OS_LATEST.zip'));
    okF1719($browserZipAttempts !== [], 'Phải thử đường github.com trước khi chuyển dự phòng.');
    okF1719(in_array($apiBase . '/releases/assets/111', $calls, true), 'Phải tải ZIP qua API asset khi đường chính 504.');
    okF1719(in_array($apiBase . '/releases/assets/222', $calls, true), 'Phải lấy checksum qua API asset khi đường chính 504.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    putenv('TMS_UPDATE_SKIP_RESTART');
    removeF1719($temp);
}

echo "PASS: github.com 504 -> UpdateService tự chuyển đường API asset, áp dụng V17.1.9 thành công; cả hai đường lỗi thì báo rõ.\n";
