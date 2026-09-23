<?php
declare(strict_types=1);

$root = realpath(dirname(__DIR__));
require $root . '/app/Services/WebsiteHealthService.php';

$name = (string)($argv[1] ?? '');
$port = (int)($argv[2] ?? 0);
$path = (string)($argv[3] ?? '/');

$service = new WebsiteHealthService();
$lock = '';
try {
    $lock = $service->workerLockPath($name);
    // Đợi request Web Panel kết thúc để tránh tự gọi vòng vào PHP Engine single-process.
    usleep(900000);
    $result = $service->probeApplication($port, $path);
    $service->writeCache($name, $port, $result);
} catch (Throwable $e) {
    try {
        if ($name !== '' && $port > 0) {
            $service->writeCache($name, $port, [
                'status' => 'offline',
                'http_status' => 0,
                'reachable' => false,
                'latency_ms' => 0,
                'message' => 'Health worker lỗi: ' . mb_substr(preg_replace('/\s+/', ' ', trim($e->getMessage())), 0, 240),
            ]);
        }
    } catch (Throwable) {
    }
} finally {
    if ($lock !== '') {
        @unlink($lock);
    }
}
