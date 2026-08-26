<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/helpers.php';

$version = tms_asset_version();
if (!str_starts_with($version, '17.0.4') || str_contains($version, '-test')) {
    fwrite(STDERR, "Expected a stable V17.0.4 asset version, received: {$version}\n");
    exit(1);
}

echo "OK: stable asset version is {$version}\n";
