<?php
declare(strict_types=1);

/**
 * V17.0.22: Update Center phải ưu tiên giữ panel/tunnel online.
 * Chỉ restart riêng PHP engine nếu health local thực sự thất bại.
 */
function restartWorkerFail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function restartWorkerExpect(bool $condition, string $message): void
{
    if (!$condition) restartWorkerFail($message);
}

function restartWorkerRemoveTree(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    @rmdir($dir);
}

$root = dirname(__DIR__);
$service = (string)file_get_contents($root . '/app/Services/UpdateService.php');
$worker = $root . '/scripts/tms-update-restart.sh';

restartWorkerExpect(is_file($worker), 'Thiếu worker xác nhận Update Center.');
restartWorkerExpect(str_contains($service, 'tms-update-restart.sh'), 'UpdateService chưa gọi worker riêng.');
restartWorkerExpect(str_contains($service, "' nohup sh ' . escapeshellarg(\$restartWorker)"), 'UpdateService phải detach worker qua nohup.');
restartWorkerExpect(str_contains($service, "'TMS_UPDATE_SKIP_RESTART'"), 'Cờ chặn restart cho kiểm thử phải được bảo toàn.');
restartWorkerExpect(str_contains($service, 'TMS_UPDATE_STATE_FILE'), 'UpdateService phải truyền state file.');
restartWorkerExpect(str_contains($service, 'TMS_UPDATE_QUEUE_FILE'), 'UpdateService phải truyền queue file.');

$workerSource = (string)file_get_contents($worker);
restartWorkerExpect(str_contains($workerSource, 'http://127.0.0.1:8888/login'), 'Worker phải kiểm tra panel local.');
restartWorkerExpect(str_contains($workerSource, 'restart_failed'), 'Worker phải ghi trạng thái thất bại.');
restartWorkerExpect(str_contains($workerSource, 'tms-php-engine.sh" restart'), 'Worker phải có fallback restart riêng PHP engine.');
restartWorkerExpect(str_contains($workerSource, 'ensure_tunnel'), 'Worker phải chủ động giữ Cloudflare Tunnel.');
restartWorkerExpect(!str_contains($workerSource, 'bash "$SCRIPT_DIR/start-tms.sh"'), 'Worker không được full-stack restart.');
$healthPos = strpos($workerSource, 'if panel_ok; then');
$restartPos = strpos($workerSource, 'tms-php-engine.sh" restart');
restartWorkerExpect($healthPos !== false && $restartPos !== false && $healthPos < $restartPos, 'Health-check phải chạy trước PHP restart.');

if (!preg_match('/private function scheduleRestart\(\): bool\s*\{(.*?)\n\s*\}\n\s*private function validateZip/s', $service, $match)) {
    restartWorkerFail('Không đọc được hàm scheduleRestart.');
}
restartWorkerExpect(!str_contains($match[1], 'pkill'), 'scheduleRestart không được nhúng pkill.');
restartWorkerExpect(!str_contains($match[1], 'bash -c'), 'scheduleRestart không được dùng bash -c restart.');

$tmp = sys_get_temp_dir() . '/tms-update-restart-worker-' . bin2hex(random_bytes(5));
try {
    mkdir($tmp . '/scripts', 0700, true);
    mkdir($tmp . '/bin', 0700, true);
    copy($worker, $tmp . '/scripts/tms-update-restart.sh');
    file_put_contents($tmp . '/scripts/tms-php-engine.sh', "#!/usr/bin/env sh\n[ \"\${1:-}\" = restart ] || exit 2\ntouch \"\${TMS_RESTART_MARKER:?}\"\n");
    file_put_contents($tmp . '/bin/sleep', "#!/bin/sh\nexit 0\n");
    file_put_contents($tmp . '/bin/curl', "#!/bin/sh\nexit \"\${TMS_CURL_EXIT:-0}\"\n");
    chmod($tmp . '/scripts/tms-update-restart.sh', 0700);
    chmod($tmp . '/scripts/tms-php-engine.sh', 0700);
    chmod($tmp . '/bin/sleep', 0700);
    chmod($tmp . '/bin/curl', 0700);

    $marker = $tmp . '/restarted';
    $stateFile = $tmp . '/apply-state.json';
    $queueFile = $tmp . '/github-apply.job.json';
    $oldPath = getenv('PATH') ?: '/usr/bin:/bin';
    $baseCommand = 'PATH=' . escapeshellarg($tmp . '/bin:' . $oldPath)
        . ' TMS_RESTART_MARKER=' . escapeshellarg($marker)
        . ' TMS_UPDATE_STATE_FILE=' . escapeshellarg($stateFile)
        . ' TMS_UPDATE_QUEUE_FILE=' . escapeshellarg($queueFile)
        . ' TMS_UPDATE_EXPECTED_VERSION=17.0.22'
        . ' TMS_RESTART_HEALTH_ATTEMPTS=1';

    // Panel healthy: worker phải hoàn tất mà KHÔNG restart PHP.
    file_put_contents($stateFile, json_encode(['to' => '17.0.22', 'applying' => false, 'phase' => 'restarting'], JSON_UNESCAPED_UNICODE));
    file_put_contents($queueFile, json_encode(['to' => '17.0.22'], JSON_UNESCAPED_UNICODE));
    $command = $baseCommand . ' TMS_CURL_EXIT=0 sh ' . escapeshellarg($tmp . '/scripts/tms-update-restart.sh');
    exec($command . ' 2>&1', $output, $code);
    restartWorkerExpect($code === 0, 'Worker phải hoàn tất khi panel đang healthy.');
    restartWorkerExpect(!is_file($marker), 'Panel healthy thì không được restart PHP engine.');
    $completed = json_decode((string)file_get_contents($stateFile), true);
    restartWorkerExpect(($completed['phase'] ?? '') === 'completed' && empty($completed['applying']), 'Health thành công phải hoàn tất trạng thái cập nhật.');
    restartWorkerExpect(!is_file($queueFile), 'Health thành công phải dọn hàng đợi.');

    // Panel down: worker được phép restart riêng PHP, nhưng không full-stack restart.
    @unlink($marker);
    file_put_contents($stateFile, json_encode(['to' => '17.0.22', 'applying' => false, 'phase' => 'restarting'], JSON_UNESCAPED_UNICODE));
    file_put_contents($queueFile, json_encode(['to' => '17.0.22'], JSON_UNESCAPED_UNICODE));
    $failedCommand = $baseCommand . ' TMS_CURL_EXIT=1 sh ' . escapeshellarg($tmp . '/scripts/tms-update-restart.sh');
    exec($failedCommand . ' 2>&1', $failedOutput, $failedCode);
    restartWorkerExpect($failedCode !== 0, 'Panel vẫn down sau fallback phải trả lỗi xác nhận.');
    restartWorkerExpect(is_file($marker), 'Panel down phải thử restart riêng PHP engine.');
    $failed = json_decode((string)file_get_contents($stateFile), true);
    restartWorkerExpect(($failed['phase'] ?? '') === 'restart_failed' && empty($failed['applying']), 'Health thất bại phải được ghi rõ.');
    restartWorkerExpect(!is_file($queueFile), 'Health thất bại phải dọn hàng đợi.');

    echo "PASS: update worker keeps healthy panel online and only restarts PHP as fallback\n";
} finally {
    restartWorkerRemoveTree($tmp);
}
