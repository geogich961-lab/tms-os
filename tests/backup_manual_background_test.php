<?php
declare(strict_types=1);

/**
 * Hồi quy V17.1.12 — Tạo backup THỦ CÔNG ở chế độ nền + báo cáo Telegram:
 * 1. requestManualCreate() ghi file job đúng nội dung, khởi chạy worker CLI
 *    tách rời (nohup + scripts/tms-manual-backup.php) qua launcher, trả ngay.
 * 2. Đang có vòng thủ công chạy (khóa flock bị giữ) → requestManualCreate()
 *    báo đang chạy, KHÔNG khởi chạy thêm; runManualJob() trả skipped=true,
 *    không tạo backup, không ghi state, không gửi Telegram.
 * 3. runManualJob() thành công: tạo backup qua BackupService, ghi state có
 *    dung lượng, xoá job + dấu vết, gửi đúng 1 báo cáo Telegram ✅ chứa tên
 *    website; trong lúc chạy manualStatus()['running'] = true.
 * 4. runManualJob() thất bại: vẫn ghi state ok=false và gửi Telegram ❌ kèm lý do.
 * 5. Thiếu file worker → requestManualCreate() báo lỗi rõ ràng (ok=false).
 * 6. CLI scripts/tms-manual-backup.php khi bị trùng vòng: in SKIP, thoát 0.
 */

function failMB(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectMB(bool $condition, string $message): void { if (!$condition) { failMB($message); } }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-manual-backup-bg-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
putenv('HOME=' . $home);
@mkdir($home . '/.tms-os/config', 0700, true);

require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/AutoBackupService.php';

final class MbStubBackups
{
    public array $created = [];
    /** @var null|callable */
    public $onCreate = null;
    public ?Throwable $failWith = null;

    public function create(string $scope, string $website = '', string $note = '', bool $locked = false): string
    {
        $this->created[] = [$scope, $website, $note, $locked];
        if ($this->onCreate !== null) {
            call_user_func($this->onCreate);
        }
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
        return 'manual-bg-1';
    }
    public function all(): array { return [['id' => 'manual-bg-1', 'size' => 157286400]]; }
    public function delete(string $id): void {}
}

final class MbStubCron
{
    public array $saved = [];
    public array $telegram = [];
    public function save(array $job): void { $this->saved[$job['id']] = $job; }
    public function delete(string $id): void { unset($this->saved[$id]); }
    public function all(): array { return array_values($this->saved); }
    public function sendTelegramNotification(string $message): array { $this->telegram[] = $message; return ['ok' => true]; }
}

$jobFile = $home . '/.tms-os/config/manual-backup-job.json';
$lockFile = $home . '/.tms-os/config/manual-backup-run.lock';
$markFile = $home . '/.tms-os/config/manual-backup-running.json';
$stateFile = $home . '/.tms-os/config/manual-backup-state.json';

try {
    $backups = new MbStubBackups();
    $cron = new MbStubCron();
    $launched = [];
    $launcher = static function (string $cmd) use (&$launched): bool {
        $launched[] = $cmd;
        return true;
    };
    $service = new AutoBackupService($backups, $cron, $root, $launcher);

    // 1. requestManualCreate happy path
    $r = $service->requestManualCreate('website', 'volam', 'Trước khi thử nghiệm', true);
    expectMB($r['ok'] === true && ($r['started'] ?? false) === true, 'requestManualCreate phải báo started=true: ' . json_encode($r));
    expectMB(count($launched) === 1, 'Launcher phải được gọi đúng 1 lần.');
    expectMB(str_contains($launched[0], 'nohup') && str_contains($launched[0], 'tms-manual-backup.php'), 'Lệnh nền phải là nohup + worker CLI thủ công: ' . $launched[0]);
    expectMB(is_file($jobFile), 'Phải ghi file job cho worker.');
    $job = json_decode((string)file_get_contents($jobFile), true);
    expectMB(($job['scope'] ?? '') === 'website' && ($job['website'] ?? '') === 'volam' && ($job['note'] ?? '') === 'Trước khi thử nghiệm' && !empty($job['locked']), 'File job phải chứa đúng scope/website/note/locked: ' . json_encode($job));

    // 2. Đang chạy → không chạy thêm; runManualJob skip sạch
    $fh = fopen($lockFile, 'c');
    expectMB($fh !== false && flock($fh, LOCK_EX), 'Test phải giữ được khóa chạy thủ công.');
    file_put_contents($markFile, json_encode(['started_at' => date('c'), 'pid' => 456]));

    $r2 = $service->requestManualCreate('system', '', '', false);
    expectMB($r2['ok'] === true && ($r2['started'] ?? true) === false, 'Đang chạy thì requestManualCreate không được started: ' . json_encode($r2));
    expectMB(count($launched) === 1, 'Đang chạy thì không được khởi chạy thêm worker.');
    $jobAfter = json_decode((string)file_get_contents($jobFile), true);
    expectMB(($jobAfter['website'] ?? '') === 'volam', 'Đang chạy thì không được ghi đè file job của vòng thật.');

    $rSkip = $service->runManualJob();
    expectMB(($rSkip['skipped'] ?? false) === true && $rSkip['ok'] === false, 'runManualJob khi bị giữ khóa phải trả skipped=true.');
    expectMB(count($backups->created) === 0, 'Vòng skip không được tạo backup.');
    expectMB(!is_file($stateFile), 'Vòng skip không được ghi state.');
    expectMB(count($cron->telegram) === 0, 'Vòng skip không được gửi Telegram.');

    $stRun = $service->manualStatus();
    expectMB(($stRun['running'] ?? false) === true && ($stRun['running_since'] ?? '') !== '', 'manualStatus() phải báo running=true + running_since khi đang chạy.');

    // 6. CLI khi trùng vòng: SKIP + exit 0
    $cmd = 'HOME=' . escapeshellarg($home) . ' php ' . escapeshellarg($root . '/scripts/tms-manual-backup.php') . ' 2>&1';
    exec($cmd, $cliOut, $cliCode);
    expectMB($cliCode === 0, 'CLI khi trùng vòng phải thoát 0, nhận: ' . $cliCode . ' / ' . implode("\n", $cliOut));
    expectMB(str_contains(implode("\n", $cliOut), 'SKIP:'), 'CLI khi trùng vòng phải in SKIP.');

    flock($fh, LOCK_UN);
    fclose($fh);
    @unlink($markFile);

    // 3. runManualJob thành công (job website volam còn nguyên từ mục 1)
    $during = [];
    $backups->onCreate = static function () use ($service, $markFile, &$during): void {
        $during['running'] = $service->manualStatus()['running'] ?? null;
        $during['markExists'] = is_file($markFile);
    };
    $r3 = $service->runManualJob();
    expectMB($r3['ok'] === true, 'runManualJob bình thường phải thành công: ' . ($r3['message'] ?? ''));
    expectMB(($r3['backup_id'] ?? '') === 'manual-bg-1', 'Kết quả phải có backup_id.');
    expectMB(count($backups->created) === 1 && $backups->created[0][0] === 'website' && $backups->created[0][1] === 'volam', 'create() phải nhận đúng scope/website từ job.');
    expectMB(str_contains((string)$r3['message'], 'snapshot website volam'), 'Thông báo phải nêu đúng website: ' . ($r3['message'] ?? ''));
    expectMB(str_contains((string)$r3['message'], '150'), 'Thông báo phải kèm dung lượng đã định dạng: ' . ($r3['message'] ?? ''));
    expectMB(($during['running'] ?? null) === true && ($during['markExists'] ?? false) === true, 'Trong lúc chạy, manualStatus phải báo running + có dấu vết.');
    expectMB(!is_file($jobFile), 'Chạy xong phải xoá file job.');
    expectMB(!is_file($markFile), 'Chạy xong phải xoá dấu vết.');
    $state = json_decode((string)file_get_contents($stateFile), true);
    expectMB(($state['ok'] ?? false) === true && ($state['backup_id'] ?? '') === 'manual-bg-1', 'State phải ghi kết quả thành công.');
    expectMB(count($cron->telegram) === 1, 'Hoàn tất phải gửi đúng 1 báo cáo Telegram.');
    expectMB(str_contains($cron->telegram[0], '✅') && str_contains($cron->telegram[0], 'TMS Backup thủ công') && str_contains($cron->telegram[0], 'volam'), 'Telegram thành công phải có ✅ + tiêu đề + website: ' . $cron->telegram[0]);
    $stAfter = $service->manualStatus();
    expectMB(($stAfter['running'] ?? true) === false && (($stAfter['last']['ok'] ?? false) === true), 'manualStatus sau khi xong: running=false, last.ok=true.');

    // 4. runManualJob thất bại: state + Telegram ❌
    file_put_contents($jobFile, json_encode(['scope' => 'website', 'website' => 'khongco', 'note' => '', 'locked' => false, 'requested_at' => date('c')]));
    $backups->failWith = new RuntimeException('Không tìm thấy dữ liệu để sao lưu.');
    $telegramBefore = count($cron->telegram);
    $r4 = $service->runManualJob();
    expectMB($r4['ok'] === false && str_contains((string)$r4['message'], 'Không tìm thấy dữ liệu'), 'runManualJob thất bại phải trả ok=false kèm lý do.');
    $state4 = json_decode((string)file_get_contents($stateFile), true);
    expectMB(($state4['ok'] ?? true) === false, 'State phải ghi kết quả thất bại.');
    expectMB(count($cron->telegram) === $telegramBefore + 1 && str_contains($cron->telegram[$telegramBefore], '❌'), 'Thất bại vẫn phải gửi Telegram ❌.');
    expectMB(!is_file($jobFile), 'Job thất bại cũng phải được xoá để không kẹt yêu cầu hỏng.');

    // 5. Thiếu file worker → requestManualCreate báo lỗi rõ
    $svcNoWorker = new AutoBackupService($backups, $cron, $temp . '/khong-co-app', $launcher);
    $r5 = $svcNoWorker->requestManualCreate('system', '', '', false);
    expectMB($r5['ok'] === false && ($r5['started'] ?? true) === false, 'Thiếu worker thì requestManualCreate phải ok=false: ' . json_encode($r5));

    echo "OK: manual backup background run + Telegram report V17.1.12\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
}
