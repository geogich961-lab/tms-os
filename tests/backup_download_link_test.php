<?php
declare(strict_types=1);

/*
 * Kiểm thử cơ chế tải backup V17.1.13: downloadUrl() tạo liên kết tĩnh
 * public/_backup_downloads/<token>/<tên file> trỏ symlink tới archive để
 * nginx phục vụ trực tiếp, thay readfile() qua php-cgi (đứt 502/520 với
 * file ~1 GB). Code cũ (chưa có downloadUrl) phải FAIL.
 */

$rootDir = dirname(__DIR__);
$failures = [];
$pass = 0;
function check(bool $cond, string $label): void
{
    global $failures, $pass;
    if ($cond) { $pass++; echo "PASS: {$label}\n"; }
    else { $failures[] = $label; echo "FAIL: {$label}\n"; }
}

require_once $rootDir . '/app/Services/BackupService.php';

// ---------- HOME tạm có backup thật ----------
$home = sys_get_temp_dir() . '/tms-dl-test-' . bin2hex(random_bytes(4));
mkdir($home . '/tms-os/public', 0775, true);
mkdir($home . '/.tms-os/config', 0775, true);
file_put_contents($home . '/.tms-os/config/marker.txt', "noi dung test\n");
putenv('HOME=' . $home);

$svc = new BackupService();
check(method_exists($svc, 'downloadUrl'), 'BackupService có downloadUrl()');
if (!method_exists($svc, 'downloadUrl')) {
    echo "\nKẾT QUẢ: " . count($failures) . " FAIL\n";
    exit(1);
}
$id = $svc->create('config', '', 'test tai xuong');
$archive = $svc->file($id);
check(is_file($archive), 'đã tạo backup config thật để tải');

// ---------- 1. URL + symlink đúng đích ----------
$url = $svc->downloadUrl($id);
check((bool)preg_match('#^/_backup_downloads/[a-f0-9]{24}/' . preg_quote(basename($archive), '#') . '$#', $url), 'URL đúng mẫu /_backup_downloads/<token 24 hex>/<tên archive>');
$link = $home . '/tms-os/public' . $url;
check(is_link($link), 'liên kết là symlink trong public/');
check(is_link($link) && realpath($link) === realpath($archive), 'symlink trỏ đúng file archive');
check(is_file($home . '/tms-os/public/_backup_downloads/index.html'), 'có index.html chắn liệt kê thư mục');

// ---------- 2. Mỗi lần gọi 1 token mới, link cũ vẫn còn ----------
$url2 = $svc->downloadUrl($id);
check($url2 !== '' && $url2 !== $url, 'lần gọi 2 cho token khác');
check(is_link($home . '/tms-os/public' . $url2), 'liên kết lần 2 tồn tại');

// ---------- 3. Tự dọn liên kết cũ hơn 2 giờ ----------
$staleDir = $home . '/tms-os/public/_backup_downloads/' . str_repeat('a', 24);
mkdir($staleDir, 0775, true);
symlink($archive, $staleDir . '/' . basename($archive));
touch($staleDir, time() - 3 * 3600);
clearstatcache(true);
$url3 = $svc->downloadUrl($id);
check($url3 !== '', 'lần gọi 3 vẫn tạo được liên kết');
check(!is_dir($staleDir), 'thư mục token cũ >2 giờ đã bị dọn');
check(is_link($home . '/tms-os/public' . $url), 'liên kết còn hạn (<2 giờ) không bị dọn nhầm');

// ---------- 4. Id không tồn tại -> ném lỗi như file() ----------
$threw = false;
try { $svc->downloadUrl('khong-ton-tai'); } catch (RuntimeException) { $threw = true; }
check($threw, 'downloadUrl(id sai) ném RuntimeException');

// ---------- 5. Không có thư mục public -> trả '' để caller fallback ----------
$home2 = sys_get_temp_dir() . '/tms-dl-test2-' . bin2hex(random_bytes(4));
mkdir($home2 . '/tms-os', 0775, true); // cố ý KHÔNG tạo public/
mkdir($home2 . '/.tms-os/config', 0775, true);
file_put_contents($home2 . '/.tms-os/config/marker.txt', "x\n");
putenv('HOME=' . $home2);
$svc2 = new BackupService();
$id2 = $svc2->create('config', '', 'test fallback');
check($svc2->downloadUrl($id2) === '', 'thiếu public/ -> trả chuỗi rỗng (fallback readfile)');
putenv('HOME=' . $home);

// ---------- 6. Controller: ưu tiên chuyển hướng, fallback giữ nguyên ----------
$ctl = (string)file_get_contents($rootDir . '/app/Controllers/BackupController.php');
check(str_contains($ctl, "downloadUrl(\$id)") && str_contains($ctl, "header('Location: '.\$url)"), 'Controller chuyển hướng sang liên kết tĩnh khi tạo được');
check(str_contains($ctl, 'readfile($p)'), 'Controller vẫn giữ fallback readfile() cho trường hợp không tạo được liên kết');

echo "\nKẾT QUẢ: {$pass} PASS, " . count($failures) . " FAIL\n";
exit($failures ? 1 : 0);
