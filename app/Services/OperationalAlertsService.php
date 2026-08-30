<?php
declare(strict_types=1);

/**
 * OperationalAlertsService — cảnh báo vận hành qua Telegram theo ngưỡng.
 *
 * Chạy mỗi 15 phút qua cron job "tms-alerts-check". Thu thập chỉ số đặc thù
 * của điện thoại cắm điện 24/7: dung lượng, RAM, pin 100% quá lâu (nguy cơ
 * phồng pin), nhiệt độ (termux-api nếu có) và Cloudflare Tunnel rớt.
 * Mỗi loại cảnh báo chỉ gửi lại sau khoảng cooldown để không spam.
 */
final class OperationalAlertsService
{
    private string $home;
    private string $configFile;
    private string $stateFile;
    private const CRON_JOB_ID = 'tms-alerts-check';

    /** $cron được truyền động để unit test stub được (cần all/save/delete/sendTelegramNotification). */
    public function __construct(private $cron)
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->configFile = $this->home . '/.tms-os/config/alerts.json';
        $this->stateFile = $this->home . '/.tms-os/config/alerts-state.json';
    }

    public function config(): array
    {
        $data = @json_decode((string)@file_get_contents($this->configFile), true);
        $config = is_array($data) ? $data : [];
        return [
            'enabled' => (bool)($config['enabled'] ?? false),
            'cooldown_minutes' => max(5, min(720, (int)($config['cooldown_minutes'] ?? 60))),
            'storage_min_free_percent' => max(1, min(50, (int)($config['storage_min_free_percent'] ?? 10))),
            'ram_max_percent' => max(50, min(99, (int)($config['ram_max_percent'] ?? 85))),
            'battery_full_max_minutes' => max(30, min(2880, (int)($config['battery_full_max_minutes'] ?? 240))),
            'temp_max_c' => max(35, min(80, (int)($config['temp_max_c'] ?? 48))),
            'check_tunnel' => (bool)($config['check_tunnel'] ?? true),
            'check_battery' => (bool)($config['check_battery'] ?? true),
        ];
    }

    public function saveConfig(array $input): array
    {
        $config = [
            'enabled' => (bool)($input['enabled'] ?? false),
            'cooldown_minutes' => max(5, min(720, (int)($input['cooldown_minutes'] ?? 60))),
            'storage_min_free_percent' => max(1, min(50, (int)($input['storage_min_free_percent'] ?? 10))),
            'ram_max_percent' => max(50, min(99, (int)($input['ram_max_percent'] ?? 85))),
            'battery_full_max_minutes' => max(30, min(2880, (int)($input['battery_full_max_minutes'] ?? 240))),
            'temp_max_c' => max(35, min(80, (int)($input['temp_max_c'] ?? 48))),
            'check_tunnel' => (bool)($input['check_tunnel'] ?? true),
            'check_battery' => (bool)($input['check_battery'] ?? true),
        ];
        $this->writeJson($this->configFile, $config);
        $this->syncCron();
        return $config;
    }

    /** Đăng ký/gỡ cron job chạy mỗi 15 phút theo cấu hình. */
    public function syncCron(): void
    {
        if (!$this->config()['enabled']) {
            $this->cron->delete(self::CRON_JOB_ID);
            return;
        }
        $this->cron->save([
            'id' => self::CRON_JOB_ID,
            'name' => 'TMS Alerts Check',
            'command' => 'php ' . $this->home . '/tms-os/scripts/tms-alerts-check.php',
            'schedule' => '*/15 * * * *',
            'enabled' => true,
            'notify_telegram' => false,
        ]);
    }

    /** Thu thập chỉ số; thiếu nền tảng (Windows, không termux-api) trả null ở mục đó. */
    public function collectMetrics(): array
    {
        $home = $this->home;
        $total = @disk_total_space($home);
        $free = @disk_free_space($home);
        $metrics = [
            'storage_free_percent' => ($total && $free) ? (int)round($free / $total * 100) : null,
            'ram_used_percent' => null,
            'battery' => ['available' => false, 'percentage' => null, 'status' => '', 'temperature_c' => null],
            'tunnel' => ['configured' => is_file($home . '/.tms-os/cloudflare-hosting/config.json'), 'running' => false],
        ];

        $meminfo = @file_get_contents('/proc/meminfo');
        if (is_string($meminfo) && preg_match('/MemTotal:\s+(\d+) kB/', $meminfo, $totalM) && preg_match('/MemAvailable:\s+(\d+) kB/', $meminfo, $availM)) {
            $totalKb = (int)$totalM[1];
            if ($totalKb > 0) {
                $metrics['ram_used_percent'] = (int)round((1 - (int)$availM[1] / $totalKb) * 100);
            }
        }

        $battery = CommandRunner::proc(['termux-battery-status'], 10);
        $data = @json_decode(trim($battery['out']), true);
        if ($battery['code'] === 0 && is_array($data) && isset($data['percentage'])) {
            $metrics['battery'] = [
                'available' => true,
                'percentage' => (int)$data['percentage'],
                'status' => (string)($data['status'] ?? ''),
                'temperature_c' => isset($data['temperature']) ? (float)$data['temperature'] : null,
            ];
        }

        if ($metrics['tunnel']['configured']) {
            $pid = trim((string)@file_get_contents($home . '/.tms-os/cloudflare-hosting/tunnel.pid'));
            if (preg_match('/^\d+$/', $pid) && is_dir('/proc/' . $pid)) {
                $cmdline = @file_get_contents('/proc/' . $pid . '/cmdline');
                $metrics['tunnel']['running'] = is_string($cmdline) && str_contains($cmdline, 'cloudflared');
            }
        }
        return $metrics;
    }

    /**
     * So ngưỡng và chọn cảnh báo cần gửi, tôn trọng cooldown trong state.
     * Hàm thuần để test; không đụng mạng.
     *
     * @return array<string,string> alert_key => message
     */
    public function evaluate(array $config, array $metrics, array &$state): array
    {
        $state['battery_full_since'] = $state['battery_full_since'] ?? null;
        $state['last_sent'] = $state['last_sent'] ?? [];
        $battery = $metrics['battery'] ?? [];
        $isFull = ($battery['available'] ?? false)
            && (($battery['percentage'] ?? 0) >= 99 || ($battery['status'] ?? '') === 'FULL');

        if ($config['check_battery'] && $isFull) {
            if ($state['battery_full_since'] === null) {
                $state['battery_full_since'] = time();
            }
        } else {
            $state['battery_full_since'] = null;
        }

        $alerts = [];
        $cooldown = $config['cooldown_minutes'] * 60;
        $canSend = static fn(string $key): bool => (int)($state['last_sent'][$key] ?? 0) <= time() - $cooldown;

        $storageFree = $metrics['storage_free_percent'] ?? null;
        if ($storageFree !== null && $storageFree <= $config['storage_min_free_percent'] && $canSend('storage')) {
            $alerts['storage'] = '💾 Bộ nhớ còn trống ' . $storageFree . '% (ngưỡng ' . $config['storage_min_free_percent'] . '%). Hãy dọn bớt file hoặc backup rồi xoá.';
        }

        $ramUsed = $metrics['ram_used_percent'] ?? null;
        if ($ramUsed !== null && $ramUsed >= $config['ram_max_percent'] && $canSend('ram')) {
            $alerts['ram'] = '🧠 RAM đang dùng ' . $ramUsed . '% (ngưỡng ' . $config['ram_max_percent'] . '%). Nhiều dịch vụ có thể bị kill ngẫu nhiên.';
        }

        if ($config['check_battery'] && $isFull && $state['battery_full_since'] !== null) {
            $fullMinutes = (int)round((time() - (int)$state['battery_full_since']) / 60);
            if ($fullMinutes >= $config['battery_full_max_minutes'] && $canSend('battery')) {
                $hours = (int)round($fullMinutes / 60);
                $alerts['battery'] = '🔋 Pin đã đầy ' . $hours . ' giờ liên tục. Pin sạc 100% quá lâu dễ phồng — nên ngắt sạc một lát nếu không dùng.';
            }
        }

        $temp = $battery['temperature_c'] ?? null;
        if ($temp !== null && $temp >= $config['temp_max_c'] && $canSend('temp')) {
            $alerts['temp'] = '🌡️ Nhiệt độ pin ' . $temp . '°C (ngưỡng ' . $config['temp_max_c'] . '°C). Kiểm tra chỗ thoát nhiệt của thiết bị.';
        }

        if ($config['check_tunnel'] && ($metrics['tunnel']['configured'] ?? false) && !($metrics['tunnel']['running'] ?? true) && $canSend('tunnel')) {
            $alerts['tunnel'] = '🌐 Cloudflare Tunnel đã cấu hình nhưng cloudflared không chạy. Website công khai đang offline — vào Guardian/Cloudflare để khởi động lại.';
        }

        foreach (array_keys($alerts) as $key) {
            $state['last_sent'][$key] = time();
        }
        return $alerts;
    }

    /** Một vòng kiểm tra: thu thập → đánh giá → gửi Telegram → lưu state. */
    public function run(): array
    {
        $config = $this->config();
        $state = @json_decode((string)@file_get_contents($this->stateFile), true);
        $state = is_array($state) ? $state : [];
        $metrics = $this->collectMetrics();
        $alerts = $config['enabled'] ? $this->evaluate($config, $metrics, $state) : [];
        if ($alerts !== []) {
            $message = "⚠️ TMS OS cảnh báo vận hành\n" . implode("\n", $alerts);
            try {
                $this->cron->sendTelegramNotification($message);
            } catch (Throwable) {
                // Không cho lỗi gửi Telegram chặn vòng kiểm tra.
            }
        }
        $state['last_run'] = date('c');
        $state['last_alerts'] = array_keys($alerts);
        $state['last_metrics'] = $metrics;
        $this->writeJson($this->stateFile, $state);
        return ['ok' => true, 'alerts' => $alerts, 'metrics' => $metrics];
    }

    public function status(): array
    {
        $state = @json_decode((string)@file_get_contents($this->stateFile), true);
        $registered = false;
        foreach ((array)$this->cron->all() as $job) {
            if ((string)($job['id'] ?? '') === self::CRON_JOB_ID) {
                $registered = true;
            }
        }
        return [
            'config' => $this->config(),
            'state' => is_array($state) ? $state : null,
            'cron_registered' => $registered,
            'termux_api_available' => CommandRunner::proc(['termux-battery-status'], 5)['code'] === 0,
        ];
    }

    private function writeJson(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX) !== false) {
            @chmod($tmp, 0600);
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }
}
