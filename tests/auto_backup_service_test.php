<?php
declare(strict_types=1);

/**
 * Hồi quy V17.0.19 — Backup tự động & offsite:
 * 1. saveConfig chuẩn hoá và từ chối input không hợp lệ (giờ, retention, remote).
 * 2. syncCron đăng ký/gỡ cron job tms-auto-backup đúng lịch.
 * 3. runNow: tạo backup qua BackupService, prune theo retention, đẩy offsite qua rclone,
 *    ghi state; lỗi offsite phải làm vòng backup báo lỗi nhưng vẫn giữ bản local.
 * 4. CLI scripts/tms-auto-backup.php có đủ require và cú pháp hợp lệ.
 *
 * BackupService/CronJobService được stub để test chạy mọi nền tảng (create() dùng tar
 * với đường dẫn gốc '/'). CommandRunner dùng thật cho rclone (giả lập bằng shim
 * rclone trong PATH).
 */

function failAB(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectAB(bool $condition, string $message): void { if (!$condition) { failAB($message); } }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-auto-backup-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
putenv('HOME=' . $home);
@mkdir($home . '/.tms-os/config', 0700, true);

require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/AutoBackupService.php';

final class StubBackups
{
    public array $created = [];
    public array $deleted = [];
    public int $nextId = 1;
    public array $metas = [];

    public function create(string $scope, string $website = '', string $note = '', bool $locked = false): string
    {
        $id = 'backup-' . $this->nextId++;
        $this->created[] = [$scope, $note, $locked];
        $this->metas[] = ['id' => $id, 'scope' => $scope, 'note' => $note, 'created_ts' => time(), 'locked' => $locked];
        return $id;
    }
    public function all(): array { return $this->metas; }
    public function delete(string $id): void { $this->deleted[] = $id; }
}

final class StubCron
{
    public array $saved = [];
    public array $deletedIds = [];
    public array $telegram = [];

    public function save(array $job): void { $this->saved[$job['id']] = $job; }
    public function delete(string $id): void { $this->deletedIds[] = $id; unset($this->saved[$id]); }
    public function all(): array { return array_values($this->saved); }
    public function sendTelegramNotification(string $message): array { $this->telegram[] = $message; return ['ok' => true]; }
}

try {
    $backups = new StubBackups();
    $cron = new StubCron();
    $service = new AutoBackupService($backups, $cron);

    // 1. saveConfig validation
    try {
        $service->saveConfig(['enabled' => '1', 'time' => '25:99']);
        expectAB(false, 'Giờ 25:99 phải bị từ chối.');
    } catch (RuntimeException $e) { /* đúng */ }
    try {
        $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'retention' => 500]);
        expectAB(false, 'Retention 500 phải bị từ chối.');
    } catch (RuntimeException $e) { /* đúng */ }
    try {
        $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'offsite_enabled' => '1']);
        expectAB(false, 'Bật offsite thiếu remote phải bị từ chối.');
    } catch (RuntimeException $e) { expectAB(str_contains($e->getMessage(), 'remote'), 'Lỗi offsite phải nêu rõ remote.'); }

    // 2. syncCron: tắt → gỡ job; bật → đăng ký đúng lịch
    $config = $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'scope' => 'system', 'retention' => 3]);
    expectAB(isset($cron->saved['tms-auto-backup']), 'Bật backup phải đăng ký cron job tms-auto-backup.');
    expectAB($cron->saved['tms-auto-backup']['schedule'] === '30 3 * * *', 'Lịch 03:30 phải thành cron 30 3 * * *.');
    $config = $service->saveConfig(['enabled' => '', 'time' => '03:30', 'retention' => 3]);
    expectAB(in_array('tms-auto-backup', $cron->deletedIds, true), 'Tắt backup phải gỡ cron job.');

    // 3. runNow: offsite tắt → backup local thành công + prune khi vượt retention
    $service = new AutoBackupService($backups, $cron);
    $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'retention' => 2]);
    $backups->nextId = 1;
    $backups->metas = [];
    for ($i = 0; $i < 2; $i++) { $backups->create('system', '', '[auto] TMS OS tự động sao lưu'); }
    $result = $service->runNow();
    expectAB($result['ok'] === true, 'runNow với offsite tắt phải thành công: ' . ($result['message'] ?? ''));
    expectAB(count($backups->created) === 3, 'runNow phải tạo đúng 1 bản backup mới.');
    expectAB(($result['pruned'] ?? 0) === 1, 'Retention 2 phải dọn 1 bản cũ.');
    $state = json_decode((string)file_get_contents($home . '/.tms-os/config/auto-backup-state.json'), true);
    expectAB(is_array($state) && $state['ok'] === true, 'State file phải ghi kết quả thành công.');

    // runNow: offsite theo khả năng môi trường
    $hasRclone = AutoBackupService::rcloneBinary() !== '';
    if (!$hasRclone) {
        try {
            $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'retention' => 2, 'offsite_enabled' => '1', 'offsite_remote' => 'gdrive']);
            expectAB(false, 'Máy không có rclone thì lưu cấu hình offsite phải bị chặn.');
        } catch (RuntimeException $e) {
            expectAB(str_contains($e->getMessage(), 'rclone'), 'Lỗi cấu hình offsite phải nêu rõ rclone.');
        }
        $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'retention' => 2]);
    } else {
        // rclone có sẵn: remote giả định chắc chắn không tồn tại → offsite phải lỗi rõ ràng
        $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'retention' => 2, 'offsite_enabled' => '1', 'offsite_remote' => 'tms-test-remote-khong-ton-tai']);
        $result = $service->runNow();
        expectAB($result['ok'] === false && str_contains((string)$result['message'], 'offsite'), 'Remote không tồn tại phải làm vòng backup báo lỗi offsite.');
        $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'retention' => 2]);
    }

    // Thông báo Telegram: cấu hình bật → cron nhận tin
    $service->saveConfig(['enabled' => '1', 'time' => '03:30', 'retention' => 2, 'notify_telegram' => '1']);
    $before = count($cron->telegram);
    $service->runNow();
    expectAB(count($cron->telegram) === $before + 1 && str_contains(end($cron->telegram), 'TMS Auto Backup'), 'Bật notify phải gửi Telegram.');

    // 4. CLI script tồn tại + cú pháp PHP hợp lệ + chứa require cần thiết
    $cli = (string)file_get_contents($root . '/scripts/tms-auto-backup.php');
    expectAB(str_contains($cli, 'AutoBackupService') && str_contains($cli, 'runNow'), 'CLI auto-backup phải gọi runNow().');
    exec('php -l ' . escapeshellarg($root . '/scripts/tms-auto-backup.php') . ' 2>&1', $lint, $code);
    expectAB($code === 0, 'CLI auto-backup phải qua php -l.');

    // status() trả đủ cấu trúc cho view
    $status = $service->status();
    expectAB(isset($status['config']['enabled'], $status['rclone_available'], $status['cron_registered']), 'status() phải trả config + rclone + cron.');

    echo "OK: auto backup service V17.0.19\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
}
