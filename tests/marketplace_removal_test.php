<?php
declare(strict_types=1);

function marketplaceRemovalFail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function marketplaceRemovalExpect(bool $condition, string $message): void
{
    if (!$condition) {
        marketplaceRemovalFail($message);
    }
}

$root = dirname(__DIR__);
$header = (string)file_get_contents($root . '/app/Views/layouts/header.php');
$routes = (string)file_get_contents($root . '/routes/web.php');
$startup = (string)file_get_contents($root . '/scripts/start-tms.sh');
$runtimePackages = (string)file_get_contents($root . '/app/Services/PluginService.php');
$manifest = (string)file_get_contents($root . '/public/manifest.php');

foreach ([
    'app/Controllers/MarketplaceController.php',
    'app/Controllers/AppInstallerController.php',
    'app/Services/AppInstallerService.php',
    'app/Views/marketplace/index.php',
    'app/Views/apps/index.php',
    'public/assets/marketplace.css',
    'app/Modules/app-installer/module.json',
] as $obsolete) {
    marketplaceRemovalExpect(!file_exists($root . '/' . $obsolete), "Tệp Marketplace không còn sử dụng vẫn tồn tại: {$obsolete}");
}

foreach (['App Marketplace', 'href="/marketplace"', 'href="/apps"'] as $needle) {
    marketplaceRemovalExpect(!str_contains($header, $needle), "Điều hướng Marketplace vẫn còn: {$needle}");
}
foreach (['MarketplaceController', 'AppInstallerController', 'AppInstallerService', "'/marketplace'", "'/apps'"] as $needle) {
    marketplaceRemovalExpect(!str_contains($routes, $needle), "Route Marketplace vẫn còn: {$needle}");
}
foreach (['App Installer', "'url'=>'/apps'"] as $needle) {
    marketplaceRemovalExpect(!str_contains($manifest, $needle), "Shortcut PWA Marketplace vẫn còn: {$needle}");
}

marketplaceRemovalExpect(str_contains($startup, 'start-filebrowser-*.sh'), 'Phải tiếp tục phục hồi File Browser đã cài từ phiên bản cũ.');
marketplaceRemovalExpect(str_contains($startup, 'managed-services.log'), 'Log dịch vụ cũ phải được chuyển sang tên trung tính.');
marketplaceRemovalExpect(!str_contains($startup, 'marketplace-services.log'), 'Không được giữ tên Marketplace trong cơ chế phục hồi dịch vụ cũ.');
foreach (['class PluginService', "'cloudflared'", "'/packages'"] as $needle) {
    marketplaceRemovalExpect(str_contains($runtimePackages . $routes, $needle), "Runtime Packages/Cloudflared phải được bảo toàn: {$needle}");
}

echo "PASS: App Marketplace removed while Runtime Packages and legacy File Browser recovery remain safe\n";
