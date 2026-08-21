<?php
declare(strict_types=1);

/**
 * V15.4.4 — Resource Monitor hoàn chỉnh, chống crash và chống SELinux.
 *
 * Nguyên tắc:
 *  1. Không dùng proc_open/pcntl (dễ treo trên Android) — chỉ file_get_contents
 *     với context timeout + shell_exec (có sẵn timeout mặc định của PHP) + set_time_limit ngắn.
 *  2. Mọi hàm lấy dữ liệu đều try/catch riêng — một nguồn lỗi không làm sập cả trang.
 *  3. Cache invalidated khi app version đổi — tránh trang hiển thị số 0 từ cache bản cũ.
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
        // Cache invalidated khi version app đổi (bản cũ đã có thể không có 'device')
        $maxAge = $force ? 0 : 12;
        $cached = $this->readFreshCache($maxAge);
        if ($cached !== null && isset($cached['details']['memory_total_mb']) && (int)$cached['details']['memory_total_mb'] > 0) {
            return $cached;
        }

        $out = [];
        try {
            $m = $this->system->metrics();
            $row = [
                'time' => time(),
                'memory' => (int)($m['memory_percent'] ?? 0),
                'storage' => (int)($m['storage_percent'] ?? 0),
                'load' => (float)($m['load_1m'] ?? 0),
            ];

            $rows = $this->history();
            $last = empty($rows) ? 0 : (int)(end($rows)['time'] ?? 0);
            if ($last === 0 || time() - $last >= 30) {
                $rows[] = $row;
                $rows = array_slice($rows, -288);
                @file_put_contents($this->file, json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);
            }

            $out = [
                'current' => $row,
                'history' => $rows,
                'services' => $this->safeServices(),
                'details' => [
                    'battery' => $this->safeBattery(),
                    'temperature' => $this->safeTemperature(),
                    'network' => $this->safeNetwork(),
                    'processes' => $this->safeProcessCount(),
                    'memory_used_mb' => (int)($m['memory_used_mb'] ?? 0),
                    'memory_total_mb' => (int)($m['memory_total_mb'] ?? 0),
                    'storage_used_gb' => (float)($m['storage_used_gb'] ?? 0),
                    'storage_total_gb' => (float)($m['storage_total_gb'] ?? 0),
                    'uptime' => (string)($m['uptime'] ?? ''),
                    'uptime_seconds' => $this->safeUptimeSeconds(),
                    'architecture' => (string)($m['architecture'] ?? ''),
                    'php_version' => (string)($m['php_version'] ?? PHP_VERSION),
                    'hostname' => (string)($m['hostname'] ?? 'Android'),
                ],
                'device' => $this->safeDeviceInfo(),
            ];
        } catch (\Throwable $e) {
            // Không bao giờ crash toàn trang — trả số liệu tối thiểu
            $out = [
                'current' => ['time' => time(), 'memory' => 0, 'storage' => 0, 'load' => 0.0],
                'history' => $this->history(),
                'services' => [],
                'details' => [
                    'battery' => ['percentage' => null, 'status' => 'Lỗi đọc dữ liệu', 'health' => '', 'temperature' => null, 'current' => ''],
                    'temperature' => null,
                    'network' => ['rx_mb' => 0.0, 'tx_mb' => 0.0, 'rx_bytes' => 0, 'tx_bytes' => 0],
                    'processes' => 0,
                    'memory_used_mb' => 0, 'memory_total_mb' => 0,
                    'storage_used_gb' => 0.0, 'storage_total_gb' => 0.0,
                    'uptime' => '', 'uptime_seconds' => 0,
                    'architecture' => php_uname('m'), 'php_version' => PHP_VERSION, 'hostname' => 'Android',
                ],
                'device' => ['model' => 'Không xác định', 'android_version' => '', 'kernel' => php_uname('s') . ' ' . php_uname('r'), 'api' => ''],
            ];
        }

        @file_put_contents($this->cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $out;
    }

    public function history(): array
    {
        try {
            $d = @json_decode((string)@file_get_contents($this->file), true);
            return is_array($d) ? $d : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function readFreshCache(int $maxAge): ?array
    {
        try {
            if (!is_file($this->cacheFile)) {
                return null;
            }
            $mtime = @filemtime($this->cacheFile);
            if (!$mtime || time() - $mtime > max(1, $maxAge)) {
                return null;
            }
            $data = @json_decode((string)@file_get_contents($this->cacheFile), true);
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeServices(): array
    {
        try {
            return $this->system->serviceStatus();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Thông tin thiết bị Android (model, Android version, kernel, API level). */
    private function safeDeviceInfo(): array
    {
        try {
            $device = ['model' => 'Không xác định', 'android_version' => '', 'kernel' => PHP_OS . ' ' . php_uname('r'), 'api' => ''];

            // getprop — lệnh chuẩn Android, nhanh, không cần quyền đặc biệt
            $getprop = static function (string $key): string {
                $r = @shell_exec('getprop ' . escapeshellarg($key) . ' 2>/dev/null');
                return is_string($r) ? trim($r) : '';
            };

            $model = $getprop('ro.product.model') ?: $getprop('ro.product.brand');
            $android = $getprop('ro.build.version.release');
            if ($model !== '') {
                $device['model'] = $model;
            }
            if ($android !== '') {
                $device['android_version'] = $android;
                $device['api'] = $getprop('ro.build.version.sdk');
            }

            // Fallback non-Android
            if ($device['android_version'] === '' && is_file('/etc/os-release')) {
                $os = @parse_ini_file('/etc/os-release');
                if (isset($os['PRETTY_NAME'])) {
                    $device['model'] = $os['PRETTY_NAME'];
                }
            }

            return $device;
        } catch (\Throwable) {
            return ['model' => 'Không xác định', 'android_version' => '', 'kernel' => '', 'api' => ''];
        }
    }

    /** Pin — Termux:API hoặc trạng thái rõ ràng. */
    private function safeBattery(): array
    {
        $unknown = ['percentage' => null, 'status' => 'Không khả dụng', 'health' => '', 'temperature' => null, 'current' => ''];
        try {
            if (@shell_exec('command -v termux-battery-status 2>/dev/null') === null || trim((string)@shell_exec('command -v termux-battery-status 2>/dev/null')) === '') {
                return ['percentage' => null, 'status' => 'Chưa cài Termux:API', 'health' => '', 'temperature' => null, 'current' => ''];
            }

            $oldLimit = ini_get('max_execution_time');
            @set_time_limit(4);
            $output = @shell_exec('termux-battery-status 2>/dev/null');
            @set_time_limit((int)$oldLimit ?: 30);
            if (!is_string($output) || trim($output) === '') {
                return $unknown;
            }

            $d = @json_decode($output, true);
            if (!is_array($d)) {
                return $unknown;
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
        } catch (\Throwable) {
            return $unknown;
        }
    }

    /** Nhiệt độ CPU — đọc nhiệt nhất trong thermal zones, fallback cat khi file API bị chặn. */
    private function safeTemperature(): ?float
    {
        try {
            $files = @glob('/sys/class/thermal/thermal_zone*/temp') ?: [];
            $candidates = [];
            foreach ($files as $f) {
                $raw = '';
                // Cách 1: file API trực tiếp (nhanh nhất)
                $tmp = @file_get_contents($f);
                if (is_string($tmp)) {
                    $raw = $tmp;
                }
                // Cách 2: shell cat (vượt qua SELinux file-API restriction)
                if ($raw === '') {
                    $tmp = @shell_exec('cat ' . escapeshellarg($f) . ' 2>/dev/null');
                    if (is_string($tmp)) {
                        $raw = $tmp;
                    }
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
            return $candidates !== [] ? round(max($candidates), 1) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Network RX/TX thật, bỏ loopback; fallback cat khi file API bị chặn. */
    private function safeNetwork(): array
    {
        try {
            $rx = 0;
            $tx = 0;
            foreach (@glob('/sys/class/net/*/statistics/rx_bytes') ?: [] as $f) {
                if (str_contains($f, '/lo/')) {
                    continue;
                }
                $txf = str_replace('rx_bytes', 'tx_bytes', $f);
                $rv = $this->readCounter($f);
                $tv = $this->readCounter($txf);
                if ($rv === 0 && $tv === 0 && !is_file($f)) {
                    continue;
                }
                $rx += $rv;
                $tx += $tv;
            }
            return ['rx_mb' => round($rx / 1048576, 1), 'tx_mb' => round($tx / 1048576, 1), 'rx_bytes' => $rx, 'tx_bytes' => $tx];
        } catch (\Throwable) {
            return ['rx_mb' => 0.0, 'tx_mb' => 0.0, 'rx_bytes' => 0, 'tx_bytes' => 0];
        }
    }

    private function readCounter(string $path): int
    {
        $raw = '';
        $tmp = @file_get_contents($path);
        if (is_string($tmp)) {
            $raw = $tmp;
        }
        if ($raw === '') {
            $tmp = @shell_exec('cat ' . escapeshellarg($path) . ' 2>/dev/null');
            if (is_string($tmp)) {
                $raw = $tmp;
            }
        }
        return is_numeric(trim($raw)) ? (int)trim($raw) : 0;
    }

    /** Số tiến trình — glob /proc trước, fallback ls (SELinux). */
    private function safeProcessCount(): int
    {
        try {
            $dirs = @glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [];
            if ($dirs !== []) {
                return count($dirs);
            }
            $out = @shell_exec('ls -d /proc/[0-9]* 2>/dev/null | wc -l');
            if (is_string($out) && is_numeric(trim($out))) {
                return max(0, (int)trim($out));
            }
            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Uptime thiết bị (giây). */
    private function safeUptimeSeconds(): int
    {
        try {
            // Đồng bộ với SystemService::androidUptimeSeconds nhưng có fallback shell
            $raw = @file_get_contents('/proc/uptime');
            if (is_string($raw) && preg_match('/^([0-9.]+)/', trim($raw), $m)) {
                return max(0, (int)floor((float)$m[1]));
            }
            $raw = @shell_exec('cat /proc/uptime 2>/dev/null');
            if (is_string($raw) && preg_match('/^([0-9.]+)/', trim($raw), $m)) {
                return max(0, (int)floor((float)$m[1]));
            }
            $stat = @shell_exec('grep -m1 btime /proc/stat 2>/dev/null');
            if (is_string($stat) && preg_match('/btime\s+(\d+)/', $stat, $m)) {
                return max(0, time() - (int)$m[1]);
            }
            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
