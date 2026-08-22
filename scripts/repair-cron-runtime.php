<?php
declare(strict_types=1);

// TMS OS Cron Runtime Repair
// Chuẩn hóa các tác vụ cũ (đặc biệt Job ID rỗng) rồi tạo lại crontab quản lý.

$home = getenv('HOME') ?: '/data/data/com.termux/files/home';
require_once $home . '/tms-os/app/Services/CronJobService.php';

try {
    (new CronJobService())->repairRuntime();
    echo "[OK] Cron jobs đã được chuẩn hóa và nạp lại.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[LỖI] Không thể sửa Cron runtime: ' . $e->getMessage() . "\n");
    exit(1);
}
