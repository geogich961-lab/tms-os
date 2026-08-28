<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/updates/index.php');
$controller = (string) file_get_contents($root . '/app/Controllers/UpdateController.php');
$service = (string) file_get_contents($root . '/app/Services/UpdateService.php');
$routes = (string) file_get_contents($root . '/routes/web.php');
$worker = (string) file_get_contents($root . '/scripts/tms-update-worker.php');
$login = (string) file_get_contents($root . '/app/Views/auth/login.php');

$required = [
	' id="current-version-card"',
	' id="online-update-action" hidden',
	'class="update-available-action"',
	'function setOnlineUpdateVisibility(visible)',
	'function showAvailableUpdate(available)',
	'setOnlineUpdateVisibility(false);',
	'showAvailableUpdate(d.available);',
		'Đã tìm thấy bản cập nhật mới.',
		'id="apply-github-btn">Cập nhật ngay</button>',
		'<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button type="button" class="btn btn-primary" id="apply-github-btn">Cập nhật ngay</button>',
	    "verifyAppliedVersion(0)",
    "cache:'no-store'",
    "Đang xác minh phiên bản",
    "Không thể xác minh trạng thái cập nhật:",
    "function requestUpdateReauthentication",
    "Phiên đăng nhập đã hết hạn sau khi TMS OS khởi động lại.",
    "if (error && error.authRequired)",
    "/login?next=%2Fupdates",
    "parseUpdateJson(response)",
    "Đang chờ panel khởi động lại",
];
foreach ($required as $needle) {
    if (!str_contains($view, $needle)) {
        fwrite(STDERR, "Missing Update Center verification guard: {$needle}\n");
        exit(1);
    }
}

if (str_contains($view, 'name="csrf" value="<?=tms_h($csrf)?><button')) {
    fwrite(STDERR, "Update Center has a malformed CSRF input that swallows the apply button.\n");
    exit(1);
}

$currentCardPos = strpos($view, 'id="current-version-card"');
$quickCardPos = strpos($view, 'id="online-update-action" hidden');
$checkHandlerPos = strpos($view, "document.getElementById('check-update-btn')");
$availableRevealPos = strpos($view, 'showAvailableUpdate(d.available);');
if ($currentCardPos === false || $quickCardPos === false || $checkHandlerPos === false || $availableRevealPos === false || $availableRevealPos < $checkHandlerPos) {
	fwrite(STDERR, "Update action must remain hidden until a successful update check reports a new release.\n");
	exit(1);
}

$checkResultPos = strpos($view, 'id="check-result"');
$manualCardPos = strpos($view, 'Tải gói cập nhật thủ công');
if ($quickCardPos < $currentCardPos || $checkResultPos === false || $quickCardPos < $checkResultPos || $manualCardPos === false || $quickCardPos > $manualCardPos || str_contains($view, 'online-update-card') || str_contains($view, 'NEW RELEASE') || str_contains($view, '>Cập nhật nhanh<')) {
	fwrite(STDERR, "Available-update action must be a single current-version-card action, not a separate quick-update card.\n");
	exit(1);
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
    'Đang kiểm tra phiên bản thực tế sau khi khởi động lại...',
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

$restartMaxPos = strpos($view, "if (status.phase === 'restarting')");
$restartVerifyPos = strpos($view, 'verifyAppliedVersion(0, fallbackError', $restartMaxPos === false ? 0 : $restartMaxPos);
if ($restartMaxPos === false || $restartVerifyPos === false || $restartVerifyPos < $restartMaxPos) {
    fwrite(STDERR, "Update Center must verify source after an extended restarting phase instead of treating a transient gateway outage as failure.\n");
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
    'tms_clear_cache(false)',
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

foreach ([
    "require dirname(__DIR__) . '/layouts/header.php';",
    "require dirname(__DIR__) . '/layouts/footer.php';",
    '$showShell = false;',
] as $needle) {
    if (!str_contains($login, $needle)) {
        fwrite(STDERR, "Login re-authentication page must load the shared styled layout: {$needle}\n");
        exit(1);
    }
}

echo "OK: Update Center queues work, waits for restart health, and preserves API errors.\n";
