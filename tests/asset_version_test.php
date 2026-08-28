<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/helpers.php';

$version = tms_asset_version();
$app = require dirname(__DIR__) . '/config/app.php';
$build = (string)($app['build'] ?? '');
preg_match('/V(\d+(?:\.\d+){2})\b/', $build, $matches);
$expected = $matches[1] ?? '';
if ($expected === '' || !str_starts_with($version, $expected) || str_contains($version, '-test')) {
    fwrite(STDERR, "Expected a stable asset version beginning {$expected}, received: {$version}\n");
    exit(1);
}

echo "OK: stable asset version is {$version}\n";
