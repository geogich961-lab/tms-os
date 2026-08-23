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

    /** URL panel công khai đọc từ cấu hình cục bộ, không gọi Cloudflare API. */
    public function publicPanelUrl(): string
    {
        $hostname = strtolower(trim((string)($this->readJson($this->configFile)['panel_hostname'] ?? '')));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i', $hostname)) {
            return '';
        }
        return 'https://' . $hostname;
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

    /** V15.4.0 — cache in-process cho các GET đọc trạng thái (60 giây) */
    private static array $apiCache = [];
    private static array $apiCacheAt = [];

    private function api(string $method, string $path, ?array $json = null, bool $fresh = false): array
    {
        // V15.4.0: cache kết quả GET đọc trạng thái — giảm gấp nhiều lần số lần gọi API
        if (!$fresh && $method === 'GET' && $json === null && $this->isCacheablePath($path)) {
            $key = $method . '|' . $path;
            $at = (float)($this::$apiCacheAt[$key] ?? 0);
            if (($at > 0) && (microtime(true) - $at) < 60.0 && isset($this::$apiCache[$key])) {
                return $this::$apiCache[$key];
            }
        }
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
            $rateLimited = false;
            foreach (($data['errors'] ?? []) as $e) {
                $m = trim((string)($e['message'] ?? ''));
                if ($m !== '') { $msg .= ($msg !== '' ? ' · ' : '') . $m; }
                if (stripos($m, 'rate limit') !== false || stripos($m, 'throttl') !== false || stripos($m, '429') !== false || (($e['code'] ?? '') === 10121)) {
                    $rateLimited = true;
                }
            }
            if ($msg === '') { $msg = 'Cloudflare trả về lỗi (success=false).'; }
            // V15.4.0: retry 1 lần sau 5s khi bị rate limit
            if ($rateLimited) {
                usleep(5_000_000);
                $out2 = trim((string)shell_exec($cmd));
                $data2 = json_decode($out2, true);
                if (is_array($data2) && !empty($data2['success'])) {
                    $result = (array)($data2['result'] ?? []);
                    $this->cachePut($method, $path, $result);
                    return $result;
                }
                throw new RuntimeException('Cloudflare đang giới hạn tốc độ truy cập (rate limit). Vui lòng đợi khoảng 60 giây rồi thử lại. (' . $msg . ')');
            }
            throw new RuntimeException($msg);
        }
        $result = (array)($data['result'] ?? []);
        // Một PUT/PATCH/DELETE vào cấu hình tunnel làm dữ liệu GET đã cache trở nên
        // cũ. Bỏ cache ngay để bước xác minh route đọc đúng cấu hình Cloudflare vừa
        // nhận, thay vì thấy ingress trước đó trong tối đa 60 giây.
        if ($method !== 'GET' && $this->isCacheablePath($path)) {
            $this->cacheForget('GET', $path);
        }
        $this->cachePut($method, $path, $result);
        return $result;
    }

    private function isCacheablePath(string $path): bool
    {
        return (bool)preg_match('#^/(accounts/[^/]+/cfd_tunnel/[^/]+/configurations|zones(?:/[^/]+/dns_records)?|user/memberships|accounts)$#', $path);
    }

    private function cachePut(string $method, string $path, array $result): void
    {
        if ($method === 'GET' && $this->isCacheablePath($path)) {
            $key = $method . '|' . $path;
            $this::$apiCache[$key] = $result;
            $this::$apiCacheAt[$key] = microtime(true);
        }
    }

    private function cacheForget(string $method, string $path): void
    {
        $key = $method . '|' . $path;
        unset($this::$apiCache[$key], $this::$apiCacheAt[$key]);
    }

    /**
     * Kiểm tra token và trả account_id + danh sách zone.
     *
     * Account ID đã lưu là nguồn ưu tiên vì tunnel đang chạy không phụ thuộc việc
     * liệt kê zone. Nếu Cloudflare tạm thời từ chối GET /zones, trả trạng thái
     * suy giảm thay vì làm hỏng toàn bộ trang Cloudflare Hosting.
     */
    public function accountInfo(): array
    {
        $info = $this->cf();
        $accountId = trim((string)($info['account_id'] ?? ''));
        $list = [];
        $zoneWarn = '';

        // Chỉ gọi /zones một lần. Ngoài việc tránh lỗi 400 do scope phụ, dữ liệu
        // này còn có thể cung cấp account_id khi cấu hình cũ chưa lưu giá trị đó.
        try {
            $zones = $this->api('GET', '/zones?per_page=50');
            foreach ((array)$zones as $zone) {
                if (!is_array($zone)) { continue; }
                $list[] = [
                    'id' => (string)($zone['id'] ?? ''),
                    'name' => (string)($zone['name'] ?? ''),
                    'status' => (string)($zone['status'] ?? ''),
                ];
                if ($accountId === '') {
                    $account = (array)($zone['account'] ?? []);
                    $accountId = trim((string)($account['id'] ?? ''));
                }
            }
        } catch (Throwable $e) {
            $zoneWarn = $e->getMessage();
        }

        // Với cấu hình mới chưa có account_id, thử endpoint account một lần và
        // phân tích đúng mảng bản ghi. Không gọi endpoint này ở trang làm mới
        // bình thường khi account_id đã tồn tại.
        if ($accountId === '') {
            try {
                $accounts = $this->api('GET', '/accounts');
                foreach ((array)$accounts as $account) {
                    if (!is_array($account)) { continue; }
                    $accountId = trim((string)($account['id'] ?? ''));
                    if ($accountId !== '') { break; }
                }
            } catch (Throwable $e) {
                if ($zoneWarn === '') { $zoneWarn = $e->getMessage(); }
            }
        }
        if ($accountId === '') {
            throw new RuntimeException('Không thể đọc thông tin tài khoản. Hãy tạo lại token tại dash.cloudflare.com/profile/api-tokens với quyền: Cloudflare Tunnel (Edit) + Zone DNS (Edit) + Zone Zone (Read), phạm vi Account: All accounts, Zone: All zones.');
        }
        if ($accountId !== '' && $accountId !== $info['account_id']) {
            $cfg = $info['cfg'];
            $cfg['account_id'] = $accountId;
            $this->writeJson($this->configFile, $cfg);
        }
        return ['account_id' => $accountId, 'zones' => $list, 'zone_warn' => $zoneWarn];
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
        $existingTunnelId = trim((string)($info['cfg']['tunnel_id'] ?? ''));
        if ($existingTunnelId !== '') {
            throw new RuntimeException('Đã có Cloudflare Tunnel được lưu trong TMS OS. Không tạo tunnel mới vì có thể làm gián đoạn các hostname hiện tại. Hãy khởi động lại tunnel này từ Termux nếu nó đang offline.');
        }
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

    /**
     * Gắn một tên host (domain gốc HOẶC subdomain) vào tunnel, trỏ đến website nội bộ.
     * V15.3.5 multi-site: GIỮ NGUYÊN các ingress/dns đang có, chỉ THÊM hostname mới —
     * nhiều website/subdomain có thể hoạt động song song trên cùng một tunnel.
     */
    public function attachHostname(string $zoneId, string $hostname, string $service): array
    {
        $info = $this->cf();
        $cfg = $info['cfg'];
        $tunnelId = (string)($cfg['tunnel_id'] ?? '');
        if ($tunnelId === '') {
            throw new RuntimeException('Chưa có tunnel. Hãy bấm "Tạo Cloudflare Tunnel" trước.');
        }
        $hostname = strtolower(trim($hostname));
        if ($hostname === '' || strpos($hostname, '.') === false) {
            throw new RuntimeException('Tên host không hợp lệ. Ví dụ: shop.thc.io.vn');
        }
        // Website inventory cũ từng gửi dạng "ten-site:port". Cloudflare Tunnel
        // chỉ chấp nhận service có scheme + hostname, nên chỉ chuẩn hóa route MỚI
        // về loopback; ingress hiện hữu vẫn được giữ nguyên khi đọc/ghi lại.
        $service = $this->normalizeTunnelService($service);
        // 1. Đọc ingress hiện tại, thêm rule mới (giữ nguyên rule cũ — multi-site)
        try {
            $current = $this->api('GET', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations');
        } catch (Throwable $e) {
            throw new RuntimeException('Không thể đọc route tunnel hiện có; chưa thay đổi DNS hoặc hostname. Vui lòng thử lại sau.');
        }
        $ingress = (array)(($current['config'] ?? [])['ingress'] ?? []);
        foreach ($ingress as $rule) {
            if (strcasecmp((string)($rule['hostname'] ?? ''), $hostname) === 0) {
                throw new RuntimeException('Tên host "' . $hostname . '" đã được gắn rồi.');
            }
        }
        $ingress = array_values(array_filter($ingress, static function ($rule) {
            return isset($rule['hostname']);
        }));
        $ingress[] = ['hostname' => $hostname, 'service' => $service, 'originRequest' => (object)[
            'connectTimeout' => 10, 'tcpKeepAlive' => 60, 'noHappyEyeballs' => true,
            'httpHostHeader' => $hostname,
        ]];
        $ingress[] = ['service' => 'http_status:404'];
        // Ghi ingress đã đọc được trước khi xác minh route mới; không dùng cấu hình rỗng
        // khi GET thất bại để tránh làm mất các route đang hoạt động.
        try {
            $this->api('PUT', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations', [
                'config' => ['ingress' => $ingress],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Không thể cập nhật route tunnel; chưa thay đổi DNS hoặc hostname. ' . $e->getMessage());
        }
        // Xác nhận rule thực sự được thêm trên Cloudflare. Luôn ép đọc mới: cấu hình
        // vừa PUT có thể vẫn đang nằm trong cache GET cũ của tiến trình hiện tại.
        $verified = false;
        for ($attempt = 0; $attempt < 4; $attempt++) {
            if ($attempt > 0) { usleep(1000000); }
            try {
                $verify = $this->api('GET', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations', null, true);
            } catch (Throwable $e) { $verify = []; }
            foreach ((array)(($verify['config'] ?? [])['ingress'] ?? []) as $rule) {
                if (strcasecmp((string)($rule['hostname'] ?? ''), $hostname) === 0) {
                    $verified = true;
                    break 2;
                }
            }
        }
        // Cloudflare đã chấp nhận PUT ở trên. GET cấu hình có thể nhất thời trả về
        // ingress cũ do đồng bộ control-plane, nên không được phủ nhận một PUT thành
        // công, cũng không được bỏ qua DNS/cấu hình local rồi buộc người dùng gắn lại.
        $routeStatus = $verified ? 'ok' : 'pending';
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
        $site = [
            'hostname' => $hostname,
            'service' => $service,
            'zone_id' => $zoneId,
            'record_id' => $recordId,
            'url' => 'https://' . $hostname,
            'route_status' => $routeStatus,
            'route_pending_at' => $verified ? 0 : time(),
        ];
        $hostnames = (array)($cfg['hostnames'] ?? []);
        $already = false;
        foreach ($hostnames as $index => $h) {
            if (strcasecmp((string)($h['hostname'] ?? ''), $hostname) === 0) {
                $hostnames[$index] = $site;
                $already = true;
                break;
            }
        }
        if (!$already) {
            $hostnames[] = $site;
        }
        $cfg['hostnames'] = array_values($hostnames);
        // hostname/service/zone_id/record_id cũ giữ làm "mặc định" (tương thích logic status cũ)
        if (!isset($cfg['hostname']) || $cfg['hostname'] === '') {
            $cfg['hostname'] = $hostname;
            $cfg['service'] = $service;
            $cfg['zone_id'] = $zoneId;
            $cfg['record_id'] = $recordId;
        }
        $this->writeJson($this->configFile, $cfg);
        @chmod($this->configFile, 0600);
        return $site + ['message' => $verified
            ? 'Đã gắn route và tạo record DNS cho ' . $hostname . '.'
            : 'Cloudflare đã nhận route và DNS đã được tạo. Route đang đồng bộ; hãy làm mới trạng thái sau ít phút.'];
    }

    /** Chuẩn hóa service website nội bộ thành địa chỉ hợp lệ cho Cloudflare Tunnel. */
    private function normalizeTunnelService(string $service): string
    {
        $service = trim($service);
        $port = 0;
        if (preg_match('#^https?://(?:127\.0\.0\.1|localhost):(\d{1,5})/?$#i', $service, $matches)) {
            $port = (int)$matches[1];
        } elseif (preg_match('/^(?:[a-z0-9][a-z0-9._-]*|127\.0\.0\.1|localhost):(\d{1,5})$/i', $service, $matches)) {
            $port = (int)$matches[1];
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Địa chỉ website nội bộ không hợp lệ. Hãy chọn lại website trong danh sách.');
        }
        return 'http://127.0.0.1:' . $port;
    }

    /**
     * Tách MỘT tên host khỏi tunnel (multi-site). Không truyền hostname = tách tên chính (tương thích cũ).
     */
    public function detachHostname(?string $hostname = null): array
    {
        $info = $this->cf();
        $cfg = $info['cfg'];
        $tunnelId = (string)($cfg['tunnel_id'] ?? '');
        $target = $hostname !== null ? strtolower(trim($hostname)) : (string)($cfg['hostname'] ?? '');
        if ($target === '') {
            throw new RuntimeException('Không xác định được tên host cần tách.');
        }
        $hostnames = array_values(array_filter((array)($cfg['hostnames'] ?? []), static function ($h) use ($target) {
            return strcasecmp((string)($h['hostname'] ?? ''), $target) !== 0;
        }));
        $zoneId = '';
        $recordId = '';
        foreach ((array)($cfg['hostnames'] ?? []) as $h) {
            if (strcasecmp((string)($h['hostname'] ?? ''), $target) === 0) {
                $zoneId = (string)($h['zone_id'] ?? $cfg['zone_id'] ?? '');
                $recordId = (string)($h['record_id'] ?? $cfg['record_id'] ?? '');
                break;
            }
        }
        // 1. Xóa ingress rule + rebuild lại cấu hình (giữ các rule khác)
        if ($tunnelId !== '') {
            $ingress = [['service' => 'http_status:404']];
            foreach ($hostnames as $h) {
                array_unshift($ingress, ['hostname' => (string)($h['hostname'] ?? ''), 'service' => (string)($h['service'] ?? ''), 'originRequest' => (object)[
                    'connectTimeout' => 10, 'tcpKeepAlive' => 60, 'noHappyEyeballs' => true,
                    'httpHostHeader' => $h['hostname'] ?? '',
                ]]);
            }
            // Giữ rule panel nếu đang bật
            $panelHostname = (string)($cfg['panel_hostname'] ?? '');
            if ($panelHostname !== '') {
                array_splice($ingress, count($ingress) - 1, 0, [['hostname' => $panelHostname, 'service' => 'http://localhost:8888', 'originRequest' => (object)[
                    'connectTimeout' => 10, 'tcpKeepAlive' => 60, 'noHappyEyeballs' => true,
                    'httpHostHeader' => $panelHostname,
                ]]]);
            }
            try {
                $this->api('PUT', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations', [
                    'config' => ['ingress' => $ingress],
                ]);
            } catch (Throwable $e) { /* ingress có thể đã bị thay đổi thủ công — vẫn xóa DNS */ }
        }
        // 2. Xóa record DNS
        if ($zoneId !== '' && $recordId !== '') {
            try { $this->api('DELETE', '/zones/' . $zoneId . '/dns_records/' . $recordId); } catch (Throwable $e) { /* bỏ qua */ }
        }
        $cfg['hostnames'] = $hostnames;
        unset($cfg['hostname'], $cfg['service'], $cfg['zone_id'], $cfg['record_id']);
        // Nếu còn tên host khác, chọn cái đầu làm mặc định
        if (count($hostnames) > 0) {
            $first = $hostnames[0];
            $cfg['hostname'] = (string)($first['hostname'] ?? '');
            $cfg['service'] = (string)($first['service'] ?? '');
            $cfg['zone_id'] = (string)($first['zone_id'] ?? '');
            $cfg['record_id'] = (string)($first['record_id'] ?? '');
        }
        $this->writeJson($this->configFile, $cfg);
        @chmod($this->configFile, 0600);
        return ['message' => 'Đã tách tên host "' . $target . '" khỏi tunnel.'];
    }

    /** Tạo rule ingress chuẩn cho một hostname nội bộ của TMS OS. */
    private function tunnelIngressRule(string $hostname, string $service): array
    {
        return ['hostname' => $hostname, 'service' => $service, 'originRequest' => (object)[
            'connectTimeout' => 10, 'tcpKeepAlive' => 60, 'noHappyEyeballs' => true,
            'httpHostHeader' => $hostname,
        ]];
    }

    /**
     * Tập hợp tất cả website mà TMS OS đang quản lý, bao gồm cấu hình đơn-host
     * của các phiên bản cũ. Không dùng riêng `hostname` vì nó chỉ là giá trị
     * mặc định tương thích ngược và có thể không phải website mới nhất.
     *
     * @return array<string,array>
     */
    private function managedWebsiteIngress(array $cfg): array
    {
        $managed = [];
        foreach ((array)($cfg['hostnames'] ?? []) as $site) {
            if (!is_array($site)) { continue; }
            $hostname = strtolower(trim((string)($site['hostname'] ?? '')));
            $service = trim((string)($site['service'] ?? ''));
            if ($hostname === '' || $service === '') { continue; }
            try {
                $managed[$hostname] = $this->tunnelIngressRule($hostname, $this->normalizeTunnelService($service));
            } catch (Throwable $e) { /* Bỏ qua bản ghi cũ không hợp lệ, không làm mất route khác. */ }
        }
        // Tương thích cấu hình trước multi-site: chỉ thêm legacy khi danh sách mới chưa có hostname đó.
        $legacyHostname = strtolower(trim((string)($cfg['hostname'] ?? '')));
        $legacyService = trim((string)($cfg['service'] ?? ''));
        if ($legacyHostname !== '' && $legacyService !== '' && !isset($managed[$legacyHostname])) {
            try {
                $managed[$legacyHostname] = $this->tunnelIngressRule($legacyHostname, $this->normalizeTunnelService($legacyService));
            } catch (Throwable $e) { /* Bản ghi legacy không hợp lệ không được dùng để ghi đè ingress hiện hữu. */ }
        }
        return $managed;
    }

    /**
     * Hợp nhất ingress thật của Cloudflare và inventory local trước mỗi lần ghi.
     * Điều này bảo toàn hostname tạo thủ công, đồng thời khôi phục website đã có
     * trong `hostnames[]` nếu một bản TMS OS cũ từng ghi thiếu rule khi bật panel.
     */
    private function mergedTunnelIngress(array $cfg, array $currentIngress, string $panelHostname = '', bool $includePanel = false): array
    {
        $rules = [];
        $oldPanelHostname = strtolower(trim((string)($cfg['panel_hostname'] ?? '')));
        $newPanelHostname = strtolower(trim($panelHostname));
        $excluded = array_filter([$oldPanelHostname, $newPanelHostname]);
        foreach ($currentIngress as $rule) {
            if (!is_array($rule)) { continue; }
            $hostname = strtolower(trim((string)($rule['hostname'] ?? '')));
            if ($hostname === '' || in_array($hostname, $excluded, true)) { continue; }
            $rules[$hostname] = $rule;
        }
        // Bản ghi TMS OS quản lý là nguồn ưu tiên cho chính hostname của nó;
        // các hostname chỉ có trên Cloudflare vẫn được giữ nguyên ở vòng lặp trên.
        foreach ($this->managedWebsiteIngress($cfg) as $hostname => $rule) {
            $rules[$hostname] = $rule;
        }
        if ($includePanel && $newPanelHostname !== '') {
            $rules[$newPanelHostname] = $this->tunnelIngressRule($newPanelHostname, 'http://127.0.0.1:8888');
        }
        $rules[] = ['service' => 'http_status:404'];
        return array_values($rules);
    }

    /**
     * Remote Access (V15.2.0): bật truy cập panel từ xa qua tunnel.
     * Hợp nhất toàn bộ route website trước khi thêm panel, không thay thế ingress.
     */
    public function attachPanelHostname(string $panelHostname): array
    {
        $info = $this->cf();
        $cfg = $info['cfg'];
        $tunnelId = (string)($cfg['tunnel_id'] ?? '');
        $zoneId = (string)($cfg['zone_id'] ?? '');
        if ($tunnelId === '') {
            throw new RuntimeException('Chưa có tunnel. Hãy bấm "Tạo Cloudflare Tunnel" trước.');
        }
        if ($zoneId === '') {
            throw new RuntimeException('Chưa gắn tên miền website. Hãy gắn tên miền trước khi bật truy cập từ xa.');
        }
        $panelHostname = strtolower(trim($panelHostname));
        if ($panelHostname === '' || strpos($panelHostname, '.') === false) {
            throw new RuntimeException('Hostname không hợp lệ. Ví dụ: panel.thc.io.vn');
        }
        if (isset($this->managedWebsiteIngress($cfg)[$panelHostname])) {
            throw new RuntimeException('Hostname panel không được trùng với tên miền website.');
        }
        // 1. Luôn đọc ingress thật trước. Nếu Cloudflare tạm thời không phản hồi,
        // inventory local vẫn đủ để khôi phục mọi hostname TMS OS đã lưu.
        $currentIngress = [];
        try {
            $current = $this->api('GET', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations', null, true);
            $currentIngress = (array)(($current['config'] ?? [])['ingress'] ?? []);
        } catch (Throwable $e) { /* Không ghi cấu hình trống; mergedTunnelIngress dùng hostnames[] an toàn. */ }
        $ingress = $this->mergedTunnelIngress($cfg, $currentIngress, $panelHostname, true);
        $this->api('PUT', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations', [
            'config' => ['ingress' => $ingress],
        ]);
        // 2. DNS CNAME → <tunnel_id>.cfargotunnel.com
        $existing = $this->dnsRecords($zoneId);
        $recordId = '';
        foreach ($existing as $r) {
            if (strcasecmp($r['name'], $panelHostname) === 0) {
                $recordId = $r['id'];
                $this->api('PUT', '/zones/' . $zoneId . '/dns_records/' . $recordId, [
                    'type' => 'CNAME', 'name' => $panelHostname,
                    'content' => $tunnelId . '.cfargotunnel.com', 'proxied' => true,
                ]);
                break;
            }
        }
        if ($recordId === '') {
            $created = $this->api('POST', '/zones/' . $zoneId . '/dns_records', [
                'type' => 'CNAME', 'name' => $panelHostname,
                'content' => $tunnelId . '.cfargotunnel.com', 'proxied' => true,
            ]);
            $recordId = (string)($created['id'] ?? '');
        }
        $cfg['panel_hostname'] = $panelHostname;
        $cfg['panel_record_id'] = $recordId;
        $this->writeJson($this->configFile, $cfg);
        @chmod($this->configFile, 0600);
        return ['hostname' => $panelHostname, 'url' => 'https://' . $panelHostname];
    }

    /**
     * Tắt truy cập panel từ xa: xóa ingress rule panel + CNAME DNS.
     */
    public function detachPanelHostname(): array
    {
        $info = $this->cf();
        $cfg = $info['cfg'];
        $tunnelId = (string)($cfg['tunnel_id'] ?? '');
        $zoneId = (string)($cfg['zone_id'] ?? '');
        $panelHostname = (string)($cfg['panel_hostname'] ?? '');
        $panelRecordId = (string)($cfg['panel_record_id'] ?? '');
        if ($tunnelId !== '') {
            $currentIngress = [];
            try {
                $current = $this->api('GET', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations', null, true);
                $currentIngress = (array)(($current['config'] ?? [])['ingress'] ?? []);
            } catch (Throwable $e) { /* Dùng inventory local, không xóa các route website đã lưu. */ }
            $ingress = $this->mergedTunnelIngress($cfg, $currentIngress, $panelHostname, false);
            try {
                $this->api('PUT', '/accounts/' . $info['account_id'] . '/cfd_tunnel/' . $tunnelId . '/configurations', [
                    'config' => ['ingress' => $ingress],
                ]);
            } catch (Throwable $e) { /* ingress có thể đã bị thay đổi thủ công — vẫn xóa DNS */ }
        }
        if ($zoneId !== '' && $panelHostname !== '' && $panelRecordId !== '') {
            try { $this->api('DELETE', '/zones/' . $zoneId . '/dns_records/' . $panelRecordId); } catch (Throwable $e) { /* bỏ qua */ }
        }
        unset($cfg['panel_hostname'], $cfg['panel_record_id']);
        $this->writeJson($this->configFile, $cfg);
        return ['message' => 'Đã tắt truy cập panel từ xa.'];
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
        // Connector chỉ cần Tunnel token đã lưu. Không gọi API Cloudflare ở đây,
        // vì repair/khởi động phải có thể khôi phục tunnel ngay cả khi API token
        // đã bị đổi hoặc không còn cần thiết cho việc chạy connector.
        $cfg = $this->readJson($this->configFile);
        $token = trim((string)($cfg['tunnel_token'] ?? ''));
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
        // cloudflared có thể thoát ngay vì binary/token/mạng có vấn đề. Chờ ngắn
        // để helper Termux báo lỗi thay vì trả thành công giả rồi biến mất.
        usleep(500000);
        if (!$this->running()) {
            throw new RuntimeException('Cloudflared đã thoát ngay sau khi khởi động. Kiểm tra cloudflared và nhật ký tunnel.');
        }
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
        $zones = [];
        $zoneWarn = '';
        if (trim((string)($cfg['api_token'] ?? '')) !== '') {
            try {
                $raw = $this->api('GET', '/zones');
                foreach ((array)$raw as $z) {
                    $zones[] = ['id' => (string)($z['id'] ?? ''), 'name' => (string)($z['name'] ?? '')];
                }
            } catch (Throwable $e) {
                $zoneWarn = $e->getMessage(); /* V15.4.0: truyền lỗi zone về UI thay vì im lặng */
            }
        }
        $health = ['status' => 'unconfigured', 'connections' => 0, 'running' => $running];
        try { $health = $this->tunnelHealth(); } catch (Throwable $e) { /* giữ giá trị mặc định */ }
        $log = '';
        if (is_file($this->logFile)) {
            $lines = array_slice(file($this->logFile, FILE_IGNORE_NEW_LINES) ?: [], -10);
            $log = implode("\n", $lines);
        }
        $panelHostname = (string)($cfg['panel_hostname'] ?? '');
        $defaultUrl = '';
        $hostnamesOut = [];
        // V15.3.8: đọc ingress THẬT trên Cloudflare để xác nhận từng route (so với config local)
        $ingressReal = [];
        $tunnelIdSt = (string)($cfg['tunnel_id'] ?? '');
        $accountIdSt = trim((string)($cfg['account_id'] ?? ''));
        if ($tunnelIdSt !== '' && $accountIdSt !== '' && trim((string)($cfg['api_token'] ?? '')) !== '') {
            try {
                $ingCfg = $this->api('GET', '/accounts/' . $accountIdSt . '/cfd_tunnel/' . $tunnelIdSt . '/configurations');
                foreach ((array)(($ingCfg['config'] ?? [])['ingress'] ?? []) as $rule) {
                    $hn = (string)($rule['hostname'] ?? '');
                    if ($hn !== '') { $ingressReal[strtolower($hn)] = (string)($rule['service'] ?? ''); }
                }
            } catch (Throwable $e) { /* không bắt buộc */ }
        }
        foreach ((array)($cfg['hostnames'] ?? []) as $h) {
            $hn = (string)($h['hostname'] ?? '');
            $hnLow = strtolower($hn);
            $pendingAt = (int)($h['route_pending_at'] ?? 0);
            $routeStatus = $ingressReal !== [] ? 'missing' : 'unknown';
            if (isset($ingressReal[$hnLow]) && $ingressReal[$hnLow] !== '') {
                $routeStatus = 'ok';
            } elseif (($h['route_status'] ?? '') === 'pending' && $pendingAt > 0 && (time() - $pendingAt) < 300) {
                // Chờ tối đa 5 phút để Cloudflare hiển thị ingress đã trả về PUT.
                // Sau mốc này trạng thái thật từ Cloudflare vẫn là "missing" để syncRoutes() có thể sửa.
                $routeStatus = 'pending';
            }
            $hostnamesOut[] = [
                'hostname' => $hn,
                'service' => (string)($h['service'] ?? ''),
                'url' => (string)($h['url'] ?? ('https://' . $h['hostname'])),
                'route_status' => $routeStatus,
            ];
        }
        if ($defaultUrl === '' && $hostnamesOut !== []) {
            $defaultUrl = (string)($hostnamesOut[0]['url'] ?? '');
        }
        return [
            'configured' => trim((string)($cfg['api_token'] ?? '')) !== '',
            'account_id' => (string)($cfg['account_id'] ?? ''),
            'tunnel_id' => (string)($cfg['tunnel_id'] ?? ''),
            'tunnel_name' => (string)($cfg['tunnel_name'] ?? ''),
            'hostname' => (string)($cfg['hostname'] ?? ''),
            'service' => (string)($cfg['service'] ?? ''),
            'zone_id' => (string)($cfg['zone_id'] ?? ''),
            'panel_hostname' => $panelHostname,
            'panel_url' => $panelHostname !== '' ? 'https://' . $panelHostname : '',
            'panel_configured' => $panelHostname !== '',
            'running' => $running,
            'health' => $health,
            'url' => $defaultUrl,
            'hostnames' => $hostnamesOut,
            'zones' => $zones,
            'zone_warn' => $zoneWarn,
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

    /**
     * V15.3.9 — Tự động kiểm tra & đồng bộ route: so ingress thật trên Cloudflare
     * với danh sách tên miền đã gắn (config local) và tự sửa route còn thiếu.
     * @return array{fixed: list<array{hostname, service, action}>, missing_dns: list<string>, errors: list<string>}
     */
    public function syncRoutes(): array
    {
        $info = $this->cf();
        $cfg = $info['cfg'];
        $tunnelId = (string)($cfg['tunnel_id'] ?? '');
        $accountId = (string)($info['account_id'] ?? '');
        $fixed = [];
        $missingDns = [];
        $errors = [];
        if ($tunnelId === '') { return ['fixed' => [], 'missing_dns' => [], 'errors' => ['Chưa có tunnel.']]; }
        // 1. Đọc ingress THẬT trên Cloudflare
        $ingressReal = [];
        try {
            $ingCfg = $this->api('GET', '/accounts/' . $accountId . '/cfd_tunnel/' . $tunnelId . '/configurations');
            foreach ((array)(($ingCfg['config'] ?? [])['ingress'] ?? []) as $rule) {
                $hn = (string)($rule['hostname'] ?? '');
                if ($hn !== '') { $ingressReal[strtolower($hn)] = (string)($rule['service'] ?? ''); }
            }
        } catch (Throwable $e) {
            return ['fixed' => [], 'missing_dns' => [], 'errors' => ['Không đọc được cấu hình tunnel: ' . $e->getMessage()]];
        }
        $hostnames = (array)($cfg['hostnames'] ?? []);
        // 2. Tên miền đã gắn nhưng CHƯA có route → thêm route tự động (giữ nguyên rule cũ — multi-site)
        $changed = false;
        $ingressToPut = null;
        foreach ($hostnames as $h) {
            $hn = strtolower((string)($h['hostname'] ?? ''));
            if ($hn === '' || isset($ingressReal[$hn])) { continue; }
            try {
                $current = $this->api('GET', '/accounts/' . $accountId . '/cfd_tunnel/' . $tunnelId . '/configurations');
            } catch (Throwable $e) {
                $current = ['config' => ['ingress' => [['service' => 'http_status:404']]]];
            }
            $ingress = (array)(($current['config'] ?? [])['ingress'] ?? []);
            $already = false;
            foreach ($ingress as $rule) {
                if (strcasecmp((string)($rule['hostname'] ?? ''), $hn) === 0) { $already = true; break; }
            }
            if ($already) { $ingressReal[$hn] = ''; continue; }
            $ingress = array_values(array_filter($ingress, static function ($rule) { return isset($rule['hostname']); }));
            $ingress[] = ['hostname' => $h['hostname'] ?? $hn, 'service' => (string)($h['service'] ?? ''), 'originRequest' => (object)[
                'connectTimeout' => 10, 'tcpKeepAlive' => 60, 'noHappyEyeballs' => true,
                'httpHostHeader' => $h['hostname'] ?? $hn,
            ]];
            $ingress[] = ['service' => 'http_status:404'];
            try {
                $this->api('PUT', '/accounts/' . $accountId . '/cfd_tunnel/' . $tunnelId . '/configurations', ['config' => ['ingress' => $ingress]]);
                $fixed[] = ['hostname' => (string)($h['hostname'] ?? $hn), 'service' => (string)($h['service'] ?? ''), 'action' => 'Đã thêm route còn thiếu'];
                $ingressReal[$hn] = (string)($h['service'] ?? '');
                $changed = true;
            } catch (Throwable $e) {
                $errors[] = $h['hostname'] . ': không thể thêm route — ' . $e->getMessage();
            }
        }
        // 3. Nếu ingress được lấy lại từ Cloudflare ngay trước đó (thay vì PUT vừa xong), dùng luôn
        if (!$changed && $ingressToPut !== null) {
            // không cần hành động
        }
        // 4. Cảnh báo record DNS: tên miền đã gắn nhưng DNS không trỏ về tunnel
        $expectedCname = strtolower($tunnelId) . '.cfargotunnel.com';
        foreach ($hostnames as $h) {
            $hn = (string)($h['hostname'] ?? '');
            $zoneId = (string)($h['zone_id'] ?? '');
            if ($hn === '' || $zoneId === '') { continue; }
            if (isset($ingressReal[strtolower($hn)]) && $ingressReal[strtolower($hn)] === '') { continue; }
            try {
                $records = $this->dnsRecords($zoneId);
                $ok = false;
                foreach ($records as $r) {
                    if (strcasecmp($r['name'], $hn) === 0 && strcasecmp($r['type'], 'CNAME') === 0
                        && rtrim(strtolower((string)$r['content']), '.') === $expectedCname) {
                        $ok = true; break;
                    }
                }
                if (!$ok) { $missingDns[] = $hn; }
            } catch (Throwable $e) { /* bỏ qua — không bắt buộc */ }
        }
        // 5. Đồng bộ danh sách hostnames local nếu record_id trống (tái tạo lần đầu khi sync)
        if ($changed) {
            foreach ($hostnames as &$h) {
                $hn = strtolower((string)($h['hostname'] ?? ''));
                if ($hn !== '' && isset($ingressReal[$hn]) && ($h['record_id'] ?? '') === '') {
                    $records = $this->dnsRecords((string)($h['zone_id'] ?? ''));
                    foreach ($records as $r) {
                        if (strcasecmp($r['name'], $hn) === 0) {
                            $h['record_id'] = $r['id'];
                            break;
                        }
                    }
                }
            }
            unset($h);
            $cfg['hostnames'] = $hostnames;
            $this->writeJson($this->configFile, $cfg);
            @chmod($this->configFile, 0600);
        }
        return ['fixed' => $fixed, 'missing_dns' => $missingDns, 'errors' => $errors];
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
