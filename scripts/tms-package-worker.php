<?php
declare(strict_types=1);

// Worker tách khỏi request FastCGI. Job chỉ được PluginService tạo trong thư
// mục 0700 và được đối chiếu lại với catalog trước khi gọi pkg.
$root = getenv('TMS_OS_ROOT') ?: dirname(__DIR__);
require $root . '/app/Core/helpers.php';
require $root . '/app/Services/PluginService.php';

try {
    (new PluginService())->runQueuedInstalls();
} catch (Throwable $e) {
    fwrite(STDERR, "TMS package worker failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
