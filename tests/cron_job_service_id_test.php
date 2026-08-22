<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-os-cron-id-test-' . bin2hex(random_bytes(4));
$home = $base . '/home';
$prefix = $base . '/prefix';
$capture = $base . '/installed-crontab';
mkdir($home . '/.tms-os', 0700, true);
mkdir($home . '/tms-os/scripts', 0700, true);
mkdir($prefix . '/bin', 0700, true);
putenv('HOME=' . $home);
putenv('PREFIX=' . $prefix);
putenv('TMS_CRON_CAPTURE=' . $capture);
putenv('PATH=' . $prefix . '/bin:/usr/bin:/bin');

file_put_contents($prefix . '/bin/crontab', "#!/bin/sh\ncp \"\$1\" \"\$TMS_CRON_CAPTURE\"\n");
chmod($prefix . '/bin/crontab', 0700);
file_put_contents($home . '/tms-os/scripts/tms-cron-engine.sh', "#!/bin/sh\nexit 0\n");
chmod($home . '/tms-os/scripts/tms-cron-engine.sh', 0700);

// Tái hiện chính xác dữ liệu do biểu mẫu tạo mới gửi lên: id là chuỗi rỗng.
file_put_contents($home . '/.tms-os/cron-jobs.json', json_encode([
    '' => [
        'id' => '',
        'name' => 'TMS Cron Test',
        'command' => 'date',
        'schedule' => '* * * * *',
        'enabled' => true,
        'notify_telegram' => false,
    ],
], JSON_PRETTY_PRINT));

require_once $root . '/app/Services/CronJobService.php';
$service = new CronJobService();
$migrated = $service->all();
$migratedId = $migrated[0]['id'] ?? '';

$service->save([
    'id' => '',
    'name' => 'TMS Cron Test 2',
    'command' => 'date',
    'schedule' => '* * * * *',
    'enabled' => true,
    'notify_telegram' => false,
]);

$stored = json_decode((string)file_get_contents($home . '/.tms-os/cron-jobs.json'), true);
$crontab = (string)file_get_contents($capture);
$ids = array_keys($stored);
$validIds = array_filter($ids, static fn (string $id): bool => preg_match('/^[a-f0-9]{16}$/', $id) === 1);
$crontabHasIds = count($validIds) === 2;
foreach ($validIds as $id) {
    $crontabHasIds = $crontabHasIds && str_contains($crontab, "cron-wrapper.php' '" . $id . "'");
}

$ok = preg_match('/^[a-f0-9]{16}$/', $migratedId) === 1
    && count($stored) === 2
    && $crontabHasIds;

exec('rm -rf ' . escapeshellarg($base));

if (!$ok) {
    fwrite(STDERR, "Cron Job ID migration test failed.\n");
    exit(1);
}

echo "Cron Job ID migration and crontab test passed.\n";
