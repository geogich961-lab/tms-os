<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = (string)file_get_contents($root . '/app/Services/AppInstallerService.php');
$controller = (string)file_get_contents($root . '/app/Controllers/MarketplaceController.php');
$startup = (string)file_get_contents($root . '/scripts/start-tms.sh');

foreach (['CURLOPT_FAILONERROR', "'health' => \$appInfo['type'] === 'service' ? 'running' : 'ready'", "['id' => 'file-browser'"] as $needle) {
    if (!str_contains($service, $needle)) { fwrite(STDERR, "App installer regression: missing {$needle}\n"); exit(1); }
}
if (str_contains($service, "['id' => 'adguard-home'") || str_contains($service, 'installAdGuardHome')) {
    fwrite(STDERR, "AdGuard Home must be removed from the Marketplace installer.\n"); exit(1);
}
foreach (['private AuthService $auth', 'tms_verify_csrf', 'HTTP_X_CSRF_TOKEN'] as $needle) {
    if (!str_contains($controller, $needle)) { fwrite(STDERR, "Marketplace access control regression: missing {$needle}\n"); exit(1); }
}
foreach (['start-filebrowser-*.sh', 'marketplace-services.log'] as $needle) {
    if (!str_contains($startup, $needle)) { fwrite(STDERR, "Marketplace service recovery regression: missing {$needle}\n"); exit(1); }
}
echo "App installer service test passed.\n";
