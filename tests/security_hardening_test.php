<?php
declare(strict_types=1);

/**
 * Hồi quy V17.0.18 — Bảo mật:
 * 1. Router default-deny: route không khai báo public() phải chặn khi chưa đăng nhập
 *    (redirect /login?next= cho trang, JSON 401 cho /api/), cho qua khi đã đăng nhập.
 * 2. Route public() vẫn chạy khi chưa đăng nhập; /api/updates/run cũng phải public.
 * 3. AuthService rate limit: sai 5 lần liên tiếp bị khoá; đúng mật khẩu khi đang khoá vẫn bị từ chối.
 * 4. CommandRunner: exec/shell/proc trả đúng định dạng, timeout hoạt động.
 *
 * Test khai báo tms_redirect riêng (ném RedirectSignal) vì helpers.php gốc exit() —
 * không thể bắt trong cùng process.
 */

function failSec(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectSec(bool $condition, string $message): void { if (!$condition) { failSec($message); } }

final class RedirectSignal extends RuntimeException
{
    public function __construct(public readonly string $location)
    {
        parent::__construct($location);
    }
}

if (!function_exists('tms_redirect')) {
    function tms_redirect(string $path): never
    {
        throw new RedirectSignal($path);
    }
}

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-security-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
putenv('HOME=' . $home);
@mkdir($home . '/.tms-os/config', 0700, true);

require $root . '/app/Core/Router.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/AuthService.php';

file_put_contents($home . '/.tms-os/config/panel-secret.php', "<?php return ['username'=>'admin','password_hash'=>" . var_export(password_hash('correct-horse', PASSWORD_DEFAULT), true) . "];\n");

function tms_capture(callable $fn): string
{
    ob_start();
    try {
        $fn();
        return (string)ob_get_clean();
    } catch (RedirectSignal $signal) {
        ob_get_clean();
        return 'REDIRECT:' . $signal->location;
    }
}

try {
    // ===== 1+2. Router default-deny =====
    $router = new Router();
    $router->get('/dashboard', function () { echo 'DASHBOARD-OK'; });
    $router->get('/api/monitoring', function () { echo 'API-OK'; });
    $router->get('/login', function () { echo 'LOGIN-OK'; });
    $router->get('/api/updates/run', function () { echo 'RUN-OK'; });
    $router->post('/telegram/webhook', function () { echo 'HOOK-OK'; });
    $router->public('/login');
    $router->public('/api/updates/run');
    $router->public('/telegram/webhook');

    $out = tms_capture(function () use ($router) { $router->dispatch('GET', '/api/monitoring', fn(): bool => false); });
    expectSec(str_contains($out, 'AUTH_REQUIRED'), 'Route /api/ chưa đăng nhập phải trả JSON AUTH_REQUIRED, nhận: ' . $out);

    $out = tms_capture(function () use ($router) { $router->dispatch('GET', '/dashboard', fn(): bool => false); });
    expectSec($out === 'REDIRECT:/login?next=%2Fdashboard', 'Trang bảo vệ phải redirect /login?next=<path>, nhận: ' . $out);

    $out = tms_capture(function () use ($router) { $router->dispatch('GET', '/dashboard', fn(): bool => true); });
    expectSec($out === 'DASHBOARD-OK', 'Đã đăng nhập phải đi tới handler.');

    $out = tms_capture(function () use ($router) { $router->dispatch('GET', '/login', fn(): bool => false); });
    expectSec($out === 'LOGIN-OK', 'Route public() phải chạy không cần đăng nhập (login).');
    $out = tms_capture(function () use ($router) { $router->dispatch('POST', '/telegram/webhook', fn(): bool => false); });
    expectSec($out === 'HOOK-OK', 'Route public() phải chạy không cần đăng nhập (webhook).');
    $out = tms_capture(function () use ($router) { $router->dispatch('GET', '/api/updates/run', fn(): bool => false); });
    expectSec($out === 'RUN-OK', '/api/updates/run phải public (xác thực bằng token riêng).');

    // ===== web.php thật: hợp đồng 134 route + 6 path public (phân tích tĩnh) =====
    $web = (string)file_get_contents($root . '/routes/web.php');
    expectSec(preg_match_all('/\$router->(?:get|post)\(/', $web) === 134, 'web.php phải đăng ký đúng 134 route.');
    foreach (['/', '/login', '/telegram/webhook', '/status', '/api/public-status', '/api/updates/run'] as $p) {
        expectSec(str_contains($web, "\$router->public('" . $p . "');"), 'web.php phải khai báo public cho ' . $p);
    }

    // ===== 3. Rate limit =====
    $auth = new AuthService();
    expectSec($auth->lockedFor() === 0, 'Ban đầu không bị khoá.');
    for ($i = 0; $i < 5; $i++) {
        expectSec($auth->attempt('admin', 'wrong-' . $i) === false, 'Sai mật khẩu phải bị từ chối.');
    }
    expectSec($auth->lockedFor() > 0, 'Sau 5 lần sai phải bị khoá.');
    expectSec($auth->attempt('admin', 'correct-horse') === false, 'Đúng mật khẩu khi đang khoá vẫn phải bị từ chối.');
    expectSec($auth->attempt('admin-x', 'correct-horse') === false, 'Khoá áp dụng cho mọi username.');

    // ===== 4. CommandRunner =====
    $out = CommandRunner::exec('echo hello-command', $lines, $code);
    expectSec($code === 0 && $out === 'hello-command', 'CommandRunner::exec trả stdout+exit code.');
    expectSec(trim(CommandRunner::shell('echo shell-ok')) === 'shell-ok', 'CommandRunner::shell trả chuỗi.');
    $result = CommandRunner::proc([PHP_BINARY, '-r', 'echo fread(STDIN, 8);'], 5, 'proc_in');
    expectSec($result['code'] === 0 && str_contains($result['out'], 'proc_in'), 'CommandRunner::proc truyền stdin và trả output.');
    $result = CommandRunner::proc([PHP_BINARY, '-r', 'sleep(5);'], 1);
    if (PHP_OS_FAMILY === 'Windows') {
        // PHP trên Windows không hỗ trợ non-blocking pipe: đọc chờ đến EOF.
        echo "NOTE: bỏ qua assert timeout proc trên Windows (chạy thật trên Linux/CI).\n";
    } else {
        expectSec($result['code'] === -1, 'CommandRunner::proc phải cắt process vượt timeout.');
    }

    echo "OK: security hardening V17.0.18\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
}
