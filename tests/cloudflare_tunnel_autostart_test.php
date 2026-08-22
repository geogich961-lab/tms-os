<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-cloudflare-autostart-' . bin2hex(random_bytes(4));
$home = $base . '/home';
$prefix = $base . '/prefix';
@mkdir($home . '/.tms-os/cloudflare-hosting', 0700, true);
@mkdir($home . '/bin', 0700, true);
@mkdir($prefix . '/bin', 0700, true);
@mkdir($home . '/tms-os', 0700, true);
symlink($root . '/app', $home . '/tms-os/app');
symlink($root . '/scripts', $home . '/tms-os/scripts');
symlink(PHP_BINARY, $prefix . '/bin/php');

$fakeCloudflared = $home . '/bin/cloudflared';
file_put_contents($fakeCloudflared, "#!/bin/sh\nsleep 30\n");
chmod($fakeCloudflared, 0700);
file_put_contents($home . '/.tms-os/cloudflare-hosting/config.json', json_encode(['tunnel_token' => 'test-token-not-real']));
chmod($home . '/.tms-os/cloudflare-hosting/config.json', 0600);

$script = $root . '/scripts/tms-cloudflare-tunnel.sh';
$run = 'HOME=' . escapeshellarg($home) . ' PREFIX=' . escapeshellarg($prefix) . ' bash ' . escapeshellarg($script) . ' start';
exec($run, $output, $firstCode);
$pidFile = $home . '/.tms-os/cloudflare-hosting/tunnel.pid';
$pid = trim((string)@file_get_contents($pidFile));
$running = ctype_digit($pid) && trim((string)shell_exec('kill -0 ' . escapeshellarg($pid) . ' 2>/dev/null && echo yes')) === 'yes';

exec($run, $secondOutput, $secondCode);
$samePid = $pid !== '' && trim((string)@file_get_contents($pidFile)) === $pid;
exec('HOME=' . escapeshellarg($home) . ' PREFIX=' . escapeshellarg($prefix) . ' bash ' . escapeshellarg($script) . ' stop', $stopOutput, $stopCode);
$stopped = !is_file($pidFile);

exec('rm -rf ' . escapeshellarg($base));
if ($firstCode !== 0 || $secondCode !== 0 || $stopCode !== 0 || !$running || !$samePid || !$stopped) {
    fwrite(STDERR, 'Cloudflare Tunnel autostart test failed: first=' . $firstCode
        . ', second=' . $secondCode . ', stop=' . $stopCode . ', running=' . ($running ? 'yes' : 'no')
        . ', same_pid=' . ($samePid ? 'yes' : 'no') . ', stopped=' . ($stopped ? 'yes' : 'no')
        . "\n");
    exit(1);
}

echo "Cloudflare Tunnel autostart test passed.\n";
