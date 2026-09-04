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

$healthPos = strpos($restart, 'if panel_ok; then');
$phpRestartPos = strpos($restart, 'tms-php-engine.sh" restart');
expectV17022($healthPos !== false && $phpRestartPos !== false && $healthPos < $phpRestartPos, 'Update worker phải health-check trước khi restart PHP.');
expectV17022(str_contains($restart, 'ensure_tunnel'), 'Update worker phải chủ động giữ Cloudflare Tunnel.');
expectV17022(!str_contains($restart, 'bash "$SCRIPT_DIR/start-tms.sh"'), 'Update worker không được tự full-stack restart.');
expectV17022(str_contains($restart, 'không cần restart dịch vụ'), 'Thiếu nhánh cập nhật không downtime.');
expectV17022(str_contains($config, "'build' => 'Platform V17.0.22'"), 'config/app.php chưa bump V17.0.22.');
expectV17022(str_contains($worker, "const VERSION='tms-os-v17.0.22';"), 'Service Worker chưa bump V17.0.22.');

echo "PASS: V17.0.22 File Manager upload + Update Center remote continuity.\n";
