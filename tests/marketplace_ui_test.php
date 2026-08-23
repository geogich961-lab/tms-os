<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/marketplace/index.php');
$css = (string) file_get_contents($root . '/public/assets/marketplace.css');
foreach (["\$showShell = true", 'marketplace-grid', 'data-marketplace-install', 'marketplace-modal', 'aria-hidden', 'marketplace.css', 'marketplace-installed', 'Mở trên máy chủ', 'X-CSRF-Token'] as $needle) {
    if (!str_contains($view, $needle)) { fwrite(STDERR, "Marketplace shell/UI regression: missing {$needle}\n"); exit(1); }
}
foreach (['.marketplace-grid', '@media (max-width: 560px)', '.marketplace-modal-dialog', '.marketplace-card', '.marketplace-installed-item'] as $needle) {
    if (!str_contains($css, $needle)) { fwrite(STDERR, "Marketplace responsive style missing {$needle}\n"); exit(1); }
}
if (str_contains($view, 'openInstallModal(') || str_contains($view, 'style="display: none;"')) { fwrite(STDERR, "Legacy Marketplace UI should not remain.\n"); exit(1); }
if (stripos($view, 'adguard') !== false) { fwrite(STDERR, "AdGuard Home must not remain visible in Marketplace UI.\n"); exit(1); }
$installedPos = strpos($view, 'marketplace-installed"');
$catalogPos = strpos($view, 'marketplace-grid"');
if ($installedPos === false || $catalogPos === false || $installedPos > $catalogPos) { fwrite(STDERR, "Installed apps must appear before the catalog.\n"); exit(1); }
echo "Marketplace UI test passed.\n";
