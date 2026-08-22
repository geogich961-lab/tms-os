<?php
declare(strict_types=1);

/**
 * Xử lý lệnh Telegram qua webhook HTTPS. Mọi bí mật chỉ lưu cục bộ trong
 * ~/.tms-os và tuyệt đối không xuất hiện trong trạng thái trả về panel.
 */
final class TelegramCommandService
{
    private string $home;
    private string $stateFile;
    private $transport;

    public function __construct(
        private CronJobService $cron,
        private MonitoringService $monitoring,
        private CloudflareDomainService $cloudflare,
        ?callable $transport = null,
    ) {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        @mkdir($this->home . '/.tms-os', 0700, true);
        $this->stateFile = $this->home . '/.tms-os/telegram-webhook.json';
        $this->transport = $transport;
    }

    /** Trạng thái đã làm sạch, an toàn để hiển thị trong panel. */
    public function status(): array
    {
        $config = $this->cron->getTelegramConfig();
        $state = $this->readState();
        $configured = trim((string)($config['token'] ?? '')) !== ''
            && trim((string)($config['chat_id'] ?? '')) !== '';
        $panelUrl = $this->cloudflare->publicPanelUrl();
        $enabled = !empty($state['enabled']) && $this->secretFromState($state) !== '';

        return [
            'configured' => $configured,
            'panel_configured' => $panelUrl !== '',
            'ready' => $configured && $panelUrl !== '',
            'enabled' => $enabled,
            'activated_at' => $enabled ? (string)($state['activated_at'] ?? '') : '',
            'last_update_at' => (string)($state['last_update_at'] ?? ''),
        ];
    }

    /** Đăng ký webhook Telegram với URL panel HTTPS đã cấu hình cục bộ. */
    public function enable(): array
    {
        $config = $this->requireTelegramConfig();
        $panelUrl = $this->cloudflare->publicPanelUrl();
        if ($panelUrl === '') {
            throw new RuntimeException('Chưa có tên miền HTTPS cho panel. Hãy bật Remote Access trong Cloudflare Hosting trước.');
        }

        $secret = bin2hex(random_bytes(32));
        $response = $this->telegramRequest((string)$config['token'], 'setWebhook', [
            'url' => rtrim($panelUrl, '/') . '/telegram/webhook',
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message'], JSON_UNESCAPED_SLASHES),
            'drop_pending_updates' => 'false',
        ]);
        if (empty($response['ok'])) {
            throw new RuntimeException('Telegram không xác nhận đăng ký webhook. Hãy kiểm tra lại kết nối HTTPS và cấu hình bot.');
        }

        $this->writeState([
            'enabled' => true,
            'secret' => $secret,
            'activated_at' => date('c'),
            'last_update_id' => 0,
            'last_update_at' => '',
        ]);

        return $this->status() + ['message' => 'Đã bật lệnh /status qua Telegram.'];
    }

    /** Gỡ webhook trên Telegram và xoá bí mật webhook cục bộ. */
    public function disable(): array
    {
        $config = $this->requireTelegramConfig();
        $response = $this->telegramRequest((string)$config['token'], 'deleteWebhook', [
            'drop_pending_updates' => 'false',
        ]);
        if (empty($response['ok'])) {
            throw new RuntimeException('Telegram không xác nhận tắt webhook. Hãy thử lại khi kết nối Internet ổn định.');
        }

        $this->writeState([
            'enabled' => false,
            'activated_at' => '',
            'last_update_id' => 0,
            'last_update_at' => '',
        ]);

        return $this->status() + ['message' => 'Đã tắt lệnh /status qua Telegram.'];
    }

    /**
     * Xử lý một Update đã được controller giải mã. Dữ liệu sai hoặc không được
     * phép luôn bị bỏ qua im lặng, tránh biến endpoint thành kênh dò thông tin.
     */
    public function processIncomingUpdate(array $update, string $providedSecret): array
    {
        $state = $this->readState();
        $secret = $this->secretFromState($state);
        if (empty($state['enabled']) || $secret === '' || !hash_equals($secret, $providedSecret)) {
            return ['handled' => false, 'sent' => false];
        }

        $message = $update['message'] ?? null;
        $updateId = isset($update['update_id']) && is_int($update['update_id']) ? $update['update_id'] : null;
        if (!is_array($message) || $updateId === null) {
            return ['handled' => false, 'sent' => false];
        }

        $config = $this->cron->getTelegramConfig();
        $expectedChatId = trim((string)($config['chat_id'] ?? ''));
        $receivedChatId = trim((string)($message['chat']['id'] ?? ''));
        if ($expectedChatId === '' || $receivedChatId === '' || !hash_equals($expectedChatId, $receivedChatId)) {
            return ['handled' => false, 'sent' => false];
        }

        if ($updateId <= (int)($state['last_update_id'] ?? 0)) {
            return ['handled' => false, 'sent' => false];
        }

        $command = trim((string)($message['text'] ?? ''));
        if (!preg_match('/^\/(status|help)(?:@[A-Za-z0-9_]{5,32})?$/i', $command, $matches)) {
            return ['handled' => false, 'sent' => false];
        }

        // Ghi ID trước khi gửi để Telegram retry không tạo phản hồi trùng lặp.
        $state['last_update_id'] = $updateId;
        $state['last_update_at'] = date('c');
        $this->writeState($state);

        $name = strtolower((string)$matches[1]);
        $text = $name === 'help' ? $this->helpReport() : $this->statusReport();
        $result = $this->sendConfiguredMessage($text);

        return ['handled' => true, 'sent' => !empty($result['ok'])];
    }

    /** Gửi tới đúng Chat ID đã lưu, không bao giờ dùng Chat ID từ Update nhận vào. */
    public function sendConfiguredMessage(string $text): array
    {
        $config = $this->requireTelegramConfig();
        $response = $this->telegramRequest((string)$config['token'], 'sendMessage', [
            'chat_id' => (string)$config['chat_id'],
            'text' => $this->limitText($text),
            'disable_web_page_preview' => 'true',
        ]);

        return ['ok' => !empty($response['ok'])];
    }

    private function statusReport(): string
    {
        $snapshot = $this->monitoring->snapshot(true);
        $device = is_array($snapshot['device'] ?? null) ? $snapshot['device'] : [];
        $details = is_array($snapshot['details'] ?? null) ? $snapshot['details'] : [];
        $battery = is_array($details['battery'] ?? null) ? $details['battery'] : [];
        $network = is_array($details['network'] ?? null) ? $details['network'] : [];
        $services = is_array($snapshot['services'] ?? null) ? $snapshot['services'] : [];
        $jobs = $this->cron->all();
        $enabledJobs = count(array_filter($jobs, static fn(array $job): bool => !array_key_exists('enabled', $job) || (bool)$job['enabled']));
        $successfulJobs = count(array_filter($jobs, static fn(array $job): bool => ($job['last_status'] ?? '') === 'success'));

        $model = $this->cleanValue((string)($device['model'] ?? 'Không xác định'), 70);
        $android = $this->cleanValue((string)($device['android_version'] ?? ''), 24);
        $api = $this->cleanValue((string)($device['api'] ?? ''), 12);
        $build = $this->buildLabel();
        $batteryLine = 'Không khả dụng';
        if (isset($battery['percentage']) && is_numeric($battery['percentage'])) {
            $batteryLine = (int)$battery['percentage'] . '%';
            $batteryStatus = $this->cleanValue((string)($battery['status'] ?? ''), 28);
            if ($batteryStatus !== '') {
                $batteryLine .= ' · ' . $batteryStatus;
            }
        }
        $temp = $details['temperature'] ?? $battery['temperature'] ?? null;
        $temperatureLine = is_numeric($temp) ? number_format((float)$temp, 1, '.', '') . '°C' : 'Không khả dụng';

        $serviceLines = [];
        foreach (array_slice($services, 0, 8, true) as $label => $running) {
            $safeLabel = $this->cleanValue((string)$label, 28);
            if ($safeLabel !== '') {
                $serviceLines[] = '• ' . $safeLabel . ': ' . ((bool)$running ? 'Đang chạy' : 'Đã dừng');
            }
        }
        if ($serviceLines === []) {
            $serviceLines[] = '• Chưa có dữ liệu dịch vụ';
        }

        $androidLine = $android !== '' ? $android . ($api !== '' ? ' (API ' . $api . ')' : '') : 'Không xác định';
        $report = [
            'TMS OS · Trạng thái thiết bị',
            '',
            'Thiết bị: ' . $model,
            'Android: ' . $androidLine,
            'Bản xây dựng: ' . $build,
            '',
            'Pin: ' . $batteryLine,
            'Nhiệt độ: ' . $temperatureLine,
            'RAM: ' . (int)($details['memory_used_mb'] ?? 0) . ' / ' . (int)($details['memory_total_mb'] ?? 0) . ' MB',
            'Lưu trữ: ' . number_format((float)($details['storage_used_gb'] ?? 0), 1, '.', '') . ' / ' . number_format((float)($details['storage_total_gb'] ?? 0), 1, '.', '') . ' GB',
            'Thời gian hoạt động: ' . $this->cleanValue((string)($details['uptime'] ?? ''), 48),
            'Mạng từ lúc khởi động: ↓ ' . number_format((float)($network['rx_mb'] ?? 0), 1, '.', '') . ' MB · ↑ ' . number_format((float)($network['tx_mb'] ?? 0), 1, '.', '') . ' MB',
            'Tiến trình: ' . max(0, (int)($details['processes'] ?? 0)),
            '',
            'Cron: ' . count($jobs) . ' tác vụ · ' . $enabledJobs . ' đang bật · ' . $successfulJobs . ' chạy thành công gần nhất',
            'Dịch vụ TMS:',
            ...$serviceLines,
        ];

        return implode("\n", $report);
    }

    private function helpReport(): string
    {
        return "TMS OS Bot\n\n/status — xem trạng thái an toàn của thiết bị và TMS OS\n/help — xem hướng dẫn ngắn\n\nChỉ chat đã được cấu hình mới có thể dùng các lệnh này.";
    }

    private function requireTelegramConfig(): array
    {
        $config = $this->cron->getTelegramConfig();
        if (trim((string)($config['token'] ?? '')) === '' || trim((string)($config['chat_id'] ?? '')) === '') {
            throw new RuntimeException('Cần lưu Bot Token và Chat ID Telegram trước.');
        }
        return $config;
    }

    private function telegramRequest(string $token, string $method, array $data): array
    {
        if ($this->transport !== null) {
            $response = call_user_func($this->transport, $method, $data);
            return is_array($response) ? $response : ['ok' => false];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false];
        }

        $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $payload = is_string($body) ? json_decode($body, true) : null;

        return ['ok' => $httpCode >= 200 && $httpCode < 300 && is_array($payload) && !empty($payload['ok'])];
    }

    private function buildLabel(): string
    {
        $app = @include dirname(__DIR__, 2) . '/config/app.php';
        return is_array($app) ? $this->cleanValue((string)($app['build'] ?? 'TMS OS'), 48) : 'TMS OS';
    }

    private function readState(): array
    {
        $state = @json_decode((string)@file_get_contents($this->stateFile), true);
        return is_array($state) ? $state : [];
    }

    private function writeState(array $state): void
    {
        file_put_contents($this->stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($this->stateFile, 0600);
    }

    private function secretFromState(array $state): string
    {
        $secret = (string)($state['secret'] ?? '');
        return preg_match('/^[a-f0-9]{64}$/', $secret) === 1 ? $secret : '';
    }

    private function cleanValue(string $value, int $max): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($value)) ?: '';
        return $this->limitText($value, $max);
    }

    private function limitText(string $text, int $max = 3900): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max, 'UTF-8');
        }
        return strlen($text) > $max ? substr($text, 0, $max) : $text;
    }
}
