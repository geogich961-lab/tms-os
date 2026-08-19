<?php
declare(strict_types=1);

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

        $snapshot = [
            'current' => $row,
            'history' => $rows,
            'services' => $this->system->serviceStatus(),
            'details' => [
                'battery' => $this->battery(),
                'temperature' => $this->temperature(),
                'network' => $this->network(),
                'processes' => $this->processCount(),
                'memory_used_mb' => $m['memory_used_mb'],
                'memory_total_mb' => $m['memory_total_mb'],
                'storage_used_gb' => $m['storage_used_gb'],
                'storage_total_gb' => $m['storage_total_gb'],
                'uptime' => $m['uptime'],
                'architecture' => $m['architecture'],
                'php_version' => $m['php_version'],
            ],
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

    private function battery(): array
    {
        if (!$this->commandExists('termux-battery-status')) {
            return ['percentage' => null, 'status' => 'Chưa cài Termux:API', 'health' => ''];
        }

        $result = $this->runWithTimeout(['termux-battery-status'], 1.5);
        if (!$result['ok']) {
            return ['percentage' => null, 'status' => 'Termux:API không phản hồi', 'health' => ''];
        }

        $d = @json_decode($result['output'], true);
        return is_array($d)
            ? [
                'percentage' => $d['percentage'] ?? null,
                'status' => (string)($d['status'] ?? ''),
                'health' => (string)($d['health'] ?? ''),
            ]
            : ['percentage' => null, 'status' => 'Không đọc được trạng thái pin', 'health' => ''];
    }

    private function temperature(): ?float
    {
        $files = glob('/sys/class/thermal/thermal_zone*/temp') ?: [];
        foreach (array_slice($files, 0, 32) as $f) {
            $raw = @file_get_contents($f);
            if (!is_string($raw)) {
                continue;
            }
            $v = (float)trim($raw);
            if ($v > 1000) {
                $v /= 1000;
            }
            if ($v > 5 && $v < 120) {
                return round($v, 1);
            }
        }
        return null;
    }

    private function network(): array
    {
        $rx = 0;
        $tx = 0;
        foreach (glob('/sys/class/net/*/statistics/rx_bytes') ?: [] as $f) {
            if (str_contains($f, '/lo/')) {
                continue;
            }
            $rxRaw = @file_get_contents($f);
            $txRaw = @file_get_contents(str_replace('rx_bytes', 'tx_bytes', $f));
            $rx += is_string($rxRaw) ? (int)trim($rxRaw) : 0;
            $tx += is_string($txRaw) ? (int)trim($txRaw) : 0;
        }
        return ['rx_mb' => round($rx / 1048576, 1), 'tx_mb' => round($tx / 1048576, 1)];
    }

    private function processCount(): int
    {
        $count = 0;
        foreach (glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_dir($dir)) {
                $count++;
            }
        }
        return $count;
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
