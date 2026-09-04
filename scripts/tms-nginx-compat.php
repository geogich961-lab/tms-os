<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/Core/NginxCompatibility.php';

$result = tms_repair_nginx_server_names_hash();
if (empty($result['ok'])) {
    fwrite(STDERR, (string)($result['message'] ?? 'Không thể sửa cấu hình Nginx.') . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, (string)($result['message'] ?? 'OK') . PHP_EOL);
