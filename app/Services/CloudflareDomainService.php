<?php
declare(strict_types=1);

/**
 * TMS OS V15.0 — Cloudflare Hosting (tunnel chính chủ qua API Cloudflare).
 * Người dùng cung cấp API Token có quyền: Cloudflare Tunnel (Edit) + Zone DNS (Edit) + Zone Settings (Read).
 * Luồng:
 *  1. apiGet('/accounts')                 → lấy account_id
 *  2. apiGet('/zones')                    → danh sách domain (zone)
 *  3. apiGet('/zones/{id}/dns_records')   → danh sách record hiện có của domain
 *  4. apiPost('/accounts/{id}/cfd_tunnel',{name,config_src:cloudflare}) → tunnel_id + token
 *  5. apiPut('/accounts/{id}/cfd_tunnel/{tunnel_id}/configurations',{config:{ingress:[{hostname,service}],catch-all}})
 *  6. apiPost('/zones/{id}/dns_records',{CNAME proxied → <tunnel_id>.cfargotunnel.com})
 *  7. Chạy cloudflared tunnel --no-autoupdate run --token <token> (PID file + log)
 */
final class CloudflareDomainService
{
    private string $home;
    private string $dir;
    private string $configFile;
    private string $pidFile;
    private string $logFile;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->dir = $this->home . '/.tms-os/cloudflare-hosting';
        $this->configFile = $this->dir . '/config.json';
        $this->pidFile = $this->dir . '/tunnel.pid';
        $this->logFile = $this->dir . '/tunnel.log';
        @mkdir($this->dir, 0700, true);
    }

    private function cf(): array
    {
        $cfg = $this->readJson($this->configFile);
        $token = trim((string)($cfg['api_token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('Chưa cấu hình API Token Cloudflare. Hãy mở tab "Cấu hình tài khoản" để bắt đầu.');
        }
        return ['token' => $token, 'account_id' => trim((string)($cfg['account_id'] ?? '')), 'cfg' => $cfg];
    }

    private function api(string $method, string $path, ?array $json = null): array
    {
        $info = $this->cf();
        $token = $info['token'];
        $cmd = 'curl -L -sS --connect-timeout 10 --max-time 25 -X ' . escapeshellarg($method)
            . ' -H ' . escapeshellarg('Authorization: Bearer ' . $token)
            . ' -H ' . escapeshellarg('Content-Type: application/json')
            . ($json !== null ? ' --data ' . escapeshellarg(json_encode($json, JSON_UNESCAPED_SLASHES)) : '')
            . ' ' . escapeshellarg('https://api.cloudflare.com/client/v4' . $path);
        $out = trim((string)shell_exec($cmd));
        $data = json_decode($out, true);
        if (!is_array($data)) {
            throw new RuntimeException('Cloudflare trả về phản hồi không hợp lệ. Vui lòng kiểm tra lại API Token.');
        }
        if (empty($data['success'])) {
            $msg = '';
            foreach (($data['errors'] ?? []) as $e) {
                $m = trim((string)($e['message'] ?? ''));
                if ($m !== '') { $msg .= ($msg !== '' ? ' · ' : '') . $m; }
            }
            if ($msg === '') { $msg = 'Cloudflare trả về lỗi (success=false).'; }
            throw new RuntimeException($msg);
        }
        return (array)($data['result'] ?? []);
    }

    /** Kiểm tra token và trả account_id + danh sách zone. */
    public function accountInfo(): array
    {
        $info = $this->cf();
        $acct = $this->api('GET', '/accounts');
        $accountId = '';
        foreach ((array)($acct[0] ?? []) as $acc) {
            $accountId = (string)($acc['id'] ?? '');
            break;
        }
        if ($accountId === '' && is_array($acct)) {
            $accountId = (string)($acct['id'] ?? '');
        }
        if ($accountId === '') {
            throw new RuntimeException('Không thể đọc thông tin tài khoản. Hãy kiểm tra quyền "Account Settings: Read" của API Token.');
        }
        if ($accountId !== '' && $accountId !== $info['account_id']) {
            $cfg = $info['cfg'];
            $cfg['account_id'] = $accountId;
            $this->writeJson($this->configFile, $cfg);
        }
        $zones = $this->api('GET', '/zones');
        $list = [];
        foreach ($zones as $z) {
            $list[] = ['id' => (string)($z['id'] ?? ''), 'name' => (string)($z['name'] ?? ''), 'status' => (string)($z['status'] ?? '')];
        }
        return ['account_id' => $accountId, 'zones' => $list];
    }

    public function dnsRecords(string $zoneId): array
    {
        $records = $this->api('GET', '/zones/' . $zoneId . '/dns_records?per_page=100');
        $list = [];
        foreach ($records as $r) {
            $list[] = [
                'id' => (string)($r['id'] ?? ''),
                'type' => (string)($r['type'] ?? ''),
                'name' => (string)($r['name'] ?? ''),
                'content' => (string)($r['content'] ?? ''),
                'proxied' => !empty($r['proxied']),
            ];
        }
        return $list;
    }

    public function createTunnel(): array
    {
        $info = $this->cf();
        $name = 'tms-os-' . substr(hash('sha256', (string)time() . random_int(0, 999999)), 0, 6);
        $tunnel = $this->api('POST', '/accounts/' . $info['account_id'] . '/cfd_tunnel', [
            'name' => $name, 'config_src' => 'cloudflare',
        ]);
        $tunnelId = (string)($tunnel['id'] ?? '');
        $token = (string)($tunnel['token'] ?? '');
        $cred = (array)($tunnel['credentials_file'] ?? []);
        if ($tunnelId === '' || $token === '') {
            throw new RuntimeException('Cloudflare không trả về tunnel token.');
        }
        $cfg = $info['cfg'];
        $cfg['tunnel_id'] = $tunnelId;
        $cfg['tunnel_token'] = $token;
        $cfg['tunnel_name'] = $name;
        $cfg['credentials_file'] = $cred;
        $this->writeJson($this->configFile, $cfg);
        @chmod($this->configFile, 0600);
        return ['tunnel_id' => $tunnelId, 'tunnel_name' => $name, 'token' => $token];
    }

    public function attachHostname(string $zoneId, string $hostname, string $service): array
    {
        $info = $this->cf();
        $tunnelId = (string)($info['cfg']['tunnel_id'] ?? '');
        if ($tunnelId === '') {
            throw new RuntimeException('Chưa có tunnel. Hãy bấm "Tạo Cloudflare Tunnel" trước.');
        }
        // 1. Ingress rule
        $this->api('PUT', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations', [
            'config' => ['ingress' => [
                ['hostname' => $hostname, 'service' => $service, 'originRequest' => (object)[]],
                ['service' => 'http_status:404'],
            ]],
        ]);
        // 2. DNS CNAME → <tunnel_id>.cfargotunnel.com
        $existing = $this->dnsRecords($zoneId);
        $recordId = '';
        foreach ($existing as $r) {
            if (strcasecmp($r['name'], $hostname) === 0) {
                $recordId = $r['id'];
                $this->api('PUT', '/zones/' . $zoneId . '/dns_records/' . $recordId, [
                    'type' => 'CNAME', 'name' => $hostname,
                    'content' => $tunnelId . '.cfargotunnel.com', 'proxied' => true,
                ]);
                break;
            }
        }
        if ($recordId === '') {
            $created = $this->api('POST', '/zones/' . $zoneId . '/dns_records', [
                'type' => 'CNAME', 'name' => $hostname,
                'content' => $tunnelId . '.cfargotunnel.com', 'proxied' => true,
            ]);
            $recordId = (string)($created['id'] ?? '');
        }
        $cfg = $info['cfg'];
        $cfg['hostname'] = $hostname;
        $cfg['service'] = $service;
        $cfg['zone_id'] = $zoneId;
        $cfg['record_id'] = $recordId;
        $this->writeJson($this->configFile, $cfg);
        @chmod($this->configFile, 0600);
        return ['hostname' => $hostname, 'url' => 'https://' . $hostname, 'record_id' => $recordId];
    }

    public function detachHostname(): array
    {
        $info = $this->cf();
        $cfg = $info['cfg'];
        $zoneId = (string)($cfg['zone_id'] ?? '');
        $recordId = (string)($cfg['record_id'] ?? '');
        if ($zoneId !== '' && $recordId !== '') {
            try { $this->api('DELETE', '/zones/' . $zoneId . '/dns_records/' . $recordId); } catch (Throwable $e) { /* bỏ qua */ }
        }
        unset($cfg['hostname'], $cfg['service'], $cfg['zone_id'], $cfg['record_id']);
        $this->writeJson($this->configFile, $cfg);
        return ['message' => 'Đã tách tên miền khỏi tunnel.'];
    }

    public function deleteTunnel(): array
    {
        $info = $this->cf();
        $tunnelId = (string)($info['cfg']['tunnel_id'] ?? '');
        if ($tunnelId !== '') {
            try { $this->api('DELETE', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId); } catch (Throwable $e) { /* bỏ qua */ }
        }
        unset($info['cfg']['tunnel_id'], $info['cfg']['tunnel_token'], $info['cfg']['tunnel_name'], $info['cfg']['credentials_file']);
        $this->writeJson($this->configFile, $info['cfg']);
        @chmod($this->configFile, 0600);
        return ['message' => 'Đã xóa tunnel khỏi Cloudflare.'];
    }

    public function tunnelHealth(): array
    {
        $info = $this->cf();
        $tunnelId = (string)($info['cfg']['tunnel_id'] ?? '');
        if ($tunnelId === '') { return ['status' => 'unconfigured', 'connections' => 0, 'running' => $this->running()]; }
        try {
            $t = $this->api('GET', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId);
            return [
                'status' => (string)($t['status'] ?? 'unknown'),
                'connections' => count((array)($t['connections'] ?? [])),
                'running' => $this->running(),
            ];
        } catch (Throwable $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage(), 'running' => $this->running()];
        }
    }

    private function cloudflared(): string
    {
        foreach (['cloudflared', $this->home . '/bin/cloudflared', '/data/data/com.termux/files/usr/bin/cloudflared'] as $p) {
            if (is_executable($p) || is_file($p)) { return $p; }
        }
        return 'cloudflared';
    }

    public function startTunnel(): array
    {
        $info = $this->cf();
        $token = trim((string)($info['cfg']['tunnel_token'] ?? ''));
        if ($token === '') { throw new RuntimeException('Chưa có tunnel token. Hãy tạo tunnel trước.'); }
        if (!file_exists($this->cloudflared())) {
            throw new RuntimeException('Chưa cài cloudflared. Vào Runtime Packages → cài Cloudflared rồi thử lại.');
        }
        if ($this->running()) { throw new RuntimeException('Tunnel đang chạy.'); }
        $this->terminate();
        $cmd = escapeshellarg($this->cloudflared()) . ' tunnel --no-autoupdate run --token ' . escapeshellarg($token);
        $shell = 'nohup sh -c ' . escapeshellarg('exec ' . $cmd) . ' </dev/null >' . escapeshellarg($this->logFile) . ' 2>&1 & echo $!';
        $pid = trim((string)shell_exec($shell));
        if (!ctype_digit($pid)) { throw new RuntimeException('Không thể khởi động cloudflared.'); }
        file_put_contents($this->pidFile, $pid, LOCK_EX);
        return ['pid' => $pid, 'running' => true];
    }

    public function stopTunnel(): array
    {
        $this->terminate();
        return ['running' => false, 'message' => 'Đã dừng tunnel.'];
    }

    public function status(): array
    {
        $cfg = $this->readJson($this->configFile);
        $running = $this->running();
        $health = ['status' => 'unconfigured', 'connections' => 0, 'running' => $running];
        try { $health = $this->tunnelHealth(); } catch (Throwable $e) { /* giữ giá trị mặc định */ }
        $log = '';
        if (is_file($this->logFile)) {
            $lines = array_slice(file($this->logFile, FILE_IGNORE_NEW_LINES) ?: [], -10);
            $log = implode("\n", $lines);
        }
        return [
            'configured' => trim((string)($cfg['api_token'] ?? '')) !== '',
            'account_id' => (string)($cfg['account_id'] ?? ''),
            'tunnel_id' => (string)($cfg['tunnel_id'] ?? ''),
            'tunnel_name' => (string)($cfg['tunnel_name'] ?? ''),
            'hostname' => (string)($cfg['hostname'] ?? ''),
            'service' => (string)($cfg['service'] ?? ''),
            'zone_id' => (string)($cfg['zone_id'] ?? ''),
            'running' => $running,
            'health' => $health,
            'url' => trim((string)($cfg['hostname'] ?? '')) !== '' ? 'https://' . $cfg['hostname'] : '',
            'log' => $log,
        ];
    }

    private function running(): bool
    {
        if (!is_file($this->pidFile)) { return false; }
        $pid = trim((string)file_get_contents($this->pidFile));
        if (!ctype_digit($pid)) { return false; }
        $probe = trim((string)shell_exec('kill -0 ' . escapeshellarg($pid) . ' 2>/dev/null && echo yes || echo no'));
        if ($probe !== 'yes') { @unlink($this->pidFile); return false; }
        return true;
    }

    private function terminate(): void
    {
        if (is_file($this->pidFile)) {
            $pid = trim((string)file_get_contents($this->pidFile));
            if (ctype_digit($pid)) {
                shell_exec('kill ' . escapeshellarg($pid) . ' 2>/dev/null; sleep 1; kill -9 ' . escapeshellarg($pid) . ' 2>/dev/null');
            }
            @unlink($this->pidFile);
        }
    }

    public function saveApiToken(array $input): void
    {
        $cfg = $this->readJson($this->configFile);
        $tok = trim((string)($input['api_token'] ?? ''));
        if ($tok !== '') { $cfg['api_token'] = $tok; }
        if (isset($input['account_id']) && trim((string)$input['account_id']) !== '') {
            $cfg['account_id'] = trim((string)$input['account_id']);
        }
        $this->writeJson($this->configFile, $cfg);
        @chmod($this->configFile, 0600);
    }

    public function uninstall(): array
    {
        $this->stopTunnel();
        $this->deleteTunnel();
        @unlink($this->configFile);
        return ['message' => 'Đã xóa toàn bộ cấu hình Cloudflare Hosting.'];
    }

    private function readJson(string $path): array
    {
        if (!is_file($path)) { return []; }
        $d = json_decode((string)file_get_contents($path), true);
        return is_array($d) ? $d : [];
    }

    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    }
}
