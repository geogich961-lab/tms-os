<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/updates/index.php');
$controller = (string) file_get_contents($root . '/app/Controllers/UpdateController.php');
$service = (string) file_get_contents($root . '/app/Services/UpdateService.php');
$routes = (string) file_get_contents($root . '/routes/web.php');
$worker = (string) file_get_contents($root . '/scripts/tms-update-worker.php');

$required = [
    "verifyAppliedVersion(0)",
    "cache:'no-store'",
    "Đang xác minh phiên bản",
    "Chưa xác nhận cập nhật:",
    "parseUpdateJson(response)",
    "Đang chờ panel khởi động lại",
];
foreach ($required as $needle) {
    if (!str_contains($view, $needle)) {
        fwrite(STDERR, "Missing Update Center verification guard: {$needle}\n");
        exit(1);
    }
}

$oldFalsePositive = "// Nếu bị lỗi kết nối (thường do PHP bị kill ngay lập tức), vẫn đợi rồi reload";
if (str_contains($view, $oldFalsePositive) || !str_contains($view, "Không nhận được phản hồi từ panel.")) {
    fwrite(STDERR, "Update Center still contains the old false-success reload flow.\n");
    exit(1);
}

$catchPos = strpos($view, "}).catch(function(e) {");
$verifyPos = strpos($view, "verifyAppliedVersion(0,", $catchPos === false ? 0 : $catchPos);
if ($catchPos === false || $verifyPos === false || $verifyPos < $catchPos) {
    fwrite(STDERR, "Update Center catch path must verify the running version.\n");
    exit(1);
}

if (str_contains($view, 'return r.json();')) {
    fwrite(STDERR, "Update Center must not parse temporary HTML responses as JSON directly.\n");
    exit(1);
}

foreach ([
    "pollUpdateJob(String(d.job), String(d.version || ''), 0)",
    '/api/updates/job-status?job=',
    'status.update_ok === false',
    'status.phase === \'failed\'',
    'status.phase === \'restart_failed\'',
    'status.phase === \'restarting\'',
    'function versionMatches(current, expected)',
    'function finishVerified(current)',
] as $needle) {
    if (!str_contains($view, $needle)) {
        fwrite(STDERR, "Missing queued Update Center polling guard: {$needle}\n");
        exit(1);
    }
}

$phaseFailurePos = strpos($view, "status.phase === 'restart_failed'");
$versionMatchPos = strpos($view, 'if (versionMatches(status.current, expected))');
if ($phaseFailurePos === false || $versionMatchPos === false || $phaseFailurePos > $versionMatchPos) {
    fwrite(STDERR, "Update Center must handle restart failure before accepting a matching source version.\n");
    exit(1);
}

foreach ([
    'private function apiGuard(): bool',
    'http_response_code(401)',
    "'code' => 'AUTH_REQUIRED'",
    'if (!$this->apiGuard())',
    'public function jobStatus(): void',
    "'update_ok' => array_key_exists('ok', \$state)",
    "'queued' => !empty(\$r['queued'])",
    "'job' => (string)(\$r['job'] ?? '')",
] as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "Missing JSON API authentication guard: {$needle}\n");
        exit(1);
    }
}

foreach ([
    'public function enqueueGitHubApply(): array',
    'public function runQueuedGitHubApply(): void',
    "'phase'=>'queued'",
    "'phase'=>'applying'",
    "'phase'=>'failed'",
    "'phase'=>'restarting'",
    'private function launchUpdateWorker(): void',
    'private function scheduleRestart(): bool',
    'private const JOB_TIMEOUT_SECONDS = 900',
    "'phase' => 'completed'",
    'Worker cập nhật đã quá thời gian chờ',
    'Đã xác nhận source đang chạy là V',
    'foreach ($parts as $part)',
    "'TMS_UPDATE_SKIP_RESTART'",
    "'Payload đã xử lý nhưng phiên bản source chưa đổi sang V'",
    "'Cập nhật chưa đổi được source sang V'",
] as $needle) {
    if (!str_contains($service, $needle)) {
        fwrite(STDERR, "Missing queued worker safety guard: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($routes, "'/api/updates/job-status'")) {
    fwrite(STDERR, "Missing authenticated update job status route.\n");
    exit(1);
}
if (!str_contains($worker, '(new UpdateService())->runQueuedGitHubApply();')) {
    fwrite(STDERR, "Update worker must only execute the internal queued apply method.\n");
    exit(1);
}

echo "OK: Update Center queues work, waits for restart health, and preserves API errors.\n";
