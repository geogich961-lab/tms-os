<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/cfdomain/index.php');
$scriptPath = $root . '/public/assets/cfdomain.js';
$script = (string) file_get_contents($scriptPath);

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
    '/api/cloudflare-domain/create-tunnel',
    '/api/cloudflare-domain/attach',
    '/api/cloudflare-domain/attach-panel',
    '/api/cloudflare-domain/perf-optimize',
    'refresh({ silent: true })',
] as $required) {
    if (!str_contains($script, $required)) {
        fwrite(STDERR, "Cloudflare Hosting controller lacks required integration: {$required}\n");
        exit(1);
    }
}

echo "OK: Cloudflare Hosting UI controller is loaded and wired to required endpoints.\n";
