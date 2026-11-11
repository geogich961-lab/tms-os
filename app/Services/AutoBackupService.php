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
    private string $runLockFile;
    private string $runMarkFile;
    private string $manualJobFile;
    private string $manualStateFile;
    private string $manualLockFile;
    private string $manualMarkFile;
    private const AUTO_NOTE = '[auto] TMS OS tự động sao lưu';
    private const CRON_JOB_ID = 'tms-auto-backup';
    private const MAX_RETENTION = 90;

    // $launcher (tuỳ chọn, cho unit test): callable(string $cmd): bool thay cho
    // lần exec() khởi chạy tiến trình nền thật.
    public function __construct(private $backups, private $cron, private string $targetRoot = '', private $launcher = null)
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->configFile = $this->home . '/.tms-os/config/auto-backup.json';
        $this->stateFile = $this->home . '/.tms-os/config/auto-backup-state.json';
        $this->runLockFile = $this->home . '/.tms-os/config/auto-backup-run.lock';
        $this->runMarkFile = $this->home . '/.tms-os/config/auto-backup-running.json';
        $this->manualJobFile = $this->home . '/.tms-os/config/manual-backup-job.json';
        $this->manualStateFile = $this->home . '/.tms-os/config/manual-backup-state.json';
        $this->manualLockFile = $this->home . '/.tms-os/config/manual-backup-run.lock';
        $this->manualMarkFile = $this->home . '/.tms-os/config/manual-backup-running.json';
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
        // V17.1.2: offsite chưa sẵn sàng (chưa cài rclone / chưa nhập remote)
        // KHÔNG còn chặn lưu cấu hình — backup nội bộ vẫn bật và chạy bình
        // thường; trạng thái chưa sẵn sàng được báo qua status()['offsite_issue']
        // và vòng chạy sẽ ghi rõ lý do offsite thất bại.
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

    /**
     * Chạy một vòng backup: tạo bản mới, cắt theo retention, đẩy offsite, thông báo.
     *
     * V17.1.4: toàn bộ vòng chạy được giữ bằng khóa flock (auto-backup-run.lock)
     * nên không bao giờ có 2 vòng chạy chồng nhau (cron theo lịch + nút bấm tay
     * + tiến trình nền). Vòng bị trùng sẽ trả về ['skipped' => true] ngay lập
     * tức mà KHÔNG ghi state, KHÔNG gửi thông báo — kết quả của vòng đang chạy
     * thật không bị ghi đè. Khóa tự nhả khi tiến trình kết thúc, kể cả bị kill,
     * nên không bao giờ kẹt khóa chết.
     */
    public function runNow(): array
    {
        $lock = $this->tryAcquireRunLock();
        if ($lock === null) {
            return ['ok' => false, 'skipped' => true, 'at' => date('c'), 'message' => 'Đã có một vòng backup đang chạy, bỏ qua lần này.'];
        }
        try {
            $this->writeJson($this->runMarkFile, ['started_at' => date('c'), 'pid' => (int)(getmypid() ?: 0)]);
            return $this->runNowLocked();
        } finally {
            @unlink($this->runMarkFile);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Thân vòng backup — chỉ chạy khi đã giữ khóa chạy (xem runNow()). */
    private function runNowLocked(): array
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

    /**
     * Yêu cầu chạy backup NGAY ở chế độ nền — đường đi của nút bấm trên web
     * (V17.1.4). Trước đây nút bấm gọi thẳng runNow() trong request web: vòng
     * nén + đẩy offsite dài vài phút bị Cloudflare cắt ở ~100 giây (lỗi 524)
     * và PHP-FPM giết tiến trình con rclone giữa chừng (exit -1). Nay nút bấm
     * chỉ khởi chạy worker CLI scripts/tms-auto-backup.php tách rời hoàn toàn
     * (nohup + setsid, cùng mẫu PluginService/UpdateService) rồi trả về ngay;
     * worker chạy cùng runNow() như vòng cron theo lịch nên không còn giới
     * hạn thời gian của request web. Kết quả xem ở status()['last'] và qua
     * Telegram như bình thường.
     */
    public function requestRun(): array
    {
        if ($this->probeRunning()) {
            $since = $this->formatStartedAt((string)($this->readRunMark()['started_at'] ?? ''));
            return [
                'ok' => true,
                'started' => false,
                'message' => 'Đang có một vòng backup đang chạy ở chế độ nền' . ($since !== '' ? ' (bắt đầu lúc ' . $since . ')' : '')
                    . '. Kết quả sẽ hiện tại đây và qua Telegram khi chạy xong.',
            ];
        }
        if (!$this->launchBackgroundWorker()) {
            return [
                'ok' => false,
                'started' => false,
                'message' => 'Không khởi chạy được tiến trình backup nền (thiếu worker scripts/tms-auto-backup.php). Hãy thử lại sau hoặc chờ vòng backup tự động theo lịch.',
            ];
        }
        return [
            'ok' => true,
            'started' => true,
            'message' => 'Đã bắt đầu chạy backup ở chế độ nền. Bạn không cần chờ ở trang này — kết quả sẽ hiện tại đây và được báo qua Telegram (nếu đang bật) khi chạy xong.',
        ];
    }

    /**
     * Yêu cầu tạo backup THỦ CÔNG ở chế độ nền (V17.1.12) — đường đi của nút
     * "Tạo backup ngay". Trước đây controller gọi thẳng BackupService::create()
     * trong request web: nén website nặng (volam: hàng nghìn file ảnh + DB)
     * quá ~100 giây bị Cloudflare cắt (lỗi 524). Nay request web chỉ ghi file
     * yêu cầu (job) rồi khởi chạy worker CLI scripts/tms-manual-backup.php
     * tách rời (cùng mẫu requestRun() của V17.1.4) và trả về ngay; worker gọi
     * runManualJob() nên không còn giới hạn thời gian của request web.
     * Theo yêu cầu của user, khi tạo xong hệ thống báo cáo qua Telegram.
     */
    public function requestManualCreate(string $scope, string $website, string $note, bool $locked): array
    {
        if ($this->probeManualRunning()) {
            $since = $this->formatStartedAt((string)($this->readManualMark()['started_at'] ?? ''));
            return [
                'ok' => true,
                'started' => false,
                'message' => 'Đang có một lần tạo backup thủ công đang chạy ở chế độ nền' . ($since !== '' ? ' (bắt đầu lúc ' . $since . ')' : '')
                    . '. Kết quả sẽ hiện tại đây và được báo qua Telegram khi chạy xong.',
            ];
        }
        $this->writeJson($this->manualJobFile, [
            'scope' => $scope,
            'website' => $website,
            'note' => $note,
            'locked' => $locked,
            'requested_at' => date('c'),
        ]);
        if (!$this->launchManualWorker()) {
            return [
                'ok' => false,
                'started' => false,
                'message' => 'Không khởi chạy được tiến trình tạo backup nền (thiếu worker scripts/tms-manual-backup.php). Hãy thử lại sau.',
            ];
        }
        return [
            'ok' => true,
            'started' => true,
            'message' => 'Đã bắt đầu tạo backup ở chế độ nền. Bạn không cần chờ ở trang này — khi xong, bản sao lưu sẽ xuất hiện trong danh sách bên dưới và được báo qua Telegram (nếu đã cấu hình).',
        ];
    }

    /**
     * Thân vòng tạo backup thủ công — chỉ worker CLI gọi. Giữ khóa flock
     * riêng (manual-backup-run.lock) nên không bao giờ có 2 vòng thủ công
     * chạy chồng nhau; vòng trùng trả skipped=true ngay, KHÔNG ghi state,
     * KHÔNG gửi Telegram (giữ đúng kỷ luật của runNow()). Hoàn tất — thành
     * công hay thất bại — đều ghi state để trang Sao lưu hiển thị và gửi
     * báo cáo Telegram.
     */
    public function runManualJob(): array
    {
        $lock = $this->tryAcquireManualLock();
        if ($lock === null) {
            return ['ok' => false, 'skipped' => true, 'at' => date('c'), 'message' => 'Đã có một lần tạo backup thủ công đang chạy, bỏ qua lần này.'];
        }
        try {
            $this->writeJson($this->manualMarkFile, ['started_at' => date('c'), 'pid' => (int)(getmypid() ?: 0)]);
            return $this->runManualJobLocked();
        } finally {
            @unlink($this->manualMarkFile);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Chỉ chạy khi đã giữ khóa chạy thủ công (xem runManualJob()). */
    private function runManualJobLocked(): array
    {
        $job = @json_decode((string)@file_get_contents($this->manualJobFile), true);
        $result = ['ok' => false, 'at' => date('c')];
        try {
            if (!is_array($job)) {
                throw new RuntimeException('Không tìm thấy yêu cầu tạo backup (file job bị thiếu hoặc hỏng).');
            }
            $scope = in_array($job['scope'] ?? 'system', ['system', 'website', 'config'], true) ? (string)$job['scope'] : 'system';
            $website = (string)($job['website'] ?? '');
            $id = (string)$this->backups->create($scope, $website, (string)($job['note'] ?? ''), !empty($job['locked']));
            $result['backup_id'] = $id;
            $result['scope'] = $scope;
            $result['website'] = $website;
            $size = 0;
            foreach ((array)$this->backups->all() as $meta) {
                if ((string)($meta['id'] ?? '') === $id) {
                    $size = (int)($meta['size'] ?? 0);
                    break;
                }
            }
            $result['size'] = $size;
            $label = $scope === 'website' ? 'snapshot website ' . $website : ($scope === 'config' ? 'bản sao lưu cấu hình' : 'bản sao lưu toàn hệ thống');
            $result['message'] = 'Đã tạo ' . $label . ($size > 0 ? ' (' . $this->formatBytesVi($size) . ')' : '') . '.';
            $result['ok'] = true;
        } catch (Throwable $e) {
            $result['ok'] = false;
            $result['message'] = mb_substr($e->getMessage(), 0, 300);
        }
        $this->writeJson($this->manualStateFile, $result);
        @unlink($this->manualJobFile);
        $this->notifyManualTelegram($result);
        return $result;
    }

    /** Trạng thái tạo backup thủ công cho trang Sao lưu: đang chạy + kết quả gần nhất. */
    public function manualStatus(): array
    {
        $state = @json_decode((string)@file_get_contents($this->manualStateFile), true);
        $running = $this->probeManualRunning();
        $mark = $running ? $this->readManualMark() : [];
        return [
            'running' => $running,
            'running_since' => (string)($mark['started_at'] ?? ''),
            'last' => is_array($state) ? $state : null,
        ];
    }

    public function status(): array
    {
        $state = @json_decode((string)@file_get_contents($this->stateFile), true);
        $config = $this->config();
        $issue = $this->offsiteIssue($config);
        $running = $this->probeRunning();
        $mark = $running ? $this->readRunMark() : [];
        return [
            'config' => $config,
            'last' => is_array($state) ? $state : null,
            'rclone_available' => $this->rcloneAvailable(),
            'cron_registered' => $this->cronJobRegistered(),
            'offsite_issue' => $issue,
            'offsite_ready' => !empty($config['offsite_enabled']) && $issue === '',
            'running' => $running,
            'running_since' => (string)($mark['started_at'] ?? ''),
        ];
    }

    /**
     * Lý do offsite chưa sẵn sàng: '' (sẵn sàng hoặc đang tắt),
     * 'missing_remote' (chưa nhập tên remote), 'missing_rclone' (chưa cài rclone).
     */
    public function offsiteIssue(array $config): string
    {
        if (empty($config['offsite_enabled'])) {
            return '';
        }
        if (trim((string)($config['offsite_remote'] ?? '')) === '') {
            return 'missing_remote';
        }
        if (!$this->rcloneAvailable()) {
            return 'missing_rclone';
        }
        return '';
    }

    /** Kiểm tra kết nối rclone theo cấu hình đã lưu: remote tồn tại + đọc được thư mục đích. */
    public function checkRemote(): array
    {
        $config = $this->config();
        $remote = trim((string)$config['offsite_remote']);
        if (!$this->rcloneAvailable()) {
            return ['ok' => false, 'message' => 'Chưa cài rclone trên thiết bị. Cài gói rclone trong Runtime Packages hoặc chạy: pkg install rclone'];
        }
        if ($remote === '') {
            return ['ok' => false, 'message' => 'Chưa nhập tên rclone remote trong cấu hình (ví dụ gdrive).'];
        }
        $list = CommandRunner::proc([self::rcloneBinary(), 'listremotes'], 30);
        $names = [];
        foreach (explode("\n", (string)($list['out'] ?? '')) as $line) {
            $name = rtrim(trim($line), ':');
            if ($name !== '') {
                $names[] = $name;
            }
        }
        if (!in_array($remote, $names, true)) {
            return ['ok' => false, 'message' => "Remote '{$remote}' chưa được cấu hình trên thiết bị — cần tạo bằng lệnh rclone config. Remote hiện có: " . ($names !== [] ? implode(', ', $names) : '(chưa có remote nào)') . '.'];
        }
        $path = trim((string)$config['offsite_path'], '/ ');
        $target = $remote . ':' . ($path !== '' ? $path : '.');
        $lsd = CommandRunner::proc([self::rcloneBinary(), 'lsd', $target, '--max-depth', '1'], 60);
        if ((int)($lsd['code'] ?? 1) !== 0) {
            return ['ok' => false, 'message' => 'Không truy cập được ' . $target . ': ' . mb_substr(trim((string)($lsd['out'] ?? '')), 0, 200)];
        }
        return ['ok' => true, 'message' => "Kết nối OK: remote '{$remote}' truy cập được" . ($path !== '' ? ", thư mục đích '{$path}' sẵn sàng." : '.')];
    }

    // ===== Nội bộ =====

    /**
     * Giữ khóa chạy bằng flock không chờ: trả handle khi giữ được, null khi
     * đang có vòng khác giữ (hoặc không mở được file khóa). Handle phải được
     * giữ suốt vòng chạy; khóa tự nhả khi đóng handle hoặc tiến trình chết.
     *
     * @return resource|null
     */
    private function tryAcquireRunLock()
    {
        $dir = dirname($this->runLockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $fh = @fopen($this->runLockFile, 'c');
        if ($fh === false) {
            return null;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return null;
        }
        return $fh;
    }

    /** Có vòng backup nào đang giữ khóa chạy hay không (thăm dò, không giữ). */
    private function probeRunning(): bool
    {
        $fh = @fopen($this->runLockFile, 'c');
        if ($fh === false) {
            return false;
        }
        $locked = !flock($fh, LOCK_EX | LOCK_NB);
        if (!$locked) {
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return $locked;
    }

    /** Dấu vết vòng đang chạy (started_at, pid) do runNow() ghi — chỉ để hiển thị. */
    private function readRunMark(): array
    {
        $mark = @json_decode((string)@file_get_contents($this->runMarkFile), true);
        return is_array($mark) ? $mark : [];
    }

    private function formatStartedAt(string $iso): string
    {
        $ts = $iso !== '' ? strtotime($iso) : false;
        return $ts !== false ? date('H:i d/m/Y', $ts) : '';
    }

    /**
     * Khởi chạy worker CLI backup tách rời khỏi request web (nohup + setsid,
     * stdio đóng kín, log vào ~/.tms-os/logs/auto-backup.log) — cùng mẫu với
     * PluginService::launchWorker(). Trả false khi thiếu file worker.
     */
    private function launchBackgroundWorker(): bool
    {
        $script = $this->targetRoot . '/scripts/tms-auto-backup.php';
        if (!is_file($script)) {
            return false;
        }
        $logFile = $this->home . '/.tms-os/logs/auto-backup.log';
        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0700, true);
        }
        $prefix = getenv('PREFIX') ?: '/data/data/com.termux/files/usr';
        $php = is_file($prefix . '/bin/php') ? $prefix . '/bin/php' : 'php';
        $setsid = is_executable($prefix . '/bin/setsid') ? 'setsid ' : '';
        $cmd = 'nohup ' . $setsid . escapeshellarg($php) . ' ' . escapeshellarg($script)
            . ' >>' . escapeshellarg($logFile) . ' 2>&1 < /dev/null &';
        if ($this->launcher !== null) {
            return (bool)call_user_func($this->launcher, $cmd);
        }
        @exec($cmd);
        return true;
    }

    // ===== Nội bộ: tạo backup thủ công ở chế độ nền (V17.1.12) =====

    /** Giữ khóa chạy của vòng thủ công bằng flock không chờ (bản sao của tryAcquireRunLock(), file khóa riêng). */
    private function tryAcquireManualLock()
    {
        $dir = dirname($this->manualLockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $fh = @fopen($this->manualLockFile, 'c');
        if ($fh === false) {
            return null;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return null;
        }
        return $fh;
    }

    /** Có vòng tạo backup thủ công nào đang giữ khóa chạy hay không (thăm dò, không giữ). */
    private function probeManualRunning(): bool
    {
        $fh = @fopen($this->manualLockFile, 'c');
        if ($fh === false) {
            return false;
        }
        $locked = !flock($fh, LOCK_EX | LOCK_NB);
        if (!$locked) {
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return $locked;
    }

    /** Dấu vết vòng thủ công đang chạy (started_at, pid) do runManualJob() ghi — chỉ để hiển thị. */
    private function readManualMark(): array
    {
        $mark = @json_decode((string)@file_get_contents($this->manualMarkFile), true);
        return is_array($mark) ? $mark : [];
    }

    /**
     * Khởi chạy worker tạo backup thủ công tách rời khỏi request web (nohup +
     * setsid, log vào ~/.tms-os/logs/manual-backup.log) — cùng mẫu
     * launchBackgroundWorker().
     */
    private function launchManualWorker(): bool
    {
        $script = $this->targetRoot . '/scripts/tms-manual-backup.php';
        if (!is_file($script)) {
            return false;
        }
        $logFile = $this->home . '/.tms-os/logs/manual-backup.log';
        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0700, true);
        }
        $prefix = getenv('PREFIX') ?: '/data/data/com.termux/files/usr';
        $php = is_file($prefix . '/bin/php') ? $prefix . '/bin/php' : 'php';
        $setsid = is_executable($prefix . '/bin/setsid') ? 'setsid ' : '';
        $cmd = 'nohup ' . $setsid . escapeshellarg($php) . ' ' . escapeshellarg($script)
            . ' >>' . escapeshellarg($logFile) . ' 2>&1 < /dev/null &';
        if ($this->launcher !== null) {
            return (bool)call_user_func($this->launcher, $cmd);
        }
        @exec($cmd);
        return true;
    }

    /**
     * Báo cáo Telegram khi tạo backup thủ công hoàn tất — theo yêu cầu trực
     * tiếp của user (khác backup tự động, vốn có công tắc notify_telegram
     * riêng trong cấu hình tự động). Chưa cấu hình bot thì
     * sendTelegramNotification tự trả not_configured, không thành lỗi; mọi
     * lỗi thông báo đều bị nuốt để không làm hỏng kết quả backup.
     */
    private function notifyManualTelegram(array $result): void
    {
        if (!method_exists($this->cron, 'sendTelegramNotification')) {
            return;
        }
        $icon = $result['ok'] ? '✅' : '❌';
        $text = $icon . " TMS Backup thủ công\n" . (string)($result['message'] ?? '');
        try {
            $this->cron->sendTelegramNotification($text);
        } catch (Throwable) {
            // Không cho lỗi thông báo làm hỏng kết quả backup.
        }
    }

    /** Định dạng dung lượng kiểu Việt Nam (dấu phẩy thập phân) cho câu thông báo. */
    private function formatBytesVi(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $i = -1;
        do {
            $value /= 1024;
            $i++;
        } while ($value >= 1024 && $i < count($units) - 1);
        return str_replace('.', ',', number_format($value, $value >= 100 ? 0 : 1)) . ' ' . $units[$i];
    }

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
