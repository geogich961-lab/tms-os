<?php
declare(strict_types=1);

/**
 * Hồi quy V17.1.5 — bản sao lưu không được nuốt kho backup cũ (hiệu ứng tuyết lăn):
 * Phạm vi system/config gom cả ~/.tms-os, trong đó có .tms-os/backups/archives
 * chứa mọi archive trước → bản sau phình theo tổng các bản trước (thực địa:
 * 246MB → 744MB → ~1,5GB). BackupService::create() phải loại thư mục archives
 * khỏi gói tar, nhưng vẫn giữ: dữ liệu cấu hình thật + manifest trong gói +
 * metadata. Test dùng BackupService THẬT với HOME tạm và một archive cũ giả.
 */

function failBX(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectBX(bool $condition, string $message): void { if (!$condition) { failBX($message); } }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-backup-exclude-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
putenv('HOME=' . $home);
@mkdir($home . '/.tms-os/config', 0700, true);
file_put_contents($home . '/.tms-os/config/seed.txt', "du-lieu-cau-hinh-that\n");

require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/BackupService.php';

try {
    $backups = new BackupService();

    // Archive cũ giả với nội dung nhận diện được + đủ lớn để lộ nếu bị nuốt vào.
    $fakeName = 'tms-system-20990101_000000_ffffff.tar.gz';
    $fakeFile = $home . '/.tms-os/backups/archives/' . $fakeName;
    file_put_contents($fakeFile, str_repeat('SNOWBALL-', 16384)); // ~160KB

    $listArchive = static function (string $id) use ($home): array {
        $meta = json_decode((string)file_get_contents($home . '/.tms-os/backups/metadata/' . $id . '.json'), true);
        expectBX(is_array($meta) && !empty($meta['archive']), 'Metadata của bản mới phải tồn tại.');
        $archive = $home . '/.tms-os/backups/archives/' . $meta['archive'];
        expectBX(is_file($archive), 'Archive của bản mới phải tồn tại.');
        exec('tar -tzf ' . escapeshellarg($archive) . ' 2>/dev/null', $lines, $code);
        expectBX($code === 0, 'Phải đọc được danh sách thành viên trong archive mới.');
        return [$archive, $lines];
    };

    foreach (['config', 'system'] as $scope) {
        $id = $backups->create($scope, '', 'test exclude ' . $scope, false);
        [$archive, $members] = $listArchive($id);
        $joined = implode("\n", $members);
        expectBX(str_contains($joined, '.tms-os/config/seed.txt'), "Scope {$scope}: dữ liệu cấu hình thật phải có trong gói.");
        expectBX(str_contains($joined, 'manifest-' . $id . '.json'), "Scope {$scope}: manifest trong gói phải được giữ.");
        expectBX(!str_contains($joined, $fakeName), "Scope {$scope}: archive cũ KHÔNG được nằm trong bản mới (tuyết lăn).");
        expectBX(!str_contains($joined, '/backups/archives/'), "Scope {$scope}: không thành viên nào được thuộc thư mục backups/archives.");
        expectBX(filesize($archive) < filesize($fakeFile), "Scope {$scope}: bản mới phải nhỏ hơn cả archive cũ giả (không nuốt dữ liệu cũ).");
    }

    echo "OK: backup exclude archives V17.1.5\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
}
