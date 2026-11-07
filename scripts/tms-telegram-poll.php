<?php
declare(strict_types=1);

/**
 * tms-telegram-poll.php — Nhận lệnh Telegram bằng POLLING qua PHP CLI
 * (V17.1.0-beta.5). Chạy bằng cron mỗi phút qua tms-telegram-poll.sh.
 *
 * Vì sao polling thay webhook: webhook phải đi qua Nginx/PHP-FPM của panel,
 * panel treo (502) là bot mất liên lạc đúng lúc cần /restart nhất (sự cố
 * 07/11/2026). Poller này chạy độc lập: chỉ cần crond + mạng còn sống là
 * bot vẫn nhận lệnh, kể cả khi toàn bộ tầng web đang sập.
 *
 * An toàn:
 * - Chỉ xử lý khi người dùng đã bật Lệnh Telegram trong panel
 *   (state enabled trong ~/.tms-os/telegram-webhook.json).
 * - Toàn bộ kiểm tra chat đích, chống xử lý trùng và phân quyền nằm trong
 *   TelegramCommandService::processIncomingUpdate() — poller chỉ là kênh
 *   vận chuyển, xác thực bằng chính bí mật cục bộ của service.
 * - Token bot không bao giờ được in ra log.
 */

if (php_sapi_name() !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$home = getenv('HOME') ?: '/data/data/com.termux/files/home';
$stateFile = $home . '/.tms-os/telegram-webhook.json';

$readState = static function () use ($stateFile): array {
    $state = @json_decode((string)@file_get_contents($stateFile), true);
    return is_array($state) ? $state : [];
};
$saveStatePatch = static function (array $patch) use ($stateFile, $readState): void {
    $state = array_merge($readState(), $patch);
    file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($stateFile, 0600);
};

$state = $readState();
if (empty($state['enabled'])) {
    exit(0);
}

// Nạp cùng bộ class như public/index.php để hành vi CLI khớp hệt panel.
require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Core/NginxCompatibility.php';
foreach (['AuthService', 'UnifiedSystemCoreService', 'SystemService', 'FileManagerService', 'WebsiteHealthService', 'WebsiteMetadataService', 'WebsiteService', 'DatabaseService', 'BackupService', 'AutoBackupService', 'LogService', 'NetworkService', 'TerminalService', 'DiagnosticsService', 'PluginService', 'CronJobService', 'OperationalAlertsService', 'MonitoringService', 'UpdateService', 'ModuleService', 'ServiceManagerService', 'GuardianService', 'CloudflareDomainService', 'TelegramCommandService', 'AccessReportService', 'SqlQueryService'] as $class) {
    require $root . '/app/Services/' . $class . '.php';
}
$appConfig = @require $root . '/config/app.php';
if (is_array($appConfig) && !empty($appConfig['timezone'])) {
    date_default_timezone_set((string)$appConfig['timezone']);
}

$apiBase = rtrim((string)(getenv('TMS_TELEGRAM_API_BASE') ?: 'https://api.telegram.org'), '/');
$api = static function (string $token, string $method, array $data) use ($apiBase): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false];
    }
    $ch = curl_init($apiBase . '/bot' . $token . '/' . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $payload = is_string($body) ? json_decode($body, true) : null;
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($payload) || empty($payload['ok'])) {
        return ['ok' => false];
    }
    return $payload;
};

try {
    $cron = new CronJobService();
    $config = $cron->getTelegramConfig();
    $token = trim((string)($config['token'] ?? ''));
    if ($token === '' || trim((string)($config['chat_id'] ?? '')) === '') {
        exit(0);
    }

    // Tự chuyển đổi từ bản webhook cũ: nếu webhook còn đăng ký thì
    // getUpdates sẽ bị Telegram từ chối (HTTP 409) — gỡ một lần tại đây.
    if (($state['mode'] ?? '') !== 'polling') {
        $info = $api($token, 'getWebhookInfo', []);
        if (!empty($info['ok']) && !empty($info['result']['url'])) {
            $api($token, 'deleteWebhook', ['drop_pending_updates' => 'false']);
        }
        $saveStatePatch(['mode' => 'polling']);
        $state = $readState();
    }

    // Bí mật cục bộ để poller gọi vào processIncomingUpdate() như một
    // webhook nội bộ đáng tin cậy. Thiếu/hỏng thì tự sinh lại (chỉ tiến
    // trình trên chính thiết bị mới đọc được file state 0600 này).
    $secret = (string)($state['secret'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $secret) !== 1) {
        $secret = bin2hex(random_bytes(32));
        $saveStatePatch(['secret' => $secret]);
    }

    $offset = (int)($state['last_update_id'] ?? 0) + 1;
    $response = $api($token, 'getUpdates', [
        'offset' => $offset,
        'timeout' => 0,
        'limit' => 20,
        'allowed_updates' => json_encode(['message', 'callback_query'], JSON_UNESCAPED_SLASHES),
    ]);
    if (empty($response['ok']) || !is_array($response['result'] ?? null)) {
        exit(0);
    }

    $service = new TelegramCommandService(
        $cron,
        new MonitoringService(new SystemService()),
        new CloudflareDomainService(),
        null,
        null,
    );
    foreach ($response['result'] as $update) {
        if (is_array($update)) {
            $service->processIncomingUpdate($update, $secret);
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[telegram-poll] ' . $e->getMessage() . "\n");
    exit(0);
}
exit(0);
