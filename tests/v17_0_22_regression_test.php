<?php
declare(strict_types=1);

function failV17022(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV17022(bool $condition, string $message): void { if (!$condition) failV17022($message); }

$root = realpath(dirname(__DIR__));
$index = (string)file_get_contents($root . '/public/index.php');
$controller = (string)file_get_contents($root . '/app/Controllers/FileManagerController.php');
$appJs = (string)file_get_contents($root . '/public/assets/app.js');
$restart = (string)file_get_contents($root . '/scripts/tms-update-restart.sh');
$config = (string)file_get_contents($root . '/config/app.php');
$worker = (string)file_get_contents($root . '/public/service-worker.js');

expectV17022(str_contains($index, "'/files/upload-chunk'"), 'Thiếu route /files/upload-chunk.');
expectV17022(str_contains($index, "'/files/upload-complete'"), 'Thiếu route /files/upload-complete.');
expectV17022(str_contains($controller, 'function uploadChunk'), 'FileManagerController thiếu uploadChunk().');
expectV17022(str_contains($controller, 'function completeUpload'), 'FileManagerController thiếu completeUpload().');
expectV17022(str_contains($appJs, "requestJson('/files/upload-chunk'"), 'Frontend không gọi chunk upload endpoint.');
expectV17022(str_contains($appJs, "requestJson('/files/upload-complete'"), 'Frontend không gọi complete upload endpoint.');
expectV17022(str_contains($appJs, "button.textContent='Thử lại'"), 'Upload lỗi không khôi phục nút Thử lại.');

expectV17022(str_contains($restart, 'if panel_ok; then'), 'Update worker phải health-check panel local.');
expectV17022(str_contains($restart, 'rollback_source'), 'Update worker phải rollback source khi health-check thất bại.');
expectV17022(!str_contains($restart, 'tms-php-engine.sh" restart'), 'Hot update không được restart PHP.');
expectV17022(!str_contains($restart, 'nginx -s reload'), 'Hot update không được reload Nginx.');
expectV17022(!str_contains($restart, 'tms-cloudflare-tunnel.sh'), 'Hot update không được chạm tunnel.');

expectV17022((bool)preg_match("/'build' => 'Platform V(\\d+\\.\\d+\\.\\d+)'/", $config, $m), 'Không đọc được build hiện tại.');
expectV17022(version_compare($m[1], '17.0.22', '>='), 'Build không được thấp hơn V17.0.22.');
expectV17022(str_contains($worker, "const VERSION='tms-os-v{$m[1]}';"), 'Service Worker phải khớp build hiện tại.');

echo "PASS: V17.0.22 File Manager fixes remain intact with zero-downtime updater.\n";
