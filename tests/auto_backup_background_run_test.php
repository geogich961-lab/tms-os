<?php
declare(strict_types=1);

/**
 * Hồi quy V17.1.4 — Nút "Chạy backup tự động ngay" chạy ở chế độ nền:
 * 1. requestRun() khởi chạy worker CLI tách rời (nohup + scripts/tms-auto-backup.php)
 *    qua launcher và trả về ngay với started=true.
 * 2. Đang có vòng chạy (khóa flock bị giữ) → requestRun() báo đang chạy,
 *    KHÔNG khởi chạy thêm; runNow() trả skipped=true, không tạo backup,
 *    không ghi state.
 * 3. Trong lúc runNow() chạy thật: status()['running'] = true + có file dấu
 *    vết; chạy xong: running = false, dấu vết bị xoá, state ghi như cũ.
 * 4. CLI scripts/tms-auto-backup.php khi bị trùng vòng: in SKIP và thoát 0.
 * 5. Thiếu file worker → requestRun() báo lỗi rõ ràng (ok=false).
 */

function failBG(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectBG(bool $condition, string $message): void { if (!$condition) { failBG($message); } }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-auto-backup-bg-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
putenv('HOME=' . $home);
@mkdir($home . '/.tms-os/config', 0700, true);

require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/AutoBackupService.php';

final class BgStubBackups
{
    public array $created = [];
    /** @var null|callable */
    public $onCreate = null;

    public function create(string $scope, string $website = '', string $note = '', bool $locked = false): string
    {
        $this->created[] = [$scope, $note];
        if ($this->onCreate !== null) {
            call_user_func($this->onCreate);
        }
        return 'backup-bg-1';
    }
    public function all(): array { return []; }
    public function delete(string $id): void {}
}

final class BgStubCron
{
    public array $saved = [];
    public array $telegram = [];
    public function save(array $job): void { $this->saved[$job['id']] = $job; }
    public function delete(string $id): void { unset($this->saved[$id]); }
    public function all(): array { return array_values($this->saved); }
    public function sendTelegramNotification(string $message): array { $this->telegram[] = $message; return ['ok' => true]; }
}

$lockFile = $home . '/.tms-os/config/auto-backup-run.lock';
$markFile = $home . '/.tms-os/config/auto-backup-running.json';
$stateFile = $home . '/.tms-os/config/auto-backup-state.json';

try {
    $backups = new BgStubBackups();
    $cron = new BgStubCron();

    // Launcher ghi lại lệnh được khởi chạy, không chạy thật.
    $launched = [];
    $launcher = static function (string $cmd) use (&$launched): bool {
        $launched[] = $cmd;
        return true;
    };
    $service = new AutoBackupService($backups, $cron, $root, $launcher);
    $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'retention' => 2]);

    // 1. requestRun happy path: khởi chạy nền + trả về ngay
    $r = $service->requestRun();
    expectBG($r['ok'] === true && ($r['started'] ?? false) === true, 'requestRun phải báo started=true: ' . json_encode($r));
    expectBG(count($launched) === 1, 'Launcher phải được gọi đúng 1 lần.');
    expectBG(str_contains($launched[0], 'nohup') && str_contains($launched[0], 'tms-auto-backup.php'), 'Lệnh nền phải là nohup + worker CLI: ' . $launched[0]);
    expectBG(str_contains((string)$r['message'], 'chế độ nền'), 'Thông báo requestRun phải nói rõ chạy chế độ nền.');

    // 2. Đang có vòng chạy (test giữ khóa) → requestRun không chạy thêm, runNow skip sạch
    $fh = fopen($lockFile, 'c');
    expectBG($fh !== false && flock($fh, LOCK_EX), 'Test phải giữ được khóa chạy.');
    // Ghi dấu vết như runNow() thật để kiểm tra thông tin "bắt đầu lúc"
    file_put_contents($markFile, json_encode(['started_at' => date('c'), 'pid' => 123]));

    $r2 = $service->requestRun();
    expectBG($r2['ok'] === true && ($r2['started'] ?? true) === false, 'Đang chạy thì requestRun không được started: ' . json_encode($r2));
    expectBG(count($launched) === 1, 'Đang chạy thì không được khởi chạy thêm tiến trình nền.');
    expectBG(str_contains(mb_strtolower((string)$r2['message']), 'đang chạy'), 'Thông báo phải nói rõ đang có vòng chạy.');

    $createdBefore = count($backups->created);
    $rSkip = $service->runNow();
    expectBG(($rSkip['skipped'] ?? false) === true && $rSkip['ok'] === false, 'runNow khi bị giữ khóa phải trả skipped=true.');
    expectBG(count($backups->created) === $createdBefore, 'Vòng skip không được tạo backup.');
    expectBG(!is_file($stateFile), 'Vòng skip không được ghi state (tránh đè kết quả vòng thật).');
    expectBG(count($cron->telegram) === 0, 'Vòng skip không được gửi thông báo Telegram.');

    // status() phải thấy đang chạy + giờ bắt đầu
    $st = $service->status();
    expectBG(($st['running'] ?? false) === true, 'status() phải báo running=true khi khóa bị giữ.');
    expectBG(($st['running_since'] ?? '') !== '', 'status() phải có running_since khi đang chạy.');

    // 4. CLI khi bị trùng vòng: SKIP + exit 0 (chạy tiến trình con trong lúc khóa còn giữ)
    $cmd = 'HOME=' . escapeshellarg($home) . ' php ' . escapeshellarg($root . '/scripts/tms-auto-backup.php') . ' 2>&1';
    exec($cmd, $cliOut, $cliCode);
    expectBG($cliCode === 0, 'CLI khi trùng vòng phải thoát 0, nhận: ' . $cliCode . ' / ' . implode("\n", $cliOut));
    expectBG(str_contains(implode("\n", $cliOut), 'SKIP:'), 'CLI khi trùng vòng phải in SKIP: ' . implode("\n", $cliOut));

    flock($fh, LOCK_UN);
    fclose($fh);
    @unlink($markFile);

    // 3. runNow thật: trong lúc chạy thì running=true + có dấu vết; xong thì sạch
    $during = [];
    $backups->onCreate = static function () use ($service, $markFile, &$during): void {
        $during['running'] = $service->status()['running'] ?? null;
        $during['markExists'] = is_file($markFile);
    };
    $r3 = $service->runNow();
    expectBG($r3['ok'] === true, 'runNow bình thường phải thành công: ' . ($r3['message'] ?? ''));
    expectBG(($during['running'] ?? null) === true, 'Trong lúc runNow chạy, status() phải báo running=true.');
    expectBG(($during['markExists'] ?? false) === true, 'Trong lúc runNow chạy phải có file dấu vết.');
    $stAfter = $service->status();
    expectBG(($stAfter['running'] ?? true) === false, 'Chạy xong status() phải báo running=false.');
    expectBG(!is_file($markFile), 'Chạy xong phải xoá file dấu vết.');
    expectBG(is_file($stateFile), 'Chạy xong phải ghi state như trước đây.');

    // 5. Thiếu file worker → requestRun báo lỗi rõ
    $svcNoWorker = new AutoBackupService($backups, $cron, $temp . '/khong-co-app', $launcher);
    $r5 = $svcNoWorker->requestRun();
    expectBG($r5['ok'] === false && ($r5['started'] ?? true) === false, 'Thiếu worker thì requestRun phải ok=false: ' . json_encode($r5));

    echo "OK: auto backup background run V17.1.4\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
}
