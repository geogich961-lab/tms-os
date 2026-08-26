<?php
declare(strict_types=1);
function assertion(bool $ok, string $message): void { if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/app/Services/PluginService.php');
$controller = (string) file_get_contents($root . '/app/Controllers/PluginController.php');
$view = (string) file_get_contents($root . '/app/Views/plugins/index.php');
$routes = (string) file_get_contents($root . '/routes/web.php');
$worker = (string) file_get_contents($root . '/scripts/tms-package-worker.php');
foreach ([
    [$service, 'enqueueInstall', 'Service thiếu enqueue nền.'], [$service, 'runQueuedInstalls', 'Service thiếu worker entrypoint.'], [$service, 'proc_open', 'Service thiếu timeout độc lập timeout command.'], [$service, 'catalogRaw', 'Service thiếu allowlist catalog.'], [$controller, 'apiGuard', 'Controller thiếu JSON auth guard.'], [$controller, 'HTTP_X_CSRF_TOKEN', 'Controller thiếu CSRF header.'], [$controller, 'public function status', 'Controller thiếu endpoint polling.'], [$view, 'data-package-install-form', 'View thiếu AJAX install marker.'], [$view, '/api/packages/status', 'View thiếu polling endpoint.'], [$view, 'maxPolls', 'View không giới hạn polling.'], [$routes, "'/api/packages/status'", 'Route status chưa được đăng ký.'], [$worker, 'runQueuedInstalls', 'Worker script chưa gọi package service.'],
] as [$haystack, $needle, $message]) { assertion(str_contains($haystack, $needle), $message); }
assertion(!str_contains($service, 'exec(\'pkg \'.$verb.\' '), 'Đường cài sync cũ vẫn còn trong service.');
echo "PASS: Runtime Package UI/API async markers.\n";
