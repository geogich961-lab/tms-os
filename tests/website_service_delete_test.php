<?php
declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-website-delete-' . bin2hex(random_bytes(4));
$home = $base . '/home';
$prefix = $base . '/prefix';
$sites = $prefix . '/etc/nginx/sites-enabled';
$bin = $base . '/bin';

mkdir($home . '/websites/default/public', 0700, true);
mkdir($home . '/.tms-os', 0700, true);
mkdir($sites, 0700, true);
mkdir($bin, 0700, true);
file_put_contents($bin . '/nginx', "#!/bin/sh\nexit 0\n");
chmod($bin . '/nginx', 0700);
putenv('HOME=' . $home);
putenv('PREFIX=' . $prefix);
putenv('PATH=' . $bin . ':' . getenv('PATH'));

file_put_contents($sites . '/default.conf.disabled', "server { listen 0.0.0.0:8080; root {$home}/websites/default/public; }\n");
file_put_contents($sites . '/games.conf', "server { listen 0.0.0.0:8081; root {$home}/websites/games/public; }\n");
file_put_contents($home . '/.tms-os/local-domains.json', json_encode([
    'default' => ['local_domain' => 'default.localhost', 'lan_domain' => 'default.lan', 'port' => 8080],
    'games' => ['local_domain' => 'games.localhost', 'lan_domain' => 'games.lan', 'port' => 8081],
], JSON_UNESCAPED_SLASHES));

require $root . '/app/Services/NetworkService.php';
require $root . '/app/Services/WebsiteService.php';

$service = new WebsiteService();
$service->delete('default', false);

check(!file_exists($sites . '/default.conf.disabled'), 'Website default đang dừng phải xóa được cấu hình .conf.disabled.');
check(is_file($sites . '/games.conf'), 'Xóa default không được tác động cấu hình website khác.');
check(is_dir($home . '/websites/default/public'), 'Không chọn xóa source phải giữ nguyên source website default.');
$domains = json_decode((string)file_get_contents($home . '/.tms-os/local-domains.json'), true);
check(is_array($domains) && !isset($domains['default']) && isset($domains['games']), 'Xóa default phải chỉ gỡ tên miền của default.');

echo "PASS: website service deletes disabled default site safely\n";
