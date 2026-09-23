<?php
declare(strict_types=1);

final class WebsiteHealthService
{
    private string $home;
    private string $cacheDir;
    private string $workerScript;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->cacheDir = $this->home . '/.tms-os/site-health';
        $this->workerScript = $this->home . '/tms-os/scripts/tms-site-health-worker.php';
        @mkdir($this->cacheDir, 0700, true);
    }

    /**
     * Trả trạng thái an toàn cho panel:
     * - probe static trực tiếp qua Nginx, không đi qua PHP app;
     * - đọc app-health cache;
     * - xếp worker nền nếu cache thiếu/cũ.
     */
    public function status(string $name, int $port, string $root, string $path = '/'): array
    {
        if (!$this->validName($name) || $port < 1 || $port > 65535) {
            return $this->result('offline', 0, false, 0, 'Website hoặc cổng không hợp lệ.');
        }

        $static = $this->probeStatic($port, $root);
        if (($static['status'] ?? '') !== 'healthy') {
            return $static;
        }

        $cache = $this->readCache($name);
        $fresh = $cache !== []
            && (int)($cache['port'] ?? 0) === $port
            && (time() - (int)($cache['checked_ts'] ?? 0)) <= 30;

        if (!$fresh) {
            $this->schedule($name, $port, $path);
        }

        if ($cache !== [] && (int)($cache['port'] ?? 0) === $port) {
            $cache['pending'] = !$fresh;
            if (!$fresh) {
                $cache['message'] = trim((string)($cache['message'] ?? '')) . ' · Đang làm mới app-health nền.';
            }
            return $cache;
        }

        return [
            'status' => 'healthy',
            'http_status' => 0,
            'reachable' => true,
            'latency_ms' => (int)($static['latency_ms'] ?? 0),
            'message' => 'Nginx đang phản hồi; app-health đang được kiểm tra nền.',
            'checked_at' => date('c'),
            'checked_ts' => time(),
            'pending' => true,
        ];
    }

    /** Chỉ worker nền gọi để kiểm tra HTTP app thật. */
    public function probeApplication(int $port, string $path = '/'): array
    {
        if ($port < 1 || $port > 65535) {
            return $this->result('offline', 0, false, 0, 'Port không hợp lệ.');
        }

        $started = microtime(true);
        $errno = 0;
        $error = '';
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 1.5);
        if (!is_resource($socket)) {
            return $this->result('offline', 0, false, $this->latency($started), $error !== '' ? $error : 'Không kết nối được cổng local.');
        }

        stream_set_timeout($socket, 5);
        $path = '/' . ltrim($path, '/');
        $request = "GET {$path} HTTP/1.1\r\nHost: 127.0.0.1:{$port}\r\nConnection: close\r\nUser-Agent: TMS-OS-Health-Worker/17.1\r\nAccept: */*\r\n\r\n";
        @fwrite($socket, $request);
        $statusLine = (string)@fgets($socket, 1024);
        $meta = stream_get_meta_data($socket);
        fclose($socket);

        $code = 0;
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', trim($statusLine), $match)) {
            $code = (int)$match[1];
        }

        if (!empty($meta['timed_out'])) {
            return $this->result('offline', $code, true, $this->latency($started), 'App HTTP health check hết thời gian chờ.');
        }
        if ($code >= 200 && $code < 400) {
            return $this->result('healthy', $code, true, $this->latency($started), 'Website phản hồi bình thường.');
        }
        if ($code >= 400 && $code < 500) {
            return $this->result('warning', $code, true, $this->latency($started), 'Website phản hồi lỗi phía client.');
        }
        if ($code >= 500) {
            return $this->result('error', $code, true, $this->latency($started), 'Website phản hồi lỗi server.');
        }

        return $this->result('warning', 0, true, $this->latency($started), 'Cổng mở nhưng không đọc được HTTP status.');
    }

    public function writeCache(string $name, int $port, array $result): void
    {
        if (!$this->validName($name)) {
            throw new RuntimeException('Tên website health không hợp lệ.');
        }
        $payload = array_merge($result, [
            'name' => $name,
            'port' => $port,
            'checked_ts' => time(),
            'checked_at' => date('c'),
            'pending' => false,
        ]);
        $path = $this->cachePath($name);
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || @file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('Không thể ghi website health cache.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Không thể kích hoạt website health cache.');
        }
    }

    public function workerLockPath(string $name): string
    {
        if (!$this->validName($name)) {
            throw new RuntimeException('Tên website health không hợp lệ.');
        }
        return $this->cacheDir . '/' . $name . '.lock';
    }

    private function probeStatic(int $port, string $root): array
    {
        if ($root === '' || !is_dir($root)) {
            return $this->result('offline', 0, false, 0, 'Document root không tồn tại.');
        }

        $file = rtrim($root, '/') . '/tms-health.txt';
        if (!is_file($file)) {
            @file_put_contents($file, "TMS-OS-OK\n", LOCK_EX);
            @chmod($file, 0600);
        }

        $started = microtime(true);
        $errno = 0;
        $error = '';
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.8);
        if (!is_resource($socket)) {
            return $this->result('offline', 0, false, $this->latency($started), $error !== '' ? $error : 'Không kết nối được Nginx site.');
        }

        stream_set_timeout($socket, 1);
        $request = "GET /tms-health.txt HTTP/1.1\r\nHost: 127.0.0.1:{$port}\r\nConnection: close\r\nUser-Agent: TMS-OS-Safe-Probe/17.1\r\nAccept: text/plain\r\n\r\n";
        @fwrite($socket, $request);
        $statusLine = (string)@fgets($socket, 1024);
        $meta = stream_get_meta_data($socket);
        fclose($socket);

        if (!empty($meta['timed_out'])) {
            return $this->result('offline', 0, true, $this->latency($started), 'Nginx static health check hết thời gian chờ.');
        }

        $code = 0;
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', trim($statusLine), $match)) {
            $code = (int)$match[1];
        }
        if ($code >= 200 && $code < 400) {
            return $this->result('healthy', $code, true, $this->latency($started), 'Nginx site đang phản hồi.');
        }
        return $this->result($code >= 500 ? 'error' : 'warning', $code, true, $this->latency($started), 'Nginx site phản hồi HTTP ' . ($code ?: 'không xác định') . '.');
    }

    private function schedule(string $name, int $port, string $path): void
    {
        if (!is_file($this->workerScript)) {
            return;
        }
        $lock = $this->workerLockPath($name);
        if (is_file($lock) && (time() - (int)@filemtime($lock)) < 15) {
            return;
        }
        @file_put_contents($lock, (string)time(), LOCK_EX);
        @chmod($lock, 0600);

        $cmd = 'nohup php ' . escapeshellarg($this->workerScript)
            . ' ' . escapeshellarg($name)
            . ' ' . escapeshellarg((string)$port)
            . ' ' . escapeshellarg($path)
            . ' >/dev/null 2>&1 < /dev/null &';
        @exec($cmd);
    }

    private function readCache(string $name): array
    {
        if (!$this->validName($name)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($this->cachePath($name)), true);
        return is_array($data) ? $data : [];
    }

    private function cachePath(string $name): string
    {
        return $this->cacheDir . '/' . $name . '.json';
    }

    private function validName(string $name): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name);
    }

    private function latency(float $started): int
    {
        return max(0, (int)round((microtime(true) - $started) * 1000));
    }

    private function result(string $status, int $httpStatus, bool $reachable, int $latencyMs, string $message): array
    {
        return [
            'status' => $status,
            'http_status' => $httpStatus,
            'reachable' => $reachable,
            'latency_ms' => $latencyMs,
            'message' => $message,
            'checked_at' => date('c'),
            'checked_ts' => time(),
            'pending' => false,
        ];
    }
}
