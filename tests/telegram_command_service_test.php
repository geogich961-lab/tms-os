<?php
declare(strict_types=1);

final class FakeTelegramUpdateService
{
    public array $check = ['current' => '17.0.13', 'available' => null, 'error' => null];
    public bool $passwordConfigured = false;
    public array $calls = [];
    public array $beginResult = ['ok' => true, 'code' => 'password_pending'];
    public array $skipResult = ['ok' => true, 'code' => 'skipped'];
    public bool $pending = false;
    public array $authorizeResult = ['ok' => false, 'code' => 'expired'];

    public function check(): array { return $this->check; }
    public function updatePasswordStatus(): array { return ['configured' => $this->passwordConfigured]; }
    public function createTelegramUpdateOffer(string $chatId, string $userId, string $version, string $nonce): array
    {
        $this->calls[] = ['offer', $chatId, $userId, $version, $nonce];
        return ['ok' => true];
    }
    public function beginTelegramUpdateChallenge(string $chatId, string $userId, string $nonce): array
    {
        $this->calls[] = ['begin', $chatId, $userId, $nonce];
        return $this->beginResult;
    }
    public function skipTelegramUpdateOffer(string $chatId, string $userId, string $nonce): array
    {
        $this->calls[] = ['skip', $chatId, $userId, $nonce];
        return $this->skipResult;
    }
    public function hasPendingTelegramUpdateChallenge(string $chatId, string $userId): bool
    {
        $this->calls[] = ['pending', $chatId, $userId];
        return $this->pending;
    }
    public function authorizeTelegramUpdate(string $chatId, string $userId, string $password): array
    {
        $this->calls[] = ['authorize', $chatId, $userId, $password];
        return $this->authorizeResult;
    }
}

function telegram_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function telegram_update(int $id, string $text, string $chat = '123456789', string $user = '42'): array
{
    return ['update_id' => $id, 'message' => ['chat' => ['id' => $chat], 'from' => ['id' => $user], 'text' => $text]];
}

function telegram_callback(int $id, string $callbackId, string $data, string $chat = '123456789', string $user = '42'): array
{
    return ['update_id' => $id, 'callback_query' => ['id' => $callbackId, 'from' => ['id' => $user], 'data' => $data, 'message' => ['chat' => ['id' => $chat]]]];
}

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-os-telegram-command-' . bin2hex(random_bytes(4));
$home = $base . '/home';
mkdir($home . '/.tms-os/cloudflare-hosting', 0700, true);
putenv('HOME=' . $home);

require $root . '/app/Services/UnifiedSystemCoreService.php';
require $root . '/app/Services/SystemService.php';
require $root . '/app/Services/MonitoringService.php';
require $root . '/app/Services/CronJobService.php';
require $root . '/app/Services/CloudflareDomainService.php';
require $root . '/app/Services/TelegramCommandService.php';

$cron = new CronJobService();
$cron->saveTelegramConfig('test-token-must-not-leak', '123456789');
file_put_contents($home . '/.tms-os/cloudflare-hosting/config.json', json_encode(['panel_hostname' => 'panel.example.test']));
$calls = [];
$transport = static function (string $method, array $data) use (&$calls): array {
    $calls[] = ['method' => $method, 'data' => $data];
    return ['ok' => true];
};
$updates = new FakeTelegramUpdateService();
$commands = new TelegramCommandService($cron, new MonitoringService(new SystemService()), new CloudflareDomainService(), $transport, $updates);
$commands->enable();
$enableCall = $calls[0] ?? [];
$statusJson = json_encode($commands->status());
$secret = (string)(json_decode((string)file_get_contents($home . '/.tms-os/telegram-webhook.json'), true)['secret'] ?? '');
$calls = [];

$missingSecret = $commands->processIncomingUpdate(telegram_update(1, '/status'), '');
$wrongSecret = $commands->processIncomingUpdate(telegram_update(2, '/status'), 'wrong');
$wrongChat = $commands->processIncomingUpdate(telegram_update(3, '/status', '999'), $secret);
$status = $commands->processIncomingUpdate(telegram_update(4, '/status'), $secret);
$help = $commands->processIncomingUpdate(telegram_update(5, '/help'), $secret);
$duplicateStatus = $commands->processIncomingUpdate(telegram_update(4, '/status'), $secret);

$updates->check = ['current' => '17.0.13', 'available' => null, 'error' => null];
$upToDate = $commands->processIncomingUpdate(telegram_update(6, '/checkupdate'), $secret);
$updates->check = ['current' => '17.0.13', 'available' => ['version' => '17.0.14'], 'error' => null];
$unconfigured = $commands->processIncomingUpdate(telegram_update(7, '/checkupdate'), $secret);
$updates->passwordConfigured = true;
$offer = $commands->processIncomingUpdate(telegram_update(8, '/checkupdate'), $secret);
$offerCall = end($calls);
$keyboard = json_decode((string)($offerCall['data']['reply_markup'] ?? ''), true);
$callbackData = (string)($keyboard['inline_keyboard'][0][0]['callback_data'] ?? '');
$nonce = (string)substr($callbackData, 2);

$begin = $commands->processIncomingUpdate(telegram_callback(9, 'callback-update', 'u:' . $nonce), $secret);
$updates->pending = true;
$updates->authorizeResult = ['ok' => false, 'code' => 'wrong_password', 'remaining' => 2];
$wrongPassword = $commands->processIncomingUpdate(telegram_update(10, 'mat-khau-khong-dung'), $secret);
$updates->authorizeResult = ['ok' => true, 'code' => 'queued'];
$correctPassword = $commands->processIncomingUpdate(telegram_update(11, 'mat-khau-dung'), $secret);
$duplicatePassword = $commands->processIncomingUpdate(telegram_update(11, 'mat-khau-dung'), $secret);
$updates->beginResult = ['ok' => false, 'code' => 'expired'];
$expired = $commands->processIncomingUpdate(telegram_callback(12, 'callback-expired', 'u:' . $nonce), $secret);
$skip = $commands->processIncomingUpdate(telegram_callback(13, 'callback-skip', 's:' . $nonce), $secret);

$allText = implode("\n", array_map(static fn(array $call): string => (string)($call['data']['text'] ?? ''), $calls));
$methods = array_column($calls, 'method');
$offerRecorded = array_values(array_filter($updates->calls, static fn(array $call): bool => $call[0] === 'offer'));
$authorizeCalls = array_values(array_filter($updates->calls, static fn(array $call): bool => $call[0] === 'authorize'));
$skipCalls = array_values(array_filter($updates->calls, static fn(array $call): bool => $call[0] === 'skip'));

exec('rm -rf ' . escapeshellarg($base));

telegram_test_assert(!str_contains((string)$statusJson, 'test-token-must-not-leak') && !str_contains((string)$statusJson, 'secret'), 'Trạng thái không được rò token hoặc secret.');
telegram_test_assert(($enableCall['method'] ?? '') === 'setWebhook' && ($enableCall['data']['url'] ?? '') === 'https://panel.example.test/telegram/webhook', 'Webhook phải dùng hostname panel đã cấu hình.');
telegram_test_assert(($enableCall['data']['allowed_updates'] ?? '') === '["message","callback_query"]', 'Webhook phải nhận message và callback_query.');
telegram_test_assert(empty($missingSecret['handled']) && empty($wrongSecret['handled']) && empty($wrongChat['handled']), 'Secret hoặc chat sai phải bị từ chối im lặng.');
telegram_test_assert(!empty($status['sent']) && !empty($help['sent']) && empty($duplicateStatus['handled']), '/status, /help và chống lặp phải tương thích.');
telegram_test_assert(!empty($upToDate['sent']) && !empty($unconfigured['sent']) && !empty($offer['sent']), '/checkupdate phải phản hồi ở mọi trạng thái.');
telegram_test_assert(count($offerRecorded) === 1 && ($offerRecorded[0][1] ?? '') === '123456789' && ($offerRecorded[0][2] ?? '') === '42', 'Đề nghị cập nhật phải gắn với chat và người gửi cấu hình.');
telegram_test_assert(preg_match('/^u:[a-f0-9]{16}$/', $callbackData) === 1 && ($keyboard['inline_keyboard'][0][1]['text'] ?? '') === 'Bỏ qua', 'Keyboard phải dùng nonce opaque và có nút Bỏ qua.');
telegram_test_assert(!empty($begin['handled']) && !empty($wrongPassword['sent']) && !empty($correctPassword['sent']) && empty($duplicatePassword['handled']), 'Callback và mật khẩu phải chỉ được xử lý một lần mỗi update.');
telegram_test_assert(!empty($expired['handled']) && !empty($skip['handled']) && in_array('answerCallbackQuery', $methods, true), 'Callback hết hạn hoặc bỏ qua phải được xác nhận để Telegram bỏ spinner.');
telegram_test_assert(count($authorizeCalls) === 2 && ($authorizeCalls[0][1] ?? '') === '123456789' && ($authorizeCalls[0][2] ?? '') === '42', 'Mật khẩu chỉ được chuyển cho luồng xác thực gắn chat/người gọi.');
telegram_test_assert(count($skipCalls) === 1 && !str_contains($allText, 'mat-khau-khong-dung') && !str_contains($allText, 'mat-khau-dung'), 'Bot tuyệt đối không được lặp lại mật khẩu trong phản hồi.');
telegram_test_assert(!str_contains($allText, 'test-token-must-not-leak'), 'Bot tuyệt đối không được rò token Telegram.');

echo "OK: Telegram /checkupdate, callback and password flow are guarded.\n";
