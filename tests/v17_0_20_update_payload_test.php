<?php
declare(strict_types=1);

function failV1717(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1717(bool $condition, string $message): void { if (!$condition) { failV1717($message); } }
function removeV1717Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1717Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.19/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.20/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.20/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1717-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1717(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.19 hoặc V17.0.20 để mô phỏng nâng cấp.');
    $metadata = json_decode((string)file_get_contents($metadataFile), true);
    expectV1717(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.20', 'RELEASE.json phải khai báo V17.0.20.');
    expectV1717(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.20 không khớp RELEASE.json.');

    $archive = new ZipArchive();
    expectV1717($archive->open($releaseZip) === true, 'Không mở được payload V17.0.20.');
    $entries = [];
    for ($index = 0; $index < $archive->numFiles; $index++) { $entries[] = (string)$archive->getNameIndex($index); }
    expectV1717(!in_array('scripts/verify-uci-payload.sh', $entries, true), 'Payload thiết bị không được kèm verifier nội bộ.');
    expectV1717(!in_array('RELEASE.json', $entries, true) && !in_array('storage/', $entries, true), 'Payload không được kèm metadata tự tham chiếu hoặc dữ liệu runtime.');
    $zipRead = static fn(string $name): string => (string)$archive->getFromName($name);
    $service = $zipRead('app/Services/UpdateService.php');
    $routes = $zipRead('routes/web.php');
    $engine = $zipRead('scripts/tms-php-engine.sh');
    $core = $zipRead('scripts/tms-service-core.sh');
    $installScript = $zipRead('scripts/install.sh');
    $routerFile = $zipRead('app/Core/Router.php');
    $authFile = $zipRead('app/Services/AuthService.php');
    $authControllerFile = $zipRead('app/Controllers/AuthController.php');
    $indexFile = $zipRead('public/index.php');
    $runnerFile = $zipRead('app/Core/CommandRunner.php');
    $autoBackupFile = $zipRead('app/Services/AutoBackupService.php');
    $autoBackupCli = $zipRead('scripts/tms-auto-backup.php');
    $backupControllerFile = $zipRead('app/Controllers/BackupController.php');
    $backupViewFile = $zipRead('app/Views/backups/index.php');
    $alertsFile = $zipRead('app/Services/OperationalAlertsService.php');
    $alertsCli = $zipRead('scripts/tms-alerts-check.php');
    $alertsViewFile = $zipRead('app/Views/notifications/index.php');
    $guardianScript = $zipRead('scripts/tms-guardian.sh');
    $guardianServiceFile = $zipRead('app/Services/GuardianService.php');
    $archive->close();
    foreach ($entries as $entry) { expectV1717((bool)preg_match('#^(app|config|public|routes|scripts)/#', $entry), 'Payload chỉ được chứa các source root cho phép: ' . $entry); }

    // Bản sửa #606 phải nằm trong payload: tầng HTTP chi tiết lỗi + route chẩn đoán.
    expectV1717(str_contains($service, 'httpGetDetailed') && str_contains($service, 'CURL_IPRESOLVE_V4'), 'Payload thiếu tầng HTTP chịu lỗi (httpGetDetailed/IPv4).');
    expectV1717(str_contains($service, 'networkDiagnostics'), 'Payload thiếu API chẩn đoán kết nối GitHub.');
    expectV1717(str_contains($routes, '/api/updates/diagnose'), 'Payload thiếu route /api/updates/diagnose.');
    // Bản sửa #591 phải nằm trong payload: giới hạn upload 100M/110M ở cả CGI, HTTP, FPM.
    foreach (['php-cgi -n', 'php -n'] as $mode) {
        expectV1717(preg_match('/' . preg_quote($mode, '/') . '[^\n]*upload_max_filesize=100M/', $engine) === 1, "Payload thiếu upload_max_filesize=100M cho {$mode}.");
    }
    expectV1717(preg_match('/php_admin_value\[upload_max_filesize\]\s*=\s*100M/', $engine) === 1, 'Payload thiếu php_admin_value upload_max_filesize cho php-fpm.');
    // Payload phải dùng LF tuyệt đối — bash trên Ubuntu/Termux vỡ với CRLF.
    foreach (['scripts/install.sh' => $installScript, 'scripts/tms-php-engine.sh' => $engine, 'scripts/tms-service-core.sh' => $core] as $script => $content) {
        expectV1717(!str_contains($content, "\r\n"), "Payload script vẫn còn CRLF: {$script}.");
    }

    // Bản sửa bảo mật V17.0.20 phải nằm trong payload: default-deny auth + rate limit + CommandRunner.
    expectV1717(str_contains($routerFile, 'public(string $path)') && str_contains($routerFile, 'denyUnauthenticated'), 'Payload thiếu default-deny auth middleware ở Router.');
    expectV1717(str_contains($authFile, 'lockedFor') && str_contains($authFile, 'recordFailedAttempt'), 'Payload thiếu rate limit đăng nhập (lockedFor/recordFailedAttempt).');
    expectV1717(str_contains($authFile, 'login-throttle.json'), 'Payload thiếu tệp trạng thái khoá đăng nhập.');
    expectV1717(str_contains($authControllerFile, 'khoá tạm thời do sai mật khẩu quá nhiều lần'), 'Payload thiếu thông báo khoá đăng nhập.');
    expectV1717(str_contains($indexFile, 'authService->check()'), 'index.php phải truyền authCheck vào Router::dispatch.');
    foreach (['/', '/login', '/telegram/webhook', '/status', '/api/public-status', '/api/updates/run'] as $publicPath) {
        expectV1717(str_contains($routes, "\$router->public('" . $publicPath . "');"), 'web.php thiếu khai báo public cho ' . $publicPath);
    }
    expectV1717(str_contains($runnerFile, 'final class CommandRunner') && str_contains($runnerFile, 'public static function arg'), 'Payload thiếu CommandRunner.');
    expectV1717(preg_match_all('/\$router->(?:get|post)\(/', $routes) === 138, 'web.php trong payload phải giữ nguyên 138 route.');

    // Backup tự động & offsite V17.0.20 phải nằm trong payload.
    expectV1717(str_contains($autoBackupFile, 'final class AutoBackupService') && str_contains($autoBackupFile, 'pushOffsite'), 'Payload thiếu AutoBackupService.');
    expectV1717(str_contains($autoBackupCli, 'runNow'), 'Payload thiếu CLI scripts/tms-auto-backup.php.');
    expectV1717(str_contains($backupControllerFile, 'autoSave') && str_contains($backupControllerFile, 'autoRun'), 'BackupController thiếu action backup tự động.');
    expectV1717(str_contains($backupViewFile, 'auto-backup-card') && str_contains($backupViewFile, '/backups/auto/config'), 'View backups thiếu card Backup tự động.');
    expectV1717(str_contains($routes, '/backups/auto/config') && str_contains($routes, '/backups/auto/run'), 'web.php thiếu route backup tự động.');
    expectV1717(str_contains($indexFile, "'AutoBackupService'") && str_contains($indexFile, 'CommandRunner.php'), 'index.php phải nạp AutoBackupService và CommandRunner.');

    // Cảnh báo vận hành + Guardian heal V17.0.20 phải nằm trong payload.
    expectV1717(str_contains($alertsFile, 'final class OperationalAlertsService') && str_contains($alertsFile, 'function evaluate'), 'Payload thiếu OperationalAlertsService.');
    expectV1717(str_contains($alertsFile, 'battery_full_since') && str_contains($alertsFile, 'cooldown_minutes'), 'Payload thiếu logic pin đầy + cooldown.');
    expectV1717(str_contains($alertsCli, '->run()'), 'Payload thiếu CLI scripts/tms-alerts-check.php.');
    expectV1717(str_contains($alertsViewFile, 'ops-alerts-card') && str_contains($alertsViewFile, '/notifications/alerts/config'), 'View notifications thiếu card cảnh báo vận hành.');
    expectV1717(str_contains($routes, '/notifications/alerts/config') && str_contains($routes, '/notifications/alerts/run'), 'web.php thiếu route cảnh báo vận hành.');
    expectV1717(str_contains($guardianScript, 'repair_tunnel') && str_contains($guardianScript, 'repair_crond') && str_contains($guardianScript, 'cloudflared_running'), 'Guardian thiếu auto-heal tunnel/crond.');
    expectV1717(str_contains($guardianServiceFile, 'CHECK_TUNNEL') && str_contains($guardianServiceFile, 'CHECK_CRON'), 'GuardianService thiếu cấu hình CHECK_TUNNEL/CHECK_CRON.');
    expectV1717(str_contains($indexFile, "'OperationalAlertsService'"), 'index.php phải nạp OperationalAlertsService.');

    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    expectV1717($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.0.19.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1717-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\"\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.20.zip';
    expectV1717(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.20.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1717(!empty($result['ok']) && $service->currentVersion() === 'V17.0.20', 'UpdateService V17.0.19 không áp dụng được V17.0.20.');
    expectV1717((string)file_get_contents($target . '/storage/v1717-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1717((string)file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $core = (string)file_get_contents($target . '/scripts/tms-service-core.sh');
    $worker = (string)file_get_contents($target . '/public/service-worker.js');
    expectV1717(str_contains($core, 'ensure_php_up_for_nginx') && str_contains($core, 'không khởi động Nginx để tránh 502'), 'Payload thiếu chốt bảo vệ PHP runtime trước Nginx.');
    expectV1717(str_contains($worker, "const VERSION='tms-os-v17.0.20';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.19 → V17.0.20 nâng cấp trực tiếp, bảo toàn storage/Cloudflare, có đủ bản sửa kết nối GitHub và giới hạn upload.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1717Tree($temp);
}
