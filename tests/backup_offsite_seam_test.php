<?php
declare(strict_types=1);

/**
 * Hồi quy V17.1.3 — seam BackupService::create() ↔ AutoBackupService::runNow()/pushOffsite().
 *
 * Lỗi gốc trên máy thật (08/11/2026): BackupService::create() trả về CÂU THÔNG BÁO
 * ("Đã tạo bản sao lưu system.") thay vì ID bản backup, trong khi runNow() dùng
 * giá trị trả về đó làm ID để pushOffsite() đọc metadata/<id>.json → metadata
 * không tồn tại → luôn báo "Không tìm thấy tệp archive để đẩy." dù archive có thật.
 * Test cũ dùng stub create() trả ID đúng nên không bắt được.
 *
 * Test này dùng BackupService THẬT (HOME tạm) để khóa hợp đồng:
 * 1. create() trả ID đúng định dạng, metadata + archive tồn tại khớp nhau.
 * 2. runNow() (offsite bật) dùng đúng ID đó: backup_id trong kết quả là ID thật
 *    và lỗi offsite — nếu có — KHÔNG BAO GIỜ là "Không tìm thấy tệp archive để đẩy."
 */

function failSeam(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectSeam(bool $condition, string $message): void { if (!$condition) { failSeam($message); } }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-backup-seam-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
putenv('HOME=' . $home);
@mkdir($home . '/.tms-os/config', 0700, true);
file_put_contents($home . '/.tms-os/config/seed.txt', 'seed');

require $root . '/app/Core/helpers.php';
require $root . '/app/Core/CommandRunner.php';
require $root . '/app/Services/BackupService.php';
require $root . '/app/Services/AutoBackupService.php';

final class SeamStubCron
{
    public array $saved = [];
    public array $telegram = [];
    public function save(array $job): void { $this->saved[$job['id']] = $job; }
    public function delete(string $id): void { unset($this->saved[$id]); }
    public function all(): array { return array_values($this->saved); }
    public function sendTelegramNotification(string $message): array { $this->telegram[] = $message; return ['ok' => true]; }
}

try {
    // 1. Hợp đồng create(): trả ID, metadata + archive khớp ID đó
    $backups = new BackupService();
    $id = $backups->create('config', '', '[auto] TMS OS tự động sao lưu', false);
    expectSeam(preg_match('/^\d{8}_\d{6}_[0-9a-f]{6}$/', $id) === 1, 'create() phải trả ID định dạng Ymd_His_xxxxxx, nhận được: ' . $id);
    $metaFile = $home . '/.tms-os/backups/metadata/' . $id . '.json';
    expectSeam(is_file($metaFile), 'Metadata của ID vừa tạo phải tồn tại: ' . $metaFile);
    $meta = json_decode((string)file_get_contents($metaFile), true);
    expectSeam(is_array($meta) && ($meta['id'] ?? '') === $id, 'Metadata phải ghi đúng ID vừa tạo.');
    $archive = $home . '/.tms-os/backups/archives/' . basename((string)($meta['archive'] ?? ''));
    expectSeam(($meta['archive'] ?? '') !== '' && is_file($archive), 'Archive trong metadata phải tồn tại trên đĩa: ' . $archive);

    // 2. runNow() với BackupService thật + offsite bật: không được vấp lỗi tìm archive
    $auto = new AutoBackupService($backups, new SeamStubCron());
    $auto->saveConfig(['enabled' => '1', 'time' => '03:30', 'scope' => 'config', 'retention' => 7, 'offsite_enabled' => '1', 'offsite_remote' => 'gdrive', 'offsite_path' => 'tms-os-backups']);
    $result = $auto->runNow();
    expectSeam(isset($result['backup_id']) && preg_match('/^\d{8}_\d{6}_[0-9a-f]{6}$/', (string)$result['backup_id']) === 1, 'runNow phải ghi backup_id là ID thật: ' . json_encode($result['backup_id'] ?? null));
    expectSeam(is_file($home . '/.tms-os/backups/metadata/' . $result['backup_id'] . '.json'), 'backup_id của runNow phải trỏ tới metadata có thật.');
    $offsiteMessage = (string)($result['offsite']['message'] ?? '');
    expectSeam(!str_contains($offsiteMessage, 'Không tìm thấy tệp archive'), 'pushOffsite không được báo mất archive khi archive có thật. Nhận được: ' . $offsiteMessage . ' | message vòng: ' . ($result['message'] ?? ''));
    expectSeam(!str_contains((string)($result['message'] ?? ''), 'Không tìm thấy tệp archive'), 'Thông điệp vòng backup không được chứa lỗi mất archive.');

    echo "OK: backup offsite seam V17.1.3\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($temp);
}
