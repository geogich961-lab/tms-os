<?php
declare(strict_types=1);

function failV17025(string $m): never { fwrite(STDERR, "FAIL: {$m}\n"); exit(1); }
function expectV17025(bool $ok, string $m): void { if (!$ok) failV17025($m); }

$root = realpath(dirname(__DIR__));
$guardian = (string)file_get_contents($root . '/scripts/tms-guardian.sh');
$config = require $root . '/config/app.php';
$sw = (string)file_get_contents($root . '/public/service-worker.js');

expectV17025(($config['build'] ?? '') === 'Platform V17.0.25', 'Build phải là V17.0.25.');
expectV17025(str_contains($sw, "const VERSION='tms-os-v17.0.25';"), 'Service Worker phải là V17.0.25.');
expectV17025(str_contains($guardian, 'EXTERNAL_UPDATE_LOCK="$STATE/external-update.lock"'), 'Guardian phải có maintenance lock cho app ngoài.');
expectV17025(str_contains($guardian, 'maintenance_active'), 'Guardian phải kiểm tra maintenance window.');
expectV17025(str_contains($guardian, 'confirm_upstream_failure'), 'Guardian phải xác nhận lỗi upstream lần hai trước khi repair.');
expectV17025(str_contains($guardian, 'sleep 2'), 'Guardian cần debounce lỗi upstream thoáng qua.');
expectV17025(str_contains($guardian, 'tms-php-engine.sh" restart'), 'Guardian vẫn phải tự phục hồi PHP khi lỗi thật kéo dài.');
expectV17025(!str_contains($guardian, 'nginx -s reload'), 'Repair PHP tự động không được reload Nginx.');
expectV17025(str_contains($guardian, 'Nginx/Tunnel không bị restart'), 'Thiếu contract cô lập Nginx/Tunnel.');

echo "PASS: V17.0.25 Guardian debounce lỗi thoáng qua và không kéo sập dịch vụ dùng chung khi app ngoài update.\n";
