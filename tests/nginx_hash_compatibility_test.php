<?php
declare(strict_types=1);

function failNginxCompat(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectNginxCompat(bool $condition, string $message): void { if (!$condition) failNginxCompat($message); }

$root = realpath(dirname(__DIR__));
require $root . '/app/Core/NginxCompatibility.php';
$tmp = sys_get_temp_dir() . '/tms-nginx-compat-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0700, true);
$config = $tmp . '/nginx.conf';
try {
    file_put_contents($config, "worker_processes auto;\nevents { worker_connections 1024; }\nhttp {\n include mime.types;\n}\n");
    $first = tms_repair_nginx_server_names_hash($config);
    expectNginxCompat(!empty($first['ok']) && !empty($first['changed']), 'Lần đầu phải sửa nginx.conf.');
    $content = (string)file_get_contents($config);
    expectNginxCompat(substr_count($content, 'server_names_hash_bucket_size 128;') === 1, 'Thiếu/lặp bucket_size.');
    expectNginxCompat(substr_count($content, 'server_names_hash_max_size 4096;') === 1, 'Thiếu/lặp max_size.');
    $second = tms_repair_nginx_server_names_hash($config);
    expectNginxCompat(!empty($second['ok']) && empty($second['changed']), 'Repair phải idempotent.');

    $restart = (string)file_get_contents($root . '/scripts/tms-update-restart.sh');
    expectNginxCompat(!str_contains($restart, 'nginx -s reload'), 'Hot update không được reload Nginx.');
    expectNginxCompat(!str_contains($restart, 'tms-nginx-compat.php'), 'Hot update không được sửa nginx.conf trong phiên cập nhật.');

    $configApp = require $root . '/config/app.php';
    $build = (string)($configApp['build'] ?? '');
    expectNginxCompat((bool)preg_match('/^Platform V(\d+\.\d+\.\d+)$/', $build, $m), 'Build sai định dạng.');
    $worker = (string)file_get_contents($root . '/public/service-worker.js');
    expectNginxCompat(str_contains($worker, "const VERSION='tms-os-v{$m[1]}';"), 'Service Worker phải khớp build.');
    echo "PASS: Nginx compatibility repair remains available but hot update never reloads Nginx.\n";
} finally { @unlink($config); @rmdir($tmp); }
