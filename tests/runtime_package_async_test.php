<?php
declare(strict_types=1);

/**
 * Regression Runtime Package V17.0.2.
 * Dùng fake pkg/cloudflared trong HOME tạm, không gọi mạng hay pkg thật.
 */
function fail(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expect(bool $condition, string $message): void { if (!$condition) { fail($message); } }
function findPackage(array $packages, string $id): array { foreach ($packages as $package) { if ($package['id'] === $id) { return $package; } } fail("Không tìm thấy {$id}."); }
function resetPackageState(string $home): void
{
    $base = $home . '/.tms-os/packages';
    foreach (glob($base . '/queue/*') ?: [] as $file) { @unlink($file); }
    foreach (glob($base . '/results/*') ?: [] as $file) { @unlink($file); }
    @unlink($home . '/.tms-os/packages-v11.json');
}

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-runtime-package-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$bin = $temp . '/bin';
@mkdir($home, 0700, true);
@mkdir($bin, 0700, true);

$pkg = $bin . '/pkg';
$pkgScript = <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$TMS_TEST_LOG"
case "${TMS_TEST_MODE:-success}" in
  success)
    cat > "$TMS_TEST_BIN/cloudflared" <<'EOF'
#!/bin/sh
echo fake-cloudflared
EOF
    chmod 700 "$TMS_TEST_BIN/cloudflared"
    exit 0
    ;;
  failure) echo "mirror unavailable" >&2; exit 23 ;;
  timeout) exec sleep 4 ;;
esac
SH;
file_put_contents($pkg, $pkgScript);
chmod($pkg, 0700);
foreach (['nginx', 'mariadbd', 'redis-server'] as $core) {
    file_put_contents($bin . '/' . $core, "#!/bin/sh\nprintf '%s\n' '$core' >> \"\$TMS_CORE_TOUCH_LOG\"\n");
    chmod($bin . '/' . $core, 0700);
}

putenv('HOME=' . $home);
putenv('PREFIX=' . $temp . '/prefix');
putenv('TMS_OS_ROOT=' . $root);
putenv('TMS_PKG_COMMAND=' . $pkg);
putenv('TMS_PACKAGE_NO_LAUNCH=1');
putenv('TMS_PACKAGE_TEST_MODE=1');
putenv('TMS_TEST_BIN=' . $bin);
putenv('TMS_TEST_LOG=' . $temp . '/pkg.log');
putenv('TMS_CORE_TOUCH_LOG=' . $temp . '/core-touch.log');
putenv('PATH=' . $bin . ':' . getenv('PATH'));

require $root . '/app/Services/PluginService.php';

try {
    putenv('TMS_TEST_MODE=success');
    $service = new PluginService();
    $started = microtime(true);
    $queued = $service->enqueueInstall('cloudflared');
    expect((microtime(true) - $started) < 0.5, 'enqueue không được chờ pkg/network.');
    expect(!empty($queued['queued']) && $queued['job'] !== '', 'Cloudflared phải được xếp hàng.');
    $pending = findPackage($service->catalog(), 'cloudflared');
    expect(!empty($pending['busy']), 'Cloudflared phải hiện trạng thái đang xử lý trước khi worker chạy.');
    // catalog() có thể chạy --version cho các binary giả lập; xóa dấu vết đó
    // trước khi chứng minh riêng worker không chạm Nginx/MariaDB/Redis.
    @unlink($temp . '/core-touch.log');
    try { $service->enqueueInstall('cloudflared'); fail('Job trùng không bị chặn.'); } catch (RuntimeException) {}
    try { $service->enqueueInstall('cloudflared;touch ' . $temp . '/pwned'); fail('ID không thuộc allowlist không bị chặn.'); } catch (RuntimeException) {}
    expect(!file_exists($temp . '/pwned'), 'Input ID không được thực thi qua shell.');
    $service->runQueuedInstalls();
    expect(!file_exists($temp . '/core-touch.log'), 'Worker Runtime Package không được gọi Nginx/MariaDB/Redis.');
    $done = findPackage($service->catalog(), 'cloudflared');
    expect(!empty($done['installed']) && empty($done['busy']), 'Worker thành công phải xác minh cloudflared và xóa busy.');
    expect(str_contains($done['last_result'], 'Thành công'), 'Kết quả thành công chưa được lưu bền vững.');
    expect(trim((string) file_get_contents($temp . '/pkg.log')) === 'install -y cloudflared', 'Worker chỉ được gọi pkg với package allowlist cố định.');

    @unlink($bin . '/cloudflared'); resetPackageState($home); @unlink($temp . '/core-touch.log'); putenv('TMS_TEST_MODE=failure');
    $failureService = new PluginService(); $failureService->enqueueInstall('cloudflared'); $failureService->runQueuedInstalls();
    expect(!file_exists($temp . '/core-touch.log'), 'Worker failure không được tác động dịch vụ lõi.');
    $failed = findPackage($failureService->catalog(), 'cloudflared');
    expect(empty($failed['busy']) && str_contains($failed['last_result'], 'Thất bại') && str_contains($failed['last_result'], 'mirror unavailable'), 'Lỗi pkg phải xóa busy và trả thông tin đã được lọc.');

    resetPackageState($home); @unlink($temp . '/core-touch.log'); putenv('TMS_TEST_MODE=timeout'); putenv('TMS_PACKAGE_TIMEOUT=1');
    $timeoutService = new PluginService(); $timeoutService->enqueueInstall('cloudflared'); $timeoutService->runQueuedInstalls();
    expect(!file_exists($temp . '/core-touch.log'), 'Worker timeout không được tác động dịch vụ lõi.');
    $timedOut = findPackage($timeoutService->catalog(), 'cloudflared');
    expect(empty($timedOut['busy']) && str_contains($timedOut['last_result'], 'Hết thời gian chờ'), 'Timeout phải xóa busy và lưu trạng thái hướng dẫn thử lại.');
    echo "PASS: Runtime Package async queue, allowlist, failure và timeout.\n";
} finally {
    $delete = static function (string $path) use (&$delete): void { if (is_dir($path) && !is_link($path)) { foreach (scandir($path) ?: [] as $name) { if ($name !== '.' && $name !== '..') { $delete($path . '/' . $name); } } @rmdir($path); } else { @unlink($path); } };
    $delete($temp);
}
