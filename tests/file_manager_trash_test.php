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
$base = sys_get_temp_dir() . '/tms-file-trash-' . bin2hex(random_bytes(5));
$home = $base . '/home';
$site = $home . '/websites/demo/public';
mkdir($site, 0700, true);
putenv('HOME=' . $home);

require $root . '/app/Services/FileManagerService.php';

$service = new FileManagerService();

// 1. Xóa mềm một tệp.
file_put_contents($site . '/a.txt', 'hello');
$id = $service->trash('websites', 'demo/public/a.txt');
check(preg_match('/^\d{8}_\d{6}_[a-f0-9]{16}$/', $id) === 1, 'ID thùng rác phải đúng định dạng.');
check(!file_exists($site . '/a.txt'), 'Tệp gốc phải biến mất sau khi xóa mềm.');

// 2. Liệt kê thùng rác.
$items = $service->listTrash();
check(count($items) === 1, 'listTrash phải trả về đúng 1 mục.');
check($items[0]['name'] === 'a.txt', 'Tên mục trong thùng rác phải đúng.');
check($items[0]['root'] === 'websites' && $items[0]['relative'] === 'demo/public/a.txt', 'Metadata vị trí gốc phải đúng.');

// 3. Khôi phục.
$name = $service->restoreTrash($id);
check($name === 'a.txt', 'Khôi phục phải trả đúng tên.');
check(file_get_contents($site . '/a.txt') === 'hello', 'Nội dung sau khôi phục phải nguyên vẹn.');
check(count($service->listTrash()) === 0, 'Thùng rác phải trống sau khi khôi phục.');

// 4. Khôi phục khi trùng tên → tự đổi tên.
file_put_contents($site . '/a.txt', 'new');
$id2 = $service->trash('websites', 'demo/public/a.txt');
file_put_contents($site . '/a.txt', 'conflict');
$restored = $service->restoreTrash($id2);
check($restored !== 'a.txt' && str_contains($restored, 'khôi phục'), 'Khôi phục khi trùng tên phải tự đổi tên.');
check(file_get_contents($site . '/' . $restored) === 'new', 'Nội dung tệp khôi phục phải đúng bản đã xóa.');

// 5. Xóa mềm hàng loạt (gồm cả thư mục) + dọn sạch.
file_put_contents($site . '/b.txt', 'b');
mkdir($site . '/sub', 0700);
file_put_contents($site . '/sub/c.txt', 'c');
$n = $service->trashBatch('websites', ['demo/public/b.txt', 'demo/public/sub']);
check($n === 2, 'trashBatch phải xóa 2 mục.');
check(!file_exists($site . '/b.txt') && !is_dir($site . '/sub'), 'Các mục gốc phải biến mất.');
check($service->trashCount() === 2, 'trashCount phải là 2.');
$emptied = $service->emptyTrash();
check($emptied === 2, 'emptyTrash phải trả về 2.');
check($service->trashCount() === 0, 'Thùng rác phải trống sau khi dọn.');

// 6. Xóa vĩnh viễn một mục.
file_put_contents($site . '/d.txt', 'd');
$id3 = $service->trash('websites', 'demo/public/d.txt');
$service->deleteTrashItem($id3);
check($service->trashCount() === 0, 'Xóa vĩnh viễn phải dọn mục khỏi thùng rác.');

// 7. Từ chối ID không hợp lệ.
$bad = false;
try { $service->restoreTrash('../../etc'); } catch (Throwable $e) { $bad = true; }
check($bad, 'Phải từ chối ID thùng rác chứa traversal.');
$bad2 = false;
try { $service->deleteTrashItem('khong-ton-tai'); } catch (Throwable $e) { $bad2 = true; }
check($bad2, 'Phải báo lỗi khi xóa mục không tồn tại.');

// 8. Không được xóa thư mục gốc.
$rootBad = false;
try { $service->trash('websites', ''); } catch (Throwable $e) { $rootBad = str_contains($e->getMessage(), 'thư mục gốc'); }
check($rootBad, 'Không được chuyển thư mục gốc vào thùng rác.');

// 9. Không khôi phục được mục đã bị mất dữ liệu.
$lost = false;
try {
    file_put_contents($site . '/d.txt', 'd');
    $id4 = $service->trash('websites', 'demo/public/d.txt');
    rm_rf($home . '/.tms-os/trash/' . $id4 . '/d.txt');
    $service->restoreTrash($id4);
} catch (Throwable $e) { $lost = str_contains($e->getMessage(), 'bị mất'); }
check($lost, 'Phải báo lỗi khi dữ liệu trong thùng rác đã bị mất.');

rm_rf($base);

echo "PASS: file manager trash regression\n";
