<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/Core/NginxCompatibility.php';

$ok = true;
foreach (['tms_repair_nginx_server_names_hash', 'tms_repair_nginx_service_worker_cache'] as $repair) {
    $result = $repair();
    $message = (string)($result['message'] ?? 'Không thể sửa cấu hình Nginx.');
    if (empty($result['ok'])) {
        $ok = false;
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}

exit($ok ? 0 : 1);
