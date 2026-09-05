<?php
declare(strict_types=1);

function restartWorkerFail(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function restartWorkerExpect(bool $condition, string $message): void { if (!$condition) restartWorkerFail($message); }

$root = dirname(__DIR__);
$service = (string)file_get_contents($root . '/app/Services/UpdateService.php');
$worker = (string)file_get_contents($root . '/scripts/tms-update-restart.sh');

restartWorkerExpect(str_contains($service, 'tms-update-restart.sh'), 'UpdateService chưa gọi worker hậu kiểm.');
restartWorkerExpect(str_contains($worker, 'http://127.0.0.1:8888/login'), 'Worker phải health-check panel local.');
restartWorkerExpect(str_contains($worker, 'rollback_source'), 'Worker phải tự rollback source nếu health-check thất bại.');
restartWorkerExpect(!str_contains($worker, 'tms-php-engine.sh" restart'), 'Hot update không được restart PHP Engine.');
restartWorkerExpect(!str_contains($worker, 'nginx -s reload'), 'Hot update không được reload Nginx.');
restartWorkerExpect(!str_contains($worker, 'tms-cloudflare-tunnel.sh'), 'Hot update không được chạm Cloudflare Tunnel.');
restartWorkerExpect(!str_contains($worker, 'start-tms.sh'), 'Hot update không được full-stack restart.');
restartWorkerExpect(str_contains($worker, 'không có dịch vụ nào bị restart/reload'), 'Thiếu trạng thái zero-downtime thành công.');

echo "PASS: update worker V17.0.24 is zero-downtime and rollback-first.\n";
