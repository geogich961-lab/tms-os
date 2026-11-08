<?php
declare(strict_types=1);

// Worker backup tự động, được cron-wrapper gọi theo lịch của AutoBackupService.
// Chỉ đọc ~/.tms-os/config/auto-backup.json; không nhận input từ web.
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/CronJobService.php';
require $root . '/app/Services/BackupService.php';
require $root . '/app/Services/AutoBackupService.php';

try {
    $service = new AutoBackupService(new BackupService(), new CronJobService());
    $result = $service->runNow();
    // Vòng trùng (đang có vòng khác chạy) không phải lỗi: thoát 0, không ồn.
    if (!empty($result['skipped'])) {
        fwrite(STDOUT, 'SKIP: ' . ($result['message'] ?? '') . PHP_EOL);
        exit(0);
    }
    fwrite(STDOUT, ($result['ok'] ? 'OK: ' : 'FAIL: ') . ($result['message'] ?? '') . PHP_EOL);
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, "TMS auto-backup failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
