<?php
declare(strict_types=1);

// Worker cảnh báo vận hành, được cron-wrapper gọi mỗi 15 phút khi tính năng bật.
// Chỉ đọc ~/.tms-os/config/alerts.json; không nhận input từ web.
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/CronJobService.php';
require $root . '/app/Services/OperationalAlertsService.php';

try {
    $service = new OperationalAlertsService(new CronJobService());
    $result = $service->run();
    fwrite(STDOUT, 'OK: ' . count($result['alerts']) . ' cảnh báo gửi đi.' . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "TMS alerts-check failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
