<?php
declare(strict_types=1);

/**
 * UpdateService — V14.1.3: Update Center hoàn chỉnh.
 * Chức năng: kiểm tra bản mới trên GitHub, tải về và staging an toàn,
 * áp dụng cập nhật 1 chạm (backup → staging → swap → health check → rollback nếu lỗi),
 * hỗ trợ API token cho lệnh tự cập nhật không cần đăng nhập UI.
 */
final class UpdateService
{
    private string $home;
    private string $dir;
    private string $target;
    private string $stateFile;
    private string $tokenFile;
    private string $queueFile;
    private string $workerLock;
    private string $workerScript;
    private string $workerLog;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->dir = $this->home . '/.tms-os/updates';
        $this->target = $this->home . '/tms-os';
        $this->stateFile = $this->dir . '/apply-state.json';
        $this->tokenFile = $this->home . '/.tms-os/update-token';
        $this->queueFile = $this->dir . '/github-apply.job.json';
        $this->workerLock = $this->dir . '/github-apply.worker.lock';
        $this->workerScript = $this->target . '/scripts/tms-update-worker.php';
        $this->workerLog = $this->home . '/logs/services/update-worker.log';
        @mkdir($this->dir, 0700, true);
        @mkdir($this->home . '/.tms-os', 0700, true);
        @mkdir($this->home . '/logs/services', 0700, true);
    }

    /** Kiểm tra version hiện tại so với release mới nhất trên GitHub. */
    public function check(): array
    {
        $current = $this->currentVersion();
        try {
            $release = $this->latestRelease();
        } catch (Throwable $e) {
            return ['current' => $current, 'available' => null, 'error' => 'Không thể kiểm tra bản mới: ' . $e->getMessage()];
        }
        $available = $this->normalizeVersion($release['version'] ?? '');
        return [
            'current' => $current,
            'available' => $available !== '' && $this->isNewer($available, $current) ? $release : null,
            'error' => null,
        ];
    }

    public function currentVersion(): string
    {
        $app = $this->target . '/config/app.php';
        if (!is_file($app)) {
            return 'unknown';
        }
        $config = [];
        try {
            $config = require $app;
        } catch (Throwable) {
            return 'unknown';
        }
        return str_replace('Platform ', '', (string)($config['build'] ?? 'unknown'));
    }

    /** Lấy thông tin release mới nhất từ GitHub API (release v14.0.4+ đều có TMS_OS_LATEST.zip). */
    public function latestRelease(): array
    {
        $endpoints = [
            'https://api.github.com/repos/geogich961-lab/tms-os/releases/latest',
            'https://www.github.com/api/v3/repos/geogich961-lab/tms-os/releases/latest',
        ];
        $data = null;
        foreach ($endpoints as $url) {
            $result = $this->httpGet($url, 20, 'TMS-OS-Updater/1.4');
            if ($result === '') { continue; }
            $parsed = json_decode($result, true);
            if (is_array($parsed) && !empty($parsed['tag_name'])) { $data = $parsed; break; }
        }
        if ($data === null) {
            throw new RuntimeException('Không thể kết nối GitHub — kiểm tra thiết bị đã có mạng và thử lại sau vài giây.');
        }
        $zipUrl = '';
        $zipName = '';
        foreach ((array)($data['assets'] ?? []) as $asset) {
            $name = (string)($asset['name'] ?? '');
            if ($name === 'TMS_OS_LATEST.zip') {
                $zipUrl = (string)($asset['browser_download_url'] ?? '');
                $zipName = $name;
                break;
            }
        }
        if ($zipUrl === '') {
            throw new RuntimeException('Release mới nhất không có gói cài đặt.');
        }
        // Phiên bản dạng v14.1.3
        $tag = (string)$data['tag_name'];
        $version = ltrim($tag, 'vV');
        return [
            'version' => $version,
            'tag' => $tag,
            'zip_url' => $zipUrl,
            'zip_name' => $zipName,
            'notes' => (string)($data['body'] ?? ''),
            'published_at' => (string)($data['published_at'] ?? ''),
        ];
    }

    /** Tải gói cập nhật từ GitHub vào thư mục staging, validate cấu trúc + checksum nếu có RELEASE.json. */
    public function stageFromGitHub(?string $zipUrl = null): array
    {
        $release = $this->latestRelease();
        if ($zipUrl === null) {
            $zipUrl = $release['zip_url'];
        }
        $tmp = $this->dir . '/.download-' . bin2hex(random_bytes(8));
        $content = $this->httpGet($zipUrl, 90, 'TMS-OS-Updater/1.4');
        $bytes = $content === '' ? false : @file_put_contents($tmp, $content);
        unset($content);
        if ($bytes === false || $bytes < 1000) {
            @unlink($tmp);
            throw new RuntimeException('Tải gói cập nhật thất bại (kết nối GitHub) — kiểm tra mạng và thử lại.');
        }
        $this->validateZip($tmp);

        // Nếu release có đính kèm RELEASE.json → kiểm checksum
        $releaseJsonUrl = str_replace('TMS_OS_LATEST.zip', 'RELEASE.json', $zipUrl);
        $expectedHash = $this->fetchExpectedHash($releaseJsonUrl);
        $actualHash = hash_file('sha256', $tmp);
        $hashOk = $expectedHash === '' || str_starts_with($expectedHash, $actualHash);
        if (!$hashOk) {
            @unlink($tmp);
            throw new RuntimeException('Checksum gói cập nhật không khớp — gói có thể bị hỏng.');
        }

        $name = 'tms-update-' . date('Ymd_His') . '.zip';
        $dest = $this->dir . '/' . $name;
        if (!rename($tmp, $dest)) {
            @unlink($tmp);
            throw new RuntimeException('Không thể lưu gói cập nhật.');
        }
        chmod($dest, 0600);
        return [
            'ok' => true,
            'name' => $name,
            'size' => $bytes,
            'sha256' => $actualHash,
            'version' => $release['version'],
            'message' => 'Đã tải bản ' . $release['version'] . ' từ GitHub. Sẵn sàng áp dụng.',
        ];
    }

    /** Stage file upload thủ công (giữ lại tính năng cũ). */
    public function stage(array $upload): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Tải gói cập nhật thất bại.');
        }
        if (($upload['size'] ?? 0) > 100 * 1024 * 1024) {
            throw new RuntimeException('Gói cập nhật vượt quá 100 MB.');
        }
        $tmp = (string)$upload['tmp_name'];
        $this->validateZip($tmp);
        $name = 'tms-update-' . date('Ymd_His') . '.zip';
        $dest = $this->dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Không thể lưu gói cập nhật.');
        }
        return ['ok' => true, 'message' => 'Đã kiểm tra và lưu gói cập nhật.', 'name' => $name];
    }

    public function delete(string|array $names): void
    {
        $names = is_array($names) ? $names : [$names];
        foreach ($names as $name) {
            $name = basename((string)$name);
            $file = $this->dir . '/' . $name;
            if (is_file($file) && str_ends_with($name, '.zip')) {
                @unlink($file);
            }
        }
    }

    public function staged(): array
    {
        $items = [];
        foreach (glob($this->dir . '/tms-update-*.zip') ?: [] as $file) {
            $items[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'sha256' => hash_file('sha256', $file),
                'time' => filemtime($file),
            ];
        }
        usort($items, static fn($a, $b) => $b['time'] <=> $a['time']);
        return $items;
    }

    /** Áp dụng gói đã staging: backup → staging → swap → health check → rollback nếu lỗi. */
    public function apply(string $zipName): array
    {
        $zipName = basename($zipName);
        $zipFile = $this->dir . '/' . $zipName;
        if (!is_file($zipFile) || !str_ends_with($zipName, '.zip')) {
            throw new RuntimeException('Gói cập nhật không tồn tại.');
        }
        return $this->doApply($zipFile);
    }

    /** Tải bản mới nhất từ GitHub rồi áp dụng ngay (update 1 chạm qua API). */
    public function applyFromGitHub(): array
    {
        $release = $this->latestRelease();
        $current = $this->currentVersion();
        $available = $this->normalizeVersion($release['version'] ?? '');
        if ($available === '' || !$this->isNewer($available, $current)) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Đã là phiên bản mới nhất (' . $current . '). Không cần cập nhật.', 'version' => $current];
        }
        $staged = $this->stageFromGitHub($release['zip_url'] ?? null);
        return $this->doApply($this->dir . '/' . $staged['name']);
    }

    /**
     * Request web chỉ ghi job và trả JSON. Việc tải/swap/restart chạy trong
     * worker riêng, tránh làm PHP-CGI bị dừng trước khi response đến trình duyệt.
     */
    public function enqueueGitHubApply(): array
    {
        $state = $this->status()['state'] ?? [];
        if (!empty($state['applying']) || is_file($this->queueFile)) {
            throw new RuntimeException('Đang có một cập nhật trong hàng đợi. Vui lòng chờ hệ thống hoàn tất.');
        }
        $release = $this->latestRelease();
        $current = $this->currentVersion();
        $available = $this->normalizeVersion((string)($release['version'] ?? ''));
        if ($available === '' || !$this->isNewer($available, $current)) {
            return ['ok'=>true,'skipped'=>true,'version'=>$current,'message'=>'Đã là phiên bản mới nhất ('.$current.'). Không cần cập nhật.'];
        }

        $job = date('YmdHis').'-'.bin2hex(random_bytes(5));
        $payload = ['job'=>$job,'source'=>'github','from'=>$current,'to'=>$available,'queued_at'=>date('c')];
        $this->writeJsonAtomically($this->queueFile, $payload);
        $this->writeJsonAtomically($this->stateFile, array_merge($payload, [
            'applying'=>true,
            'phase'=>'queued',
            'message'=>'Đã nhận yêu cầu cập nhật. Worker đang chuẩn bị tải gói an toàn.',
        ]));
        $this->launchUpdateWorker();
        return ['ok'=>true,'queued'=>true,'job'=>$job,'message'=>'Đã nhận yêu cầu cập nhật '.$available.'. Panel sẽ tự xác minh sau khi dịch vụ khởi động lại.'];
    }

    /** Chỉ worker nội bộ được gọi phương thức này; không nhận input từ web. */
    public function runQueuedGitHubApply(): void
    {
        if (!@mkdir($this->workerLock, 0700)) {
            return;
        }
        try {
            $job = $this->readJson($this->queueFile);
            if ($job === []) {
                return;
            }
            $this->writeJsonAtomically($this->stateFile, array_merge($job, [
                'applying'=>true,
                'phase'=>'applying',
                'started_at'=>date('c'),
                'message'=>'Đang tải, kiểm tra checksum và áp dụng gói cập nhật.',
            ]));
            $result = $this->applyFromGitHub();
            $this->writeJsonAtomically($this->stateFile, array_merge($job, [
                'applying'=>false,
                'ok'=>true,
                'phase'=>!empty($result['skipped'])?'skipped':'restarting',
                'finished_at'=>date('c'),
                'current'=>$this->currentVersion(),
                'message'=>(string)($result['message'] ?? 'Đã áp dụng cập nhật.'),
            ]));
            @unlink($this->queueFile);
        } catch (Throwable $e) {
            $job = $this->readJson($this->queueFile);
            $message = mb_substr(preg_replace('/\s+/', ' ', trim($e->getMessage())), 0, 500);
            $this->writeJsonAtomically($this->stateFile, array_merge($job, [
                'applying'=>false,
                'ok'=>false,
                'phase'=>'failed',
                'finished_at'=>date('c'),
                'message'=>$message,
            ]));
            @unlink($this->queueFile);
        } finally {
            @rmdir($this->workerLock);
        }
    }

    /** Khôi phục về bản trước (previous/quarantine backup gần nhất). */
    public function rollback(): array
    {
        $previous = $this->target . '.previous';
        $backupDir = $this->home . '/.tms-os/backups';
        $backups = [];
        foreach (glob($backupDir . '/*/tms-os') ?: [] as $b) {
            // Chỉ dùng bản sao lưu có đầy đủ các phần thiết yếu (bỏ qua backup rỗng/không đủ)
            if (is_dir($b) && is_dir($b . '/app') && is_dir($b . '/public') && is_dir($b . '/config')) {
                $backups[] = $b;
            }
        }
        usort($backups, static fn($a, $b) => strcmp($b, $a));
        $latestBackup = $backups[0] ?? null;

        if (!is_dir($previous) && $latestBackup === null) {
            throw new RuntimeException('Không có bản sao lưu nào để khôi phục.');
        }

        $quarantineDir = $this->home . '/.tms-os/quarantine/rollback-' . date('Ymd_His');
        @mkdir($quarantineDir, 0700, true);

        // Đưa target hiện tại vào quarantine trước
        if (is_dir($this->target)) {
            @mkdir($quarantineDir . '/tms-os', 0700, true);
            $parts = ['app', 'config', 'public', 'routes', 'scripts', 'storage'];
            foreach ($parts as $part) {
                if (is_dir($this->target . '/' . $part)) {
                    rename($this->target . '/' . $part, $quarantineDir . '/tms-os/' . $part) ?: @rename($this->target . '/' . $part, $quarantineDir . '/tms-os/' . $part);
                }
            }
        }

        // Khôi phục từ previous (swap gần nhất) hoặc backup cũ nhất
        if (is_dir($previous)) {
            rename($previous, $this->target);
            $source = 'swap trước đó';
        } else {
            @mkdir($this->target, 0700, true);
            foreach (['app', 'config', 'public', 'routes', 'scripts'] as $part) {
                if (is_dir($latestBackup . '/' . $part)) {
                    $this->copyDir($latestBackup . '/' . $part, $this->target . '/' . $part);
                }
            }
            $source = 'sao lưu ' . basename(dirname((string)$latestBackup));
        }

        @unlink($this->stateFile);
        return ['ok' => true, 'message' => 'Đã khôi phục bản trước (' . $source . '). Vui lòng khởi động lại dịch vụ nếu panel chưa hoạt động.'];
    }

    /** Trạng thái hiện tại của Update Center. */
    public function status(): array
    {
        $state = [];
        if (is_file($this->stateFile)) {
            $state = json_decode((string)file_get_contents($this->stateFile), true) ?: [];
        }
        return [
            'current' => $this->currentVersion(),
            'previous_exists' => is_dir($this->target . '.previous'),
            'applying' => !empty($state['applying']),
            'state' => $state,
        ];
    }

    // ===== API token (cập nhật 1 lệnh không cần đăng nhập UI) =====

    /** Token lưu ở ~/.tms-os/update-token (tự tạo nếu chưa có). */
    public function token(): string
    {
        if (is_file($this->tokenFile) && is_readable($this->tokenFile)) {
            $tok = trim((string)file_get_contents($this->tokenFile));
            if ($tok !== '') {
                return $tok;
            }
        }
        $tok = bin2hex(random_bytes(32));
        file_put_contents($this->tokenFile, $tok . "\n");
        chmod($this->tokenFile, 0600);
        return $tok;
    }

    public function verifyApiToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        return hash_equals($this->token(), $token);
    }

    // ===== Nội bộ =====

    private function launchUpdateWorker(): void
    {
        if (!is_file($this->workerScript)) {
            throw new RuntimeException('Thiếu worker cập nhật. Vui lòng Repair TMS OS trước khi thử lại.');
        }
        $setsid = is_executable('/data/data/com.termux/files/usr/bin/setsid') ? 'setsid ' : '';
        $cmd = 'nohup '.$setsid.'php '.escapeshellarg($this->workerScript)
            .' >>'.escapeshellarg($this->workerLog).' 2>&1 < /dev/null &';
        @exec($cmd);
    }

    private function readJson(string $file): array
    {
        $data = @json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function writeJsonAtomically(string $file, array $data): void
    {
        $tmp = $file.'.tmp-'.bin2hex(random_bytes(4));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false || @file_put_contents($tmp, $json."\n", LOCK_EX) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Không thể lưu trạng thái cập nhật.');
        }
        @chmod($file, 0600);
    }

    private function doApply(string $zipFile): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new RuntimeException('File ZIP không đọc được.');
        }

        // 1. Backup nhanh target hiện tại (chỉ các phần core, tránh sao lưu storage lớn)
        $backupStamp = 'upd-' . date('Ymd_His');
        $backupDir = $this->home . '/.tms-os/backups/' . $backupStamp . '/tms-os';
        @mkdir($backupDir, 0700, true);
        foreach (['app', 'config', 'public', 'routes', 'scripts'] as $part) {
            if (is_dir($this->target . '/' . $part)) {
                $this->copyDir($this->target . '/' . $part, $backupDir . '/' . $part);
            }
        }

        // 2. Giải nén vào staging
        $staging = $this->home . '/.tms-os-staging-' . $backupStamp;
        $this->rmdir($staging);
        @mkdir($staging, 0700, true);
        if (!$zip->extractTo($staging)) {
            $zip->close();
            $this->rmdir($staging);
            throw new RuntimeException('Không thể giải nén gói cập nhật.');
        }
        $zip->close();

        // 3. php -l toàn bộ file PHP trong staging
        $lintFail = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $out = [];
                exec('php -l ' . escapeshellarg((string)$file->getPathname()) . ' 2>&1', $out, $code);
                if ($code !== 0) {
                    $lintFail[] = $file->getFilename();
                }
            }
        }
        if ($lintFail !== []) {
            $this->rmdir($staging);
            throw new RuntimeException('Gói cập nhật chứa lỗi cú pháp PHP: ' . implode(', ', array_slice($lintFail, 0, 3)));
        }

        // 4. Ghi state (phục vụ rollback nếu process chết giữa chừng)
        $queuedState = $this->readJson($this->stateFile);
        $state = array_merge($queuedState, [
            'applying' => true,
            'phase' => 'swapping',
            'staging' => $staging,
            'backup' => $backupDir,
            'zip' => $zipFile,
            'started_at' => time(),
        ]);
        $this->writeJsonAtomically($this->stateFile, $state);

        // 5. Swap: target → target.previous, staging → target
        $previous = $this->target . '.previous';
        $this->rmdir($previous);
        @mkdir($previous, 0700, true);
        
        $parts = ['app', 'config', 'public', 'routes', 'scripts'];
        foreach ($parts as $part) {
            $src = $this->target . '/' . $part;
            if (is_dir($src)) {
                // Sao lưu bản cũ sang .previous
                exec("cp -af " . escapeshellarg($src) . " " . escapeshellarg($previous . '/'));
            }
        }
        
        // Áp dụng code mới từ staging vào target bằng cơ chế "Xóa sạch - Chép mới"
        // Điều này giúp bẻ gãy mọi sự chiếm giữ file của PHP-CGI cũ
        foreach ($parts as $part) {
            $src = $staging . '/' . $part;
            if (is_dir($src)) {
                $dst = $this->target . '/' . $part;
                // Cưỡng bức xóa thư mục cũ
                exec("rm -rf " . escapeshellarg($dst));
                // Chép thư mục mới vào
                exec("cp -af " . escapeshellarg($src) . " " . escapeshellarg($this->target . '/'));
            }
        }
        
        // Đảm bảo file config/app.php được ghi đè tuyệt đối
        $configSrc = $staging . '/config/app.php';
        $configDst = $this->target . '/config/app.php';
        if (is_file($configSrc)) {
            @unlink($configDst); // Xóa hẳn file cũ
            exec("cp -af " . escapeshellarg($configSrc) . " " . escapeshellarg($configDst));
            @chmod($configDst, 0644);
        }
        
        // Cố gắng xóa OPcache của PHP cưỡng bức
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if (function_exists('clearstatcache')) {
            clearstatcache(true);
        }
        
        // Dọn dẹp staging
        $this->rmdir($staging);

        // 6. Health check — kiểm tra cú pháp PHP của source mới vừa swap vào
        // (không gọi HTTP vì request hiện tại đang chiếm worker php-cgi duy nhất).
        $healthy = false;
        try {
            $lintOk = true;
            foreach (['config/app.php', 'public/index.php', 'routes/web.php', 'app/Core/helpers.php', 'app/Core/Router.php'] as $critical) {
                $f = $this->target . '/' . $critical;
                if (!is_file($f)) {
                    $lintOk = false;
                    break;
                }
                $out = [];
                exec('php -l ' . escapeshellarg($f) . ' 2>&1', $out, $code);
                if ($code !== 0) {
                    $lintOk = false;
                    break;
                }
            }
            $healthy = $lintOk;
        } catch (Throwable) {
            // không healthy
        }
        if (!$healthy) {
            // Rollback: đưa previous về target
            $quarantineDir = $this->home . '/.tms-os/quarantine/' . $backupStamp;
            @mkdir($quarantineDir, 0700, true);
            foreach ($parts as $part) {
                if (is_dir($this->target . '/' . $part)) {
                    rename($this->target . '/' . $part, $quarantineDir . '/' . $part);
                }
            }
            if (is_dir($previous)) {
                foreach (['app', 'config', 'public', 'routes', 'scripts'] as $part) {
                    if (is_dir($previous . '/' . $part)) {
                        rename($previous . '/' . $part, $this->target . '/' . $part);
                    }
                }
            }
            @unlink($this->stateFile);
            throw new RuntimeException('Cập nhật thất bại khi kiểm tra sức khỏe — đã tự động khôi phục bản trước.');
        }

        // 7. Tự động xóa cache và tăng asset version để trình duyệt nhận diện code mới ngay lập tức
        if (function_exists('tms_clear_cache')) {
            tms_clear_cache();
        }
        
        // Tăng asset version trong config để ép trình duyệt tải lại CSS/JS mới nhất (tránh lỗi 520 do cache cũ)
        $configPath = $this->target . '/config/app.php';
        if (is_file($configPath)) {
            $configContent = file_get_contents($configPath);
            $newVersion = date('Y.m.d.His');
            $configContent = preg_replace("/'asset_version' => '.*'/", "'asset_version' => '{$newVersion}'", $configContent);
            file_put_contents($configPath, $configContent);
        }

        // 8. Hoàn tất xử lý file và dọn dẹp
        @unlink($this->stateFile);

        // 9. Chuẩn bị khởi động lại bất đồng bộ
        // Chúng ta cần đảm bảo response được gửi về trình duyệt TRƯỚC khi kill tiến trình PHP
        $restartScript = $this->target . '/scripts/start-tms.sh';
        if (is_file($restartScript)) {
            // Sử dụng cơ chế "Sát thủ tiến trình" mạnh mẽ hơn:
            // 1. Gửi phản hồi HTTP về Cloudflare trước
            // 2. Chờ 2 giây để kết nối đóng an toàn
            // 3. Force kill mọi tiến trình Nginx/PHP cũ và khởi động lại
            $cmd = "sleep 2 && (pkill -9 -f php-cgi; pkill -9 -f nginx; bash " . escapeshellarg($restartScript) . ")";
            exec("nohup bash -c " . escapeshellarg($cmd) . " > /dev/null 2>&1 &");
        }

        return [
            'ok' => true,
            'backup' => $backupDir,
            'message' => 'Đã áp dụng cập nhật thành công. Hệ thống đang khởi động lại dịch vụ (mất khoảng 5-10 giây), vui lòng đợi...',
            'restarting' => true
        ];
    }

    private function validateZip(string $tmp): void
    {
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new RuntimeException('File không phải ZIP hợp lệ.');
        }
        $required = ['config/app.php', 'public/index.php', 'scripts/install.sh'];
        foreach ($required as $r) {
            if ($zip->locateName($r) === false) {
                $zip->close();
                throw new RuntimeException('Gói cập nhật thiếu ' . $r);
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (str_contains($n, '../') || str_starts_with($n, '/')) {
                $zip->close();
                throw new RuntimeException('ZIP chứa đường dẫn không an toàn.');
            }
        }
        $zip->close();
    }

    private function fetchExpectedHash(string $releaseJsonUrl): string
    {
        $json = $this->httpGet($releaseJsonUrl, 15, 'TMS-OS-Updater/1.4');
        if ($json === '') {
            return '';
        }
        $data = json_decode($json, true);
        return (string)($data['checksum_sha256'] ?? '');
    }

    /** GET HTTP với retry — V14.1.6: chống lỗi mạng thoáng qua (DNS chặn api.github.com trên một số mạng di động). */
    private function httpGet(string $url, int $timeout, string $ua): string
    {
        $lastError = '';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'follow_location' => true,
                    'header' => "User-Agent: {$ua}\r\nAccept: */*\r\n",
                    'ignore_errors' => true,
                ],
            ]);
            $content = @file_get_contents($url, false, $ctx);
            if ($content !== false) {
                return $content;
            }
            $lastError = (error_get_last()['message'] ?? '');
            if ($attempt < 3) { usleep(1000000 * $attempt); }
        }
        return '';
    }

    private function normalizeVersion(string $v): string
    {
        return ltrim(trim($v), 'vV');
    }

    /** So sánh version dạng MAJOR.MINOR.PATCH. */
    private function isNewer(string $a, string $b): bool
    {
        $pa = array_map('intval', explode('.', $this->normalizeVersion($a)));
        $pb = array_map('intval', explode('.', $this->normalizeVersion($b)));
        while (count($pa) < 3) {
            $pa[] = 0;
        }
        while (count($pb) < 3) {
            $pb[] = 0;
        }
        return $pa > $pb;
    }

    private function copyDir(string $src, string $dst): bool
    {
        @mkdir($dst, 0700, true);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $rel = substr((string)$item->getPathname(), strlen($src) + 1);
            $dest = $dst . '/' . $rel;
            if ($item->isDir()) {
                @mkdir($dest, 0700, true);
            } else {
                @copy((string)$item->getPathname(), $dest);
            }
        }
        return true;
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) {
            if ($item->isDir()) {
                @rmdir((string)$item->getPathname());
            } else {
                @unlink((string)$item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
