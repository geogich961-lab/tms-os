<?php
declare(strict_types=1);

final class CronJobService
{
    private string $home;
    private string $cronFile;
    private string $telegramConfigFile;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        @mkdir($this->home . '/.tms-os', 0700, true);
        $this->cronFile = $this->home . '/.tms-os/cron-jobs.json';
        $this->telegramConfigFile = $this->home . '/.tms-os/telegram-config.json';
    }

    public function all(): array
    {
        $data = @json_decode((string)@file_get_contents($this->cronFile), true);
        return is_array($data) ? array_values($data) : [];
    }

    public function save(array $job): void
    {
        $jobs = $this->readJobs();
        $id = $job['id'] ?? bin2hex(random_bytes(8));
        $jobs[$id] = [
            'id' => $id,
            'name' => $job['name'] ?? 'Unnamed Job',
            'command' => $job['command'] ?? '',
            'schedule' => $job['schedule'] ?? '* * * * *',
            'enabled' => (bool)($job['enabled'] ?? true),
            'notify_telegram' => (bool)($job['notify_telegram'] ?? false),
            'last_run' => $job['last_run'] ?? null,
            'last_status' => $job['last_status'] ?? null,
            'created_at' => $job['created_at'] ?? date('c'),
        ];
        $this->writeJobs($jobs);
        $this->syncCrontab();
    }

    public function delete(string $id): void
    {
        $jobs = $this->readJobs();
        if (isset($jobs[$id])) {
            unset($jobs[$id]);
            $this->writeJobs($jobs);
            $this->syncCrontab();
        }
    }

    public function getTelegramConfig(): array
    {
        $data = @json_decode((string)@file_get_contents($this->telegramConfigFile), true);
        return is_array($data) ? $data : ['token' => '', 'chat_id' => ''];
    }

    public function saveTelegramConfig(string $token, string $chatId): void
    {
        file_put_contents($this->telegramConfigFile, json_encode([
            'token' => $token,
            'chat_id' => $chatId,
            'updated_at' => date('c')
        ], JSON_PRETTY_PRINT));
        @chmod($this->telegramConfigFile, 0600);
    }

    public function sendTelegramNotification(string $message): bool
    {
        $config = $this->getTelegramConfig();
        if (empty($config['token']) || empty($config['chat_id'])) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$config['token']}/sendMessage";
        $data = [
            'chat_id' => $config['chat_id'],
            'text' => "🔔 *TMS OS Cron Notification*\n\n{$message}",
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response !== false;
    }

    private function syncCrontab(): void
    {
        $jobs = $this->all();
        // crond không kế thừa PATH/HOME của PHP-CGI. Khai báo rõ môi trường
        // Termux giúp wrapper tìm được php và đọc đúng dữ liệu người dùng.
        $prefix = getenv('PREFIX') ?: dirname($this->home) . '/usr';
        $phpBinary = $prefix . '/bin/php';
        $shellBinary = $prefix . '/bin/bash';
        $lines = [
            '# TMS OS Managed Cron Jobs - DO NOT EDIT MANUALLY',
            'SHELL=' . $shellBinary,
            'PATH=' . $prefix . '/bin:/system/bin:/system/xbin',
            'HOME=' . $this->home,
        ];
        
        foreach ($jobs as $job) {
            if (!$job['enabled']) continue;
            
            // Tất cả job đều qua wrapper để trạng thái lần chạy cuối được cập nhật.
            // Wrapper chỉ gửi Telegram khi job bật notify_telegram.
            $wrapperPath = $this->home . '/tms-os/scripts/cron-wrapper.php';
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($wrapperPath) . ' ' . escapeshellarg($job['id']);
            
            $lines[] = "{$job['schedule']} {$cmd}";
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'tms-cron');
        file_put_contents($tmpFile, implode("\n", $lines) . "\n");
        if (!self::hasCommand('crontab')) {
            @unlink($tmpFile);
            throw new RuntimeException('Chưa cài Cron runtime. Hãy chạy: pkg install cronie');
        }
        exec("crontab " . escapeshellarg($tmpFile) . ' 2>&1', $output, $exitCode);
        @unlink($tmpFile);
        if ($exitCode !== 0) {
            throw new RuntimeException('Không thể lưu lịch Cron: ' . trim(implode(' ', $output)));
        }
        $engine = $this->home . '/tms-os/scripts/tms-cron-engine.sh';
        exec('bash ' . escapeshellarg($engine) . ' start 2>&1', $engineOutput, $engineCode);
        if ($engineCode !== 0) {
            throw new RuntimeException('Không thể khởi động Cron engine: ' . trim(implode(' ', $engineOutput)));
        }
    }

    private static function hasCommand(string $command): bool
    {
        exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null', $output, $code);
        return $code === 0;
    }

    private function readJobs(): array
    {
        $data = @json_decode((string)@file_get_contents($this->cronFile), true);
        return is_array($data) ? $data : [];
    }

    private function writeJobs(array $jobs): void
    {
        file_put_contents($this->cronFile, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
