<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/cfdomain/index.php');
$scriptPath = $root . '/public/assets/cfdomain.js';
$script = (string) file_get_contents($scriptPath);
$service = (string) file_get_contents($root . '/app/Services/CloudflareDomainService.php');
$controller = (string) file_get_contents($root . '/app/Controllers/CloudflareDomainController.php');

if (!str_contains($view, '/assets/cfdomain.js')) {
    fwrite(STDERR, "Cloudflare Hosting view does not load its page controller.\n");
    exit(1);
}
if (!str_contains($view, '/api/cloudflare-domain/status')) {
    fwrite(STDERR, "Cloudflare Hosting view does not provide its status endpoint.\n");
    exit(1);
}
if ($script === '') {
    fwrite(STDERR, "Cloudflare Hosting page controller is missing or empty.\n");
    exit(1);
}

foreach ([
    '/api/cloudflare-domain/token',
    '/api/cloudflare-domain/account-info',
    '/api/cloudflare-domain/create-tunnel',
    '/api/cloudflare-domain/attach',
    '/api/cloudflare-domain/attach-panel',
    '/api/cloudflare-domain/perf-optimize',
    '/api/cloudflare-domain/sync-routes',
    'Promise.allSettled',
    'latestStatus',
    'setZoneWarning',
    'refresh({ silent: true })',
    'Đang đồng bộ Cloudflare',
    'Không thể dừng tunnel từ panel đang chạy qua chính tunnel.',
    'Dừng Tunnel (chỉ từ localhost/LAN)',
    '<br><small>',
] as $required) {
    if (!str_contains($script, $required)) {
        fwrite(STDERR, "Cloudflare Hosting controller lacks required integration: {$required}\n");
        exit(1);
    }
}

foreach ([
    "'/zones?per_page=50'",
    "'zone_warn' => \$zoneWarn",
    "\$accountIdSt = trim((string)(\$cfg['account_id'] ?? ''));",
    "if (\$tunnelIdSt !== '' && \$accountIdSt !== ''",
    "Không thể đọc route tunnel hiện có; chưa thay đổi DNS hoặc hostname.",
    "\$service = \$this->normalizeTunnelService(\$service);",
    "return 'http://127.0.0.1:' . \$port;",
    "'config' => ['ingress' => \$ingress]",
    "Không thể cập nhật route tunnel; chưa thay đổi DNS hoặc hostname.",
    "\$this->cacheForget('GET', \$path);",
    "null, true);",
    "'route_status' => \$routeStatus",
    "'route_pending_at' => \$verified ? 0 : time()",
    'Đã có Cloudflare Tunnel được lưu trong TMS OS.',
    'Cloudflared đã thoát ngay sau khi khởi động.',
    'usleep(500000)',
    'private function managedWebsiteIngress(array $cfg): array',
    'private function mergedTunnelIngress(array $cfg, array $currentIngress, string $panelHostname = \'\', bool $includePanel = false): array',
    "foreach ((array)(\$cfg['hostnames'] ?? []) as \$site)",
    'Hợp nhất toàn bộ route website trước khi thêm panel, không thay thế ingress.',
] as $required) {
    if (!str_contains($service, $required)) {
        fwrite(STDERR, "Cloudflare Hosting service lacks safe Zone fallback: {$required}\n");
        exit(1);
    }
}

foreach ([
    'private function isPublicPanelRequest(): bool',
    'Không thể dừng Cloudflare Tunnel từ panel đang chạy qua chính tunnel.',
    '$this->cfDomain->stopTunnel()',
] as $required) {
    if (!str_contains($controller, $required)) {
        fwrite(STDERR, "Cloudflare Hosting controller lacks tunnel-stop safety: {$required}\n");
        exit(1);
    }
}

echo "OK: Cloudflare Hosting safely preserves routes, blocks destructive remote tunnel stops, and supports non-destructive route synchronization.\n";
