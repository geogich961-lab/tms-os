<?php
declare(strict_types=1);

function failV17024(string $m): never { fwrite(STDERR, "FAIL: {$m}\n"); exit(1); }
function expectV17024(bool $ok, string $m): void { if (!$ok) failV17024($m); }

$root = realpath(dirname(__DIR__));
$restart = (string)file_get_contents($root . '/scripts/tms-update-restart.sh');
$config = require $root . '/config/app.php';
$sw = (string)file_get_contents($root . '/public/service-worker.js');
$build = (string)($config['build'] ?? '');

expectV17024(preg_match('/Platform V(\d+\.\d+\.\d+)/', $build, $m) === 1, 'Build phải có định dạng version hợp lệ.');
expectV17024(version_compare($m[1], '17.0.24', '>='), 'Build không được thấp hơn V17.0.24.');
expectV17024(str_contains($sw, "const VERSION='tms-os-v{$m[1]}';"), 'Service Worker phải khớp build hiện tại.');
expectV17024(str_contains($restart, 'rollback_source'), 'Worker phải có rollback source.');
expectV17024(str_contains($restart, 'panel_ok'), 'Worker phải health-check panel.');
foreach (['tms-php-engine.sh', 'nginx -s reload', 'tms-cloudflare-tunnel.sh', 'start-tms.sh'] as $forbidden) {
    expectV17024(!str_contains($restart, $forbidden), 'Hot update không được gọi: ' . $forbidden);
}
expectV17024(str_contains($restart, 'không có dịch vụ nào bị restart/reload'), 'Thiếu thông báo zero-downtime.');

echo "PASS: contract V17.0.24+ hot update không chạm Nginx/PHP/Tunnel và tự rollback source.\n";
