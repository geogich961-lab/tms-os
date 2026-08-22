<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-os-access-report-' . bin2hex(random_bytes(4));
$home = $base . '/home';
mkdir($home . '/logs/nginx', 0700, true);
mkdir($home . '/.tms-os/cloudflare-hosting', 0700, true);
putenv('HOME=' . $home);
$prefix = $base . '/prefix';
mkdir($prefix . '/etc/nginx', 0700, true);
mkdir($prefix . '/etc/nginx/sites-enabled', 0700, true);
mkdir($prefix . '/bin', 0700, true);
file_put_contents($prefix . '/etc/nginx/nginx.conf', "worker_processes 1;\nevents { worker_connections 32; }\nhttp {\n}\n");
file_put_contents($prefix . '/etc/nginx/sites-enabled/demo.conf', "server { access_log {$home}/logs/nginx/demo-access.log; }\n");
file_put_contents($prefix . '/bin/nginx', "#!/bin/sh\nexit 0\n");
chmod($prefix . '/bin/nginx', 0700);
putenv('PREFIX=' . $prefix);

require $root . '/app/Services/UnifiedSystemCoreService.php';
require $root . '/app/Services/SystemService.php';
require $root . '/app/Services/MonitoringService.php';
require $root . '/app/Services/CronJobService.php';
require $root . '/app/Services/CloudflareDomainService.php';
require $root . '/app/Services/TelegramCommandService.php';
require $root . '/app/Services/AccessReportService.php';

file_put_contents($home . '/logs/nginx/tms-access.log', "203.0.113.42 - - [22/Aug/2026:17:00:01 +0700] \"GET /login?token=private-value HTTP/1.1\" 200 14 \"-\" \"test\"\n");
file_put_contents($home . '/logs/nginx/default-access.log', "198.51.100.8 - - [22/Aug/2026:17:00:02 +0700] \"GET /?secret=private-value HTTP/1.1\" 404 14 \"-\" \"test\"\n");
file_put_contents($home . '/logs/nginx/demo-access.log', "203.0.113.42 - - [22/Aug/2026:17:00:03 +0700] \"GET /wp-admin?cookie=private-value HTTP/1.1\" 500 14 \"-\" \"test\"\n");

$cron = new CronJobService();
$cron->saveTelegramConfig('test-token-must-not-leak', '123456789');
$sent = [];
$transport = static function (string $method, array $data) use (&$sent): array {
    $sent[] = ['method' => $method, 'data' => $data];
    return ['ok' => true];
};
$commands = new TelegramCommandService($cron, new MonitoringService(new SystemService()), new CloudflareDomainService(), $transport);
file_put_contents($home . '/.tms-os/access-report-config.json', json_encode(['enabled' => true]));

$reports = new AccessReportService($cron, $commands);
$ensureRealIp = Closure::bind(function (): void { $this->ensureTrustedVisitorIpConfiguration(); }, $reports, AccessReportService::class);
$ensureRealIp();
$migratedNginxConfig = (string)file_get_contents($prefix . '/etc/nginx/nginx.conf');
$migratedSiteConfig = (string)file_get_contents($prefix . '/etc/nginx/sites-enabled/demo.conf');
$preinstalledNginxConfig = "worker_processes 1;\nevents { worker_connections 32; }\nhttp {\n    set_real_ip_from 127.0.0.1;\n    set_real_ip_from ::1;\n    real_ip_header CF-Connecting-IP;\n    real_ip_recursive off;\n}\n";
file_put_contents($prefix . '/etc/nginx/nginx.conf', $preinstalledNginxConfig);
$ensureRealIp();
$nginxConfig = (string)file_get_contents($prefix . '/etc/nginx/nginx.conf');
$unmarkedInstallerConfig = <<<'NGINX'
worker_processes 1;
events { worker_connections 32; }
http {
    set_real_ip_from 127.0.0.1;
    set_real_ip_from ::1;
    real_ip_header CF-Connecting-IP;
    real_ip_recursive off;
    map $realip_remote_addr $tms_from_cloudflared {
        127.0.0.1 1;
        ::1 1;
        default 0;
    }
    map "$tms_from_cloudflared:$http_cf_connecting_ip:$http_x_forwarded_for" $tms_access_client {
        ~^1:(?<tms_cf_ip>[0-9][0-9]?\.[0-9][0-9]?\.[0-9][0-9]?\.[0-9][0-9]?|[0-9A-Fa-f:]+): $tms_cf_ip;
        ~^1::(?<tms_fallback_ip>[^,\s]+)(?:\s*,|\s*$) $tms_fallback_ip;
        default $remote_addr;
    }
    log_format tms_access '$tms_access_client - $remote_user [$time_local] "$request" $status $body_bytes_sent "$http_referer" "$http_user_agent"';
}
NGINX;
file_put_contents($prefix . '/etc/nginx/nginx.conf', $unmarkedInstallerConfig);
$ensureRealIp();
$unmarkedAfterEnsure = (string)file_get_contents($prefix . '/etc/nginx/nginx.conf');
$first = $reports->runHourly();
$firstText = (string)($sent[0]['data']['text'] ?? '');
$statePath = $home . '/.tms-os/access-report-state.json';
$state = json_decode((string)file_get_contents($statePath), true);
$mode = fileperms($statePath) & 0777;

$second = $reports->runHourly();
$secondText = (string)($sent[1]['data']['text'] ?? '');

$noSensitiveData = !str_contains($firstText, '/login')
    && !str_contains($firstText, 'private-value')
    && !str_contains($firstText, 'test-token-must-not-leak');
$ok = !empty($first['ok'])
    && !empty($second['ok'])
    && count($sent) === 2
    && (string)($sent[0]['data']['chat_id'] ?? '') === '123456789'
    && str_contains($firstText, '203.0.113.42')
    && str_contains($firstText, '198.51.100.8')
    && str_contains($firstText, 'Panel TMS OS')
    && str_contains($firstText, 'Website mặc định')
    && str_contains($firstText, 'Website: demo')
    && str_contains($firstText, '1 × 4xx')
    && str_contains($firstText, '1 × 5xx')
    && str_contains($secondText, 'Chưa ghi nhận request mới')
    && $noSensitiveData
    && $mode === 0600
    && str_contains($migratedNginxConfig, 'set_real_ip_from 127.0.0.1;')
    && str_contains($migratedNginxConfig, 'set_real_ip_from ::1;')
    && str_contains($migratedNginxConfig, 'real_ip_header CF-Connecting-IP;')
    && str_contains($migratedNginxConfig, 'log_format tms_access')
    && str_contains($migratedNginxConfig, '$http_x_forwarded_for')
    && substr_count($nginxConfig, 'real_ip_header CF-Connecting-IP;') === 1
    && str_contains($nginxConfig, 'log_format tms_access')
    && str_contains($migratedSiteConfig, 'demo-access.log tms_access;')
    && substr_count($unmarkedAfterEnsure, 'map $realip_remote_addr $tms_from_cloudflared') === 1
    && substr_count($unmarkedAfterEnsure, 'log_format tms_access') === 1
    && isset($state['files'][$home . '/logs/nginx/tms-access.log']['offset']);

exec('rm -rf ' . escapeshellarg($base));
if (!$ok) {
    fwrite(STDERR, "Access report service test failed.\n");
    exit(1);
}
echo "Access report service test passed.\n";
