<?php
declare(strict_types=1);

function failV17024(string $m): never { fwrite(STDERR, "FAIL: {$m}\n"); exit(1); }
function expectV17024(bool $ok, string $m): void { if (!$ok) failV17024($m); }

$root = realpath(dirname(__DIR__));
$restart = (string)file_get_contents($root . '/scripts/tms-update-restart.sh');
$config = require $root . '/config/app.php';
$sw = (string)file_get_contents($root . '/public/service-worker.js');

expectV17024(($config['build'] ?? '') === 'Platform V17.0.24', 'Build phải là V17.0.24.');
expectV17024(str_contains($sw, "const VERSION='tms-os-v17.0.24';"), 'Service Worker phải là V17.0.24.');
expectV17024(str_contains($restart, 'rollback_source'), 'Worker phải có rollback source.');
expectV17024(str_contains($restart, 'panel_ok'), 'Worker phải health-check panel.');
foreach (['tms-php-engine.sh', 'nginx -s reload', 'tms-cloudflare-tunnel.sh', 'start-tms.sh'] as $forbidden) {
    expectV17024(!str_contains($restart, $forbidden), 'Hot update không được gọi: ' . $forbidden);
}
expectV17024(str_contains($restart, 'không có dịch vụ nào bị restart/reload'), 'Thiếu thông báo zero-downtime.');

echo "PASS: V17.0.24 hot update không chạm Nginx/PHP/Tunnel và tự rollback source.\n";
