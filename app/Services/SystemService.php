<?php
declare(strict_types=1);

final class SystemService
{
    private string $home;
    private string $prefix;
    private string $runtimeFile;
    private UnifiedSystemCoreService $core;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->prefix = getenv('PREFIX') ?: '/data/data/com.termux/files/usr';
        $this->runtimeFile = $this->home . '/.tms-os/runtime_started_at';
        $this->core = new UnifiedSystemCoreService();

        if (!is_file($this->runtimeFile)) {
            @mkdir(dirname($this->runtimeFile), 0700, true);
            @file_put_contents($this->runtimeFile, (string)time(), LOCK_EX);
        }
    }

    // V14.1.5: trạng thái dịch vụ theo danh mục động — MariaDB chỉ xuất hiện khi chế độ database là mariadb.
    public function serviceStatus(): array
    {
        $defs = $this->core->definitions();
        $all = $this->core->all(false);
        $status = [];
        foreach (array_keys($defs) as $id) { if (isset($all[$id])) { $status[$defs[$id]['name']] = (bool)$all[$id]['running']; } }
        return $status;
    }

    public function metrics(): array
    {
        $total = @disk_total_space($this->home) ?: 0;
        $free = @disk_free_space($this->home) ?: 0;
        $used = max(0, $total - $free);
        $memory = $this->memoryInfo();

        return [
            'storage_used_gb' => round($used / 1073741824, 2),
            'storage_total_gb' => round($total / 1073741824, 2),
            'storage_percent' => $total > 0 ? (int)round(($used / $total) * 100) : 0,
            'memory_used_mb' => $memory['used_mb'],
            'memory_total_mb' => $memory['total_mb'],
            'memory_percent' => $memory['percent'],
            'load_1m' => round($this->loadAverage(), 2),
            'php_version' => PHP_VERSION,
            'architecture' => php_uname('m'),
            'hostname' => gethostname() ?: 'Android',
            'time' => date('d/m/Y H:i:s'),
            'uptime' => $this->uptime(),
            'uptime_type' => $this->androidUptimeSeconds() > 0 ? 'Thiết bị' : 'TMS OS',
        ];
    }

    public function action(string $action): array
    {
        // V14.1.5: hành động dịch vụ chỉ áp dụng với dịch vụ trong danh mục động (SQLite mode không có MariaDB).
        $defs = $this->core->definitions();
        $map = [
            'reload_nginx' => ['nginx','restart'],
            'restart_nginx' => ['nginx','restart'],
            'stop_nginx' => ['nginx','stop'],
            'restart_php' => ['php','restart'],
            'stop_php' => ['php','stop'],
        ];
        foreach (array_keys($defs) as $id) {
            if ($id === 'redis') continue;
            $map['restart_'.$id] = [$id,'restart'];
            $map['stop_'.$id] = [$id,'stop'];
            if ($id !== 'nginx' && $id !== 'php') { $map['start_'.$id] = [$id,'start']; }
        }
        if($action==='start_all'){$r=$this->core->run('bash '.escapeshellarg($this->home.'/tms-os/scripts/start-tms.sh'),60);return ['ok'=>$r['code']===0,'message'=>$r['output']?:'Hoàn tất.'];}
        if($action==='backup'){$r=$this->core->run('bash '.escapeshellarg($this->home.'/tms-os/scripts/quick-backup.sh'),120);return ['ok'=>$r['code']===0,'message'=>$r['output']?:'Hoàn tất.'];}
        if(!isset($map[$action]))return ['ok'=>false,'message'=>'Thao tác không hợp lệ.'];
        [$id,$verb]=$map[$action];
        if($id==='php'&&$verb==='stop')return ['ok'=>false,'message'=>'Không thể dừng PHP từ giao diện web.'];
        $r=$this->core->core($id,$verb);
        return ['ok'=>$r['code']===0,'message'=>$r['output']?:($r['code']===0?'Hoàn tất và đã xác minh trạng thái.':'Thao tác thất bại.')];
    }


    private function memoryInfo(): array
    {
        $text = @file_get_contents('/proc/meminfo') ?: '';
        preg_match('/MemTotal:\s+(\d+)/', $text, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+)/', $text, $availableMatch);
        $totalKb = (int)($totalMatch[1] ?? 0);
        $availableKb = (int)($availableMatch[1] ?? 0);
        $usedKb = max(0, $totalKb - $availableKb);
        return [
            'total_mb' => (int)round($totalKb / 1024),
            'used_mb' => (int)round($usedKb / 1024),
            'percent' => $totalKb > 0 ? (int)round(($usedKb / $totalKb) * 100) : 0,
        ];
    }

    private function loadAverage(): float
    {
        $raw = @file_get_contents('/proc/loadavg');
        if (is_string($raw) && preg_match('/^([0-9.]+)/', trim($raw), $m)) {
            return (float)$m[1];
        }
        return 0.0;
    }

    private function androidUptimeSeconds(): int
    {
        $raw = @file_get_contents('/proc/uptime');
        if (is_string($raw) && preg_match('/^([0-9.]+)/', trim($raw), $m)) {
            return max(0, (int)floor((float)$m[1]));
        }
        $stat = @file_get_contents('/proc/stat');
        if (is_string($stat) && preg_match('/^btime\s+(\d+)/m', $stat, $m)) {
            return max(0, time() - (int)$m[1]);
        }
        return 0;
    }

    private function uptime(): string
    {
        $seconds = $this->androidUptimeSeconds();
        if ($seconds <= 0) {
            $started = (int)trim((string)@file_get_contents($this->runtimeFile));
            $seconds = $started > 0 ? max(0, time() - $started) : 0;
        }
        if ($seconds <= 0) {
            return 'Vừa khởi động';
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($days > 0) $parts[] = $days . ' ngày';
        if ($hours > 0) $parts[] = $hours . ' giờ';
        $parts[] = $minutes . ' phút';
        return implode(' ', $parts);
    }
}
