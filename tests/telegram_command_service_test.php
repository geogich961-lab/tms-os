<?php
declare(strict_types=1);

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
$commands = new TelegramCommandService($cron, new MonitoringService(new SystemService()), new CloudflareDomainService(), $transport);
$commands->enable();
$enableCall = $calls[0] ?? [];
$sanitizedStatus = json_encode($commands->status());
$noSecretInStatus = !str_contains((string)$sanitizedStatus, 'test-token-must-not-leak') && !str_contains((string)$sanitizedStatus, 'secret');
$calls = [];

$state = json_decode((string)file_get_contents($home . '/.tms-os/telegram-webhook.json'), true);
$secret = (string)($state['secret'] ?? '');
$missingSecret = $commands->processIncomingUpdate(['update_id' => 1, 'message' => ['chat' => ['id' => '123456789'], 'text' => '/status']], '');
$invalidSecret = $commands->processIncomingUpdate(['update_id' => 2, 'message' => ['chat' => ['id' => '123456789'], 'text' => '/status']], 'wrong');
$wrongChat = $commands->processIncomingUpdate(['update_id' => 3, 'message' => ['chat' => ['id' => '999'], 'text' => '/status']], $secret);
$unsupported = $commands->processIncomingUpdate(['update_id' => 4, 'message' => ['chat' => ['id' => '123456789'], 'text' => '/unknown']], $secret);
$valid = $commands->processIncomingUpdate(['update_id' => 5, 'message' => ['chat' => ['id' => '123456789'], 'text' => '/status']], $secret);
$duplicate = $commands->processIncomingUpdate(['update_id' => 5, 'message' => ['chat' => ['id' => '123456789'], 'text' => '/status']], $secret);

$sendCalls = array_values(array_filter($calls, static fn(array $call): bool => $call['method'] === 'sendMessage'));
$message = (string)($sendCalls[0]['data']['text'] ?? '');
$ok = $noSecretInStatus
    && ($enableCall['method'] ?? '') === 'setWebhook'
    && ($enableCall['data']['url'] ?? '') === 'https://panel.example.test/telegram/webhook'
    && ($enableCall['data']['allowed_updates'] ?? '') === '["message"]'
    && empty($missingSecret['handled']) && empty($invalidSecret['handled']) && empty($wrongChat['handled']) && empty($unsupported['handled'])
    && !empty($valid['handled']) && !empty($valid['sent']) && empty($duplicate['handled'])
    && count($sendCalls) === 1
    && (string)($sendCalls[0]['data']['chat_id'] ?? '') === '123456789'
    && strlen($message) > 30 && strlen($message) <= 4096
    && !str_contains($message, 'test-token-must-not-leak');

exec('rm -rf ' . escapeshellarg($base));
if (!$ok) {
    fwrite(STDERR, "Telegram command service test failed.\n");
    exit(1);
}
echo "Telegram command service test passed.\n";
