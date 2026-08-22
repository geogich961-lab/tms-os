<?php
declare(strict_types=1);

/** Worker nội bộ cho Cron: tổng hợp access log mới và gửi đúng chat Telegram đã cấu hình. */
$base = dirname(__DIR__);
foreach (['CronJobService', 'SystemService', 'MonitoringService', 'CloudflareDomainService', 'TelegramCommandService', 'AccessReportService'] as $class) {
    require_once $base . '/app/Services/' . $class . '.php';
}

$cron = new CronJobService();
$telegram = new TelegramCommandService($cron, new MonitoringService(new SystemService()), new CloudflareDomainService());
$service = new AccessReportService($cron, $telegram);
$result = $service->runHourly();
if (empty($result['ok'])) {
    fwrite(STDERR, 'TMS access report: ' . (string)($result['status'] ?? 'failed') . PHP_EOL);
    exit(1);
}
exit(0);
