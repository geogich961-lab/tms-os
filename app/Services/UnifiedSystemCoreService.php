<?php
declare(strict_types=1);

/**
 * TMS OS V13 Unified Core
 * Single source of truth for service discovery, status, PID, metrics and versions.
 */
final class UnifiedSystemCoreService
{
    private string $home;
    private string $prefix;
    private string $coreScript;

    public function __construct()
    {
        $this->home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $this->prefix=getenv('PREFIX')?:'/data/data/com.termux/files/usr';
        $this->coreScript=$this->home.'/tms-os/scripts/tms-service-core.sh';
    }

    public function definitions(): array
    {
        // V14.0.3: MariaDB chỉ xuất hiện trong danh sách dịch vụ khi chế độ database là mariadb.
        $defs = [
            'nginx'=>['name'=>'Nginx','process'=>'nginx','version'=>'nginx -v','log'=>$this->home.'/logs/services/nginx.log'],
            'php'=>['name'=>'PHP Engine','process'=>'php-fpm','version'=>'php -r '.escapeshellarg('echo PHP_VERSION;'),'log'=>$this->home.'/logs/services/php-engine.log'],
            'ssh'=>['name'=>'OpenSSH','process'=>'sshd','version'=>'ssh -V','log'=>$this->home.'/logs/services/sshd.log'],
            'redis'=>['name'=>'Redis','process'=>'redis-server','version'=>'redis-server --version','log'=>$this->home.'/logs/services/redis.log'],
        ];
        $modeFile = $this->home . '/.tms-os/db-mode';
        $dbMode = is_file($modeFile) ? trim((string)@file_get_contents($modeFile)) : 'mariadb';
        if ($dbMode === 'mariadb') {
            $defs['mariadb'] = ['name'=>'MariaDB','process'=>'mariadbd','version'=>'mariadb --version','log'=>$this->home.'/logs/services/mariadb.log'];
        }
        return $defs;
    }

    public function all(bool $withVersion=true): array
    {
        $result=[];
        foreach(array_keys($this->definitions()) as $id)$result[$id]=$this->service($id,$withVersion);
        return $result;
    }

    public function service(string $id,bool $withVersion=true): array
    {
        $defs=$this->definitions();
        if(!isset($defs[$id]))throw new InvalidArgumentException('Dịch vụ không hợp lệ.');
        $installed=$this->core($id,'installed')['code']===0;
        $running=$installed && $this->core($id,'status')['code']===0;
        $pid=0;
        if($running){$raw=trim($this->core($id,'pid')['output']);if(preg_match('/^\d+$/',$raw))$pid=(int)$raw;}
        $metrics=$pid>0?$this->metrics($pid):['memory_mb'=>0.0,'threads'=>0,'uptime'=>'—'];
        $version='Không xác định';
        if($withVersion&&$installed){$r=$this->run($defs[$id]['version'],4);$text=preg_replace('/\s+/',' ',trim($r['output']));if($text!=='')$version=mb_substr($text,0,100);}
        return array_merge([
            'id'=>$id,'name'=>$defs[$id]['name'],'installed'=>$installed,'running'=>$running,'pid'=>$pid,
            'version'=>$version,'log'=>$defs[$id]['log'],
        ],$metrics);
    }

    public function summary(): array
    {
        $all=$this->all(false);$installed=0;$running=0;
        foreach($all as $item){if($item['installed'])$installed++;if($item['running'])$running++;}
        return ['services'=>$all,'total'=>count($all),'installed'=>$installed,'running'=>$running,'stopped'=>max(0,$installed-$running)];
    }

    public function isRunning(string $id): bool{return $this->service($id,false)['running'];}
    public function pid(string $id): int{return $this->service($id,false)['pid'];}

    public function core(string $id,string $action): array
    {
        if(!is_file($this->coreScript))return ['code'=>127,'output'=>'Không tìm thấy Unified Service Core.','timed_out'=>false];
        $timeout=in_array($action,['status','pid','installed'],true)?5:40;
        return $this->run('bash '.escapeshellarg($this->coreScript).' '.escapeshellarg($id).' '.escapeshellarg($action),$timeout);
    }

    public function run(string $command,int $timeout=8): array
    {
        $process=@proc_open(['bash','-lc',$command],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))return ['code'=>127,'output'=>'Không thể khởi chạy lệnh.','timed_out'=>false];
        stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$start=microtime(true);$output='';$timed=false;$exit=null;
        while(true){$output.=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);$s=proc_get_status($process);if(!$s['running']){$exit=(int)$s['exitcode'];break;}if(microtime(true)-$start>$timeout){$timed=true;proc_terminate($process,15);usleep(200000);$s=proc_get_status($process);if($s['running'])proc_terminate($process,9);break;}usleep(100000);}
        $output.=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($process);
        return ['code'=>$exit!==null?$exit:$closed,'output'=>trim($output),'timed_out'=>$timed];
    }

    private function metrics(int $pid): array
    {
        $status=@file('/proc/'.$pid.'/status',FILE_IGNORE_NEW_LINES)?:[];$memoryKb=0;$threads=0;
        foreach($status as $line){if(str_starts_with($line,'VmRSS:'))$memoryKb=(int)filter_var($line,FILTER_SANITIZE_NUMBER_INT);elseif(str_starts_with($line,'Threads:'))$threads=(int)filter_var($line,FILTER_SANITIZE_NUMBER_INT);}
        $uptime='—';$stat=@file_get_contents('/proc/'.$pid.'/stat');$sys=(float)trim((string)@file_get_contents('/proc/uptime'));
        if(is_string($stat)&&$stat!==''&&$sys>0){$parts=preg_split('/\s+/',trim($stat));$ticks=(int)($parts[21]??0);$hz=100;$seconds=max(0,(int)($sys-($ticks/$hz)));$uptime=$this->formatDuration($seconds);}
        return ['memory_mb'=>round($memoryKb/1024,1),'threads'=>$threads,'uptime'=>$uptime];
    }

    private function formatDuration(int $s): string
    { $d=intdiv($s,86400);$h=intdiv($s%86400,3600);$m=intdiv($s%3600,60);return $d>0?"{$d} ngày {$h} giờ":($h>0?"{$h} giờ {$m} phút":"{$m} phút"); }
}
