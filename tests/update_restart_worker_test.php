<?php
declare(strict_types=1);

/**
 * Hồi quy cho lỗi Update Center: shell `bash -c` chứa chuỗi pkill -f có thể
 * tự bị pkill trước khi gọi start-tms.sh, khiến panel local không quay lại.
 * Restart phải được thực hiện bởi file worker riêng có argv không chứa mẫu pkill.
 */
function restartWorkerFail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function restartWorkerExpect(bool $condition, string $message): void
{
    if (!$condition) {
        restartWorkerFail($message);
    }
}

function restartWorkerRemoveTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

$root = dirname(__DIR__);
$service = (string)file_get_contents($root . '/app/Services/UpdateService.php');
$worker = $root . '/scripts/tms-update-restart.sh';

restartWorkerExpect(is_file($worker), 'Thiếu worker restart tách biệt cho Update Center.');
restartWorkerExpect(str_contains($service, 'tms-update-restart.sh'), 'UpdateService chưa gọi worker restart riêng.');
restartWorkerExpect(str_contains($service, "' nohup sh ' . escapeshellarg(\$restartWorker)"), 'UpdateService phải detach worker restart riêng qua nohup.');
restartWorkerExpect(str_contains($service, "'TMS_UPDATE_SKIP_RESTART'"), 'Cờ chặn restart cho kiểm thử phải được bảo toàn.');
restartWorkerExpect(str_contains($service, 'TMS_UPDATE_STATE_FILE'), 'UpdateService phải truyền state file cho worker xác nhận health.');
restartWorkerExpect(str_contains($service, 'TMS_UPDATE_QUEUE_FILE'), 'UpdateService phải truyền queue file để worker luôn dọn hàng đợi khi đã kết thúc.');
restartWorkerExpect(str_contains($service, "'restarting'"), 'UpdateService phải giữ trạng thái restarting trước khi worker xác nhận health.');
$workerSource = (string)file_get_contents($worker);
restartWorkerExpect(str_contains($workerSource, 'http://127.0.0.1:8888/login'), 'Worker phải kiểm tra health của panel local sau restart.');
restartWorkerExpect(str_contains($workerSource, 'restart_failed'), 'Worker phải ghi rõ trạng thái restart thất bại.');

if (!preg_match('/private function scheduleRestart\(\): bool\s*\{(.*?)\n\s*\}\n\s*private function validateZip/s', $service, $match)) {
    restartWorkerFail('Không đọc được hàm scheduleRestart.');
}
restartWorkerExpect(!str_contains($match[1], 'pkill'), 'scheduleRestart không được nhúng pkill trong bash -c.');
restartWorkerExpect(!str_contains($match[1], 'bash -c'), 'scheduleRestart không được dùng bash -c chứa lệnh restart.');

$tmp = sys_get_temp_dir() . '/tms-update-restart-worker-' . bin2hex(random_bytes(5));
try {
    mkdir($tmp . '/scripts', 0700, true);
    mkdir($tmp . '/bin', 0700, true);
    copy($worker, $tmp . '/scripts/tms-update-restart.sh');
    file_put_contents($tmp . '/scripts/start-tms.sh', "#!/usr/bin/env bash\ntouch \"\${TMS_RESTART_MARKER:?}\"\n");
    file_put_contents($tmp . '/bin/sleep', "#!/bin/sh\nexit 0\n");
    file_put_contents($tmp . '/bin/curl', "#!/bin/sh\nexit \"\${TMS_CURL_EXIT:-0}\"\n");
    chmod($tmp . '/scripts/tms-update-restart.sh', 0700);
    chmod($tmp . '/scripts/start-tms.sh', 0700);
    chmod($tmp . '/bin/sleep', 0700);
    chmod($tmp . '/bin/curl', 0700);
    $marker = $tmp . '/restarted';
    $stateFile = $tmp . '/apply-state.json';
    $queueFile = $tmp . '/github-apply.job.json';
    file_put_contents($stateFile, json_encode(['to' => '17.0.5', 'applying' => false, 'phase' => 'restarting'], JSON_UNESCAPED_UNICODE));
    file_put_contents($queueFile, json_encode(['to' => '17.0.5'], JSON_UNESCAPED_UNICODE));
    $oldPath = getenv('PATH') ?: '/usr/bin:/bin';
    $command = 'PATH=' . escapeshellarg($tmp . '/bin:' . $oldPath)
        . ' TMS_RESTART_MARKER=' . escapeshellarg($marker)
        . ' TMS_UPDATE_STATE_FILE=' . escapeshellarg($stateFile)
        . ' TMS_UPDATE_QUEUE_FILE=' . escapeshellarg($queueFile)
        . ' TMS_UPDATE_EXPECTED_VERSION=17.0.5'
        . ' TMS_RESTART_HEALTH_ATTEMPTS=1'
        . ' bash ' . escapeshellarg($tmp . '/scripts/tms-update-restart.sh');
    exec($command . ' 2>&1', $output, $code);
    restartWorkerExpect($code === 0, 'Worker restart riêng phải chạy thành công trong môi trường cô lập.');
    restartWorkerExpect(is_file($marker), 'Worker restart riêng chưa gọi start-tms.sh.');
    $completed = json_decode((string)file_get_contents($stateFile), true);
    restartWorkerExpect(($completed['phase'] ?? '') === 'completed' && empty($completed['applying']), 'Health thành công phải hoàn tất trạng thái cập nhật.');
    restartWorkerExpect(!is_file($queueFile), 'Health thành công phải dọn hàng đợi cập nhật.');

    file_put_contents($tmp . '/bin/curl', "#!/bin/sh\nexit 1\n");
    chmod($tmp . '/bin/curl', 0700);
    file_put_contents($stateFile, json_encode(['to' => '17.0.5', 'applying' => false, 'phase' => 'restarting'], JSON_UNESCAPED_UNICODE));
    file_put_contents($queueFile, json_encode(['to' => '17.0.5'], JSON_UNESCAPED_UNICODE));
    exec($command . ' 2>&1', $failedOutput, $failedCode);
    $failed = json_decode((string)file_get_contents($stateFile), true);
    restartWorkerExpect(($failed['phase'] ?? '') === 'restart_failed' && empty($failed['applying']), 'Health thất bại phải được ghi rõ để tránh báo cập nhật thành công sai.');
    restartWorkerExpect(!is_file($queueFile), 'Health thất bại phải dọn hàng đợi để người dùng không bị kẹt cập nhật.');
    echo "PASS: update restart uses a detached script without self-termination\n";
} finally {
    restartWorkerRemoveTree($tmp);
}
