<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-os-telegram-test-' . bin2hex(random_bytes(4));
$home = $base . '/home';
mkdir($home . '/.tms-os', 0700, true);
mkdir($home . '/tms-os', 0700, true);
symlink($root . '/app', $home . '/tms-os/app');
symlink($root . '/scripts', $home . '/tms-os/scripts');
putenv('HOME=' . $home);

$jobId = '1234567890abcdef';
file_put_contents($home . '/.tms-os/cron-jobs.json', json_encode([
    $jobId => [
        'id' => $jobId,
        'name' => 'Telegram status test',
        'command' => 'true',
        'schedule' => '* * * * *',
        'enabled' => true,
        'notify_telegram' => true,
    ],
], JSON_PRETTY_PRINT));

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/cron-wrapper.php') . ' ' . $jobId, $output, $exitCode);
$jobs = json_decode((string)file_get_contents($home . '/.tms-os/cron-jobs.json'), true);
$job = $jobs[$jobId] ?? [];
$ok = $exitCode === 0
    && ($job['last_status'] ?? '') === 'success'
    && ($job['telegram_last_status'] ?? '') === 'not_configured'
    && ($job['telegram_last_message'] ?? '') === 'Chưa có Bot Token hoặc Chat ID.';

exec('rm -rf ' . escapeshellarg($base));
if (!$ok) {
    fwrite(STDERR, "Cron Telegram status test failed.\n");
    exit(1);
}

echo "Cron Telegram status test passed.\n";
