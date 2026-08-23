<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = (string)file_get_contents($root . '/app/Services/CloudflareDomainService.php');
$controller = (string)file_get_contents($root . '/app/Controllers/CloudflareDomainController.php');
$routes = (string)file_get_contents($root . '/routes/web.php');
$view = (string)file_get_contents($root . '/app/Views/status/public.php');
$script = (string)file_get_contents($root . '/public/assets/public-status.js');

foreach (['publicStatus(): array', 'public-status.json', 'Cache trên đĩa 45 giây', "unset(\$cached['checked_at_unix'])"] as $needle) {
    if (!str_contains($service, $needle)) { fwrite(STDERR, "Missing safe public status service behavior: $needle\n"); exit(1); }
}
foreach (['publicStatusPage', 'publicStatus(): void', "'/api/public-status'", "'/status'"] as $needle) {
    if (!str_contains($controller . $routes, $needle)) { fwrite(STDERR, "Missing public status route/controller: $needle\n"); exit(1); }
}
foreach (['api/public-status', 'Cloudflare Tunnel', 'Hostname công khai', 'app.css', 'Đăng nhập quản trị', 'public-status.css'] as $needle) {
    if (!str_contains($view . $script, $needle)) { fwrite(STDERR, "Missing public status UI: $needle\n"); exit(1); }
}
$publicMethod = (string)strstr($service, 'public function publicStatus(): array');
$publicMethod = (string)strstr($publicMethod, 'private function publicTunnelState', true);
foreach (['api_token', 'tunnel_token', 'account_id', 'tunnel_id', 'service', "'log'"] as $forbidden) {
    if (preg_match('/\'' . preg_quote($forbidden, '/') . '\'\s*=>/', $publicMethod) === 1) { fwrite(STDERR, "Public summary exposes forbidden field: $forbidden\n"); exit(1); }
}
echo "Public status safety test passed.\n";
