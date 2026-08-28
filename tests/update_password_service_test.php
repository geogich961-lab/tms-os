<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/Services/UpdateService.php';

function update_password_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function update_password_test_expect(callable $action, string $message): void
{
    try {
        $action();
    } catch (RuntimeException) {
        return;
    }
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function update_password_test_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

$previousHome = getenv('HOME');
$home = sys_get_temp_dir() . '/tms-update-password-' . bin2hex(random_bytes(6));
putenv('HOME=' . $home);

try {
    $queuedCount = 0;
    $initialPassword = 'initial-' . bin2hex(random_bytes(12));
    $replacementPassword = 'replacement-' . bin2hex(random_bytes(12));
    $service = new UpdateService(
        static fn(): array => ['version' => '17.0.14', 'checksum_sha256' => str_repeat('a', 64), 'url' => 'https://example.test/TMS_OS_LATEST.zip'],
        static function () use (&$queuedCount): array {
            $queuedCount++;
            return ['queued' => true, 'message' => 'queued'];
        },
    );
    mkdir($home . '/tms-os/config', 0700, true);
    file_put_contents($home . '/tms-os/config/app.php', "<?php return ['build' => 'Platform 17.0.13'];\n");
    update_password_test_assert($service->updatePasswordStatus() === ['configured' => false], 'Mật khẩu phải mặc định chưa được thiết lập.');

    update_password_test_expect(
        static fn() => $service->setUpdatePassword('', '12345678', '87654321'),
        'Không được chấp nhận xác nhận mật khẩu sai.'
    );
    update_password_test_expect(
        static fn() => $service->setUpdatePassword('', '1234567', '1234567'),
        'Không được chấp nhận mật khẩu ngắn hơn giới hạn.'
    );

    $service->setUpdatePassword('', $initialPassword, $initialPassword);
    update_password_test_assert($service->updatePasswordStatus() === ['configured' => true], 'Mật khẩu đã lưu phải chỉ trả trạng thái đã thiết lập.');

    $file = $home . '/.tms-os/update-password.json';
    $stored = (string)file_get_contents($file);
    update_password_test_assert(!str_contains($stored, $initialPassword), 'Tệp mật khẩu không được chứa plaintext.');
    $state = json_decode($stored, true);
    update_password_test_assert(is_array($state) && isset($state['hash']) && password_verify($initialPassword, (string)$state['hash']), 'Hash mật khẩu phải xác minh được.');
    update_password_test_assert(((int)fileperms($file) & 0777) === 0600, 'Tệp mật khẩu phải có quyền 0600.');

    update_password_test_expect(
        static fn() => $service->setUpdatePassword('sai-mat-khau', 'replacement-value', 'replacement-value'),
        'Không được đổi mật khẩu khi mật khẩu hiện tại sai.'
    );
    update_password_test_expect(
        static fn() => $service->clearUpdatePassword('sai-mat-khau'),
        'Không được tắt bảo vệ khi mật khẩu hiện tại sai.'
    );

    $service->setUpdatePassword($initialPassword, $replacementPassword, $replacementPassword);
    $state = json_decode((string)file_get_contents($file), true);
    update_password_test_assert(is_array($state) && password_verify($replacementPassword, (string)$state['hash']), 'Mật khẩu mới phải thay thế hash cũ.');

    $nonce = bin2hex(random_bytes(8));
    $service->createTelegramUpdateOffer('123456789', '42', '17.0.14', $nonce);
    update_password_test_assert($service->beginTelegramUpdateChallenge('123456789', '42', $nonce) === ['ok' => true, 'code' => 'password_pending'], 'Callback Cập nhật phải chỉ mở phiên chờ mật khẩu.');
    update_password_test_assert($service->hasPendingTelegramUpdateChallenge('123456789', '42'), 'Phiên mật khẩu phải gắn với chat và người gọi.');
    $wrong = $service->authorizeTelegramUpdate('123456789', '42', 'sai-mat-khau');
    update_password_test_assert(($wrong['code'] ?? '') === 'wrong_password' && ($wrong['remaining'] ?? 0) === 2 && $queuedCount === 0, 'Mật khẩu sai không được enqueue cập nhật.');
    $correct = $service->authorizeTelegramUpdate('123456789', '42', $replacementPassword);
    update_password_test_assert(($correct['code'] ?? '') === 'queued' && $queuedCount === 1 && !$service->hasPendingTelegramUpdateChallenge('123456789', '42'), 'Mật khẩu đúng phải hủy phiên rồi enqueue đúng một lần.');
    update_password_test_assert(($service->authorizeTelegramUpdate('123456789', '42', $replacementPassword)['code'] ?? '') === 'expired' && $queuedCount === 1, 'Phát lại tin nhắn mật khẩu không được enqueue lần hai.');

    $nonce = bin2hex(random_bytes(8));
    $service->createTelegramUpdateOffer('123456789', '42', '17.0.14', $nonce);
    $challengeFile = $home . '/.tms-os/telegram-update-challenge.json';
    $challenge = json_decode((string)file_get_contents($challengeFile), true);
    $challenge['expires_at'] = time() - 1;
    file_put_contents($challengeFile, json_encode($challenge));
    update_password_test_assert(($service->beginTelegramUpdateChallenge('123456789', '42', $nonce)['code'] ?? '') === 'expired', 'Đề nghị hết hạn không được mở phiên mật khẩu.');

    $nonce = bin2hex(random_bytes(8));
    $service->createTelegramUpdateOffer('123456789', '42', '17.0.14', $nonce);
    $service->beginTelegramUpdateChallenge('123456789', '42', $nonce);
    $service->authorizeTelegramUpdate('123456789', '42', 'sai-1');
    $service->authorizeTelegramUpdate('123456789', '42', 'sai-2');
    update_password_test_assert(($service->authorizeTelegramUpdate('123456789', '42', 'sai-3')['code'] ?? '') === 'locked', 'Ba lần sai phải hủy phiên xác thực.');
    update_password_test_assert(($service->authorizeTelegramUpdate('123456789', '42', $replacementPassword)['code'] ?? '') === 'expired' && $queuedCount === 1, 'Phiên đã khóa không thể tiếp tục cập nhật.');

    $service->clearUpdatePassword($replacementPassword);
    update_password_test_assert($service->updatePasswordStatus() === ['configured' => false] && !is_file($file), 'Tắt mật khẩu phải xóa trạng thái riêng.');
} finally {
    update_password_test_remove_tree($home);
    putenv($previousHome === false ? 'HOME' : 'HOME=' . $previousHome);
}

echo "OK: Update password is one-way, verified, and private.\n";
