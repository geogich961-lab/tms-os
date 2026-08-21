<?php
declare(strict_types=1);

/**
 * V15.4.3 — Resource Monitor hoàn chỉnh cho Android/Termux.
 * Lấy dữ liệu thật từ hệ thống theo thứ tự ưu tiên:
 *  1) Đọc trực tiếp /proc, /sys (nhanh, không shell)
 *  2) CLI fallback (cat, free, df, uptime, getprop) khi SELinux chặn đọc file
 *  3) Termux:API cho pin (termux-battery-status)
 * Luôn trả giá trị thật hoặc trạng thái rõ ràng — không bao giờ im lặng về 0.
 */
final class MonitoringService
{
    private string $file;
    private string $cacheFile;

    public function __construct(private SystemService $system)
    {
        $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        @mkdir($home . '/.tms-os', 0700, true);
        $this->file = $home . '/.tms-os/monitoring.json';
        $this->cacheFile = $home . '/.tms-os/monitoring-current.json';
    }

    public function snapshot(bool $force = false): array
    {
        if (!$force) {
            $cached = $this->readFreshCache(12);
            if ($cached !== null) {
                return $cached;
            }
        }

        $m = $this->system->metrics();
        $row = [
            'time' => time(),
            'memory' => (int)$m['memory_percent'],
            'storage' => (int)$m['storage_percent'],
            'load' => (float)$m['load_1m'],
        ];

        $rows = $this->history();
        $last = empty($rows) ? 0 : (int)(end($rows)['time'] ?? 0);
        if ($last === 0 || time() - $last >= 30) {
            $rows[] = $row;
            $rows = array_slice($rows, -288);
            @file_put_contents($this->file, json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }

        $net = $this->network();
        $snapshot = [
            'current' => $row,
            'history' => $rows,
            'services' => $this->system->serviceStatus(),
            'details' => [
                'battery' => $this->battery(),
                'temperature' => $this->temperature(),
                'network' => $net,
                'processes' => $this->processCount(),
                'memory_used_mb' => $m['memory_used_mb'],
                'memory_total_mb' => $m['memory_total_mb'],
                'storage_used_gb' => $m['storage_used_gb'],
                'storage_total_gb' => $m['storage_total_gb'],
                'uptime' => $m['uptime'],
                'uptime_seconds' => $this->uptimeSeconds(),
                'architecture' => $m['architecture'],
                'php_version' => $m['php_version'],
                'hostname' => $m['hostname'] ?? 'Android',
            ],
            'device' => $this->deviceInfo(),
        ];

        @file_put_contents($this->cacheFile, json_encode($snapshot, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $snapshot;
    }

    public function history(): array
    {
        $d = @json_decode((string)@file_get_contents($this->file), true);
        return is_array($d) ? $d : [];
    }

    private function readFreshCache(int $maxAge): ?array
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }
        $mtime = @filemtime($this->cacheFile);
        if (!$mtime || time() - $mtime > $maxAge) {
            return null;
        }
        $data = @json_decode((string)@file_get_contents($this->cacheFile), true);
        return is_array($data) ? $data : null;
    }

    /** Thông tin thiết bị Android (model, phiên bản Android, kernel). */
    private function deviceInfo(): array
    {
        $getprop = static function (string $key): string {
            $r = shell_exec('getprop ' . escapeshellarg($key) . ' 2>/dev/null');
            return is_string($r) ? trim($r) : '';
        };

        $model = $getprop('ro.product.model') ?: $getprop('ro.product.brand');
        $android = $getprop('ro.build.version.release');
        $kernel = PHP_OS . ' ' . php_uname('r');

        // Fallback khi không có getprop (non-Android / sandbox)
        if ($model === '' && $android === '') {
            if (is_file('/etc/os-release')) {
                $os = @parse_ini_file('/etc/os-release');
                $model = $os['PRETTY_NAME'] ?? 'Server';
            }
            $kernel = PHP_OS . ' ' . php_uname('r') . ' ' . php_uname('m');
        }

        return [
            'model' => $model ?: 'Không xác định',
            'android_version' => $android ?: '',
            'kernel' => $kernel,
            'api' => $getprop('ro.build.version.sdk') ?: '',
        ];
    }

    /** Pin — Termux:API hoặc "Chưa cài". Không bao giờ giả số 0. */
    private function battery(): array
    {
        if (!$this->commandExists('termux-battery-status')) {
            return ['percentage' => null, 'status' => 'Chưa cài Termux:API', 'health' => '', 'temperature' => null, 'current' => ''];
        }

        $result = $this->runWithTimeout(['termux-battery-status'], 2.0);
        if (!$result['ok']) {
            return ['percentage' => null, 'status' => 'Termux:API không phản hồi', 'health' => '', 'temperature' => null, 'current' => ''];
        }

        $d = @json_decode($result['output'], true);
        if (!is_array($d)) {
            return ['percentage' => null, 'status' => 'Không đọc được trạng thái pin', 'health' => '', 'temperature' => null, 'current' => ''];
        }

        $info = ['current' => '', 'temperature' => null];
        if (isset($d['current'])) {
            $info['current'] = rtrim((string)($d['current'] ?? ''), 'A') . ' mA';
        }
        if (isset($d['temperature']) && is_numeric($d['temperature'])) {
            $info['temperature'] = round((float)$d['temperature'], 1);
        }

        return array_merge($info, [
            'percentage' => $d['percentage'] ?? null,
            'status' => (string)($d['status'] ?? ''),
            'health' => (string)($d['health'] ?? ''),
        ]);
    }

    /** Nhiệt độ CPU — /sys/class/thermal trước, fallback CLI cat (tránh SELinux block file_get_contents). */
    private function temperature(): ?float
    {
        $files = glob('/sys/class/thermal/thermal_zone*/temp') ?: [];
        $candidates = [];

        foreach ($files as $f) {
            $raw = (string)@shell_exec('cat ' . escapeshellarg($f) . ' 2>/dev/null');
            if ($raw === '' && is_readable($f)) {
                $raw = (string)@file_get_contents($f);
            }
            if ($raw === '') {
                continue;
            }
            $v = (float)trim($raw);
            if ($v > 1000) {
                $v /= 1000;
            }
            if ($v > 5 && $v < 120) {
                $candidates[] = $v;
            }
        }

        if ($candidates === []) {
            return null;
        }

        // Dùng giá trị lớn nhất (CPU thường là zone nóng nhất)
        return round(max($candidates), 1);
    }

    /** Network RX/TX thật từ /sys/class/net, bỏ loopback; fallback CLI cat. */
    private function network(): array
    {
        $read = static function (string $path): int {
            $raw = (string)@shell_exec('cat ' . escapeshellarg($path) . ' 2>/dev/null');
            if ($raw === '' && is_readable($path)) {
                $raw = (string)@file_get_contents($path);
            }
            return is_numeric(trim($raw)) ? (int)trim($raw) : 0;
        };

        $rx = 0;
        $tx = 0;
        foreach (glob('/sys/class/net/*/statistics/rx_bytes') ?: [] as $f) {
            if (str_contains($f, '/lo/')) {
                continue;
            }
            $rx += $read($f);
            $tx += $read(str_replace('rx_bytes', 'tx_bytes', $f));
        }

        return ['rx_mb' => round($rx / 1048576, 1), 'tx_mb' => round($tx / 1048576, 1), 'rx_bytes' => $rx, 'tx_bytes' => $tx];
    }

    /** Số tiến trình — glob /proc trước, fallback lệnh ps/pgrep (SELinux). */
    private function processCount(): int
    {
        $dirs = glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [];
        if ($dirs !== []) {
            return count($dirs);
        }

        $out = (string)@shell_exec('ls -d /proc/[0-9]* 2>/dev/null | wc -l');
        if (is_numeric(trim($out))) {
            return max(0, (int)trim($out));
        }

        return 0;
    }

    /** Uptime thiết bị (giây) — nhất quán với SystemService. */
    private function uptimeSeconds(): int
    {
        $raw = (string)@shell_exec('cat /proc/uptime 2>/dev/null');
        if ($raw !== '' && preg_match('/^([0-9.]+)/', trim($raw), $m)) {
            return max(0, (int)floor((float)$m[1]));
        }

        $raw = (string)@file_get_contents('/proc/uptime');
        if ($raw !== '' && preg_match('/^([0-9.]+)/', trim($raw), $m)) {
            return max(0, (int)floor((float)$m[1]));
        }

        $stat = (string)@shell_exec('grep -m1 btime /proc/stat 2>/dev/null');
        if ($stat !== '' && preg_match('/btime\s+(\d+)/', $stat, $m)) {
            return max(0, time() - (int)$m[1]);
        }

        return 0;
    }

    private function commandExists(string $command): bool
    {
        $path = getenv('PATH') ?: '';
        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            if ($dir !== '' && is_file(rtrim($dir, '/') . '/' . $command) && is_executable(rtrim($dir, '/') . '/' . $command)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{ok:bool,output:string} */
    private function runWithTimeout(array $command, float $seconds): array
    {
        if (!function_exists('proc_open')) {
            return ['ok' => false, 'output' => ''];
        }

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = @proc_open($command, $descriptor, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return ['ok' => false, 'output' => ''];
        }

        @fclose($pipes[0]);
        @stream_set_blocking($pipes[1], false);
        @stream_set_blocking($pipes[2], false);
        $started = microtime(true);
        $stdout = '';
        $stderr = '';
        $timedOut = false;

        while (true) {
            $stdout .= (string)@stream_get_contents($pipes[1]);
            $stderr .= (string)@stream_get_contents($pipes[2]);
            $status = @proc_get_status($process);
            if (!is_array($status) || !$status['running']) {
                break;
            }
            if (microtime(true) - $started >= $seconds) {
                $timedOut = true;
                @proc_terminate($process, 9);
                break;
            }
            usleep(20000);
        }

        $stdout .= (string)@stream_get_contents($pipes[1]);
        $stderr .= (string)@stream_get_contents($pipes[2]);
        @fclose($pipes[1]);
        @fclose($pipes[2]);
        $code = @proc_close($process);

        return [
            'ok' => !$timedOut && ($code === 0 || $code === -1) && trim($stdout) !== '',
            'output' => trim($stdout !== '' ? $stdout : $stderr),
        ];
    }
}
