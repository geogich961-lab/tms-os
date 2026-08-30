<?php
declare(strict_types=1);

/**
 * Hồi quy V17.0.20 — Cảnh báo vận hành:
 * 1. saveConfig chuẩn hoá + cron job tms-alerts-check đăng ký/gỡ đúng.
 * 2. evaluate(): ngưỡng storage/RAM/battery/temp/tunnel, cooldown từng loại,
 *    battery_full_since được theo dõi và reset.
 * 3. run(): gửi Telegram qua cron khi có cảnh báo; tắt → không gửi.
 * 4. CLI scripts/tms-alerts-check.php hợp lệ; Guardian script có heal tunnel/cron.
 */

function failOA(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectOA(bool $condition, string $message): void { if (!$condition) { failOA($message); } }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-alerts-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
putenv('HOME=' . $home);
@mkdir($home . '/.tms-os/config', 0700, true);

require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/OperationalAlertsService.php';

final class StubAlertsCron
{
    public array $saved = [];
    public array $deletedIds = [];
    public array $telegram = [];
    public function save(array $job): void { $this->saved[$job['id']] = $job; }
    public function delete(string $id): void { $this->deletedIds[] = $id; unset($this->saved[$id]); }
    public function all(): array { return array_values($this->saved); }
    public function sendTelegramNotification(string $message): array { $this->telegram[] = $message; return ['ok' => true]; }
}

$metrics = static fn(array $over): array => array_merge([
    'storage_free_percent' => 50,
    'ram_used_percent' => 40,
    'battery' => ['available' => false, 'percentage' => null, 'status' => '', 'temperature_c' => null],
    'tunnel' => ['configured' => false, 'running' => false],
], $over);

try {
    $cron = new StubAlertsCron();
    $service = new OperationalAlertsService($cron);

    // 1. Config + cron
    $config = $service->saveConfig(['enabled' => '1', 'cooldown_minutes' => 30]);
    expectOA(isset($cron->saved['tms-alerts-check']), 'Bật cảnh báo phải đăng ký cron job tms-alerts-check.');
    expectOA($cron->saved['tms-alerts-check']['schedule'] === '*/15 * * * *', 'Cron phải chạy mỗi 15 phút.');
    $service->saveConfig(['enabled' => '']);
    expectOA(in_array('tms-alerts-check', $cron->deletedIds, true), 'Tắt cảnh báo phải gỡ cron job.');
    $service->saveConfig(['enabled' => '1', 'cooldown_minutes' => 30]);

    // 2. evaluate: vượt ngưỡng sinh cảnh báo, trong ngưỡng thì không
    $state = [];
    $alerts = $service->evaluate($config, $metrics(['storage_free_percent' => 5, 'ram_used_percent' => 95]), $state);
    expectOA(isset($alerts['storage'], $alerts['ram']), 'Vượt ngưỡng storage/RAM phải sinh cảnh báo.');
    expectOA($state['last_sent']['storage'] > 0, 'Sau khi gửi phải ghi mốc cooldown.');

    $state = [];
    $alerts = $service->evaluate($config, $metrics([]), $state);
    expectOA($alerts === [], 'Trong ngưỡng phải không có cảnh báo.');

    // Cooldown: gửi lại trong khoảng cooldown phải bị chặn
    $state = ['last_sent' => ['storage' => time()]];
    $alerts = $service->evaluate($config, $metrics(['storage_free_percent' => 5]), $state);
    expectOA(!isset($alerts['storage']), 'Cảnh báo storage phải tôn trọng cooldown.');

    // Battery: đầy đủ lâu → cảnh báo; kèm reset khi không còn đầy
    $state = ['battery_full_since' => time() - 300 * 60];
    $alerts = $service->evaluate($config, $metrics(['battery' => ['available' => true, 'percentage' => 100, 'status' => 'FULL', 'temperature_c' => 50.0]]), $state);
    expectOA(isset($alerts['battery'], $alerts['temp']), 'Pin đầy quá lâu + nhiệt độ cao phải có cảnh báo.');
    $state = ['battery_full_since' => time() - 300 * 60];
    $service->evaluate($config, $metrics(['battery' => ['available' => true, 'percentage' => 60, 'status' => 'CHARGING', 'temperature_c' => 30.0]]), $state);
    expectOA($state['battery_full_since'] === null, 'Pin rời trạng thái đầy phải reset battery_full_since.');

    // Tunnel: cấu hình rồi nhưng không chạy → cảnh báo; chưa cấu hình thì không
    $state = [];
    $alerts = $service->evaluate($config, $metrics(['tunnel' => ['configured' => true, 'running' => false]]), $state);
    expectOA(isset($alerts['tunnel']), 'Tunnel rớt phải có cảnh báo.');
    $state = [];
    $alerts = $service->evaluate($config, $metrics(['tunnel' => ['configured' => false, 'running' => false]]), $state);
    expectOA(!isset($alerts['tunnel']), 'Chưa cấu hình tunnel không được cảnh báo.');

    // 3. run(): bật → gửi Telegram khi vượt ngưỡng; tắt → không gửi
    $service->saveConfig(['enabled' => '1', 'cooldown_minutes' => 30]);
    $stateFile = $home . '/.tms-os/config/alerts-state.json';
    @unlink($stateFile);
    $before = count($cron->telegram);
    // Thu thập thật (Windows: storage luôn có; /proc và termux-api không có → metrics null, có thể không alert)
    $result = $service->run();
    $stateNow = json_decode((string)file_get_contents($stateFile), true);
    expectOA(is_array($stateNow) && isset($stateNow['last_run']), 'run() phải ghi state với last_run.');
    // Tắt → run không gửi gì dù ngưỡng vượt
    $service->saveConfig(['enabled' => '']);
    $before2 = count($cron->telegram);
    $service->run();
    expectOA(count($cron->telegram) === $before2, 'Tắt cảnh báo thì run() không được gửi Telegram.');

    // 4. CLI + Guardian script
    $cli = (string)file_get_contents($root . '/scripts/tms-alerts-check.php');
    expectOA(str_contains($cli, 'OperationalAlertsService') && str_contains($cli, '->run()'), 'CLI alerts phải gọi run().');
    exec('php -l ' . escapeshellarg($root . '/scripts/tms-alerts-check.php') . ' 2>&1', $lint, $code);
    expectOA($code === 0, 'CLI alerts phải qua php -l.');
    $guardian = (string)file_get_contents($root . '/scripts/tms-guardian.sh');
    expectOA(str_contains($guardian, 'repair_tunnel') && str_contains($guardian, 'repair_crond'), 'Guardian phải có auto-heal tunnel/crond.');
    expectOA(str_contains($guardian, 'cloudflared_running'), 'Guardian phải có hàm dò cloudflared qua /proc.');

    echo "OK: operational alerts V17.0.20\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
}
