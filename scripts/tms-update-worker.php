<?php
declare(strict_types=1);

// Worker tách khỏi request HTTP: Update Center có thể trả JSON trước khi
// PHP/Nginx restart. Không nhận input từ web và chỉ xử lý job GitHub đã được
// UpdateService tạo trong thư mục quyền 0700 của người dùng Termux.
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
require $root . '/app/Services/UpdateService.php';

try {
    (new UpdateService())->runQueuedGitHubApply();
} catch (Throwable $e) {
    fwrite(STDERR, "TMS update worker failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
