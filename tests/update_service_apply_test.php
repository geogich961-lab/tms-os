<?php
declare(strict_types=1);

function failUpdateApply(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectUpdateApply(bool $condition, string $message): void { if (!$condition) { failUpdateApply($message); } }
function removeUpdateApplyTree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) { if ($item !== '.' && $item !== '..') { removeUpdateApplyTree($path . '/' . $item); } }
        @rmdir($path);
    } else { @unlink($path); }
}

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-update-apply-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
putenv('HOME=' . $home);
putenv('TMS_UPDATE_SKIP_RESTART=1');
require $root . '/app/Services/UpdateService.php';

try {
    foreach (['app/Core', 'config', 'public', 'routes', 'scripts', 'storage'] as $dir) { @mkdir($target . '/' . $dir, 0700, true); }
    file_put_contents($target . '/config/app.php', "<?php return ['build' => 'Platform V17.0.1'];\n");
    file_put_contents($target . '/public/index.php', "<?php echo 'old';\n");
    file_put_contents($target . '/routes/web.php', "<?php\n");
    file_put_contents($target . '/app/Core/helpers.php', "<?php\n");
    file_put_contents($target . '/app/Core/Router.php', "<?php\n");
    file_put_contents($target . '/scripts/install.sh', "#!/bin/sh\nexit 0\n");
    file_put_contents($target . '/scripts/start-tms.sh', "#!/bin/sh\nexit 0\n");
    file_put_contents($target . '/storage/keep.txt', 'persistent-user-data');

    $updatesDir = $home . '/.tms-os/updates';
    @mkdir($updatesDir, 0700, true);
    $zipPath = $updatesDir . '/tms-update-test.zip';
    $zip = new ZipArchive();
    expectUpdateApply($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'Không tạo được ZIP test.');
    $zip->addFromString('config/app.php', "<?php return ['build' => 'Platform V17.0.3'];\n");
    $zip->addFromString('public/index.php', "<?php echo 'new';\n");
    $zip->addFromString('routes/web.php', "<?php\n");
    $zip->addFromString('app/Core/helpers.php', "<?php\n");
    $zip->addFromString('app/Core/Router.php', "<?php\n");
    $zip->addFromString('scripts/install.sh', "#!/bin/sh\nexit 0\n");
    $zip->addFromString('scripts/start-tms.sh', "#!/bin/sh\nexit 0\n");
    $zip->close();

    $service = new UpdateService();
    $result = $service->apply('tms-update-test.zip');
    expectUpdateApply(!empty($result['ok']), 'Apply không trả trạng thái thành công.');
    expectUpdateApply($service->currentVersion() === 'V17.0.3', 'Apply không thay đổi config/app.php sang version mới.');
    expectUpdateApply((string) file_get_contents($target . '/public/index.php') === "<?php echo 'new';\n", 'Source mới không được kích hoạt.');
    expectUpdateApply((string) file_get_contents($target . '/storage/keep.txt') === 'persistent-user-data', 'Update không được chạm dữ liệu storage.');
    expectUpdateApply(is_dir($target . '.previous/config'), 'Bản source trước phải có trong vùng khôi phục.');

    $statePath = $updatesDir . '/apply-state.json';
    $queuePath = $updatesDir . '/github-apply.job.json';
    file_put_contents($statePath, json_encode(['job' => 'done-after-restart', 'to' => 'V17.0.3', 'applying' => true, 'phase' => 'applying']));
    file_put_contents($queuePath, json_encode(['job' => 'done-after-restart', 'to' => 'V17.0.3']));
    $recovered = $service->status();
    expectUpdateApply(empty($recovered['applying']), 'Status phải tự hoàn tất khi source đã đổi sang version đích.');
    expectUpdateApply(($recovered['state']['phase'] ?? '') === 'completed', 'Status phải ghi phase completed sau restart.');
    expectUpdateApply(!is_file($queuePath), 'Job đã hoàn tất phải được dọn khỏi queue.');

    file_put_contents($statePath, json_encode(['job' => 'expired-job', 'to' => 'V17.0.4', 'applying' => true, 'phase' => 'applying']));
    file_put_contents($queuePath, json_encode(['job' => 'expired-job', 'to' => 'V17.0.4']));
    touch($statePath, time() - 1000);
    touch($queuePath, time() - 1000);
    $expired = $service->status();
    expectUpdateApply(empty($expired['applying']), 'Job quá hạn không còn worker phải được kết thúc.');
    expectUpdateApply(($expired['state']['phase'] ?? '') === 'failed', 'Job quá hạn phải có trạng thái failed rõ ràng.');
    expectUpdateApply(!is_file($queuePath), 'Queue quá hạn phải được dọn để cho phép thử lại.');
    echo "PASS: UpdateService swap source, thay version và giữ storage.\n";
} finally {
    removeUpdateApplyTree($temp);
}
