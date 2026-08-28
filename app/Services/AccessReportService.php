<?php
declare(strict_types=1);

/**
 * Tổng hợp access log Nginx theo phần mới thêm và gửi báo cáo quản trị về
 * đúng chat Telegram đã lưu. Không gửi URL, query string, user-agent hay log thô.
 */
final class AccessReportService
{
    private const MAX_BYTES_PER_FILE = 2_000_000;
    private const MAX_IPS_PER_MESSAGE = 42;
    private const MAX_REPORT_MESSAGES = 3;
    private const REPORT_TIMEZONE = 'Asia/Ho_Chi_Minh';

    private string $home;
    private string $configFile;
    private string $stateFile;
    private string $lockFile;

    public function __construct(
        private CronJobService $cron,
        private TelegramCommandService $telegram,
    ) {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $dir = $this->home . '/.tms-os';
        @mkdir($dir, 0700, true);
        $this->configFile = $dir . '/access-report-config.json';
        $this->stateFile = $dir . '/access-report-state.json';
        $this->lockFile = $dir . '/access-report.lock';
    }

    /** Trạng thái làm sạch, phù hợp hiển thị trong panel. */
    public function status(): array
    {
        $config = $this->readJson($this->configFile);
        $state = $this->readJson($this->stateFile);
        $telegram = $this->cron->getTelegramConfig();
        $configured = trim((string)($telegram['token'] ?? '')) !== '' && trim((string)($telegram['chat_id'] ?? '')) !== '';
        $job = $this->scheduledJob();

        return [
            'configured' => $configured,
            'enabled' => !empty($config['enabled']),
            'scheduled' => $job !== null && !empty($job['enabled']),
            'last_run_at' => $this->cleanTimestamp((string)($state['last_run_at'] ?? '')),
            'last_sent_at' => $this->cleanTimestamp((string)($state['last_sent_at'] ?? '')),
            'last_status' => $this->cleanStatus((string)($state['last_status'] ?? '')),
        ];
    }

    /** Bật lịch gửi, chụp mốc cuối log để không gửi lịch sử truy cập cũ. */
    public function enable(): array
    {
        $telegram = $this->cron->getTelegramConfig();
        if (trim((string)($telegram['token'] ?? '')) === '' || trim((string)($telegram['chat_id'] ?? '')) === '') {
            throw new RuntimeException('Cần lưu Bot Token và Chat ID Telegram trước.');
        }

        $this->ensureTrustedVisitorIpConfiguration();
        $this->baselineLogs();
        $this->writeJson($this->configFile, [
            'enabled' => true,
            'activated_at' => $this->reportTimestamp(),
        ]);
        $this->ensureScheduledJob(true);

        return $this->status() + ['message' => 'Đã bật báo cáo truy cập chi tiết mỗi giờ. Báo cáo đầu tiên sẽ chỉ lấy lượt truy cập phát sinh sau thời điểm này.'];
    }

    /** Tắt lịch nhưng giữ cấu hình Nginx để tiếp tục ghi IP đúng cho các tính năng khác. */
    public function disable(): array
    {
        $this->writeJson($this->configFile, ['enabled' => false, 'activated_at' => '']);
        $this->ensureScheduledJob(false);
        return $this->status() + ['message' => 'Đã tắt báo cáo truy cập theo giờ.'];
    }

    /** Chạy từ worker Cron. Chỉ commit offset sau khi Telegram xác nhận đã nhận. */
    public function runHourly(): array
    {
        $config = $this->readJson($this->configFile);
        if (empty($config['enabled'])) {
            return ['ok' => true, 'status' => 'disabled'];
        }

        try {
            // Bản cập nhật có thể bổ sung log format mới khi lịch đã bật sẵn.
            $this->ensureTrustedVisitorIpConfiguration();
        } catch (Throwable) {
            $state = $this->readJson($this->stateFile);
            $state['last_run_at'] = $this->reportTimestamp();
            $state['last_status'] = 'nginx_config_failed';
            $this->writeJson($this->stateFile, $state);
            return ['ok' => false, 'status' => 'nginx_config_failed'];
        }

        $lock = @fopen($this->lockFile, 'c');
        if ($lock === false || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) @fclose($lock);
            return ['ok' => true, 'status' => 'busy'];
        }

        try {
            $state = $this->readJson($this->stateFile);
            [$summary, $nextFiles] = $this->collectNewAccess($state);
            $messages = $this->formatReportMessages($summary);
            foreach ($messages as $message) {
                $sent = $this->telegram->sendConfiguredMessage($message);
                if (empty($sent['ok'])) {
                    $state['last_run_at'] = $this->reportTimestamp();
                    $state['last_status'] = 'send_failed';
                    $this->writeJson($this->stateFile, $state);
                    return ['ok' => false, 'status' => 'send_failed'];
                }
            }

            $state['files'] = $nextFiles;
            $state['last_run_at'] = $this->reportTimestamp();
            $state['last_sent_at'] = $this->reportTimestamp();
            $state['last_status'] = $summary['requests'] > 0 ? 'sent' : 'sent_empty';
            $this->writeJson($this->stateFile, $state);
            return ['ok' => true, 'status' => (string)$state['last_status'], 'requests' => $summary['requests']];
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /** Chạy thử cùng luồng thật; chỉ dữ liệu mới sau lần bật gần nhất được báo cáo. */
    public function sendTest(): array
    {
        $config = $this->readJson($this->configFile);
        if (empty($config['enabled'])) {
            throw new RuntimeException('Hãy bật báo cáo truy cập theo giờ trước khi gửi thử.');
        }
        $result = $this->runHourly();
        if (empty($result['ok'])) {
            throw new RuntimeException('Telegram chưa xác nhận báo cáo thử. Hãy kiểm tra kết nối và cấu hình bot.');
        }
        return $this->status() + ['message' => 'Đã gửi báo cáo thử với các lượt truy cập mới hiện có.'];
    }

    private function collectNewAccess(array $state): array
    {
        $summary = ['requests' => 0, 'ips' => [], 'destinations' => [], 'status' => ['4xx' => 0, '5xx' => 0], 'truncated_files' => 0];
        $oldFiles = is_array($state['files'] ?? null) ? $state['files'] : [];
        $nextFiles = [];

        foreach ($this->accessLogFiles() as $path) {
            $stat = @stat($path);
            if (!is_array($stat)) continue;
            $size = max(0, (int)($stat['size'] ?? 0));
            $identity = (string)($stat['dev'] ?? '') . ':' . (string)($stat['ino'] ?? '');
            $previous = is_array($oldFiles[$path] ?? null) ? $oldFiles[$path] : [];
            $offset = $identity !== '' && $identity === (string)($previous['identity'] ?? '') && $size >= (int)($previous['offset'] ?? 0)
                ? (int)$previous['offset']
                : 0;
            if ($size - $offset > self::MAX_BYTES_PER_FILE) {
                $offset = max(0, $size - self::MAX_BYTES_PER_FILE);
                $summary['truncated_files']++;
            }

            $handle = @fopen($path, 'rb');
            if ($handle === false) continue;
            @fseek($handle, $offset);
            $label = $this->labelForLog($path);
            while (($line = fgets($handle)) !== false) {
                $this->addLogLine($summary, $label, $line);
            }
            @fclose($handle);
            $nextFiles[$path] = ['identity' => $identity, 'offset' => $size];
        }

        return [$summary, $nextFiles];
    }

    private function addLogLine(array &$summary, string $label, string $line): void
    {
        if (!preg_match('/^([^\s]+)\s+\S+\s+\S+\s+\[[^\]]+\]\s+"[^"]*"\s+(\d{3})\s+/', $line, $matches)) return;
        $ip = trim($matches[1]);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) return;
        $status = (int)$matches[2];

        $summary['requests']++;
        $summary['ips'][$ip] = (int)($summary['ips'][$ip] ?? 0) + 1;
        if (!isset($summary['destinations'][$label])) $summary['destinations'][$label] = ['requests' => 0, 'ips' => []];
        $summary['destinations'][$label]['requests']++;
        $summary['destinations'][$label]['ips'][$ip] = (int)($summary['destinations'][$label]['ips'][$ip] ?? 0) + 1;
        if ($status >= 500) $summary['status']['5xx']++;
        elseif ($status >= 400) $summary['status']['4xx']++;
    }

    /** @return list<string> */
    private function formatReportMessages(array $summary): array
    {
        $now = $this->reportNow()->format('H:i · d/m/Y');
        $destinations = $summary['destinations'];
        uasort($destinations, static fn(array $a, array $b): int => $b['requests'] <=> $a['requests']);
        $lines = [
            'TMS OS · Báo cáo truy cập theo giờ',
            'Mốc gửi: ' . $now,
            '',
            'Tổng: ' . (int)$summary['requests'] . ' yêu cầu · ' . count($summary['ips']) . ' IP duy nhất',
            'Phản hồi: ' . (int)$summary['status']['4xx'] . ' × 4xx · ' . (int)$summary['status']['5xx'] . ' × 5xx',
            '',
            'Theo đích:',
        ];
        if ($destinations === []) {
            $lines[] = '• Chưa ghi nhận request mới kể từ lần báo cáo gần nhất.';
        } else {
            foreach ($destinations as $label => $data) {
                $lines[] = '• ' . $label . ': ' . (int)$data['requests'] . ' yêu cầu · ' . count($data['ips']) . ' IP';
            }
        }
        if (!empty($summary['truncated_files'])) {
            $lines[] = '';
            $lines[] = 'Lưu ý: một số log tăng quá nhanh; chỉ phần mới nhất trong kỳ được tổng hợp.';
        }

        arsort($summary['ips'], SORT_NUMERIC);
        $ipEntries = [];
        foreach ($summary['ips'] as $ip => $count) {
            $targets = [];
            foreach ($destinations as $label => $data) {
                if (isset($data['ips'][$ip])) $targets[] = $label . ': ' . (int)$data['ips'][$ip];
            }
            $ipEntries[] = '• ' . $ip . ' — ' . (int)$count . ' yêu cầu' . ($targets ? ' (' . implode(', ', $targets) . ')' : '');
        }
        if ($ipEntries === []) return [implode("\n", $lines)];

        $messages = [];
        $chunks = array_chunk($ipEntries, self::MAX_IPS_PER_MESSAGE);
        $shown = array_slice($chunks, 0, self::MAX_REPORT_MESSAGES);
        foreach ($shown as $index => $chunk) {
            $prefix = $index === 0 ? array_merge($lines, ['', 'IP hoạt động:']) : ['TMS OS · Báo cáo truy cập (tiếp)'];
            $messages[] = $this->limitTelegramText(implode("\n", array_merge($prefix, $chunk)));
        }
        if (count($chunks) > self::MAX_REPORT_MESSAGES) {
            $remaining = max(0, count($ipEntries) - self::MAX_IPS_PER_MESSAGE * self::MAX_REPORT_MESSAGES);
            $messages[count($messages) - 1] .= "\n… Còn {$remaining} IP không hiển thị để giới hạn tin nhắn.";
        }
        return $messages;
    }

    private function reportNow(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::REPORT_TIMEZONE));
    }

    private function reportTimestamp(): string
    {
        return $this->reportNow()->format(DATE_ATOM);
    }

    private function baselineLogs(): void
    {
        $files = [];
        foreach ($this->accessLogFiles() as $path) {
            $stat = @stat($path);
            if (!is_array($stat)) continue;
            $files[$path] = [
                'identity' => (string)($stat['dev'] ?? '') . ':' . (string)($stat['ino'] ?? ''),
                'offset' => max(0, (int)($stat['size'] ?? 0)),
            ];
        }
        $this->writeJson($this->stateFile, ['files' => $files, 'last_run_at' => '', 'last_sent_at' => '', 'last_status' => 'waiting']);
    }

    /** @return list<string> */
    private function accessLogFiles(): array
    {
        $dir = $this->home . '/logs/nginx';
        $files = [];
        foreach (glob($dir . '/*-access.log') ?: [] as $path) {
            $base = basename($path);
            if ($base === 'tms-access.log' || $base === 'default-access.log' || preg_match('/^[a-z0-9][a-z0-9_-]{0,63}-access\.log$/i', $base)) $files[] = $path;
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function labelForLog(string $path): string
    {
        $base = basename($path);
        if ($base === 'tms-access.log') return 'Panel TMS OS';
        if ($base === 'default-access.log') return 'Website mặc định';
        $name = preg_replace('/-access\.log$/', '', $base) ?: 'không xác định';
        return 'Website: ' . substr($name, 0, 64);
    }

    private function ensureScheduledJob(bool $enabled): void
    {
        $job = $this->scheduledJob();
        if ($job === null) {
            if (!$enabled) return;
            $this->cron->save([
                'name' => 'TMS OS · Báo cáo truy cập Telegram mỗi giờ',
                'command' => $this->workerCommand(),
                'schedule' => '0 * * * *',
                'enabled' => true,
                'notify_telegram' => false,
            ]);
            return;
        }
        $job['enabled'] = $enabled;
        $job['notify_telegram'] = false;
        $this->cron->save($job);
    }

    private function scheduledJob(): ?array
    {
        $command = $this->workerCommand();
        foreach ($this->cron->all() as $job) {
            if (trim((string)($job['command'] ?? '')) === $command) return $job;
        }
        return null;
    }

    private function workerCommand(): string
    {
        $prefix = getenv('PREFIX') ?: dirname($this->home) . '/usr';
        return escapeshellarg($prefix . '/bin/php') . ' ' . escapeshellarg($this->home . '/tms-os/scripts/access-report.php');
    }

    /**
     * Chỉ tin IP header khi cloudflared kết nối từ loopback. Access log dùng
     * CF-Connecting-IP trước, rồi mới dùng IP đầu của X-Forwarded-For khi
     * Cloudflare không gửi CF header. Truy cập LAN không thể giả hai header này.
     */
    private function ensureTrustedVisitorIpConfiguration(): void
    {
        $prefix = getenv('PREFIX') ?: dirname($this->home) . '/usr';
        $nginx = $prefix . '/bin/nginx';
        $configPath = $prefix . '/etc/nginx/nginx.conf';
        $content = @file_get_contents($configPath);
        if (!is_string($content) || $content === '') throw new RuntimeException('Không tìm thấy cấu hình Nginx để bật ghi nhận IP khách.');
        if (!is_executable($nginx)) throw new RuntimeException('Không tìm thấy Nginx Termux để kiểm tra cấu hình IP khách.');

        $block = "    # TMS OS access-report real-ip begin\n"
            . "    set_real_ip_from 127.0.0.1;\n"
            . "    set_real_ip_from ::1;\n"
            . "    real_ip_header CF-Connecting-IP;\n"
            . "    real_ip_recursive off;\n"
            . "    # TMS OS access-report real-ip end\n";
        $hasSafeExistingConfig = preg_match('/set_real_ip_from\s+127\.0\.0\.1\s*;/', $content) === 1
            && preg_match('/set_real_ip_from\s+::1\s*;/', $content) === 1
            && preg_match('/real_ip_header\s+CF-Connecting-IP\s*;/', $content) === 1
            && preg_match('/real_ip_recursive\s+off\s*;/', $content) === 1;
        // V16.1.1 repair đã tạo đúng map/log_format nhưng chưa mang marker của
        // migration này. Nhận diện bằng đủ ba directive thực tế, thay vì marker,
        // để không chèn trùng map/log_format rồi khiến nginx -t thất bại.
        $hasAccessFormat = preg_match('/\bmap\s+\$realip_remote_addr\s+\$tms_from_cloudflared\s*\{/', $content) === 1
            && preg_match('/\bmap\s+"?\$tms_from_cloudflared:\$http_cf_connecting_ip:\$http_x_forwarded_for"?\s+\$tms_access_client\s*\{/', $content) === 1
            && preg_match('/\blog_format\s+tms_access\b/', $content) === 1
            && str_contains($content, '$tms_access_client');

        $formatBlock = "    # TMS OS access-report format begin\n"
            . "    map \$realip_remote_addr \$tms_from_cloudflared {\n"
            . "        127.0.0.1 1;\n"
            . "        ::1 1;\n"
            . "        default 0;\n"
            . "    }\n"
            . "    map \"\$tms_from_cloudflared:\$http_cf_connecting_ip:\$http_x_forwarded_for\" \$tms_access_client {\n"
            . "        ~^1:(?<tms_cf_ip>[0-9][0-9]?\\.[0-9][0-9]?\\.[0-9][0-9]?\\.[0-9][0-9]?|[0-9A-Fa-f:]+): \$tms_cf_ip;\n"
            . "        ~^1::(?<tms_fallback_ip>[^,\\s]+)(?:\\s*,|\\s*$) \$tms_fallback_ip;\n"
            . "        default \$remote_addr;\n"
            . "    }\n"
            . "    log_format tms_access '\$tms_access_client - \$remote_user [\$time_local] \"\$request\" \$status \$body_bytes_sent \"\$http_referer\" \"\$http_user_agent\"';\n"
            . "    # TMS OS access-report format end\n";

        if ($hasSafeExistingConfig && $hasAccessFormat) {
            return;
        }
        $updated = preg_replace('/\n?\s*# TMS OS access-report real-ip begin.*?# TMS OS access-report real-ip end\n?/s', "\n", $content);
        $updated = is_string($updated)
            ? preg_replace('/\n?\s*# TMS OS access-report format begin.*?# TMS OS access-report format end\n?/s', "\n", $updated)
            : null;
        if (!is_string($updated)) throw new RuntimeException('Không thể chuẩn bị cấu hình Nginx cho IP khách.');
        $insert = ($hasSafeExistingConfig ? '' : $block) . $formatBlock;
        $updated = preg_replace('/(http\s*\{\s*\n?)/', "$1" . $insert, $updated, 1, $replacements);
        if (!is_string($updated) || $replacements !== 1) throw new RuntimeException('Không tìm thấy khối http trong cấu hình Nginx.');

        $updates = [$configPath => $updated];
        $logRoot = preg_quote(rtrim($this->home, '/') . '/logs/nginx/', '/');
        foreach (glob($prefix . '/etc/nginx/sites-enabled/*.conf') ?: [] as $siteConfig) {
            $siteContent = @file_get_contents($siteConfig);
            if (!is_string($siteContent)) continue;
            $siteUpdated = preg_replace('/(access_log\s+' . $logRoot . '[^;\s]*-access\.log)(?:\s+\w+)?;/', '$1 tms_access;', $siteContent);
            if (is_string($siteUpdated) && $siteUpdated !== $siteContent) $updates[$siteConfig] = $siteUpdated;
        }

        $backupDir = $this->home . '/.tms-os/backups';
        @mkdir($backupDir, 0700, true);
        $originals = [];
        foreach ($updates as $path => $newContent) {
            $original = $path === $configPath ? $content : (string)@file_get_contents($path);
            $originals[$path] = $original;
            $backup = $backupDir . '/nginx-before-access-report-' . date('Ymd_His') . '-' . basename($path);
            if (@file_put_contents($backup, $original, LOCK_EX) === false || @chmod($backup, 0600) === false || @file_put_contents($path, $newContent, LOCK_EX) === false) {
                foreach ($originals as $rollbackPath => $rollbackContent) @file_put_contents($rollbackPath, $rollbackContent, LOCK_EX);
                throw new RuntimeException('Không thể ghi cấu hình Nginx an toàn cho báo cáo truy cập.');
            }
        }

        exec(escapeshellarg($nginx) . ' -t -c ' . escapeshellarg($configPath) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            foreach ($originals as $rollbackPath => $rollbackContent) @file_put_contents($rollbackPath, $rollbackContent, LOCK_EX);
            throw new RuntimeException('Nginx không hỗ trợ cấu hình IP khách an toàn trên máy này; không bật báo cáo.');
        }
        exec(escapeshellarg($nginx) . ' -c ' . escapeshellarg($configPath) . ' -s reload 2>&1', $reloadOutput, $reloadCode);
        if ($reloadCode !== 0) {
            foreach ($originals as $rollbackPath => $rollbackContent) @file_put_contents($rollbackPath, $rollbackContent, LOCK_EX);
            exec(escapeshellarg($nginx) . ' -c ' . escapeshellarg($configPath) . ' -s reload 2>&1');
            throw new RuntimeException('Không thể reload Nginx sau khi kiểm tra cấu hình IP khách.');
        }
    }

    private function readJson(string $path): array
    {
        $data = @json_decode((string)@file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function writeJson(string $path, array $data): void
    {
        if (@file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new RuntimeException('Không thể lưu trạng thái báo cáo truy cập.');
        }
        @chmod($path, 0600);
    }

    private function cleanTimestamp(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) === 1 ? $value : '';
    }

    private function cleanStatus(string $value): string
    {
        return in_array($value, ['waiting', 'sent', 'sent_empty', 'send_failed', 'nginx_config_failed'], true) ? $value : '';
    }

    private function limitTelegramText(string $text): string
    {
        return function_exists('mb_substr') ? mb_substr($text, 0, 3500, 'UTF-8') : substr($text, 0, 3500);
    }
}
