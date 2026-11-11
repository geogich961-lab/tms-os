<?php
declare(strict_types=1);

function failBackupPicker(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectBackupPicker(bool $condition, string $message): void { if (!$condition) failBackupPicker($message); }

$root = realpath(dirname(__DIR__));

// 1) View: ô chọn website phải có lựa chọn trống mặc định để trình duyệt KHÔNG
// tự gửi website đầu tiên trong danh sách khi người dùng chưa chọn.
$view = (string)file_get_contents($root . '/app/Views/backups/index.php');
$scopePos = strpos($view, 'data-backup-scope');
expectBackupPicker($scopePos !== false, 'View thiếu select phạm vi data-backup-scope.');
$fieldPos = strpos($view, 'data-backup-website hidden');
expectBackupPicker($fieldPos !== false && $fieldPos > $scopePos, 'View thiếu ô chọn website ẩn data-backup-website sau select phạm vi.');
$placeholderPos = strpos($view, '<option value="">-- Chọn website --</option>');
expectBackupPicker($placeholderPos !== false && $placeholderPos > $fieldPos, 'Select website phải có option trống "-- Chọn website --" đứng trước danh sách website.');
$loopPos = strpos($view, 'foreach($sites as $site)', $placeholderPos);
expectBackupPicker($loopPos !== false, 'Option trống phải đứng trước vòng lặp liệt kê website.');
expectBackupPicker(str_contains($view, '<select name="website" required>'), 'Select website phải có required để trình duyệt chặn submit khi chưa chọn.');

// 2) app.js: phải có logic bật ô chọn website đúng khi phạm vi là "Một website".
$appJs = (string)file_get_contents($root . '/public/assets/app.js');
expectBackupPicker(str_contains($appJs, "querySelectorAll('[data-backup-scope]')"), 'app.js chưa gắn logic cho select phạm vi backup.');
expectBackupPicker(str_contains($appJs, "form.querySelector('[data-backup-website]')"), 'app.js chưa tìm ô chọn website trong cùng form.');
expectBackupPicker(str_contains($appJs, "scopeSelect.value !== 'website'"), 'app.js chưa ẩn/hiện ô website theo giá trị phạm vi.');
expectBackupPicker(str_contains($appJs, "addEventListener('change', syncBackupWebsite)"), 'app.js chưa nghe sự kiện change của select phạm vi.');

// 3) Controller: phạm vi website mà chưa chọn (hoặc chọn tên không tồn tại)
// phải báo lỗi rõ ràng thay vì lặng lẽ sao lưu website khác.
$controller = (string)file_get_contents($root . '/app/Controllers/BackupController.php');
expectBackupPicker(str_contains($controller, 'Vui lòng chọn website cần sao lưu.'), 'Controller chưa chặn trường hợp chưa chọn website.');
expectBackupPicker(str_contains($controller, 'Website cần sao lưu không tồn tại.'), 'Controller chưa chặn website không có trong danh sách.');

// 4) Hợp đồng ở tầng service: phạm vi website với tên rỗng phải bị từ chối
// ngay trong BackupService (hàng phòng thủ cuối cùng).
$home = sys_get_temp_dir() . '/tms-backup-picker-' . bin2hex(random_bytes(5));
@mkdir($home, 0700, true);
$oldHome = getenv('HOME');
try {
    putenv('HOME=' . $home);
    require $root . '/app/Services/BackupService.php';
    $service = new BackupService();
    $threw = false;
    try { $service->create('website', '', 'test', false); } catch (RuntimeException $e) { $threw = str_contains($e->getMessage(), 'Tên website không hợp lệ.'); }
    expectBackupPicker($threw, 'BackupService phải từ chối tạo snapshot website khi chưa chọn website.');
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($home, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($home);
}

echo "PASS: Ô chọn website khi tạo backup hiện đúng theo phạm vi, có lựa chọn trống mặc định và bị chặn ở cả giao diện lẫn server khi chưa chọn.\n";
