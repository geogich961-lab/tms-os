<?php
declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-file-upload-' . bin2hex(random_bytes(5));
$home = $base . '/home';
$site = $home . '/websites/demo/public';
mkdir($site, 0700, true);
mkdir($home . '/.tms-os', 0700, true);
putenv('HOME=' . $home);

require $root . '/app/Services/FileManagerService.php';

session_id('upload-regression-session');
$service = new FileManagerService();
$uploadId = 'web_' . str_repeat('a', 24);
$work = $home . '/.tms-os/upload-parts/' . $uploadId;
mkdir($work, 0700, true);
$owner = hash('sha256', session_id());
$meta = [
    'root' => 'websites',
    'relative' => 'demo/public',
    'name' => 'hello.txt',
    'total_chunks' => 2,
    'total_size' => 11,
    'owner' => $owner,
];
file_put_contents($work . '/meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($work . '/part-000000', 'hello ');
file_put_contents($work . '/part-000001', 'world');

$result = $service->completeUpload($uploadId);
check($result['name'] === 'hello.txt', 'Hoàn tất upload phải trả đúng tên tệp.');
check($result['size'] === 11, 'Kích thước sau khi ghép phải khớp.');
check(file_get_contents($site . '/hello.txt') === 'hello world', 'Các phần upload phải được ghép đúng thứ tự.');
check(!is_dir($work), 'Phiên tạm phải được dọn sau khi hoàn tất.');

$bad = false;
try {
    $service->uploadChunk('websites', 'demo/public', ['error' => UPLOAD_ERR_OK], '../bad', 0, 1, 'bad.txt', 0);
} catch (Throwable $error) {
    $bad = str_contains($error->getMessage(), 'Mã phiên upload không hợp lệ');
}
check($bad, 'Phải từ chối mã phiên upload có path traversal/ký tự không hợp lệ.');

$limitError = false;
try {
    $service->upload('websites', 'demo/public', ['error' => UPLOAD_ERR_INI_SIZE]);
} catch (Throwable $error) {
    $limitError = str_contains($error->getMessage(), 'vượt giới hạn upload');
}
check($limitError, 'Phải báo rõ khi PHP từ chối tệp vì vượt giới hạn upload.');

$leftoverId = 'web_' . str_repeat('b', 24);
$leftover = $home . '/.tms-os/upload-parts/' . $leftoverId;
mkdir($leftover, 0700, true);
file_put_contents($leftover . '/meta.json', '{}');
touch($leftover, time() - 90000);
$service2 = new FileManagerService();
check(!is_dir($leftover), 'Phiên tạm cũ hơn 24 giờ phải được dọn khi khởi tạo service.');

@unlink($site . '/hello.txt');
@rmdir($site);
@rmdir(dirname($site));
@rmdir($home . '/websites');
@rmdir($home . '/.tms-os');
@rmdir($home);
@rmdir($base);

echo "PASS: file manager chunked upload regression\n";
