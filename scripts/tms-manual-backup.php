<?php
declare(strict_types=1);

// Worker tạo backup THỦ CÔNG ở chế độ nền (V17.1.12), do
// AutoBackupService::launchManualWorker() khởi chạy tách rời khỏi request
// web (nohup + setsid). Chỉ đọc file job ~/.tms-os/config/manual-backup-job.json
// do request web ghi; không nhận input trực tiếp từ web. Nhờ chạy tách rời,
// việc nén website nặng không còn bị Cloudflare cắt ở ~100 giây (lỗi 524)
// như khi tạo đồng bộ trong request web trước đây.
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/CronJobService.php';
require $root . '/app/Services/BackupService.php';
require $root . '/app/Services/AutoBackupService.php';

try {
    $service = new AutoBackupService(new BackupService(), new CronJobService());
    $result = $service->runManualJob();
    // Vòng trùng (đang có vòng thủ công khác chạy) không phải lỗi: thoát 0, không ồn.
    if (!empty($result['skipped'])) {
        fwrite(STDOUT, 'SKIP: ' . ($result['message'] ?? '') . PHP_EOL);
        exit(0);
    }
    fwrite(STDOUT, ($result['ok'] ? 'OK: ' : 'FAIL: ') . ($result['message'] ?? '') . PHP_EOL);
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, "TMS manual-backup failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
