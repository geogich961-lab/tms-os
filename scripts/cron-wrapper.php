<?php
declare(strict_types=1);

// TMS OS Cron Wrapper
$home = getenv('HOME') ?: '/data/data/com.termux/files/home';
require_once $home . '/tms-os/app/Services/CronJobService.php';

$jobId = $argv[1] ?? '';
if (empty($jobId)) exit(1);

$service = new CronJobService();
$jobs = @json_decode((string)@file_get_contents($home . '/.tms-os/cron-jobs.json'), true);
$job = $jobs[$jobId] ?? null;

if (!$job) exit(1);

$startTime = microtime(true);
exec($job['command'] . ' 2>&1', $output, $status);
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

$jobs[$jobId]['last_run'] = date('c');
$jobs[$jobId]['last_status'] = ($status === 0 ? 'success' : 'failed');

if ($job['notify_telegram']) {
    $statusEmoji = ($status === 0 ? '✅ Thành công' : '❌ Thất bại');
    $outputStr = implode("\n", array_slice($output, -10)); // Lấy 10 dòng cuối
    if (strlen($outputStr) > 1800) {
        $outputStr = substr($outputStr, -1800) . "\n… (đã rút gọn)";
    }
    $message = "Tác vụ: {$job['name']}\n"
             . "Trạng thái: {$statusEmoji}\n"
             . "Thời gian chạy: {$duration}s\n"
             . "Kết quả:\n{$outputStr}";
    $telegram = $service->sendTelegramNotification($message);
    $jobs[$jobId]['telegram_last_status'] = $telegram['status'];
    $jobs[$jobId]['telegram_last_message'] = $telegram['message'];
    $jobs[$jobId]['telegram_last_sent_at'] = date('c');
}

file_put_contents($home . '/.tms-os/cron-jobs.json', json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
@chmod($home . '/.tms-os/cron-jobs.json', 0600);

exit($status);
