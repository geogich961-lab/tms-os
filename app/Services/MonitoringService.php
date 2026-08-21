<?php
declare(strict_types=1);

/**
 * V15.4.7 — Resource Monitor hoàn thiện: quét đa card mạng, tối ưu nhiệt độ và pin.
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

            $battery = $this->safeBattery();
            $temp = $this->safeTemperature();
            // Fallback nhiệt độ CPU sang nhiệt độ pin nếu không đọc được cảm biến nhiệt trực tiếp
            if ($temp === null && isset($battery['temperature'])) {
                $temp = $battery['temperature'];
            }

            $out = [
                'current' => $row,
                'history' => $rows,
                'services' => $this->safeServices(),
                'details' => [
                    'battery' => $battery,
                    'temperature' => $temp,
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
            if (!is_file($this->cacheFile)) return null;
            $mtime = @filemtime($this->cacheFile);
            if (!$mtime || time() - $mtime > max(1, $maxAge)) return null;
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

    private function safeDeviceInfo(): array
    {
        try {
            $device = ['model' => 'Không xác định', 'android_version' => '', 'kernel' => php_uname('s') . ' ' . php_uname('r'), 'api' => ''];
            $getprop = static function (string $key): string {
                $r = @shell_exec('timeout 1s getprop ' . escapeshellarg($key) . ' 2>/dev/null');
                return is_string($r) ? trim($r) : '';
            };
            
            $model = $getprop('ro.product.model');
            $brand = $getprop('ro.product.brand');
            $manufacturer = $getprop('ro.product.manufacturer');
            
            $finalModel = '';
            if ($brand !== '') $finalModel .= ucfirst($brand) . ' ';
            if ($model !== '') $finalModel .= $model;
            if ($finalModel === '' && $manufacturer !== '') $finalModel = ucfirst($manufacturer);
            
            $android = $getprop('ro.build.version.release');
            if ($finalModel !== '') $device['model'] = trim($finalModel);
            if ($android !== '') {
                $device['android_version'] = $android;
                $device['api'] = $getprop('ro.build.version.sdk');
            }
            return $device;
        } catch (\Throwable) {
            return ['model' => 'Không xác định', 'android_version' => '', 'kernel' => php_uname('s') . ' ' . php_uname('r'), 'api' => ''];
        }
    }

    private function safeBattery(): array
    {
        $unknown = ['percentage' => null, 'status' => 'Không khả dụng', 'health' => '', 'temperature' => null, 'current' => ''];
        try {
            $hasCmd = @shell_exec('timeout 1s command -v termux-battery-status 2>/dev/null');
            if (empty($hasCmd)) {
                return ['percentage' => null, 'status' => 'Chưa cài Termux:API', 'health' => '', 'temperature' => null, 'current' => ''];
            }
            $output = @shell_exec('timeout 2s termux-battery-status 2>/dev/null');
            if (!is_string($output) || trim($output) === '') return $unknown;
            $d = @json_decode($output, true);
            if (!is_array($d)) return $unknown;
            
            $info = ['current' => '', 'temperature' => null];
            if (isset($d['current'])) {
                $c = (int)$d['current'];
                if (abs($c) > 10000) $c = (int)round($c / 1000);
                $info['current'] = $c . ' mA';
            }
            if (isset($d['temperature']) && is_numeric($d['temperature'])) $info['temperature'] = round((float)$d['temperature'], 1);
            
            return array_merge($info, [
                'percentage' => $d['percentage'] ?? null,
                'status' => (string)($d['status'] ?? ''),
                'health' => (string)($d['health'] ?? ''),
            ]);
        } catch (\Throwable) {
            return $unknown;
        }
    }

    private function safeTemperature(): ?float
    {
        try {
            $files = @glob('/sys/class/thermal/thermal_zone*/temp') ?: [];
            $candidates = [];
            foreach ($files as $f) {
                $raw = @shell_exec('timeout 1s cat ' . escapeshellarg($f) . ' 2>/dev/null');
                if (empty($raw)) continue;
                $v = (float)trim($raw);
                if ($v > 1000) $v /= 1000;
                if ($v > 5 && $v < 120) $candidates[] = $v;
            }
            return $candidates !== [] ? round(max($candidates), 1) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeNetwork(): array
    {
        try {
            $rx = 0; $tx = 0;
            // Cách 1: Quét /proc/net/dev (Chính xác và bao quát nhất trên Android)
            $dev = @shell_exec('timeout 1s cat /proc/net/dev 2>/dev/null');
            if (is_string($dev)) {
                $lines = explode("\n", $dev);
                foreach ($lines as $line) {
                    if (!str_contains($line, ':')) continue;
                    $parts = preg_split('/\s+/', trim($line));
                    $iface = str_replace(':', '', $parts[0]);
                    if (in_array($iface, ['lo', 'sit0', 'ip6tnl0'])) continue;
                    if (isset($parts[1])) $rx += (int)$parts[1];
                    if (isset($parts[9])) $tx += (int)$parts[9];
                }
            }
            
            // Cách 2: Fallback /sys/class/net nếu cách 1 không ra kết quả
            if ($rx === 0) {
                foreach (@glob('/sys/class/net/*/statistics/rx_bytes') ?: [] as $f) {
                    if (str_contains($f, '/lo/')) continue;
                    $txf = str_replace('rx_bytes', 'tx_bytes', $f);
                    $rx += $this->readCounter($f);
                    $tx += $this->readCounter($txf);
                }
            }
            
            return ['rx_mb' => round($rx / 1048576, 1), 'tx_mb' => round($tx / 1048576, 1), 'rx_bytes' => $rx, 'tx_bytes' => $tx];
        } catch (\Throwable) {
            return ['rx_mb' => 0.0, 'tx_mb' => 0.0, 'rx_bytes' => 0, 'tx_bytes' => 0];
        }
    }

    private function readCounter(string $path): int
    {
        $raw = @shell_exec('timeout 1s cat ' . escapeshellarg($path) . ' 2>/dev/null');
        return is_numeric(trim((string)$raw)) ? (int)trim((string)$raw) : 0;
    }

    private function safeProcessCount(): int
    {
        try {
            $out = @shell_exec('timeout 1s ls -d /proc/[0-9]* 2>/dev/null | wc -l');
            return is_numeric(trim((string)$out)) ? max(0, (int)trim((string)$out)) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeUptimeSeconds(): int
    {
        try {
            $raw = @shell_exec('timeout 1s cat /proc/uptime 2>/dev/null');
            if (is_string($raw) && preg_match('/^([0-9.]+)/', trim($raw), $m)) return (int)floor((float)$m[1]);
            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
