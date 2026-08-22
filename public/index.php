<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$basePath = dirname(__DIR__);
require $basePath . '/app/Core/helpers.php';
require $basePath . '/app/Core/Router.php';

foreach (['AuthService', 'UnifiedSystemCoreService', 'SystemService', 'FileManagerService', 'WebsiteService', 'DatabaseService', 'BackupService', 'LogService', 'NetworkService', 'TerminalService', 'DiagnosticsService', 'PluginService', 'AppInstallerService', 'CronJobService', 'MonitoringService', 'UpdateService', 'ModuleService', 'ServiceManagerService', 'GuardianService', 'CloudflareDomainService', 'SqlQueryService'] as $class) {
    require $basePath . '/app/Services/' . $class . '.php';
}

foreach (['AuthController', 'DashboardController', 'FileManagerController', 'WebsiteController', 'DatabaseController', 'BackupController', 'LogController', 'SettingsController', 'NetworkController', 'TerminalController', 'DiagnosticsController', 'PluginController', 'AppInstallerController', 'MarketplaceController', 'CronController', 'MonitoringController', 'NotificationController', 'UpdateController', 'ModuleController', 'ServiceManagerController', 'GuardianController', 'CloudflareDomainController', 'SqlController'] as $class) {
    require $basePath . '/app/Controllers/' . $class . '.php';
}

date_default_timezone_set((string)tms_config('timezone', 'Asia/Ho_Chi_Minh'));

function tms_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $last = strtolower($value[strlen($value) - 1]);
    $number = (float)$value;
    return match ($last) {
        'g' => (int)round($number * 1024 * 1024 * 1024),
        'm' => (int)round($number * 1024 * 1024),
        'k' => (int)round($number * 1024),
        default => (int)$number,
    };
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
$postLimit = tms_ini_bytes((string)ini_get('post_max_size'));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $postLimit > 0 && $contentLength > $postLimit) {
    http_response_code(413);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tệp quá lớn</title><style>body{font-family:system-ui;background:#f3f6ff;color:#182037;padding:24px}.box{max-width:560px;margin:12vh auto;background:#fff;padding:24px;border-radius:20px;box-shadow:0 12px 40px #1e3a8a1a}a{color:#315ee8}</style><div class="box"><h1>Tệp tải lên quá lớn</h1><p>Kích thước yêu cầu vượt giới hạn PHP hiện tại (' . tms_h(ini_get('post_max_size')) . ').</p><p>Chạy <code>bash ~/tms-os/scripts/repair.sh</code> để áp dụng giới hạn tải lên của TMS OS rồi thử lại.</p><p><a href="/files">Quay lại TMS Explorer</a></p></div></html>';
    exit;
}

$sessionPath = $basePath . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}
session_save_path($sessionPath);
session_name('TMSSESSID');
session_start();

$router = new Router();
require $basePath . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
