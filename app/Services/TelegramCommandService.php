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
    private ?object $updates;

    public function __construct(
        private CronJobService $cron,
        private MonitoringService $monitoring,
        private CloudflareDomainService $cloudflare,
        ?callable $transport = null,
        ?object $updates = null,
    ) {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        @mkdir($this->home . '/.tms-os', 0700, true);
        $this->stateFile = $this->home . '/.tms-os/telegram-webhook.json';
        $this->transport = $transport;
        $this->updates = $updates;
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
            'allowed_updates' => json_encode(['message', 'callback_query'], JSON_UNESCAPED_SLASHES),
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

        $updateId = isset($update['update_id']) && is_int($update['update_id']) ? $update['update_id'] : null;
        $event = $this->incomingEvent($update);
        if ($event === null || $updateId === null) {
            return ['handled' => false, 'sent' => false];
        }

        $config = $this->cron->getTelegramConfig();
        $expectedChatId = trim((string)($config['chat_id'] ?? ''));
        $receivedChatId = trim((string)($event['chat_id'] ?? ''));
        if ($expectedChatId === '' || $receivedChatId === '' || !hash_equals($expectedChatId, $receivedChatId)) {
            return ['handled' => false, 'sent' => false];
        }

        if ($this->wasProcessed($state, $updateId)) {
            return ['handled' => false, 'sent' => false];
        }

        if ($event['type'] === 'callback') {
            $this->markProcessed($state, $updateId);
            $sent = $this->handleUpdateCallback($event);
            return ['handled' => true, 'sent' => $sent];
        }

        $command = trim((string)($event['text'] ?? ''));
        if (preg_match('/^\/(status|help|checkupdate)(?:@[A-Za-z0-9_]{5,32})?$/i', $command, $matches)) {
            $this->markProcessed($state, $updateId);
            $name = strtolower((string)$matches[1]);
            $sent = match ($name) {
                'help' => !empty($this->sendConfiguredMessage($this->helpReport())['ok']),
                'status' => !empty($this->sendConfiguredMessage($this->statusReport())['ok']),
                'checkupdate' => $this->handleCheckUpdate((string)$event['user_id']),
            };
            return ['handled' => true, 'sent' => $sent];
        }

        if ($command === '' || str_starts_with($command, '/') || (string)$event['user_id'] === ''
            || !$this->updateService()->hasPendingTelegramUpdateChallenge($expectedChatId, (string)$event['user_id'])) {
            return ['handled' => false, 'sent' => false];
        }

        // Đánh dấu event trước side effect để Telegram retry không thể enqueue trùng.
        $this->markProcessed($state, $updateId);
        return ['handled' => true, 'sent' => $this->handlePasswordMessage((string)$event['user_id'], $command)];
    }

    /** Gửi tới đúng Chat ID đã lưu, không bao giờ dùng Chat ID từ Update nhận vào. */
    public function sendConfiguredMessage(string $text, ?array $replyMarkup = null): array
    {
        $config = $this->requireTelegramConfig();
        $payload = [
            'chat_id' => (string)$config['chat_id'],
            'text' => $this->limitText($text),
            'disable_web_page_preview' => 'true',
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $response = $this->telegramRequest((string)$config['token'], 'sendMessage', $payload);

        return ['ok' => !empty($response['ok'])];
    }

    private function handleCheckUpdate(string $userId): bool
    {
        if ($userId === '') {
            return false;
        }
        $updates = $this->updateService();
        $check = $updates->check();
        $current = $this->cleanValue((string)($check['current'] ?? 'unknown'), 48);
        if (($check['error'] ?? null) !== null) {
            return !empty($this->sendConfiguredMessage("TMS OS · Kiểm tra cập nhật\n\nĐang chạy: V{$current}\nChưa thể kiểm tra bản mới. Hãy thử lại sau.")['ok']);
        }
        $available = is_array($check['available'] ?? null) ? $check['available'] : null;
        $version = $this->cleanValue((string)($available['version'] ?? ''), 48);
        if ($version === '') {
            return !empty($this->sendConfiguredMessage("TMS OS · Kiểm tra cập nhật\n\nĐang chạy: V{$current}\nBạn đang sử dụng bản mới nhất.")['ok']);
        }
        if (empty($updates->updatePasswordStatus()['configured'])) {
            return !empty($this->sendConfiguredMessage("TMS OS · Kiểm tra cập nhật\n\nĐang chạy: V{$current}\nCó bản mới: V{$version}\n\nĐể cập nhật qua Telegram, hãy thiết lập Mật khẩu nâng cấp Telegram trong Update Center trước.")['ok']);
        }

        $nonce = bin2hex(random_bytes(8));
        $config = $this->cron->getTelegramConfig();
        $chatId = trim((string)($config['chat_id'] ?? ''));
        $updates->createTelegramUpdateOffer($chatId, $userId, $version, $nonce);
        return !empty($this->sendConfiguredMessage(
            "TMS OS · Kiểm tra cập nhật\n\nĐang chạy: V{$current}\nCó bản mới: V{$version}\n\nChọn Cập nhật để tiếp tục xác thực mật khẩu nâng cấp.",
            ['inline_keyboard' => [[
                ['text' => 'Cập nhật', 'callback_data' => 'u:' . $nonce],
                ['text' => 'Bỏ qua', 'callback_data' => 's:' . $nonce],
            ]]],
        )['ok']);
    }

    private function handleUpdateCallback(array $event): bool
    {
        $callbackId = (string)($event['callback_id'] ?? '');
        $data = (string)($event['data'] ?? '');
        $chatId = (string)($event['chat_id'] ?? '');
        $userId = (string)($event['user_id'] ?? '');
        if ($callbackId === '' || $userId === '' || !preg_match('/^([us]):([a-f0-9]{16,64})$/', $data, $matches)) {
            return $this->answerCallback($callbackId, 'Thao tác không còn hợp lệ.');
        }

        if ($matches[1] === 's') {
            $result = $this->updateService()->skipTelegramUpdateOffer($chatId, $userId, $matches[2]);
            if (empty($result['ok'])) {
                return $this->answerCallback($callbackId, 'Yêu cầu đã hết hạn hoặc đã được xử lý.');
            }
            $acknowledged = $this->answerCallback($callbackId, 'Đã bỏ qua bản cập nhật này.');
            $sent = $this->sendConfiguredMessage('Đã bỏ qua yêu cầu cập nhật hiện tại. Bạn có thể dùng /checkupdate bất cứ lúc nào.')['ok'];
            return $acknowledged || !empty($sent);
        }

        $result = $this->updateService()->beginTelegramUpdateChallenge($chatId, $userId, $matches[2]);
        if (empty($result['ok'])) {
            $message = match ((string)($result['code'] ?? '')) {
                'password_unconfigured' => 'Chưa thiết lập mật khẩu nâng cấp.',
                'no_longer_available' => 'Bản mới không còn khả dụng.',
                default => 'Yêu cầu đã hết hạn hoặc đã được xử lý.',
            };
            return $this->answerCallback($callbackId, $message);
        }
        $acknowledged = $this->answerCallback($callbackId, 'Hãy gửi mật khẩu nâng cấp trong 5 phút.');
        $sent = $this->sendConfiguredMessage("Xác thực cập nhật\n\nHãy gửi mật khẩu nâng cấp Telegram trong 5 phút. Mật khẩu sai sẽ không thể cập nhật; sau 3 lần sai, yêu cầu sẽ tự hủy. Tin nhắn mật khẩu có thể vẫn lưu trong lịch sử chat, vì vậy hãy xóa thủ công sau khi bot phản hồi.")['ok'];
        return $acknowledged || !empty($sent);
    }

    private function handlePasswordMessage(string $userId, string $password): bool
    {
        $config = $this->cron->getTelegramConfig();
        $result = $this->updateService()->authorizeTelegramUpdate(trim((string)($config['chat_id'] ?? '')), $userId, $password);
        $message = match ((string)($result['code'] ?? '')) {
            'queued' => 'Đã xác thực. Yêu cầu cập nhật đã được xếp hàng an toàn; TMS OS sẽ tải, kiểm checksum, sao lưu và xác nhận trước khi hoàn tất.',
            'wrong_password' => 'Mật khẩu nâng cấp không đúng. Còn ' . max(0, (int)($result['remaining'] ?? 0)) . ' lần thử.',
            'locked' => 'Đã nhập sai quá số lần cho phép. Yêu cầu cập nhật đã bị hủy.',
            'no_longer_available' => 'Không có bản mới để cập nhật. Hãy dùng /checkupdate để kiểm tra lại.',
            'expired' => 'Yêu cầu xác thực đã hết hạn. Hãy dùng /checkupdate để bắt đầu lại.',
            default => 'Không thể xếp hàng cập nhật. Bản đang chạy vẫn được giữ nguyên; hãy kiểm tra Update Center.',
        };
        return !empty($this->sendConfiguredMessage($message)['ok']);
    }

    private function answerCallback(string $callbackId, string $text): bool
    {
        if ($callbackId === '') {
            return false;
        }
        $config = $this->requireTelegramConfig();
        $response = $this->telegramRequest((string)$config['token'], 'answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => mb_substr($text, 0, 180),
        ]);
        return !empty($response['ok']);
    }

    private function incomingEvent(array $update): ?array
    {
        if (is_array($update['message'] ?? null)) {
            $message = $update['message'];
            return [
                'type' => 'message',
                'chat_id' => trim((string)($message['chat']['id'] ?? '')),
                'user_id' => trim((string)($message['from']['id'] ?? '')),
                'text' => (string)($message['text'] ?? ''),
            ];
        }
        if (is_array($update['callback_query'] ?? null)) {
            $callback = $update['callback_query'];
            return [
                'type' => 'callback',
                'chat_id' => trim((string)($callback['message']['chat']['id'] ?? '')),
                'user_id' => trim((string)($callback['from']['id'] ?? '')),
                'callback_id' => (string)($callback['id'] ?? ''),
                'data' => (string)($callback['data'] ?? ''),
            ];
        }
        return null;
    }

    private function wasProcessed(array $state, int $updateId): bool
    {
        $ids = array_map('intval', (array)($state['processed_update_ids'] ?? []));
        return $updateId <= (int)($state['last_update_id'] ?? 0) || in_array($updateId, $ids, true);
    }

    private function markProcessed(array &$state, int $updateId): void
    {
        $ids = array_values(array_unique(array_map('intval', (array)($state['processed_update_ids'] ?? []))));
        $ids[] = $updateId;
        $state['processed_update_ids'] = array_slice(array_values(array_unique($ids)), -64);
        $state['last_update_id'] = max((int)($state['last_update_id'] ?? 0), $updateId);
        $state['last_update_at'] = date('c');
        $this->writeState($state);
    }

    private function updateService(): object
    {
        return $this->updates ??= new UpdateService();
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
        return "TMS OS Bot\n\n/status — xem trạng thái an toàn của thiết bị và TMS OS\n/checkupdate — kiểm tra bản mới và yêu cầu cập nhật có xác thực\n/help — xem hướng dẫn ngắn\n\nChỉ chat đã được cấu hình mới có thể dùng các lệnh này.";
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
