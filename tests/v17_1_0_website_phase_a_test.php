<?php
declare(strict_types=1);

function failPhaseA(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}
function expectPhaseA(bool $condition, string $message): void
{
    if (!$condition) failPhaseA($message);
}

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-site-phase-a-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$prefix = $temp . '/prefix';
$sitesDir = $prefix . '/etc/nginx/sites-enabled';
@mkdir($home . '/.tms-os', 0700, true);
@mkdir($home . '/websites/demo/public', 0700, true);
@mkdir($sitesDir, 0700, true);
putenv('HOME=' . $home);
putenv('PREFIX=' . $prefix);

require $root . '/app/Services/NetworkService.php';
require $root . '/app/Services/WebsiteHealthService.php';
require $root . '/app/Services/WebsiteMetadataService.php';
require $root . '/app/Services/WebsiteService.php';

try {
    // 1. Metadata được tạo riêng cho website và có thể đồng bộ port/root.
    $metadata = new WebsiteMetadataService();
    $first = $metadata->ensure('demo', 19080, $home . '/websites/demo/public');
    expectPhaseA(($first['schema'] ?? null) === 1, 'Metadata phải dùng schema 1.');
    expectPhaseA(($first['type'] ?? '') === 'php', 'Website mặc định phải có type php.');
    expectPhaseA(is_file($home . '/.tms-os/sites/demo.json'), 'Metadata website phải được lưu dưới ~/.tms-os/sites.');

    $updated = $metadata->ensure('demo', 19081, $home . '/websites/demo/public');
    expectPhaseA((int)($updated['port'] ?? 0) === 19081, 'Metadata phải đồng bộ khi port thay đổi.');

    // 2. Auto Port bỏ qua cổng đã có trong vhost.
    file_put_contents(
        $sitesDir . '/existing.conf',
        "server { listen 0.0.0.0:19080; root {$home}/websites/demo/public; }\n"
    );
    $sites = new WebsiteService();
    $autoPort = $sites->findAvailablePort(19080, 19082);
    expectPhaseA($autoPort === 19081 || $autoPort === 19082, 'Auto Port phải bỏ qua cổng đã được website khác dùng.');
    expectPhaseA($autoPort !== 19080, 'Auto Port không được trả lại cổng nằm trong Nginx config.');

    // 3. HTTP Health Check phải đọc được HTTP status thực, không chỉ kiểm tra TCP.
    $healthPort = 19190;
    while (@fsockopen('127.0.0.1', $healthPort, $errno, $errstr, 0.05)) {
        $healthPort++;
    }
    $router = $temp . '/health-router.php';
    file_put_contents($router, "<?php http_response_code(200); header('Content-Type: text/plain'); echo 'ok';\n");
    $log = $temp . '/server.log';
    $command = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $healthPort . ' ' . escapeshellarg($router);
    $process = proc_open($command, [['pipe','r'], ['file',$log,'a'], ['file',$log,'a']], $pipes, $temp);
    expectPhaseA(is_resource($process), 'Không khởi chạy được PHP test server cho health check.');

    $ready = false;
    for ($i = 0; $i < 30; $i++) {
        $sock = @fsockopen('127.0.0.1', $healthPort, $errno, $errstr, 0.1);
        if (is_resource($sock)) {
            fclose($sock);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    expectPhaseA($ready, 'PHP test server không sẵn sàng.');

    $health = (new WebsiteHealthService())->probe($healthPort);
    expectPhaseA(($health['status'] ?? '') === 'healthy', 'HTTP 200 phải được đánh dấu healthy.');
    expectPhaseA((int)($health['http_status'] ?? 0) === 200, 'Health Check phải trả HTTP status 200.');
    expectPhaseA(!empty($health['reachable']), 'Health Check phải xác nhận endpoint reachable.');
    proc_terminate($process);
    proc_close($process);

    // 4. Bootstrap và UI phải expose hai service mới + Auto Port.
    $bootstrap = (string)file_get_contents($root . '/public/index.php');
    expectPhaseA(str_contains($bootstrap, "'WebsiteHealthService'"), 'Bootstrap phải load WebsiteHealthService.');
    expectPhaseA(str_contains($bootstrap, "'WebsiteMetadataService'"), 'Bootstrap phải load WebsiteMetadataService.');
    $view = (string)file_get_contents($root . '/app/Views/websites/index.php');
    expectPhaseA(str_contains($view, 'Để trống để TMS OS tự chọn'), 'UI tạo website phải hướng dẫn Auto Port.');
    expectPhaseA(str_contains($view, 'health_http_status'), 'Website card phải hiển thị kết quả health check.');

    echo "PASS: V17.1 Website Control Center Phase A\n";
} finally {
    if (isset($process) && is_resource($process)) {
        @proc_terminate($process);
        @proc_close($process);
    }
    if (is_dir($temp)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($temp);
    }
}
