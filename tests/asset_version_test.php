<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/helpers.php';

$version = tms_asset_version();
if (!str_starts_with($version, '16.1.0-test')) {
    fwrite(STDERR, "Expected a TEST asset version, received: {$version}\n");
    exit(1);
}

echo "OK: TEST asset version is {$version}\n";
