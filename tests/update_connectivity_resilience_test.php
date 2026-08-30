<?php
declare(strict_types=1);

/**
 * Hồi quy connectivity Update Center (#606) và giới hạn upload PHP engine (#591):
 * - API GitHub hỏng vẫn phải nâng qua fallback metadata RELEASE.json.
 * - Thất bại hoàn toàn phải nêu rõ lỗi từng endpoint, không nêu lỗi chung chung.
 * - httpGetDetailed phải hỗ trợ client injected để test không đụng mạng thật.
 * - tms-php-engine.sh phải nâng upload_max_filesize/post_max_size ở cả CGI, HTTP và FPM.
 */

function failConn(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectConn(bool $condition, string $message): void { if (!$condition) { failConn($message); } }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-update-conn-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
putenv('HOME=' . $home);
require $root . '/app/Services/UpdateService.php';

foreach (['app/Core', 'config', 'public', 'routes', 'scripts', 'storage'] as $dir) { @mkdir($target . '/' . $dir, 0700, true); }
file_put_contents($target . '/config/app.php', "<?php return ['build' => 'Platform V17.0.9'];\n");

const API_URL = 'https://api.github.com/repos/geogich961-lab/tms-os/releases/latest';
const METADATA_URL = 'https://github.com/geogich961-lab/tms-os/releases/latest/download/RELEASE.json';

$apiPayload = json_encode([
    'tag_name' => 'v17.0.15',
    'body' => 'hotfix test',
    'published_at' => '2026-08-30T00:00:00Z',
    'assets' => [['name' => 'TMS_OS_LATEST.zip', 'browser_download_url' => 'https://github.com/geogich961-lab/tms-os/releases/download/v17.0.15/TMS_OS_LATEST.zip']],
]);
$metadataPayload = json_encode(['version' => '17.0.15', 'tag' => 'v17.0.15', 'notes' => 'hotfix test']);

/** @var array<string, mixed> $responses map URL => string body | array{body:string,error:string} */
$responses = [];
$fakeClient = function (string $url) use (&$responses) {
    if (!array_key_exists($url, $responses)) { return ['body' => '', 'error' => 'không có endpoint ' . $url]; }
    $r = $responses[$url];
    return is_array($r) ? $r : ['body' => (string)$r, 'error' => ''];
};

try {
    // 1. API hoạt động bình thường → phát hiện bản mới.
    $responses = [API_URL => $apiPayload];
    $service = new UpdateService(null, null, $fakeClient);
    $check = $service->check();
    expectConn($check['error'] === null, 'check() không được báo lỗi khi API hoạt động: ' . (string)$check['error']);
    expectConn(is_array($check['available']) && $check['available']['version'] === '17.0.15', 'check() phải phát hiện 17.0.15 mới hơn 17.0.9.');

    // 2. API hỏng (HTTP 403 — rate limit) → fallback metadata RELEASE.json vẫn nâng được.
    $responses = [API_URL => ['body' => '', 'error' => 'HTTP 403'], METADATA_URL => $metadataPayload];
    $service = new UpdateService(null, null, $fakeClient);
    $check = $service->check();
    expectConn($check['error'] === null, 'Fallback metadata phải kế thay API khi API trả HTTP 403.');
    expectConn(is_array($check['available']) && $check['available']['version'] === '17.0.15', 'Fallback metadata phải trả version 17.0.15 và zip_url hợp lệ.');
    expectConn(str_contains((string)($check['available']['zip_url'] ?? ''), 'v17.0.15/TMS_OS_LATEST.zip'), 'Fallback metadata phải dựng đúng URL tải TMS_OS_LATEST.zip.');

    // 3. Cả hai endpoint hỏng → thông báo lỗi phải nêu rõ lỗi từng endpoint.
    $responses = [API_URL => ['body' => '', 'error' => 'Could not resolve host: api.github.com'], METADATA_URL => ['body' => '', 'error' => 'HTTP 503']];
    $service = new UpdateService(null, null, $fakeClient);
    $check = $service->check();
    $error = (string)$check['error'];
    expectConn($error !== '', 'Cả hai endpoint hỏng phải trả về lỗi.');
    expectConn(str_contains($error, 'api.github.com') && str_contains($error, 'Could not resolve host'), 'Lỗi phải nêu rõ api.github.com và nguyên nhân DNS.');
    expectConn(str_contains($error, 'github.com (metadata)') && str_contains($error, 'HTTP 503'), 'Lỗi phải nêu rõ endpoint metadata và mã HTTP.');
    expectConn(str_contains($error, 'Không thể kết nối GitHub'), 'Lỗi phải giữ thông điệp gốc dễ hiểu cho người dùng.');

    // 4. API trả JSON rác → phải rơi xuống metadata thay vì báo thành công sai.
    $responses = [API_URL => '<html>proxy login page</html>', METADATA_URL => $metadataPayload];
    $service = new UpdateService(null, null, $fakeClient);
    $check = $service->check();
    expectConn(is_array($check['available']) && $check['available']['version'] === '17.0.15', 'JSON rác từ API phải được bỏ qua và dùng metadata.');

    // 5. networkDiagnostics() trả cấu trúc dùng được cho panel.
    $responses = [API_URL => ['body' => '', 'error' => 'timeout sau 10 giây'], METADATA_URL => $metadataPayload];
    $service = new UpdateService(null, null, $fakeClient);
    $diag = $service->networkDiagnostics();
    expectConn(is_bool($diag['curl']) && is_bool($diag['json']), 'networkDiagnostics() phải báo có/không cURL và JSON extension.');
    expectConn(count($diag['endpoints']) === 2, 'networkDiagnostics() phải dò đủ 2 endpoint.');
    expectConn($diag['endpoints'][0]['endpoint'] === 'api.github.com (API release)' && $diag['endpoints'][0]['ok'] === false, 'Endpoint API hỏng phải được đánh dấu ok=false.');
    expectConn($diag['endpoints'][1]['endpoint'] === 'github.com (metadata RELEASE.json)' && $diag['endpoints'][1]['ok'] === true, 'Endpoint metadata hoạt động phải được đánh dấu ok=true.');

    // 6. #591: PHP engine phải nâng upload/post size ở cả ba chế độ chạy.
    $engine = (string)file_get_contents($root . '/scripts/tms-php-engine.sh');
    foreach (['php-cgi -n', 'php -n'] as $mode) {
        expectConn(preg_match('/' . preg_quote($mode, '/') . '[^\n]*upload_max_filesize=100M/', $engine) === 1, "{$mode} phải có -d upload_max_filesize=100M.");
        expectConn(preg_match('/' . preg_quote($mode, '/') . '[^\n]*post_max_size=110M/', $engine) === 1, "{$mode} phải có -d post_max_size=110M.");
    }
    expectConn(preg_match('/php_admin_value\[upload_max_filesize\]\s*=\s*100M/', $engine) === 1, 'php-fpm runtime conf phải có php_admin_value upload_max_filesize.');
    expectConn(preg_match('/php_admin_value\[post_max_size\]\s*=\s*110M/', $engine) === 1, 'php-fpm runtime conf phải có php_admin_value post_max_size.');

    // 7. isNewer phải cho phép nâng trực tiếp V17.0.9 → V17.0.15 (hồi quy #592).
    $responses = [API_URL => str_replace('17.0.15', '17.0.10', $apiPayload)];
    $service = new UpdateService(null, null, $fakeClient);
    $check = $service->check();
    expectConn(is_array($check['available']) && $check['available']['version'] === '17.0.10', 'V17.0.9 phải tự nâng trực tiếp lên 17.0.10 không cần hạ bản trung gian.');

    echo "OK: update connectivity resilience\n";
} finally {
    if (is_dir($temp)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($temp);
    }
}
