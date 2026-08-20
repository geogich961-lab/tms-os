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
        private CloudflareService $cloudflare,
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
        $cf = [];
        try {
            $cf = $this->cloudflare->status();
        } catch (Throwable $e) {
            $cf = ['status' => 'stopped', 'running' => false, 'provider' => 'auto', 'message' => '', 'log' => '', 'capabilities' => [], 'settings' => []];
        }
        $state = (string)($cf['status'] ?? 'stopped');
        $stateLabels = ['stopped' => 'Đã dừng', 'starting' => 'Đang khởi động', 'connecting' => 'Đang kết nối', 'verifying' => 'Đang xác minh', 'connected' => 'Đã kết nối', 'timeout' => 'Hết thời gian', 'error' => 'Có lỗi'];
        $stateLabel = $stateLabels[$state] ?? $state;
        tms_view('cfdomain.index', [
            'status' => $cf,
            'sites' => $this->websites->all(),
            'flash' => [],
            'csrf' => tms_csrf_token(),
            'state' => $state,
            'stateLabel' => $stateLabel,
            'settings' => $cf['settings'] ?? [],
            'caps' => $cf['capabilities'] ?? [],
        ]);
    }

    /** GET /api/cloudflare-domain/status */
    public function status(): void
    {
        $this->guard();
        $this->json(array_merge(['success' => true], $this->cfDomain->status()));
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
        $this->json(array_merge(['success' => true], $this->cfDomain->stopTunnel()));
    }

    /** POST /api/cloudflare-domain/detach */
    public function detach(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) { http_response_code(400); $this->json(['success' => false, 'error' => 'Phiên không hợp lệ.']); return; }
        try {
            $this->json(array_merge(['success' => true], $this->cfDomain->detachHostname()));
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
}
