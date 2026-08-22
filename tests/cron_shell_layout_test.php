<?php
declare(strict_types=1);

$_SERVER['REQUEST_URI'] = '/cron';
$_COOKIE = [];
$jobs = [];
$telegram = [];

require dirname(__DIR__) . '/app/Core/helpers.php';

ob_start();
require dirname(__DIR__) . '/app/Views/cron/index.php';
$html = (string) ob_get_clean();

foreach (['<div class="os-shell">', '<aside class="os-sidebar"', '<header class="mobile-header">', '/assets/app.js?v='] as $required) {
    if (!str_contains($html, $required)) {
        fwrite(STDERR, "Cron layout is missing: {$required}\n");
        exit(1);
    }
}

echo "Cron shell layout test passed.\n";
