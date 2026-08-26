<?php
declare(strict_types=1);

/** Runtime Package Manager: cài đặt luôn chạy ngoài request FastCGI. */
final class PluginService
{
    private string $home;
    private string $prefix;
    private string $root;
    private string $stateFile;
    private string $queueDir;
    private string $resultDir;
    private string $lockDir;
    private string $logFile;
    private string $workerScript;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->prefix = getenv('PREFIX') ?: '/data/data/com.termux/files/usr';
        $this->root = getenv('TMS_OS_ROOT') ?: $this->home . '/tms-os';
        $this->stateFile = $this->home . '/.tms-os/packages-v11.json';
        $base = $this->home . '/.tms-os/packages';
        $this->queueDir = $base . '/queue';
        $this->resultDir = $base . '/results';
        $this->lockDir = $base . '/worker.lock';
        $this->logFile = $this->home . '/logs/services/package-worker.log';
        $this->workerScript = $this->root . '/scripts/tms-package-worker.php';
        foreach ([$this->home . '/.tms-os', $base, $this->queueDir, $this->resultDir, dirname($this->logFile)] as $dir) {
            @mkdir($dir, 0700, true);
        }
    }

    public function catalog(): array
    {
        $this->harvestResults();
        $state = $this->readState();
        $items = $this->catalogRaw();
        foreach ($items as &$item) {
            $item['installed'] = $this->commandExists($item['command']);
            $item['version'] = $item['installed'] ? $this->version($item['version_command'] ?? ($item['command'] . ' --version')) : '';
            $item['pending'] = $state['pending'][$item['id']] ?? null;
            $item['busy'] = is_array($item['pending']);
            $item['last_result'] = $this->resultLabel($state['results'][$item['id']] ?? null);
        }
        unset($item);
        return $items;
    }

    /** Chỉ trả trạng thái an toàn cho polling, không trả path/log thô. */
    public function status(): array
    {
        $packages = $this->catalog();
        return ['ok' => true, 'generated_at' => date('c'), 'packages' => $packages];
    }

    /** Job được tạo từ catalog allowlist; HTTP không truyền package command. */
    public function enqueueInstall(string $id): array
    {
        $item = $this->find($id);
        if (!$item) {
            throw new RuntimeException('Gói không tồn tại trong danh mục đã kiểm duyệt.');
        }
        if ($this->commandExists($item['command'])) {
            return ['ok' => true, 'queued' => false, 'job' => '', 'message' => $item['name'] . ' đã được cài và xác minh.'];
        }
        $this->harvestResults();
        $state = $this->readState();
        if (!empty($state['pending'][$id])) {
            throw new RuntimeException($item['name'] . ' đang được cài trong nền. Vui lòng chờ trạng thái hoàn tất.');
        }
        $job = date('YmdHis') . '-' . sprintf('%06d', (int) (microtime(true) * 1000000) % 1000000) . '-' . bin2hex(random_bytes(4));
        $this->writeJson($this->queueDir . '/' . $job . '.job', ['job' => $job, 'id' => $id, 'action' => 'install', 'queued_at' => date('c')]);
        $state['pending'][$id] = ['job' => $job, 'action' => 'install', 'phase' => 'queued', 'queued_at' => date('c')];
        $this->writeState($state);
        $this->launchWorker();
        return ['ok' => true, 'queued' => true, 'job' => $job, 'message' => 'Đã xếp hàng cài ' . $item['name'] . ' trong nền. Panel vẫn hoạt động khi Termux tải gói.'];
    }

    /** Tương thích form cũ: cài đặt cũng luôn đi qua hàng đợi. */
    public function install(string $id): array { return $this->enqueueInstall($id); }
    public function remove(string $id): array { return $this->runPackageAction($id, 'remove'); }

    public function updatePackages(): array
    {
        $out = []; $code = 0;
        exec('pkg update -y 2>&1', $out, $code);
        return ['ok' => $code === 0, 'message' => trim(implode("\n", array_slice($out, -50))) ?: 'Hoàn tất.'];
    }

    /** Entrypoint của tms-package-worker.php; không gọi Service Core. */
    public function runQueuedInstalls(): void
    {
        if (!@mkdir($this->lockDir, 0700)) { return; }
        try {
            while (true) {
                $jobs = glob($this->queueDir . '/*.job') ?: [];
                sort($jobs, SORT_STRING);
                $file = $jobs[0] ?? null;
                if (!is_string($file)) { break; }
                $job = basename($file, '.job');
                $payload = @json_decode((string) @file_get_contents($file), true);
                @unlink($file);
                $id = is_array($payload) ? (string) ($payload['id'] ?? '') : '';
                $item = is_array($payload) && ($payload['job'] ?? '') === $job && ($payload['action'] ?? '') === 'install' ? $this->find($id) : null;
                if (!$item) {
                    $this->writeResult($job, $id, false, 'Công việc không thuộc danh mục Runtime Package đã kiểm duyệt.');
                    $this->harvestResults();
                    continue;
                }
                $this->setPhase($id, $job, 'running');
                $this->log('Bắt đầu cài ' . $item['id'] . ' (' . $job . ').');
                $run = $this->installPackage($item);
                $this->log(($run['ok'] ? 'Hoàn tất' : 'Thất bại') . ' ' . $item['id'] . ' (' . $job . '): ' . $run['message']);
                $this->writeResult($job, $id, $run['ok'], $run['message']);
                $this->harvestResults();
            }
        } finally {
            @rmdir($this->lockDir);
        }
    }

    private function installPackage(array $item): array
    {
        // command/package chỉ đến từ catalogRaw(), không từ input người dùng.
        $pkg = getenv('TMS_PKG_COMMAND') ?: 'pkg';
        $minimum = getenv('TMS_PACKAGE_TEST_MODE') === '1' ? 1 : 30;
        $timeout = max($minimum, min(600, (int) (getenv('TMS_PACKAGE_TIMEOUT') ?: 300)));
        $run = $this->runProcess([$pkg, 'install', '-y', (string) $item['package']], $timeout);
        if ($run['timed_out']) {
            return ['ok' => false, 'message' => 'Hết thời gian chờ khi tải gói. Panel không bị ảnh hưởng; hãy kiểm tra mạng/kho Termux rồi thử lại.'];
        }
        if ($run['code'] === 0 && $this->commandExists((string) $item['command'])) {
            return ['ok' => true, 'message' => $item['name'] . ' đã cài xong và lệnh thực thi đã được xác minh.'];
        }
        $detail = $this->sanitize((string) $run['output']);
        return ['ok' => false, 'message' => $detail === '' ? 'Cài đặt thất bại hoặc chưa tìm thấy lệnh sau khi hoàn tất.' : 'Cài đặt thất bại: ' . $detail];
    }

    /** proc_open polling thay vì phụ thuộc GNU timeout trên Termux cũ. */
    private function runProcess(array $command, int $timeout): array
    {
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) { return ['code' => 127, 'output' => 'Không thể khởi chạy trình quản lý gói.', 'timed_out' => false]; }
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $started = microtime(true); $output = ''; $exit = null; $timedOut = false;
        while (true) {
            $output = $this->bound($output, (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]));
            $status = proc_get_status($process);
            if (!$status['running']) { $exit = (int) $status['exitcode']; break; }
            if (microtime(true) - $started >= $timeout) {
                $timedOut = true; @proc_terminate($process, 15); usleep(250000);
                if (proc_get_status($process)['running']) { @proc_terminate($process, 9); }
                break;
            }
            usleep(100000);
        }
        $output = $this->bound($output, (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]));
        fclose($pipes[1]); fclose($pipes[2]); $closed = proc_close($process);
        return ['code' => $exit ?? $closed, 'output' => $output, 'timed_out' => $timedOut];
    }

    private function runPackageAction(string $id, string $action): array
    {
        $item = $this->find($id);
        if (!$item) { throw new RuntimeException('Gói không tồn tại trong danh mục.'); }
        $state = $this->readState();
        if (!empty($state['pending'][$id])) { throw new RuntimeException($item['name'] . ' đang có công việc cài đặt trong nền.'); }
        if ($action === 'remove' && !$this->commandExists($item['command'])) { return ['ok' => true, 'message' => $item['name'] . ' chưa được cài.']; }
        if ($action === 'remove' && !empty($item['protected'])) { throw new RuntimeException('Gói lõi đang được TMS OS sử dụng và không thể gỡ từ giao diện.'); }
        $out = []; $code = 0;
        exec('pkg uninstall -y ' . escapeshellarg($item['package']) . ' 2>&1', $out, $code);
        $ok = $code === 0 && !$this->commandExists($item['command']);
        $state['results'][$id] = ['job' => '', 'ok' => $ok, 'action' => 'remove', 'message' => $ok ? 'Đã gỡ gói.' : $this->sanitize(implode("\n", $out)), 'finished_at' => date('c')];
        $this->writeState($state);
        return ['ok' => $ok, 'message' => trim(implode("\n", array_slice($out, -40))) ?: ($ok ? 'Hoàn tất.' : 'Thao tác thất bại.')];
    }

    private function launchWorker(): void
    {
        if (getenv('TMS_PACKAGE_NO_LAUNCH') === '1' || !is_file($this->workerScript)) { return; }
        $php = is_file($this->prefix . '/bin/php') ? $this->prefix . '/bin/php' : 'php';
        $setsid = is_executable($this->prefix . '/bin/setsid') ? 'setsid ' : '';
        @exec('nohup ' . $setsid . escapeshellarg($php) . ' ' . escapeshellarg($this->workerScript) . ' >>' . escapeshellarg($this->logFile) . ' 2>&1 < /dev/null &');
    }

    private function setPhase(string $id, string $job, string $phase): void
    {
        $state = $this->readState();
        if (($state['pending'][$id]['job'] ?? '') === $job) {
            $state['pending'][$id]['phase'] = $phase; $state['pending'][$id]['started_at'] = date('c'); $this->writeState($state);
        }
    }

    private function writeResult(string $job, string $id, bool $ok, string $message): void
    {
        $this->writeJson($this->resultDir . '/' . $job . '.json', ['job' => $job, 'id' => $id, 'action' => 'install', 'ok' => $ok, 'message' => $this->sanitize($message), 'finished_at' => date('c')]);
    }

    private function harvestResults(): void
    {
        $state = $this->readState(); $changed = false;
        foreach (glob($this->resultDir . '/*.json') ?: [] as $file) {
            $result = @json_decode((string) @file_get_contents($file), true);
            $id = is_array($result) ? (string) ($result['id'] ?? '') : '';
            if (!$this->find($id) || !is_array($result) || !isset($result['job'])) { @unlink($file); continue; }
            $state['results'][$id] = ['job' => (string) $result['job'], 'ok' => !empty($result['ok']), 'action' => 'install', 'message' => $this->sanitize((string) ($result['message'] ?? '')), 'finished_at' => (string) ($result['finished_at'] ?? date('c'))];
            if (($state['pending'][$id]['job'] ?? '') === (string) $result['job']) { unset($state['pending'][$id]); }
            @unlink($file); $changed = true;
        }
        if ($changed) { $state['results'] = array_slice($state['results'], -80, null, true); $this->writeState($state); }
    }

    private function catalogRaw(): array
    {
        return [
            ['id'=>'php','name'=>'PHP','description'=>'Runtime PHP cho website và công cụ hệ thống.','command'=>'php','package'=>'php','group'=>'Web','protected'=>true], ['id'=>'nginx','name'=>'Nginx','description'=>'Web server và reverse proxy nhẹ.','command'=>'nginx','package'=>'nginx','group'=>'Web','protected'=>true], ['id'=>'mariadb','name'=>'MariaDB','description'=>'Máy chủ cơ sở dữ liệu tương thích MySQL.','command'=>'mariadbd','package'=>'mariadb','group'=>'Database','protected'=>true], ['id'=>'composer','name'=>'Composer','description'=>'Trình quản lý thư viện PHP.','command'=>'composer','package'=>'composer','group'=>'Development'], ['id'=>'nodejs','name'=>'Node.js LTS','description'=>'Runtime JavaScript và npm.','command'=>'node','package'=>'nodejs-lts','group'=>'Development'], ['id'=>'python','name'=>'Python','description'=>'Runtime Python và pip.','command'=>'python','package'=>'python','group'=>'Development'], ['id'=>'git','name'=>'Git','description'=>'Quản lý mã nguồn.','command'=>'git','package'=>'git','group'=>'Development'], ['id'=>'redis','name'=>'Redis','description'=>'Bộ nhớ đệm và hàng đợi tốc độ cao.','command'=>'redis-server','package'=>'redis','group'=>'Database'], ['id'=>'postgresql','name'=>'PostgreSQL','description'=>'Cơ sở dữ liệu quan hệ nâng cao.','command'=>'postgres','package'=>'postgresql','group'=>'Database'], ['id'=>'openssh','name'=>'OpenSSH','description'=>'SSH server và client.','command'=>'sshd','package'=>'openssh','group'=>'Network'], ['id'=>'cloudflared','name'=>'Cloudflared','description'=>'Cloudflare Tunnel client.','command'=>'cloudflared','package'=>'cloudflared','group'=>'Network'], ['id'=>'curl','name'=>'cURL','description'=>'HTTP client và trình tải dữ liệu.','command'=>'curl','package'=>'curl','group'=>'Utilities','protected'=>true], ['id'=>'wget','name'=>'Wget','description'=>'Tải tệp qua HTTP/HTTPS.','command'=>'wget','package'=>'wget','group'=>'Utilities'], ['id'=>'jq','name'=>'jq','description'=>'Xử lý JSON bằng dòng lệnh.','command'=>'jq','package'=>'jq','group'=>'Utilities'], ['id'=>'nano','name'=>'Nano','description'=>'Trình sửa văn bản terminal.','command'=>'nano','package'=>'nano','group'=>'Utilities'], ['id'=>'ffmpeg','name'=>'FFmpeg','description'=>'Xử lý âm thanh và video.','command'=>'ffmpeg','package'=>'ffmpeg','group'=>'Media'], ['id'=>'imagemagick','name'=>'ImageMagick','description'=>'Xử lý và chuyển đổi hình ảnh.','command'=>'magick','package'=>'imagemagick','group'=>'Media'], ['id'=>'termux-api','name'=>'Termux:API CLI','description'=>'Đọc pin, Wi-Fi và cảm biến Android.','command'=>'termux-battery-status','package'=>'termux-api','group'=>'Android'],
        ];
    }

    private function find(string $id): ?array { foreach ($this->catalogRaw() as $item) { if ($item['id'] === $id) { return $item; } } return null; }
    private function commandExists(string $command): bool { exec('command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1', $unused, $code); return $code === 0; }
    private function version(string $command): string { $v = trim((string) shell_exec($command . ' 2>/dev/null | head -n1')); return function_exists('mb_substr') ? mb_substr($v, 0, 100) : substr($v, 0, 100); }
    private function resultLabel(mixed $result): string { if (is_string($result)) { return $result; } if (!is_array($result)) { return ''; } $time = (string) ($result['finished_at'] ?? ''); return (!empty($result['ok']) ? 'Thành công' : 'Thất bại') . ($time !== '' ? ' · ' . date('d/m/Y H:i', strtotime($time) ?: time()) : '') . ' · ' . $this->sanitize((string) ($result['message'] ?? '')); }
    private function bound(string $current, string $chunk): string { $text = $current . $chunk; return strlen($text) > 8192 ? substr($text, -8192) : $text; }
    private function sanitize(string $text): string { $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? ''; $text = preg_replace('/\s+/', ' ', trim($text)) ?? ''; return function_exists('mb_substr') ? mb_substr($text, 0, 900) : substr($text, 0, 900); }
    private function log(string $line): void { @file_put_contents($this->logFile, '[' . date('Y-m-d H:i:s') . '] ' . $this->sanitize($line) . PHP_EOL, FILE_APPEND | LOCK_EX); @chmod($this->logFile, 0600); }
    private function readState(): array { $d = @json_decode((string) @file_get_contents($this->stateFile), true); $d = is_array($d) ? $d : []; $d['pending'] = is_array($d['pending'] ?? null) ? $d['pending'] : []; $d['results'] = is_array($d['results'] ?? null) ? $d['results'] : []; return $d; }
    private function writeState(array $state): void { $this->writeJson($this->stateFile, $state); }
    private function writeJson(string $path, array $data): void { $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp'; $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); if (!is_string($json) || file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) { throw new RuntimeException('Không thể lưu trạng thái Runtime Package.'); } @chmod($tmp, 0600); if (!@rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('Không thể hoàn tất ghi trạng thái Runtime Package.'); } @chmod($path, 0600); }
}
