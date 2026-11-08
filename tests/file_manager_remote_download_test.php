<?php
declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function rm_rf(string $path): void
{
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (@scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        rm_rf($path . '/' . $entry);
    }
    @rmdir($path);
}

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-file-remote-' . bin2hex(random_bytes(5));
$home = $base . '/home';
$site = $home . '/websites/demo/public';
mkdir($site, 0700, true);
putenv('HOME=' . $home);

require $root . '/app/Services/FileManagerService.php';

if (!function_exists('curl_init')) {
    echo "SKIP: thiếu cURL\n";
    rm_rf($base);
    exit(0);
}

$service = new FileManagerService();

// 1. Từ chối URL không hợp lệ (không cần mạng).
$rejects = [
    'ftp://example.com/f.zip' => 'http/https',
    'file:///etc/passwd' => 'http/https',
    'javascript:alert(1)' => 'http/https',
    'http://user:pass@example.com/f' => 'đăng nhập',
    'http:///thieu-host' => 'không hợp lệ',
];
foreach ($rejects as $url => $expect) {
    $ok = false;
    try { $service->remoteDownload('websites', 'demo/public', $url); }
    catch (Throwable $e) { $ok = str_contains($e->getMessage(), $expect); }
    check($ok, "Phải từ chối URL: {$url}");
}

// 2. Chống SSRF: chặn IP nội bộ/bảo lưu (tắt bypass kiểm thử).
putenv('TMS_TEST_ALLOW_PRIVATE_IP');
foreach (['http://127.0.0.1/x', 'http://10.0.0.5/x', 'http://192.168.1.1/x', 'http://169.254.169.254/x'] as $url) {
    $ok = false;
    try { $service->remoteDownload('websites', 'demo/public', $url); }
    catch (Throwable $e) { $ok = str_contains($e->getMessage(), 'nội bộ'); }
    check($ok, "Phải chặn SSRF: {$url}");
}
putenv('TMS_TEST_ALLOW_PRIVATE_IP=1');

// 3. Kiểm thử tích hợp với PHP server nội bộ.
$docroot = $base . '/srv';
mkdir($docroot, 0700, true);
$helloContent = 'xin chao remote';
file_put_contents($docroot . '/hello.txt', $helloContent);
file_put_contents($docroot . '/redir.php', '<?php header("Location: /hello.txt"); http_response_code(302);');
file_put_contents($docroot . '/cd.php', '<?php header("Content-Disposition: attachment; filename=\"ten file.zip\""); echo "DATA";');

$proc = null; $port = 0;
$descriptors = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
foreach (range(18923, 18933) as $p) {
    $try = proc_open(['php', '-S', "127.0.0.1:{$p}", '-t', $docroot], $descriptors, $pipes);
    if (!is_resource($try)) continue;
    $up = false;
    for ($i = 0; $i < 30; $i++) {
        usleep(100000);
        $probe = @file_get_contents("http://127.0.0.1:{$p}/hello.txt");
        if ($probe === 'xin chao remote') { $up = true; break; }
    }
    if ($up) { $proc = $try; $port = $p; break; }
    proc_terminate($try); proc_close($try);
}
check(is_resource($proc), 'Không khởi động được PHP server cho kiểm thử.');
$base_url = "http://127.0.0.1:{$port}";

// 3a. Tải trực tiếp.
$r = $service->remoteDownload('websites', 'demo/public', $base_url . '/hello.txt');
check($r['name'] === 'hello.txt', 'Tên tệp tải về phải đúng.');
check($r['size'] === strlen($helloContent), 'Kích thước tệp tải về phải đúng.');
check(file_get_contents($site . '/hello.txt') === $helloContent, 'Nội dung tệp tải về phải đúng.');

// 3b. Theo redirect + tự đổi tên khi trùng.
$r2 = $service->remoteDownload('websites', 'demo/public', $base_url . '/redir.php');
check(str_starts_with($r2['name'], 'Bản sao '), 'Tải trùng tên phải tự đổi tên, nhận: ' . $r2['name']);
check(file_get_contents($site . '/' . $r2['name']) === 'xin chao remote', 'Nội dung sau redirect phải đúng.');

// 3c. Lấy tên từ Content-Disposition.
$r3 = $service->remoteDownload('websites', 'demo/public', $base_url . '/cd.php');
check($r3['name'] === 'ten file.zip', 'Phải lấy tên từ Content-Disposition, nhận: ' . $r3['name']);

// 3d. Tự tạo thư mục con lồng nhau.
$r4 = $service->remoteDownload('websites', 'demo/public/sub/dir', $base_url . '/hello.txt');
check($r4['name'] === 'hello.txt', 'Tên tệp trong thư mục con phải đúng.');
check(file_exists($site . '/sub/dir/hello.txt'), 'remoteDownload phải tự tạo thư mục con.');

// 3e. Chặn traversal trong đường dẫn đích.
$bad = false;
try { $service->remoteDownload('websites', '../bad', $base_url . '/hello.txt'); }
catch (Throwable $e) { $bad = true; }
check($bad, 'Phải chặn path traversal trong relative.');

// 3f. HTTP 404 → báo lỗi rõ.
$nf = false;
try { $service->remoteDownload('websites', 'demo/public', $base_url . '/khong-co.txt'); }
catch (Throwable $e) { $nf = str_contains($e->getMessage(), '404'); }
check($nf, 'Phải báo lỗi khi URL trả 404.');

proc_terminate($proc); proc_close($proc);
rm_rf($base);

echo "PASS: file manager remote download regression\n";
