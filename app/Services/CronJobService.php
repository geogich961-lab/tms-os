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
        return array_values($this->readJobs());
    }

    public function save(array $job): void
    {
        $jobs = $this->readJobs();

        // Biểu mẫu tạo mới luôn gửi trường hidden id="". Chuỗi rỗng không được
        // dùng làm khóa JSON hoặc truyền vào cron-wrapper.php.
        $id = $this->validJobId((string)($job['id'] ?? ''))
            ? strtolower(trim((string)$job['id']))
            : $this->newJobId($jobs);
        $existing = $jobs[$id] ?? [];

        $jobs[$id] = [
            'id' => $id,
            'name' => $job['name'] ?? 'Unnamed Job',
            'command' => $job['command'] ?? '',
            'schedule' => $job['schedule'] ?? '* * * * *',
            'enabled' => (bool)($job['enabled'] ?? true),
            'notify_telegram' => (bool)($job['notify_telegram'] ?? false),
            'last_run' => $job['last_run'] ?? ($existing['last_run'] ?? null),
            'last_status' => $job['last_status'] ?? ($existing['last_status'] ?? null),
            'telegram_last_status' => $job['telegram_last_status'] ?? ($existing['telegram_last_status'] ?? null),
            'telegram_last_message' => $job['telegram_last_message'] ?? ($existing['telegram_last_message'] ?? null),
            'telegram_last_sent_at' => $job['telegram_last_sent_at'] ?? ($existing['telegram_last_sent_at'] ?? null),
            'created_at' => $job['created_at'] ?? ($existing['created_at'] ?? date('c')),
        ];
        $this->writeJobs($jobs);
        $this->syncCrontab();
    }

    /**
     * Chuẩn hóa dữ liệu cũ, sau đó tạo lại crontab quản lý và bảo đảm Cron engine chạy.
     */
    public function repairRuntime(): void
    {
        $this->readJobs();
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

    /**
     * Chỉ trả về trạng thái kỹ thuật đã làm sạch; tuyệt đối không trả về token,
     * URL API đầy đủ hoặc nội dung phản hồi thô từ Telegram.
     *
     * @return array{ok: bool, status: string, message: string}
     */
    public function sendTelegramNotification(string $message): array
    {
        $config = $this->getTelegramConfig();
        if (empty($config['token']) || empty($config['chat_id'])) {
            return [
                'ok' => false,
                'status' => 'not_configured',
                'message' => 'Chưa có Bot Token hoặc Chat ID.',
            ];
        }
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'status' => 'runtime_error',
                'message' => 'Termux PHP chưa có tiện ích cURL.',
            ];
        }

        $url = "https://api.telegram.org/bot{$config['token']}/sendMessage";
        $data = [
            'chat_id' => $config['chat_id'],
            // Không dùng Markdown: output của lệnh Cron có thể chứa ký tự làm Telegram từ chối.
            'text' => "🔔 TMS OS Cron Notification\n\n{$message}",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'status' => 'network_error',
                'message' => 'Không kết nối được Telegram' . ($curlError !== '' ? ': ' . $curlError : '.'),
            ];
        }

        $payload = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && is_array($payload) && !empty($payload['ok'])) {
            return [
                'ok' => true,
                'status' => 'sent',
                'message' => 'Telegram đã xác nhận nhận thông báo.',
            ];
        }

        $description = is_array($payload) ? trim((string)($payload['description'] ?? '')) : '';
        $description = preg_replace('/[\r\n\t]+/', ' ', $description) ?: '';
        $description = substr($description, 0, 160);
        return [
            'ok' => false,
            'status' => 'api_error',
            'message' => $description !== ''
                ? 'Telegram từ chối yêu cầu: ' . $description
                : 'Telegram không xác nhận nhận thông báo (HTTP ' . $httpCode . ').',
        ];
    }

    private function syncCrontab(): void
    {
        $jobs = $this->all();
        // crond không kế thừa PATH/HOME của PHP-CGI. Khai báo rõ môi trường
        // Termux giúp wrapper tìm được PHP và đọc đúng dữ liệu người dùng.
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
            if (empty($job['enabled'])) {
                continue;
            }

            // Tất cả job qua wrapper để ghi lần chạy cuối và trạng thái.
            $wrapperPath = $this->home . '/tms-os/scripts/cron-wrapper.php';
            $cmd = escapeshellarg($phpBinary) . ' '
                . escapeshellarg($wrapperPath) . ' '
                . escapeshellarg((string)$job['id']);
            $lines[] = "{$job['schedule']} {$cmd}";
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'tms-cron');
        if ($tmpFile === false) {
            throw new RuntimeException('Không thể tạo tệp cron tạm thời.');
        }
        file_put_contents($tmpFile, implode("\n", $lines) . "\n");
        if (!self::hasCommand('crontab')) {
            @unlink($tmpFile);
            throw new RuntimeException('Chưa cài Cron runtime. Hãy chạy: pkg install cronie');
        }
        exec('crontab ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $exitCode);
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
        if (!is_array($data)) {
            return [];
        }

        $jobs = [];
        $changed = false;
        foreach ($data as $key => $job) {
            if (!is_array($job)) {
                $changed = true;
                continue;
            }

            $candidate = (string)($job['id'] ?? $key);
            $id = $this->validJobId($candidate)
                ? strtolower(trim($candidate))
                : $this->newJobId($jobs);

            if ((string)$key !== $id || (string)($job['id'] ?? '') !== $id) {
                $changed = true;
            }
            if (isset($jobs[$id])) {
                $id = $this->newJobId($jobs);
                $changed = true;
            }

            $job['id'] = $id;
            $jobs[$id] = $job;
        }

        if ($changed) {
            $this->writeJobs($jobs);
        }

        return $jobs;
    }

    private function validJobId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{16,64}$/i', trim($id)) === 1;
    }

    private function newJobId(array $jobs): string
    {
        do {
            $id = bin2hex(random_bytes(8));
        } while (isset($jobs[$id]));

        return $id;
    }

    private function writeJobs(array $jobs): void
    {
        file_put_contents($this->cronFile, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($this->cronFile, 0600);
    }
}
