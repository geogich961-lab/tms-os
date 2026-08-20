<?php
declare(strict_types=1);

/**
 * TMS OS V10.3 Internet Access Center.
 * Kept under the historical class name so old routes/packages remain compatible.
 */
final class CloudflareService
{
    private string $home;
    private string $dir;
    private string $stateFile;
    private string $settingsFile;
    private string $pidFile;
    private string $logFile;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->dir = $this->home . '/.tms-os/internet-access';
        $this->stateFile = $this->dir . '/state.json';
        $this->settingsFile = $this->dir . '/settings.json';
        $this->pidFile = $this->dir . '/tunnel.pid';
        $this->logFile = $this->dir . '/tunnel.log';
        @mkdir($this->dir, 0700, true);
    }

    public function status(): array
    {
        $state = $this->readJson($this->stateFile);
        $running = $this->running();
        $provider = (string)($state['provider'] ?? '');
        $log = $this->tail($this->logFile);
        $url = (string)($state['url'] ?? '');
        if ($url === '') {
            $url = $this->extractUrl($provider, $log);
        }

        if ($running && $url === '' && time() - (int)($state['started_at'] ?? time()) > 25) {
            $state = $this->advanceFallback($state, 'Không nhận được URL công khai từ ' . $this->providerLabel($provider) . '.');
            $running = $this->running();
            $provider = (string)($state['provider'] ?? '');
            $log = $this->tail($this->logFile);
            $url = $this->extractUrl($provider, $log);
        }

        if ($running && $url !== '') {
            $probe = $this->probe($url, $provider);
            $state['url'] = $url;
            $state['http_code'] = $probe['http_code'];
            $state['last_probe_at'] = time();
            if ($probe['healthy']) {
                $state['status'] = 'connected';
                $state['message'] = 'Đã kết nối qua ' . $this->providerLabel($provider) . '. URL công khai phản hồi HTTP ' . $probe['http_code'] . '.';
                $state['health'] = 'healthy';
            } elseif (time() - (int)($state['started_at'] ?? time()) > 35) {
                $state = $this->advanceFallback($state, $probe['message']);
                $running = $this->running();
                $provider = (string)($state['provider'] ?? '');
                $log = $this->tail($this->logFile);
                $url = (string)($state['url'] ?? '');
            } else {
                $state['status'] = 'verifying';
                $state['message'] = $probe['message'];
                $state['health'] = 'pending';
            }
            $this->writeJson($this->stateFile, $state);
        } elseif (!$running && in_array((string)($state['status'] ?? ''), ['starting','connecting','verifying','connected'], true)) {
            $state = $this->advanceFallback($state, $this->friendlyLogError($log));
            $running = $this->running();
            $provider = (string)($state['provider'] ?? '');
            $log = $this->tail($this->logFile);
            $url = (string)($state['url'] ?? '');
        }

        $settings = $this->settings();
        return [
            'running' => $running,
            'status' => (string)($state['status'] ?? 'stopped'),
            'message' => (string)($state['message'] ?? 'Internet Access Center chưa được khởi động.'),
            'provider' => $provider,
            'provider_label' => $this->providerLabel($provider),
            'url' => $url,
            'target' => (string)($state['target'] ?? ''),
            'http_code' => (int)($state['http_code'] ?? 0),
            'health' => (string)($state['health'] ?? 'offline'),
            'attempts' => $state['attempts'] ?? [],
            'log' => $log,
            'capabilities' => $this->capabilities(),
            'settings' => $settings,
        ];
    }

    public function startQuick(string $target, string $protocol = 'auto'): void
    {
        // Backward-compatible entry point: V10.3 auto engine.
        $this->start($target, 'auto', $protocol);
    }

    public function start(string $target, string $provider = 'auto', string $protocol = 'auto'): void
    {
        $this->validateTarget($target);
        if (!$this->targetReachable($target)) {
            throw new RuntimeException('Website nội bộ tại ' . $target . ' chưa phản hồi. Hãy khởi động Nginx và website (Trang chủ → Khởi động tất cả dịch vụ) rồi thử lại.');
        }
        $allowed = ['auto','cloudflare','ngrok','pinggy','localhostrun','relay'];
        if (!in_array($provider, $allowed, true)) {
            $provider = 'auto';
        }
        $queue = $provider === 'auto'
            ? ['cloudflare','pinggy','localhostrun','ngrok','relay']
            : [$provider];
        $state = [
            'status' => 'starting', 'message' => 'Đang chọn đường hầm phù hợp...',
            'target' => $target, 'protocol' => $protocol, 'queue' => $queue,
            'queue_index' => 0, 'attempts' => [], 'started_at' => time(),
            'url' => '', 'health' => 'pending', 'http_code' => 0,
        ];
        $this->terminate();
        $this->launchCurrent($state);
    }

    public function saveSettings(array $input): void
    {
        $settings = $this->settings();
        $newToken = trim((string)($input['ngrok_token'] ?? ''));
        if ($newToken !== '') { $settings['ngrok_token'] = $newToken; }
        $settings['relay_host'] = trim((string)($input['relay_host'] ?? $settings['relay_host'] ?? ''));
        $settings['relay_user'] = trim((string)($input['relay_user'] ?? $settings['relay_user'] ?? ''));
        $settings['relay_ssh_port'] = max(1, min(65535, (int)($input['relay_ssh_port'] ?? $settings['relay_ssh_port'] ?? 22)));
        $settings['relay_remote_port'] = max(1, min(65535, (int)($input['relay_remote_port'] ?? $settings['relay_remote_port'] ?? 10080)));
        $settings['relay_public_url'] = trim((string)($input['relay_public_url'] ?? $settings['relay_public_url'] ?? ''));
        $settings['relay_identity_file'] = trim((string)($input['relay_identity_file'] ?? $settings['relay_identity_file'] ?? ''));
        $this->writeJson($this->settingsFile, $settings);
    }

    public function stop(): void
    {
        $this->terminate();
        $this->writeJson($this->stateFile, [
            'status'=>'stopped','message'=>'Đã dừng kết nối Internet.','provider'=>'','url'=>'',
            'target'=>'','health'=>'offline','http_code'=>0,'attempts'=>[],
        ]);
    }

    private function advanceFallback(array $state, string $reason): array
    {
        $attempts = is_array($state['attempts'] ?? null) ? $state['attempts'] : [];
        $attempts[] = [
            'provider' => (string)($state['provider'] ?? ''),
            'result' => 'failed', 'message' => $reason, 'at' => date('H:i:s'),
        ];
        $state['attempts'] = $attempts;
        $index = (int)($state['queue_index'] ?? 0) + 1;
        $state['queue_index'] = $index;
        $queue = is_array($state['queue'] ?? null) ? $state['queue'] : [];
        $this->terminate();
        if (!isset($queue[$index])) {
            $state['status'] = 'error';
            $state['health'] = 'offline';
            $state['url'] = '';
            $state['message'] = 'Không có nhà cung cấp tunnel nào hoạt động trên mạng hiện tại. ' . $reason;
            $this->writeJson($this->stateFile, $state);
            return $state;
        }
        $state['started_at'] = time();
        $state['url'] = '';
        $state['http_code'] = 0;
        $state['message'] = 'Đang chuyển sang ' . $this->providerLabel((string)$queue[$index]) . '...';
        $this->launchCurrent($state);
        return $this->readJson($this->stateFile);
    }

    private function launchCurrent(array $state): void
    {
        $queue = is_array($state['queue'] ?? null) ? $state['queue'] : [];
        $provider = (string)($queue[(int)($state['queue_index'] ?? 0)] ?? '');
        $target = (string)($state['target'] ?? '');
        while ($provider !== '' && !$this->providerReady($provider)) {
            $attempts = $state['attempts'] ?? [];
            $attempts[] = ['provider'=>$provider,'result'=>'skipped','message'=>'Chưa cài đặt hoặc chưa cấu hình.','at'=>date('H:i:s')];
            $state['attempts'] = $attempts;
            $state['queue_index'] = (int)$state['queue_index'] + 1;
            $provider = (string)($queue[(int)$state['queue_index']] ?? '');
        }
        if ($provider === '') {
            $state['status'] = 'error';
            $state['message'] = 'Không có nhà cung cấp tunnel khả dụng. Hãy cài cloudflared/OpenSSH hoặc cấu hình Ngrok/TMS Relay.';
            $state['health'] = 'offline';
            $this->writeJson($this->stateFile, $state);
            return;
        }

        @unlink($this->logFile);
        $cmd = $this->providerCommand($provider, $target, (string)($state['protocol'] ?? 'auto'));
        $shell = 'nohup sh -c ' . escapeshellarg('exec ' . $cmd) . ' </dev/null >' . escapeshellarg($this->logFile) . ' 2>&1 & echo $!';
        $pid = trim((string)shell_exec($shell));
        if (!ctype_digit($pid)) {
            throw new RuntimeException('Không thể khởi động ' . $this->providerLabel($provider) . '.');
        }
        file_put_contents($this->pidFile, $pid, LOCK_EX);
        $state['provider'] = $provider;
        $state['status'] = 'connecting';
        $state['message'] = 'Đang kết nối qua ' . $this->providerLabel($provider) . '...';
        $state['started_at'] = time();
        $state['url'] = $provider === 'relay' ? (string)($this->settings()['relay_public_url'] ?? '') : '';
        $this->writeJson($this->stateFile, $state);
    }

    private function providerCommand(string $provider, string $target, string $protocol): string
    {
        $parts = parse_url($target);
        $port = (int)($parts['port'] ?? 80);
        $settings = $this->settings();
        return match ($provider) {
            'cloudflare' => escapeshellarg($this->path('cloudflared')) . ' tunnel --config /dev/null --no-autoupdate --url ' . escapeshellarg($target)
                . ($protocol === 'auto' ? '' : ' --protocol ' . escapeshellarg($protocol)),
            'pinggy' => escapeshellarg($this->path('ssh')) . ' -T -o StrictHostKeyChecking=no -o ServerAliveInterval=20 -o ServerAliveCountMax=3 -o ExitOnForwardFailure=yes -p 443 -R0:127.0.0.1:' . $port . ' free.pinggy.io',
            'localhostrun' => escapeshellarg($this->path('ssh')) . ' -o StrictHostKeyChecking=no -o ServerAliveInterval=20 -o ExitOnForwardFailure=yes -R 80:127.0.0.1:' . $port . ' nokey@localhost.run',
            'ngrok' => escapeshellarg($this->path('ngrok')) . ' http ' . escapeshellarg($target) . ' --log stdout --log-format json --authtoken ' . escapeshellarg((string)$settings['ngrok_token']),
            'relay' => $this->relayCommand($settings, $port),
            default => throw new RuntimeException('Nhà cung cấp tunnel không hợp lệ.'),
        };
    }

    private function relayCommand(array $s, int $localPort): string
    {
        $identity = trim((string)($s['relay_identity_file'] ?? ''));
        $identityArg = $identity !== '' ? ' -i ' . escapeshellarg($identity) : '';
        return escapeshellarg($this->path('ssh')) . ' -N -T -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=20 -o ServerAliveCountMax=3 -o ExitOnForwardFailure=yes'
            . $identityArg . ' -p ' . (int)$s['relay_ssh_port']
            . ' -R 0.0.0.0:' . (int)$s['relay_remote_port'] . ':127.0.0.1:' . $localPort . ' '
            . escapeshellarg((string)$s['relay_user'] . '@' . (string)$s['relay_host']);
    }

    private function extractUrl(string $provider, string $text): string
    {
        $patterns = match ($provider) {
            'cloudflare' => ['#https://[a-z0-9-]+\.trycloudflare\.com#i'],
            'pinggy' => ['#https://[a-z0-9-]+(?:\.[a-z0-9-]+)*\.free\.pinggy\.(?:link|online)#i','#https://[a-z0-9-]+(?:\.[a-z0-9-]+)*\.pinggy\.(?:link|online)#i'],
            'localhostrun' => ['#https://[a-z0-9-]+\.lhr\.life#i','#https://[a-z0-9.-]+\.localhost\.run#i'],
            'ngrok' => ['#https://[a-z0-9-]+\.ngrok-free\.app#i','#https://[a-z0-9-]+\.ngrok\.io#i'],
            'relay' => [], default => [],
        };
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $candidate) {
                    if ($this->validProviderUrl($provider, $candidate)) return $candidate;
                }
            }
        }
        return '';
    }

    private function probe(string $url, string $provider): array
    {
        if ($provider === 'relay' && $url === '') {
            return ['healthy'=>false,'http_code'=>0,'message'=>'TMS Relay đã kết nối SSH nhưng chưa cấu hình URL công khai.'];
        }
        if (!$this->validProviderUrl($provider, $url)) {
            return ['healthy'=>false,'http_code'=>0,'message'=>'Nhà cung cấp trả về URL không hợp lệ hoặc URL trang quản trị, không phải tunnel thật.'];
        }

        $tmp = $this->dir . '/probe-' . getmypid() . '.tmp';
        $headers = $this->dir . '/probe-headers-' . getmypid() . '.tmp';
        $cmd = 'curl -L -sS --connect-timeout 6 --max-time 15 --max-redirs 4 '
            . '-A ' . escapeshellarg('TMS-OS-Tunnel-Health/10.3.1') . ' '
            . '-D ' . escapeshellarg($headers) . ' -o ' . escapeshellarg($tmp)
            . ' -w ' . escapeshellarg('%{http_code}|%{url_effective}|%{content_type}') . ' ' . escapeshellarg($url);
        $meta = trim((string)shell_exec($cmd));
        $parts = explode('|', $meta, 3);
        $code = isset($parts[0]) && ctype_digit($parts[0]) ? (int)$parts[0] : 0;
        $effective = trim((string)($parts[1] ?? ''));
        $contentType = strtolower(trim((string)($parts[2] ?? '')));
        $body = is_file($tmp) ? (string)file_get_contents($tmp) : '';
        @unlink($tmp); @unlink($headers);

        if ($effective !== '' && !$this->validProviderUrl($provider, $effective)) {
            return ['healthy'=>false,'http_code'=>$code,'message'=>'URL tunnel bị chuyển hướng sang trang quản trị hoặc tên miền không hợp lệ.'];
        }
        if ($provider === 'cloudflare' && $code === 530 && stripos($body, '1033') !== false) {
            return ['healthy'=>false,'http_code'=>$code,'message'=>'Cloudflare Error 1033: Edge chưa có tunnel hoạt động.'];
        }
        if ($code < 200 || $code >= 400) {
            return ['healthy'=>false,'http_code'=>$code,'message'=>'URL công khai chưa phản hồi hợp lệ (HTTP ' . $code . ').'];
        }
        if ($this->looksLikeProviderLandingPage($provider, $body, $effective !== '' ? $effective : $url)) {
            return ['healthy'=>false,'http_code'=>$code,'message'=>'URL phản hồi HTTP ' . $code . ' nhưng là trang của nhà cung cấp, không phải website nội bộ.'];
        }

        $target = (string)($this->readJson($this->stateFile)['target'] ?? '');
        if ($target !== '' && str_contains($contentType, 'text/html') && !$this->contentMatchesTarget($target, $body)) {
            return ['healthy'=>false,'http_code'=>$code,'message'=>'URL có phản hồi nhưng nội dung không khớp website nội bộ; đã bỏ qua để tránh báo kết nối giả.'];
        }
        return ['healthy'=>true,'http_code'=>$code,'message'=>'URL tunnel thật đã được xác minh và nội dung khớp website nội bộ.'];
    }

    private function validProviderUrl(string $provider, string $url): bool
    {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '' || !str_starts_with(strtolower($url), 'https://')) return false;
        return match ($provider) {
            'cloudflare' => (bool)preg_match('/^[a-z0-9-]+\.trycloudflare\.com$/', $host),
            'pinggy' => $host !== 'dashboard.pinggy.io' && (bool)preg_match('/^[a-z0-9.-]+\.pinggy\.(?:link|online)$/', $host),
            'localhostrun' => (bool)preg_match('/^[a-z0-9.-]+\.(?:localhost\.run|lhr\.life|lhr\.rocks)$/', $host),
            'ngrok' => (bool)preg_match('/^[a-z0-9.-]+\.(?:ngrok-free\.app|ngrok\.io|ngrok\.app)$/', $host),
            'relay' => filter_var($url, FILTER_VALIDATE_URL) !== false,
            default => false,
        };
    }

    private function looksLikeProviderLandingPage(string $provider, string $body, string $url): bool
    {
        $sample = strtolower(substr(strip_tags($body), 0, 12000));
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        if ($provider === 'pinggy' && ($host === 'dashboard.pinggy.io' || str_contains($sample, 'pinggy dashboard') || str_contains($sample, 'simple localhost tunnels'))) return true;
        if ($provider === 'ngrok' && (str_contains($sample, 'ngrok browser warning') || str_contains($sample, 'visit site'))) return true;
        if ($provider === 'localhostrun' && str_contains($sample, 'localhost.run') && str_contains($sample, 'local dev')) return true;
        return false;
    }

    private function contentMatchesTarget(string $target, string $publicBody): bool
    {
        $localTmp = $this->dir . '/local-probe-' . getmypid() . '.tmp';
        $cmd = 'curl -L -sS --connect-timeout 3 --max-time 8 -A '
            . escapeshellarg('TMS-OS-Tunnel-Health/10.3.1') . ' -o ' . escapeshellarg($localTmp) . ' ' . escapeshellarg($target);
        exec($cmd, $o, $c);
        $localBody = is_file($localTmp) ? (string)file_get_contents($localTmp) : '';
        @unlink($localTmp);
        if ($c !== 0 || $localBody === '') return true; // Không chặn tunnel chỉ vì probe nội bộ tạm thời thất bại.

        $normalize = static function (string $html): string {
            $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html) ?? $html;
            $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html) ?? $html;
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
            return trim(substr($text, 0, 16000));
        };
        $local = $normalize($localBody);
        $public = $normalize($publicBody);
        if ($local === '' || $public === '') return true;

        $localTokens = array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}_-]+/u', $local) ?: [], static fn($v) => (function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v)) >= 4)));
        if (!$localTokens) return true;
        $hits = 0;
        foreach (array_slice($localTokens, 0, 80) as $token) {
            if ((function_exists('mb_strpos') ? mb_strpos($public, $token, 0, 'UTF-8') : strpos($public, $token)) !== false) $hits++;
        }
        return $hits >= min(3, count($localTokens));
    }

    private function capabilities(): array
    {
        $s = $this->settings();
        return [
            'cloudflare' => $this->exists('cloudflared'),
            'pinggy' => $this->exists('ssh'),
            'localhostrun' => $this->exists('ssh'),
            'ngrok' => $this->exists('ngrok') && trim((string)($s['ngrok_token'] ?? '')) !== '',
            'relay' => $this->exists('ssh') && $this->relayConfigured($s),
        ];
    }

    private function providerReady(string $provider): bool
    {
        return !empty($this->capabilities()[$provider]);
    }

    private function relayConfigured(array $s): bool
    {
        return trim((string)($s['relay_host'] ?? '')) !== '' && trim((string)($s['relay_user'] ?? '')) !== '' && trim((string)($s['relay_public_url'] ?? '')) !== '';
    }

    private function settings(): array
    {
        return array_merge([
            'ngrok_token'=>'','relay_host'=>'','relay_user'=>'','relay_ssh_port'=>22,
            'relay_remote_port'=>10080,'relay_public_url'=>'','relay_identity_file'=>'',
        ], $this->readJson($this->settingsFile));
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'cloudflare'=>'Cloudflare Quick Tunnel','ngrok'=>'Ngrok','pinggy'=>'Pinggy',
            'localhostrun'=>'localhost.run','relay'=>'TMS Relay Server',default=>'Chưa chọn',
        };
    }

    private function validateTarget(string $target): void
    {
        if (!preg_match('#^http://127\.0\.0\.1:(\d{1,5})$#', $target, $m) || (int)$m[1] > 65535) {
            throw new RuntimeException('Địa chỉ website nội bộ không hợp lệ.');
        }
    }

    private function targetReachable(string $target): bool
    {
        exec('curl -sS -o /dev/null --connect-timeout 3 --max-time 5 ' . escapeshellarg($target), $o, $c);
        return $c === 0;
    }

    private function friendlyLogError(string $log): string
    {
        $l = strtolower($log);
        if (str_contains($l,'permission denied')) return 'SSH từ chối xác thực. Hãy kiểm tra khóa SSH hoặc tài khoản relay.';
        if (str_contains($l,'network is unreachable')) return 'Mạng hiện tại không cho phép kết nối ra ngoài.';
        if (str_contains($l,'connection timed out') || str_contains($l,'timeout')) return 'Kết nối bị mạng công cộng chặn hoặc hết thời gian.';
        if (str_contains($l,'connection refused')) return 'Máy chủ tunnel từ chối kết nối.';
        return 'Tiến trình tunnel đã dừng trước khi kết nối hoàn tất.';
    }

    private function terminate(): void
    {
        $pid = is_file($this->pidFile) ? trim((string)file_get_contents($this->pidFile)) : '';
        if (ctype_digit($pid)) {
            exec('kill ' . escapeshellarg($pid) . ' 2>/dev/null');
            usleep(200000);
            exec('kill -9 ' . escapeshellarg($pid) . ' 2>/dev/null');
        }
        @unlink($this->pidFile);
    }

    private function running(): bool
    {
        $pid = is_file($this->pidFile) ? trim((string)file_get_contents($this->pidFile)) : '';
        if (!ctype_digit($pid)) return false;
        exec('kill -0 ' . escapeshellarg($pid) . ' 2>/dev/null', $o, $c);
        return $c === 0;
    }

    private function path(string $cmd): string
    {
        $p = trim((string)shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null'));
        return $p !== '' ? $p : $cmd;
    }

    private function exists(string $cmd): bool
    {
        exec('command -v ' . escapeshellarg($cmd) . ' >/dev/null 2>&1', $o, $c);
        return $c === 0;
    }

    private function readJson(string $file): array
    {
        if (!is_file($file)) return [];
        $d = json_decode((string)file_get_contents($file), true);
        return is_array($d) ? $d : [];
    }

    private function writeJson(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($file, 0600);
    }

    private function tail(string $file): string
    {
        if (!is_file($file)) return 'Chưa có nhật ký.';
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        return is_array($lines) ? implode("\n", array_slice($lines, -180)) : 'Không thể đọc nhật ký.';
    }
}
