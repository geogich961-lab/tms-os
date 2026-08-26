<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = (string)file_get_contents($root . '/app/Services/AppInstallerService.php');
$controller = (string)file_get_contents($root . '/app/Controllers/MarketplaceController.php');
$startup = (string)file_get_contents($root . '/scripts/start-tms.sh');
$installer = (string)file_get_contents($root . '/scripts/install.sh');
$rootInstaller = (string)file_get_contents($root . '/install.sh');
$adminSetup = (string)file_get_contents($root . '/scripts/tms-setup-admin.sh');
$phpEngine = (string)file_get_contents($root . '/scripts/tms-php-engine.sh');
$serviceCore = (string)file_get_contents($root . '/scripts/tms-service-core.sh');

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
$cleanRemoval = strpos($installer, 'rm -rf "$RUNTIME_ROOT"');
$runtimeInit = strpos($installer, 'if ! mkdir -p "$TMS_TMPDIR"');
if ($cleanRemoval === false || $runtimeInit === false || $cleanRemoval > $runtimeInit) {
    fwrite(STDERR, "Installer lock regression: clean mode must remove runtime before recreating TMPDIR.\n"); exit(1);
}
$oldRuntimeInit = strpos($installer, 'mkdir -p "$TMS_TMPDIR" "$RUNTIME_ROOT/backups"');
if ($oldRuntimeInit !== false && $oldRuntimeInit < strpos($installer, 'HAS_OLD=0')) {
    fwrite(STDERR, "Installer lock regression: runtime must not be created before clean/repair selection.\n"); exit(1);
}
if (!str_contains($rootInstaller, 'TMS_INSTALL_MODE="clean"')) {
    fwrite(STDERR, "Installer mode regression: root installer must explicitly pass clean mode.\n"); exit(1);
}
foreach (['php-cgi -n -d "sys_temp_dir=$ENGINE_TMPDIR" -b 127.0.0.1:9000', "php-cgi (-n )?-b 127.0.0.1:9000", 'rm -f "$PID"', 'TERMUX_VAR_TMP="$PREFIX/var/tmp"', 'mkdir -p "$STATE" "$(dirname "$LOG")" "$ENGINE_TMPDIR" "$TERMUX_VAR_TMP" "$PREFIX/var/run"'] as $needle) {
    if (!str_contains($phpEngine, $needle)) {
        fwrite(STDERR, "PHP Engine regression: missing {$needle}\n"); exit(1);
    }
}
if (str_contains($phpEngine, 'nohup php-cgi -b 127.0.0.1:9000') || str_contains($phpEngine, 'nohup php-cgi -n -b 127.0.0.1:9000')) {
    fwrite(STDERR, "PHP Engine regression: CGI must not start with the system php.ini.\n"); exit(1);
}
foreach (['fuser 9000/tcp', 'php-cgi .* -b 127\\.0\\.0\\.1:9000', 'php .* -S 127\\.0\\.0\\.1:9000', 'bash "$ROOT/scripts/tms-php-engine.sh" start'] as $needle) {
    if (!str_contains($serviceCore, $needle)) {
        fwrite(STDERR, "Service Manager PHP status regression: missing {$needle}\n"); exit(1);
    }
}
if (str_contains($serviceCore, "php-cgi -b 127\\.0\\.0\\.1:9000';")) {
    fwrite(STDERR, "Service Manager PHP status regression: legacy CGI pattern must not remain.\n"); exit(1);
}
if (!str_contains($rootInstaller, 'mktemp "$PREFIX/var/tmp/tms-installer.XXXXXX"') || !str_contains($installer, 'mktemp "$TERMUX_VAR_TMP/tms-preflight.XXXXXX"')) {
    fwrite(STDERR, "Installer temp preflight regression: missing var/tmp mktemp check.\n"); exit(1);
}
foreach ([$installer, $adminSetup] as $script) {
    if (!str_contains($script, 'php -n -r') || !str_contains($script, 'stream_get_contents(STDIN')) {
        fwrite(STDERR, "Password hash regression: installer must hash through stdin with PHP config disabled.\n"); exit(1);
    }
}
echo "App installer service test passed.\n";
