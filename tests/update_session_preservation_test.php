<?php
declare(strict_types=1);

function sessionPreservationFail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function sessionPreservationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        sessionPreservationFail($message);
    }
}

function sessionPreservationRemoveTree(string $dir): void
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
$helpers = (string) file_get_contents($root . '/app/Core/helpers.php');
$service = (string) file_get_contents($root . '/app/Services/UpdateService.php');

sessionPreservationExpect(
    str_contains($helpers, 'function tms_clear_cache(bool $clearSessions = true): array'),
    'Cache helper phải hỗ trợ bỏ qua dọn session khi worker cập nhật chạy nền.'
);
sessionPreservationExpect(
    str_contains($service, 'tms_clear_cache(false)'),
    'Worker cập nhật phải giữ session trình duyệt thay vì dọn toàn bộ session không có request context.'
);

$tmp = sys_get_temp_dir() . '/tms-update-session-' . bin2hex(random_bytes(5));
$oldHome = getenv('HOME');
try {
    mkdir($tmp . '/app/Core', 0700, true);
    mkdir($tmp . '/storage/sessions', 0700, true);
    mkdir($tmp . '/storage/cache', 0700, true);
    copy($root . '/app/Core/helpers.php', $tmp . '/app/Core/helpers.php');
    putenv('HOME=' . $tmp . '/home');
    session_save_path($tmp . '/storage/sessions');
    session_id('tms-current-session-id-123456');
    session_start();
    $_SESSION['tms_authenticated'] = true;
    session_write_close();
    file_put_contents($tmp . '/storage/sessions/sess_tms-older-session-id-654321', 'old');
    file_put_contents($tmp . '/storage/cache/stale-cache', 'cache');

    require $tmp . '/app/Core/helpers.php';
    tms_clear_cache(false);
    sessionPreservationExpect(
        is_file($tmp . '/storage/sessions/sess_tms-current-session-id-123456'),
        'Dọn cache trong worker không được xóa session đang đăng nhập.'
    );
    sessionPreservationExpect(
        is_file($tmp . '/storage/sessions/sess_tms-older-session-id-654321'),
        'Dọn cache trong worker không được xóa session khác khi không có request context.'
    );
    sessionPreservationExpect(
        !is_file($tmp . '/storage/cache/stale-cache'),
        'Dọn cache trong worker vẫn phải xóa cache tạm.'
    );
    echo "PASS: update worker clears cache without invalidating browser sessions\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    sessionPreservationRemoveTree($tmp);
}
