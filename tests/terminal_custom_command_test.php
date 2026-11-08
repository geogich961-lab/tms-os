<?php
declare(strict_types=1);

/**
 * Hồi quy V17.1.2 — Terminal lệnh thủ công:
 * 1. runCustom chạy lệnh bash thật trong thư mục HOME và trả output + mã thoát.
 * 2. Lệnh rỗng / quá dài bị từ chối rõ ràng, không chạy.
 * 3. Mọi lệnh đã chạy đều ghi nhật ký kiểm toán terminal-audit.log.
 * 4. Lệnh preset (run theo key) và chặn key lạ giữ nguyên như cũ.
 */

function failTC(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectTC(bool $condition, string $message): void { if (!$condition) { failTC($message); } }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-terminal-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
@mkdir($home, 0700, true);
putenv('HOME=' . $home);
$_SERVER['HOME'] = $home;

require $root . '/app/Core/helpers.php';
foreach (glob($root . '/app/Core/*.php') as $coreFile) { require_once $coreFile; }
require $root . '/app/Services/UnifiedSystemCoreService.php';
require $root . '/app/Services/TerminalService.php';

try {
    $service = new TerminalService();

    // 1. Lệnh thật: echo + pwd phải là HOME
    $r = $service->runCustom('echo TMS-TERMINAL-OK');
    expectTC($r['ok'] === true, 'Lệnh echo phải thành công.');
    expectTC(str_contains((string)$r['output'], 'TMS-TERMINAL-OK'), 'Output phải chứa kết quả echo.');
    expectTC(str_starts_with((string)$r['label'], '$ echo'), 'Nhãn kết quả phải hiển thị lệnh đã chạy.');

    $r = $service->runCustom('pwd');
    expectTC(str_contains((string)$r['output'], $home), 'Lệnh phải chạy trong thư mục HOME: ' . $r['output']);

    // Mã thoát khác 0 → ok=false nhưng vẫn có output
    $r = $service->runCustom('echo loi-mau; exit 3');
    expectTC($r['ok'] === false, 'Lệnh exit 3 phải báo ok=false.');
    expectTC(str_contains((string)$r['output'], 'loi-mau'), 'Output của lệnh lỗi vẫn phải được trả về.');

    // 2. Từ chối lệnh rỗng / quá dài
    $r = $service->runCustom('   ');
    expectTC($r['ok'] === false && str_contains((string)$r['output'], 'Chưa nhập lệnh'), 'Lệnh rỗng phải bị từ chối rõ ràng.');
    $r = $service->runCustom(str_repeat('a', 2001));
    expectTC($r['ok'] === false && str_contains((string)$r['output'], 'quá dài'), 'Lệnh quá 2000 ký tự phải bị từ chối.');

    // 3. Nhật ký kiểm toán
    $audit = $home . '/.tms-os/logs/terminal-audit.log';
    expectTC(is_file($audit), 'Phải ghi nhật ký kiểm toán terminal-audit.log.');
    $log = (string)file_get_contents($audit);
    expectTC(str_contains($log, 'echo TMS-TERMINAL-OK') && str_contains($log, 'exit=0'), 'Nhật ký phải chứa lệnh + mã thoát.');
    expectTC(str_contains($log, 'exit 3') || str_contains($log, 'exit=3'), 'Nhật ký phải ghi cả lệnh thất bại.');

    // 4. Preset giữ nguyên: key lạ bị chặn, key hợp lệ chạy được
    $r = $service->run('lệnh-lạ-không-có');
    expectTC($r['ok'] === false, 'Key preset lạ phải bị chặn như cũ.');
    $r = $service->run('php_version');
    expectTC($r['ok'] === true && str_contains((string)$r['output'], 'PHP'), 'Preset php_version vẫn phải chạy.');

    echo "OK: terminal custom command V17.1.2\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
}
