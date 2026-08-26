<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/updates/index.php');
$controller = (string) file_get_contents($root . '/app/Controllers/UpdateController.php');

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
    'private function apiGuard(): bool',
    'http_response_code(401)',
    "'code' => 'AUTH_REQUIRED'",
    'if (!$this->apiGuard())',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "Missing JSON API authentication guard: {$needle}\n");
        exit(1);
    }
}

echo "OK: Update Center retries restart responses and preserves JSON API errors.\n";
