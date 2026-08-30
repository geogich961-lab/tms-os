<?php
declare(strict_types=1);

/**
 * AutoBackupService — backup tự động theo lịch + đẩy offsite qua rclone.
 *
 * Tái sử dụng BackupService::create() nên bản backup tự động xuất hiện luôn
 * trong Backup Center với khôi phục một chạm sẵn có. Cron job "tms-auto-backup"
 * được đăng ký qua CronJobService khi bật tính năng.
 *
 * $backups/$cron được truyền động (không typehint) để unit test có thể stub:
 * chỉ cần đối tượng có create/all/delete tương ứng save/delete của cron.
 */
final class AutoBackupService
{
    private string $home;
    private string $configFile;
    private string $stateFile;
    private const AUTO_NOTE = '[auto] TMS OS tự động sao lưu';
    private const CRON_JOB_ID = 'tms-auto-backup';
    private const MAX_RETENTION = 90;

    public function __construct(private $backups, private $cron, private string $targetRoot = '')
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->configFile = $this->home . '/.tms-os/config/auto-backup.json';
        $this->stateFile = $this->home . '/.tms-os/config/auto-backup-state.json';
        $this->targetRoot = $this->targetRoot !== '' ? $this->targetRoot : $this->home . '/tms-os';
    }

    public function config(): array
    {
        $data = @json_decode((string)@file_get_contents($this->configFile), true);
        $config = is_array($data) ? $data : [];
        return [
            'enabled' => (bool)($config['enabled'] ?? false),
            'time' => (string)($config['time'] ?? '03:30'),
            'scope' => in_array($config['scope'] ?? 'system', ['system', 'config'], true) ? (string)$config['scope'] : 'system',
            'retention' => max(1, min(self::MAX_RETENTION, (int)($config['retention'] ?? 7))),
            'offsite_enabled' => (bool)($config['offsite_enabled'] ?? false),
            'offsite_remote' => (string)($config['offsite_remote'] ?? ''),
            'offsite_path' => (string)($config['offsite_path'] ?? 'tms-os-backups'),
            'notify_telegram' => (bool)($config['notify_telegram'] ?? false),
        ];
    }

    public function saveConfig(array $input): array
    {
        $time = trim((string)($input['time'] ?? '03:30'));
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time)) {
            throw new RuntimeException('Giờ chạy không hợp lệ (dạng HH:MM).');
        }
        $retention = (int)($input['retention'] ?? 7);
        if ($retention < 1 || $retention > self::MAX_RETENTION) {
            throw new RuntimeException('Số bản giữ lại phải từ 1 đến ' . self::MAX_RETENTION . '.');
        }
        $remote = trim((string)($input['offsite_remote'] ?? ''));
        if ((bool)($input['offsite_enabled'] ?? false) && $remote === '') {
            throw new RuntimeException('Cần nhập tên rclone remote (ví dụ gdrive) khi bật offsite.');
        }
        if ((bool)($input['offsite_enabled'] ?? false) && !$this->rcloneAvailable()) {
            throw new RuntimeException('Chưa cài rclone. Chạy: pkg install rclone rồi cấu hình remote bằng lệnh rclone config.');
        }
        $config = [
            'enabled' => (bool)($input['enabled'] ?? false),
            'time' => $time,
            'scope' => in_array($input['scope'] ?? 'system', ['system', 'config'], true) ? (string)($input['scope'] ?? 'system') : 'system',
            'retention' => $retention,
            'offsite_enabled' => (bool)($input['offsite_enabled'] ?? false),
            'offsite_remote' => $remote,
            'offsite_path' => trim((string)($input['offsite_path'] ?? 'tms-os-backups'), "/ "),
            'notify_telegram' => (bool)($input['notify_telegram'] ?? false),
        ];
        $this->writeJson($this->configFile, $config);
        $this->syncCron();
        return $config;
    }

    /** Đăng ký/gỡ cron job theo cấu hình; gọi cả khi bấm chạy ngay để giữ khớp. */
    public function syncCron(): void
    {
        $config = $this->config();
        if (!$config['enabled']) {
            $this->cron->delete(self::CRON_JOB_ID);
            return;
        }
        [$h, $m] = array_map('intval', explode(':', $config['time']));
        $this->cron->save([
            'id' => self::CRON_JOB_ID,
            'name' => 'TMS Auto Backup',
            'command' => 'php ' . $this->targetRoot . '/scripts/tms-auto-backup.php',
            'schedule' => $m . ' ' . $h . ' * * *',
            'enabled' => true,
            'notify_telegram' => false,
        ]);
    }

    /** Chạy một vòng backup: tạo bản mới, cắt theo retention, đẩy offsite, thông báo. */
    public function runNow(): array
    {
        $config = $this->config();
        $result = ['ok' => false, 'at' => date('c')];
        try {
            $id = (string)$this->backups->create($config['scope'], '', self::AUTO_NOTE, false);
            $result['backup_id'] = $id;

            $pruned = $this->pruneRetention($config['retention']);
            $result['pruned'] = $pruned;

            if ($config['offsite_enabled']) {
                $push = $this->pushOffsite($id, $config);
                $result['offsite'] = $push;
                if (!$push['ok']) {
                    throw new RuntimeException('Backup local xong nhưng đẩy offsite thất bại: ' . $push['message']);
                }
            } else {
                $result['offsite'] = ['ok' => true, 'skipped' => true, 'message' => 'Offsite đang tắt.'];
            }
            $result['ok'] = true;
            $result['message'] = 'Đã tạo backup ' . $id . ($pruned > 0 ? ", đã dọn {$pruned} bản cũ." : '.');
        } catch (Throwable $e) {
            $result['ok'] = false;
            $result['message'] = mb_substr($e->getMessage(), 0, 300);
        }
        $this->writeJson($this->stateFile, $result);
        $this->notifyTelegram($config, $result);
        return $result;
    }

    public function status(): array
    {
        $state = @json_decode((string)@file_get_contents($this->stateFile), true);
        return [
            'config' => $this->config(),
            'last' => is_array($state) ? $state : null,
            'rclone_available' => $this->rcloneAvailable(),
            'cron_registered' => $this->cronJobRegistered(),
        ];
    }

    // ===== Nội bộ =====

    /** Xoá các bản backup tự động cũ nhất vượt retention; trả số bản đã xoá. */
    private function pruneRetention(int $retention): int
    {
        $auto = [];
        foreach ((array)$this->backups->all() as $meta) {
            if (str_contains((string)($meta['note'] ?? ''), '[auto]') && empty($meta['locked'])) {
                $auto[] = $meta;
            }
        }
        usort($auto, static fn(array $a, array $b): int => ((int)($b['created_ts'] ?? 0)) <=> ((int)($a['created_ts'] ?? 0)));
        $removed = 0;
        foreach (array_slice($auto, $retention) as $old) {
            try {
                $this->backups->delete((string)$old['id']);
                $removed++;
            } catch (Throwable) {
                // Bỏ qua bản lỗi riêng, không làm hỏng vòng backup hiện tại.
            }
        }
        return $removed;
    }

    private function pushOffsite(string $id, array $config): array
    {
        $archive = $this->home . '/.tms-os/backups/archives';
        $target = $config['offsite_remote'] . ':' . $config['offsite_path'];
        $meta = (array)@json_decode((string)@file_get_contents($this->home . '/.tms-os/backups/metadata/' . $id . '.json'), true);
        $file = $archive . '/' . basename((string)($meta['archive'] ?? ''));
        if (!is_file($file)) {
            return ['ok' => false, 'message' => 'Không tìm thấy tệp archive để đẩy.'];
        }
        $rclone = self::rcloneBinary();
        if ($rclone === '') {
            return ['ok' => false, 'message' => 'Chưa cài rclone (pkg install rclone).'];
        }
        $result = CommandRunner::proc([$rclone, 'copy', $file, $target, '--transfers', '2', '--checkers', '4'], 1800);
        return $result['code'] === 0
            ? ['ok' => true, 'message' => 'Đã đẩy lên ' . $target]
            : ['ok' => false, 'message' => 'rclone exit ' . $result['code'] . ': ' . mb_substr(trim($result['out']), 0, 200)];
    }

    private function notifyTelegram(array $config, array $result): void
    {
        if (!$config['notify_telegram'] || !method_exists($this->cron, 'sendTelegramNotification')) {
            return;
        }
        $icon = $result['ok'] ? '✅' : '❌';
        $text = $icon . " TMS Auto Backup\n" . (string)($result['message'] ?? '');
        if (!empty($result['offsite']['message']) && empty($result['offsite']['skipped'])) {
            $text .= "\nOffsite: " . $result['offsite']['message'];
        }
        try {
            $this->cron->sendTelegramNotification($text);
        } catch (Throwable) {
            // Không cho lỗi thông báo làm hỏng kết quả backup.
        }
    }

    private function cronJobRegistered(): bool
    {
        foreach ((array)$this->cron->all() as $job) {
            if ((string)($job['id'] ?? '') === self::CRON_JOB_ID) {
                return true;
            }
        }
        return false;
    }

    public static function rcloneBinary(): string
    {
        // 'rclone version' chạy được trên cả Termux (sh) và Windows (cmd) —
        // 'command -v' không tồn tại trong cmd.exe của PHP host Windows.
        $result = CommandRunner::exec('rclone version 2>&1', $lines, $code);
        return $code === 0 ? 'rclone' : '';
    }

    private function rcloneAvailable(): bool
    {
        return self::rcloneBinary() !== '';
    }

    private function writeJson(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX) !== false) {
            @chmod($tmp, 0600);
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
            throw new RuntimeException('Không thể lưu cấu hình backup tự động.');
        }
    }
}
