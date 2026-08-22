<?php
declare(strict_types=1);

// Regression: worker chạy bởi crond phải tự nạp đủ service, không dựa vào
// bootstrap của panel và không gửi Telegram khi báo cáo đang tắt.
$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-access-worker-' . bin2hex(random_bytes(6));
$home = $base . '/home';
$prefix = $base . '/prefix';
@mkdir($home . '/.tms-os', 0700, true);
@mkdir($prefix, 0700, true);
file_put_contents($home . '/.tms-os/access-report-config.json', json_encode(['enabled' => false]));

$command = 'HOME=' . escapeshellarg($home)
    . ' PREFIX=' . escapeshellarg($prefix)
    . ' ' . escapeshellarg(PHP_BINARY)
    . ' ' . escapeshellarg($root . '/scripts/access-report.php')
    . ' 2>&1';
$output = [];
$code = 1;
exec($command, $output, $code);
$text = implode("\n", $output);

exec('rm -rf ' . escapeshellarg($base));
if ($code !== 0 || str_contains($text, 'Fatal error') || str_contains($text, 'UnifiedSystemCoreService')) {
    fwrite(STDERR, "Access report worker bootstrap test failed.\n");
    exit(1);
}
echo "Access report worker bootstrap test passed.\n";
