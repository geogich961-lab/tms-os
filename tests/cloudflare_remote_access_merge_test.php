<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Services/CloudflareDomainService.php';

$service = new CloudflareDomainService();
$method = new ReflectionMethod(CloudflareDomainService::class, 'mergedTunnelIngress');
$method->setAccessible(true);

$cfg = [
    // Legacy default không còn được xem là danh sách route đầy đủ.
    'hostname' => 'game.thc.io.vn',
    'service' => 'http://127.0.0.1:8081',
    'panel_hostname' => 'panel.thc.io.vn',
    'hostnames' => [
        ['hostname' => 'thc.io.vn', 'service' => 'http://127.0.0.1:8083'],
        ['hostname' => 'game.thc.io.vn', 'service' => 'http://127.0.0.1:8081'],
    ],
];
$current = [
    ['hostname' => 'game.thc.io.vn', 'service' => 'http://127.0.0.1:8081'],
    // Rule panel cũ phải được thay bằng service panel chuẩn, không bị nhân đôi.
    ['hostname' => 'panel.thc.io.vn', 'service' => 'http://127.0.0.1:9999'],
    // Một route chỉ có trên Cloudflare cũng không được mất khi bật Remote Access.
    ['hostname' => 'manual.thc.io.vn', 'service' => 'http://127.0.0.1:8181'],
    ['service' => 'http_status:404'],
];

$ingress = $method->invoke($service, $cfg, $current, 'panel.thc.io.vn', true);
$byHostname = [];
$catchAll = 0;
foreach ($ingress as $rule) {
    if (isset($rule['hostname'])) {
        $byHostname[(string)$rule['hostname']] = (string)($rule['service'] ?? '');
    }
    if (($rule['service'] ?? '') === 'http_status:404') { $catchAll++; }
}
$expected = [
    'thc.io.vn' => 'http://127.0.0.1:8083',
    'game.thc.io.vn' => 'http://127.0.0.1:8081',
    'panel.thc.io.vn' => 'http://127.0.0.1:8888',
    'manual.thc.io.vn' => 'http://127.0.0.1:8181',
];
ksort($byHostname);
ksort($expected);
if ($byHostname !== $expected || $catchAll !== 1 || (($ingress[array_key_last($ingress)]['service'] ?? '') !== 'http_status:404')) {
    fwrite(STDERR, 'Remote Access ingress merge did not preserve all website and panel routes.\n');
    exit(1);
}

// Tắt Remote Access chỉ được bỏ panel, các website và route thủ công vẫn còn.
$withoutPanel = $method->invoke($service, $cfg, $ingress, 'panel.thc.io.vn', false);
$hostsAfterDetach = array_values(array_filter(array_map(static fn(array $rule): string => (string)($rule['hostname'] ?? ''), $withoutPanel)));
sort($hostsAfterDetach);
if ($hostsAfterDetach !== ['game.thc.io.vn', 'manual.thc.io.vn', 'thc.io.vn']) {
    fwrite(STDERR, 'Disabling Remote Access removed a website route or retained the panel route.\n');
    exit(1);
}

echo "Cloudflare Remote Access ingress merge test passed.\n";
