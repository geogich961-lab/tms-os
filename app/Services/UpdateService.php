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

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->dir = $this->home . '/.tms-os/updates';
        $this->target = $this->home . '/tms-os';
        $this->stateFile = $this->dir . '/apply-state.json';
        $this->tokenFile = $this->home . '/.tms-os/update-token';
        @mkdir($this->dir, 0700, true);
        @mkdir($this->home . '/.tms-os', 0700, true);
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
        $state = [
            'applying' => true,
            'staging' => $staging,
            'backup' => $backupDir,
            'zip' => $zipFile,
            'started_at' => time(),
        ];
        file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT));

        // 5. Swap: target → target.previous, staging → target
        $previous = $this->target . '.previous';
        $this->rmdir($previous);
        $oldParts = ['app', 'config', 'public', 'routes', 'scripts'];
        foreach ($oldParts as $part) {
            if (is_dir($this->target . '/' . $part)) {
                @mkdir(dirname($previous) === '/' ? $previous : $previous, 0700, true);
                rename($this->target . '/' . $part, $previous . '/' . $part);
            }
        }
        @mkdir($this->target, 0700, true);
        foreach (['app', 'config', 'public', 'routes', 'scripts'] as $part) {
            if (is_dir($staging . '/' . $part)) {
                rename($staging . '/' . $part, $this->target . '/' . $part);
            }
        }
        // Storage giữ nguyên (sessions/logs/cache không nằm trong ZIP).
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
            foreach ($oldParts as $part) {
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

        // 8. Hoàn tất
        @unlink($this->stateFile);
        return [
            'ok' => true,
            'backup' => $backupDir,
            'message' => 'Đã áp dụng cập nhật thành công. Hệ thống đã tự động làm mới cache và phiên bản giao diện.',
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
