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
    file_put_contents($config, "worker_processes auto;\nevents { worker_connections 1024; }\nhttp {\n  include mime.types;\n}\n");
    $first = tms_repair_nginx_server_names_hash($config);
    expectNginxCompat(!empty($first['ok']) && !empty($first['changed']), 'Lần đầu phải sửa nginx.conf.');
    $content = (string)file_get_contents($config);
    expectNginxCompat(substr_count($content, 'server_names_hash_bucket_size 128;') === 1, 'Thiếu hoặc lặp server_names_hash_bucket_size 128.');
    expectNginxCompat(substr_count($content, 'server_names_hash_max_size 4096;') === 1, 'Thiếu hoặc lặp server_names_hash_max_size 4096.');

    $second = tms_repair_nginx_server_names_hash($config);
    expectNginxCompat(!empty($second['ok']) && empty($second['changed']), 'Repair phải idempotent.');
    $content2 = (string)file_get_contents($config);
    expectNginxCompat($content2 === $content, 'Lần repair thứ hai không được thay đổi file.');

    file_put_contents($config, "events {}\nhttp {\n  server_names_hash_bucket_size 128;\n}\n");
    $partial = tms_repair_nginx_server_names_hash($config);
    expectNginxCompat(!empty($partial['ok']) && !empty($partial['changed']), 'Phải bổ sung max_size khi chỉ có bucket_size.');
    $partialContent = (string)file_get_contents($config);
    expectNginxCompat(substr_count($partialContent, 'server_names_hash_bucket_size 128;') === 1, 'Không được lặp bucket_size có sẵn.');
    expectNginxCompat(substr_count($partialContent, 'server_names_hash_max_size 4096;') === 1, 'Phải bổ sung max_size.');

    file_put_contents($config, "events {}\n");
    $invalid = tms_repair_nginx_server_names_hash($config);
    expectNginxCompat(empty($invalid['ok']), 'Config không có http block phải bị từ chối.');

    $view = (string)file_get_contents($root . '/app/Views/websites/index.php');
    expectNginxCompat(str_contains($view, 'pattern="[A-Za-z0-9_\\-]{2,40}"'), 'Pattern tên website phải escape dấu gạch ngang cho Chrome mới.');
    expectNginxCompat(!str_contains($view, 'pattern="[A-Za-z0-9_-]{2,40}"'), 'Không được giữ pattern cũ gây SyntaxError.');

    $index = (string)file_get_contents($root . '/public/index.php');
    expectNginxCompat(str_contains($index, 'NginxCompatibility.php') && str_contains($index, 'tms_repair_nginx_server_names_hash'), 'Panel phải tự repair nginx.conf cũ.');

    $restart = (string)file_get_contents($root . '/scripts/tms-update-restart.sh');
    expectNginxCompat(str_contains($restart, 'tms-nginx-compat.php') && str_contains($restart, 'nginx -t'), 'Update restart phải repair và kiểm tra Nginx trước khi hoàn tất.');

    $configApp = require $root . '/config/app.php';
    expectNginxCompat(($configApp['build'] ?? '') === 'Platform V17.0.22', 'Build phải là Platform V17.0.22.');
    $worker = (string)file_get_contents($root . '/public/service-worker.js');
    expectNginxCompat(str_contains($worker, "const VERSION='tms-os-v17.0.22';"), 'Service Worker phải làm mới cache V17.0.22.');

    echo "PASS: Nginx server_names_hash repair + Website pattern V17.0.22.\n";
} finally {
    @unlink($config);
    @rmdir($tmp);
}
