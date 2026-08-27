<?php
declare(strict_types=1);

/**
 * TMS OS V15.0 — Controller cho Cloudflare Hosting (tunnel chính chủ + tên miền riêng).
 */
final class CloudflareDomainController
{
    public function __construct(
        private AuthService $auth,
        private CloudflareDomainService $cfDomain,
        private WebsiteService $websites,
    ) {}

    private function guard(): void
    {
        if (!$this->auth->check()) { tms_redirect('/login'); }
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function jsonError(Throwable $e): void
    {
        http_response_code(400);
        $this->json(['success' => false, 'error' => $e->getMessage()]);
    }

    private function isPublicPanelRequest(): bool
    {
        $panelHost = strtolower((string)parse_url($this->cfDomain->publicPanelUrl(), PHP_URL_HOST));
        $requestHost = strtolower(trim(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]));
        return $panelHost !== '' && $requestHost !== '' && $panelHost === $requestHost;
    }

    /** GET /cf-hosting */
    public function index(): void
    {
        $this->guard();
        $status = [];
        try {
            $status = $this->cfDomain->status();
        } catch (Throwable $e) {
            $status = ['configured' => false, 'tunnel_id' => '', 'tunnel_name' => '', 'hostname' => '', 'running' => false, 'health' => ['status' => 'unconfigured'], 'url' => '', 'log' => 'Chưa cấu hình Cloudflare Hosting.'] + (is_array($status) ? $status : []);
        }
        tms_view('cfdomain.index', [
            'sites' => $this->websites->all(),
            'csrf' => tms_csrf_token(),
        ]);
    }

    /** GET /api/cloudflare-domain/status */
    public function status(): void
    {
        $this->guard();
        $this->json(array_merge(['success' => true], $this->cfDomain->status()));
    }

    /** GET /status — trang theo dõi công khai, không yêu cầu đăng nhập. */
    public function publicStatusPage(): void
    {
        tms_view('status.public');
    }

    /** GET /api/public-status — dữ liệu đã lọc, chỉ đọc cho trang trạng thái. */
    public function publicStatus(): void
    {
        $this->json(['success' => true] + $this->cfDomain->publicStatus());
    }

    /** GET /api/cloudflare-domain/account-info (kiểm tra token + lấy account + zones) */
    public function accountInfo(): void
    {
        $this->guard();
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->accountInfo()));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** GET /api/cloudflare-domain/internal-sites (danh sách website nội bộ cho dropdown Bước 3) */
    public function internalSites(): void
    {
        $this->guard();
        $out = [];
        foreach (($this->websites->all() ?: []) as $site) {
            if (empty($site['enabled']) || empty($site['port']) || $site['port'] <= 0) {
                continue;
            }
            $out[] = [
                'name' => (string)$site['name'],
                'port' => (int)$site['port'],
                'service_url' => 'http://127.0.0.1:' . (int)$site['port'],
                'root' => (string)($site['root'] ?? ''),
                'status' => (string)($site['status'] ?? 'unknown'),
            ];
        }
        $this->json(['success' => true, 'sites' => $out]);
    }

    /** GET /api/cloudflare-domain/dns-records?zone_id=... */
    public function dnsRecords(): void
    {
        $this->guard();
        try {
            $this->json(['success' => true, 'records' => $this->cfDomain->dnsRecords((string)($_GET['zone_id'] ?? ''))]);
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/token (lưu API Token) */
    public function saveToken(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        $tok = trim((string)($_POST['api_token'] ?? ''));
        if (strlen($tok) < 20) { http_response_code(400); $this->json(['success' => false, 'error' => 'API Token không hợp lệ.']); return; }
        try {
            $this->cfDomain->saveApiToken($_POST);
            $this->json(['success' => true, 'message' => 'Đã lưu API Token.']);
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/create-tunnel */
    public function createTunnel(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->createTunnel()));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/attach */
    public function attach(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        $hostname = trim((string)($_POST['hostname'] ?? ''));
        $zoneId = trim((string)($_POST['zone_id'] ?? ''));
        if ($hostname === '' || $zoneId === '' || !preg_match('/^[a-z0-9._-]+\.[a-z]{2,}$/i', $hostname)) {
            http_response_code(400);
            $this->json(['success' => false, 'error' => 'Tên host hoặc domain không hợp lệ.']);
            return;
        }
        $target = trim((string)($_POST['target'] ?? ''));
        if ($target === '') { http_response_code(400); $this->json(['success' => false, 'error' => 'Chưa chọn website nội bộ.']); return; }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->attachHostname($zoneId, strtolower($hostname), $target)));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/start */
    public function start(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->startTunnel()));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/stop */
    public function stop(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        if ($this->isPublicPanelRequest()) {
            http_response_code(409);
            $this->json(['success' => false, 'error' => 'Không thể dừng Cloudflare Tunnel từ panel đang chạy qua chính tunnel. Hãy mở panel bằng localhost/LAN trên điện thoại nếu thực sự cần dừng tunnel.']);
            return;
        }
        $this->json(array_merge(['success' => true], $this->cfDomain->stopTunnel()));
    }

    /** POST /api/cloudflare-domain/public-wifi-dns — thiết lập opt-in resolver Termux. */
    public function publicWifiDns(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        try {
            $enabled = (string)($_POST['enabled'] ?? '') === '1';
            $this->json(array_merge(['success' => true], $this->cfDomain->setPublicWifiDnsCompatibility($enabled)));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/detach (không truyền hostname = tách tên chính) */
    public function detach(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        $hostname = trim((string)($_POST['hostname'] ?? ''));
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->detachHostname($hostname === '' ? null : $hostname)));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/delete-tunnel */
    public function deleteTunnel(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->deleteTunnel()));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/uninstall */
    public function uninstall(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->uninstall()));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/attach-panel — bật truy cập panel từ xa (Remote Access V15.2.0) */
    public function attachPanel(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        $panelHostname = trim((string)($_POST['panel_hostname'] ?? ''));
        if ($panelHostname === '') { http_response_code(400); $this->json(['success' => false, 'error' => 'Chưa nhập hostname cho panel.']); return; }
        if (!preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i', $panelHostname)) {
            http_response_code(400);
            $this->json(['success' => false, 'error' => 'Hostname không hợp lệ. Ví dụ: panel.thc.io.vn']);
            return;
        }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->attachPanelHostname($panelHostname)));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/detach-panel — tắt truy cập panel từ xa */
    public function detachPanel(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->detachPanelHostname()));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/perf-status — kiểm tra trạng thái tối ưu hiệu năng */
    public function perfStatus(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        if (!str_contains((string)getenv('PREFIX'), 'com.termux')) {
            $prefix = is_dir('/data/data/com.termux/files/usr') ? '/data/data/com.termux/files/usr' : '';
        } else {
            $prefix = (string)getenv('PREFIX');
        }
        $confPath = '';
        foreach ([$prefix . '/etc/nginx/nginx.conf', '/etc/nginx/nginx.conf'] as $p) {
            if ($p !== '' && is_file($p)) { $confPath = $p; break; }
        }
        $enabled = is_file($home . '/.tms-os/nginx-optimized')
            && $confPath !== ''
            && str_contains((string)@file_get_contents($confPath), 'gzip on;');
        $this->json(['success' => true, 'enabled' => $enabled]);
    }

    /** POST /api/cloudflare-domain/sync-routes — tự động kiểm tra & đồng bộ route Cloudflare (V15.3.9) */
    public function syncRoutes(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->syncRoutes()));
        } catch (Throwable $e) {
            $this->jsonError($e);
        }
    }

    /** POST /api/cloudflare-domain/perf-optimize — bật tối ưu Nginx + PHP */
    public function perfOptimize(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $script = $home . '/tms-os/scripts/optimize-nginx.sh';
        if (!is_file($script)) {
            $this->json(['success' => false, 'error' => 'Script tối ưu không tồn tại. Hãy cập nhật TMS OS lên phiên bản mới nhất rồi thử lại.']);
            return;
        }
        $out = [];
        $code = 0;
        exec('bash ' . escapeshellarg($script) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $this->json(['success' => false, 'error' => 'Không thể áp dụng tối ưu: ' . implode('\n', array_slice($out, -5))]);
            return;
        }
        $this->json(['success' => true, 'message' => 'Đã bật nén gzip, cache trình duyệt cho file tĩnh và OPcache cho PHP. Nginx & PHP đã khởi động lại.']);
    }
}
