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
$base = sys_get_temp_dir() . '/tms-website-upload-nginx-' . bin2hex(random_bytes(4));
$home = $base . '/home';
$prefix = $base . '/prefix';
$sites = $prefix . '/etc/nginx/sites-enabled';
mkdir($home . '/websites', 0700, true);
mkdir($home . '/.tms-os', 0700, true);
mkdir($sites, 0700, true);
putenv('HOME=' . $home);
putenv('PREFIX=' . $prefix);

require $root . '/app/Services/NetworkService.php';
require $root . '/app/Services/WebsiteService.php';

$service = new WebsiteService();
$reflection = new ReflectionClass($service);
$buildConfig = $reflection->getMethod('buildConfig');
$buildConfig->setAccessible(true);
$config = (string)$buildConfig->invoke($service, 'demo', 18083, $home . '/websites/demo/public');

check(str_contains($config, 'client_max_body_size 512M;'), 'Vhost website mới phải cho phép body upload đủ lớn.');
check(str_contains($config, 'client_body_timeout 300s;'), 'Vhost website mới phải có client body timeout rõ ràng.');
check(str_contains($config, 'fastcgi_read_timeout 300s;'), 'Vhost FastCGI phải chờ đủ lâu khi Android xử lý tệp.');
check(str_contains($config, 'fastcgi_send_timeout 300s;'), 'Vhost FastCGI phải có send timeout đồng nhất.');
check(str_contains($config, 'try_files $uri $uri/ /index.php?$query_string;'), 'Vhost website vẫn phải giữ front controller hiện tại.');

@rmdir($sites);
@rmdir(dirname($sites));
@rmdir($home . '/websites');
@rmdir($home . '/.tms-os');
@rmdir($home);
@rmdir($base);

echo "PASS: website vhost upload timeout contract\n";
