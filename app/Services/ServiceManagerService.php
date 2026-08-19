<?php
declare(strict_types=1);

final class ServiceManagerService
{
    private string $home;
    private string $prefix;
    private string $root;
    private string $stateFile;
    private string $coreDir;
    private string $queueDir;
    private string $resultDir;
    private string $coreScript;
    private string $workerScript;
    private UnifiedSystemCoreService $unifiedCore;

    public function __construct()
    {
        $this->home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $this->prefix=getenv('PREFIX')?:'/data/data/com.termux/files/usr';
        $this->root=$this->home.'/tms-os';
        $this->stateFile=$this->home.'/.tms-os/service-manager.json';
        $this->coreDir=$this->home.'/.tms-os/service-core';
        $this->queueDir=$this->coreDir.'/queue';
        $this->resultDir=$this->coreDir.'/results';
        $this->coreScript=$this->root.'/scripts/tms-service-core.sh';
        $this->workerScript=$this->root.'/scripts/tms-service-worker.sh';
        $this->unifiedCore=new UnifiedSystemCoreService();
        foreach([$this->home.'/.tms-os',$this->home.'/logs/services',$this->coreDir,$this->queueDir,$this->resultDir] as $dir)@mkdir($dir,0700,true);
    }

    public function catalog(): array
    {
        $this->harvestResults();
        $state=$this->readState();
        $items=$this->definitions();
        foreach($items as $id=>&$item){
            $item['id']=$id;
            $unified=$this->unifiedCore->service($id,true);
            $item['installed']=$unified['installed'];
            $item['running']=$unified['running'];
            $item['autostart']=in_array($id,$state['autostart']??[],true);
            $item['pid']=$unified['pid']>0?(string)$unified['pid']:'';
            $item['pending']=$state['pending'][$id]??null;
            $item['last_action']=$state['results'][$id]??'';
            $item['metrics']=['memory_mb'=>$unified['memory_mb'],'threads'=>$unified['threads'],'uptime'=>$unified['uptime']];
            $item['version']=$item['installed']?$unified['version']:'—';
            $item['health']=$this->health($item);
        }
        unset($item);
        return array_values($items);
    }

    public function snapshot(): array
    {
        $services=$this->catalog();
        $installed=count(array_filter($services,static fn(array $s):bool=>$s['installed']));
        $running=count(array_filter($services,static fn(array $s):bool=>$s['running']));
        $pending=count(array_filter($services,static fn(array $s):bool=>!empty($s['pending'])));
        return ['generated_at'=>date('c'),'summary'=>['total'=>count($services),'installed'=>$installed,'running'=>$running,'stopped'=>max(0,$installed-$running),'pending'=>$pending],'services'=>$services];
    }

    public function action(string $id,string $action): array
    {
        $definitions=$this->definitions();
        if(!isset($definitions[$id]))throw new RuntimeException('Dịch vụ không hợp lệ.');
        if(!in_array($action,['start','stop','restart'],true))throw new RuntimeException('Thao tác không hợp lệ.');
        if($id==='php'&&$action==='stop')throw new RuntimeException('Không thể dừng PHP Engine từ giao diện đang chạy bằng PHP. Chỉ hỗ trợ Restart an toàn qua worker.');
        if($this->unifiedCore->core($id,'installed')['code']!==0)throw new RuntimeException($definitions[$id]['name'].' chưa được cài.');
        $state=$this->readState();
        if(!empty($state['pending'][$id]))throw new RuntimeException($definitions[$id]['name'].' đang có một công việc trong hàng đợi.');
        $job=$this->enqueue($id,$action);
        $state=$this->readState();
        $state['pending'][$id]=['job'=>$job,'action'=>$action,'queued_at'=>date('c')];
        $this->writeState($state);
        $this->launchWorker();
        return ['ok'=>true,'queued'=>true,'job'=>$job,'message'=>'Đã đưa '.$definitions[$id]['name'].' vào hàng đợi. Worker sẽ xử lý và xác minh trạng thái độc lập.'];
    }

    public function restartAll(): array
    {
        $definitions=$this->definitions();
        $state=$this->readState();
        $results=[];
        foreach(array_keys($definitions) as $id){
            $item=$definitions[$id];
            if($this->unifiedCore->core($id,'installed')['code']!==0)continue;
            if(!empty($state['pending'][$id])){
                $results[]=['id'=>$id,'name'=>$item['name'],'ok'=>false,'message'=>'Đã có công việc đang chờ xử lý.'];
                continue;
            }
            $job=$this->enqueue($id,'restart');
            $state['pending'][$id]=['job'=>$job,'action'=>'restart','queued_at'=>date('c')];
            $results[]=['id'=>$id,'name'=>$item['name'],'ok'=>true,'queued'=>true,'job'=>$job,'message'=>'Đã xếp hàng.'];
        }
        $this->writeState($state);
        $this->launchWorker();
        $ok=!array_filter($results,static fn(array $r):bool=>empty($r['ok']));
        return ['ok'=>$ok,'queued'=>true,'message'=>$ok?'Đã xếp hàng Restart All. Các dịch vụ được xử lý tuần tự và PHP Engine luôn chạy cuối.':'Một số dịch vụ đã có công việc đang chờ; các dịch vụ còn lại vẫn được xếp hàng.','results'=>$results];
    }

    public function setAutostart(string $id,bool $enabled): array
    {
        if(!isset($this->definitions()[$id]))throw new RuntimeException('Dịch vụ không hợp lệ.');
        $state=$this->readState();$list=$state['autostart']??[];
        $list=array_values(array_filter($list,static fn($x):bool=>$x!==$id));
        if($enabled)$list[]=$id;
        $state['autostart']=array_values(array_unique($list));$this->writeState($state);
        $this->writeAutostartScript($state['autostart']);
        return ['ok'=>true,'message'=>$enabled?'Đã bật tự khởi động.':'Đã tắt tự khởi động.'];
    }

    public function log(string $id,int $lines=120): string
    {
        $definitions=$this->definitions();
        if(!isset($definitions[$id]))throw new RuntimeException('Dịch vụ không hợp lệ.');
        $file=$definitions[$id]['log'];
        if(!is_file($file))return 'Chưa có log cho dịch vụ này.';
        $lines=max(20,min(500,$lines));
        $result=$this->run('tail -n '.$lines.' '.escapeshellarg($file),5);
        return trim($result['output'])?:'Log hiện đang trống.';
    }

    private function definitions(): array
    {
        // V14.0.3: MariaDB chỉ xuất hiện khi chế độ database là mariadb.
        $defs = [
            'nginx'=>['name'=>'Nginx','version_cmd'=>'nginx -v','log'=>$this->home.'/logs/services/nginx.log'],
            'php'=>['name'=>'PHP Engine','version_cmd'=>'php -r '.escapeshellarg('echo PHP_VERSION;'),'log'=>$this->home.'/logs/services/php-engine.log'],
            'ssh'=>['name'=>'OpenSSH','version_cmd'=>'ssh -V','log'=>$this->home.'/logs/services/sshd.log'],
            'redis'=>['name'=>'Redis','version_cmd'=>'redis-server --version','log'=>$this->home.'/logs/services/redis.log'],
        ];
        $modeFile = $this->home . '/.tms-os/db-mode';
        $dbMode = is_file($modeFile) ? trim((string)@file_get_contents($modeFile)) : 'mariadb';
        if ($dbMode === 'mariadb') {
            $defs['mariadb'] = ['name'=>'MariaDB','version_cmd'=>'mariadbd --version','log'=>$this->home.'/logs/services/mariadb.log'];
        }
        return $defs;
    }

    private function enqueue(string $id,string $action): string
    {
        $job=date('YmdHis').'-'.sprintf('%06d',(int)(microtime(true)*1000000)%1000000).'-'.bin2hex(random_bytes(3));
        $tmp=$this->queueDir.'/'.$job.'.tmp';$path=$this->queueDir.'/'.$job.'.job';
        file_put_contents($tmp,$id."\t".$action."\n",LOCK_EX);@chmod($tmp,0600);rename($tmp,$path);
        return $job;
    }

    private function launchWorker(): void
    {
        $cmd='nohup '.(is_executable('/data/data/com.termux/files/usr/bin/setsid')?'setsid ':'').'bash '.escapeshellarg($this->workerScript).' >>'.escapeshellarg($this->home.'/logs/services/service-core.log').' 2>&1 < /dev/null &';
        @exec($cmd);
    }

    private function harvestResults(): void
    {
        $state=$this->readState();$changed=false;
        foreach(glob($this->resultDir.'/*.json')?:[] as $file){
            $result=@json_decode((string)@file_get_contents($file),true);
            if(!is_array($result)||empty($result['service']))continue;
            $id=(string)$result['service'];$ok=!empty($result['ok']);$action=(string)($result['action']??'');$message=(string)($result['message']??'');
            $line=date('Y-m-d H:i:s').' · '.$action.' · '.($ok?'OK':'ERROR').' · '.preg_replace('/\s+/',' ',trim($message));
            $state['results'][$id]=mb_substr($line,0,500);
            $state['events'][]=['time'=>$result['finished_at']??date('c'),'service'=>$id,'action'=>$action,'ok'=>$ok,'message'=>mb_substr($message,0,1000)];
            if(($state['pending'][$id]['job']??'')===($result['job']??''))unset($state['pending'][$id]);
            @unlink($file);$changed=true;
        }
        if($changed){$state['events']=array_slice($state['events']??[],-150);$this->writeState($state);}
    }

    private function health(array $item): array
    {
        if(!$item['installed'])return ['level'=>'missing','label'=>'Chưa cài','message'=>'Không tìm thấy lệnh thực thi.'];
        if(!empty($item['pending']))return ['level'=>'pending','label'=>'Đang xử lý','message'=>'Worker đang thực hiện '.($item['pending']['action']??'công việc').' trong hàng đợi.'];
        if(!$item['running'])return ['level'=>'stopped','label'=>'Đã dừng','message'=>'Dịch vụ đã cài nhưng không chạy.'];
        return ['level'=>'healthy','label'=>'Ổn định','message'=>'Adapter Termux đã xác minh dịch vụ đang hoạt động.'];
    }

    private function version(array $item): string
    {
        $r=$this->run((string)$item['version_cmd'],4);$v=preg_replace('/\s+/',' ',trim($r['output']));
        return $v!==''?mb_substr($v,0,90):'Không xác định';
    }

    private function core(string $id,string $action): array
    {
        return $this->unifiedCore->core($id,$action);
    }

    private function processMetrics(int $pid): array
    {
        $status=@file('/proc/'.$pid.'/status',FILE_IGNORE_NEW_LINES);
        $stat=@file_get_contents('/proc/'.$pid.'/stat');$memoryKb=0;$threads=0;
        foreach($status?:[] as $line){if(str_starts_with($line,'VmRSS:'))$memoryKb=(int)filter_var($line,FILTER_SANITIZE_NUMBER_INT);if(str_starts_with($line,'Threads:'))$threads=(int)filter_var($line,FILTER_SANITIZE_NUMBER_INT);}
        $uptime='—';
        if(is_string($stat)&&$stat!==''){$parts=preg_split('/\s+/',trim($stat));$start=(int)($parts[21]??0);$system=(float)trim((string)@file_get_contents('/proc/uptime'));$seconds=max(0,(int)($system-($start/100)));$uptime=$this->formatDuration($seconds);}
        return ['memory_mb'=>round($memoryKb/1024,1),'threads'=>$threads,'uptime'=>$uptime];
    }

    private function emptyMetrics():array{return ['memory_mb'=>0,'threads'=>0,'uptime'=>'—'];}
    private function formatDuration(int $s):string{$d=intdiv($s,86400);$h=intdiv($s%86400,3600);$m=intdiv($s%3600,60);return $d>0?"{$d} ngày {$h} giờ":($h>0?"{$h} giờ {$m} phút":"{$m} phút");}

    private function run(string $command,int $timeout): array
    {
        $process=@proc_open(['bash','-lc',$command],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))return ['code'=>127,'output'=>'Không thể khởi chạy lệnh.','timed_out'=>false];
        stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$start=microtime(true);$output='';$timedOut=false;$exitCode=null;
        while(true){$output.=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);$status=proc_get_status($process);if(!$status['running']){$exitCode=(int)$status['exitcode'];break;}if(microtime(true)-$start>$timeout){$timedOut=true;proc_terminate($process,15);usleep(250000);$status=proc_get_status($process);if($status['running'])proc_terminate($process,9);break;}usleep(100000);}
        $output.=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($process);return ['code'=>$exitCode!==null?$exitCode:$closed,'output'=>trim($output),'timed_out'=>$timedOut];
    }

    private function writeAutostartScript(array $ids): void
    {
        $lines=['#!/data/data/com.termux/files/usr/bin/bash','set +e','# Generated by TMS OS V13.0.1 Unified Core'];
        foreach($ids as $id)$lines[]='bash '.escapeshellarg($this->coreScript).' '.escapeshellarg((string)$id).' start >/dev/null 2>&1';
        $path=$this->home.'/.tms-os/autostart-services.sh';file_put_contents($path,implode("\n",$lines)."\n",LOCK_EX);@chmod($path,0700);
    }

    private function readState(): array
    {
        $d=@json_decode((string)@file_get_contents($this->stateFile),true);
        return is_array($d)?$d:['autostart'=>[],'results'=>[],'events'=>[],'pending'=>[]];
    }
    private function writeState(array $d): void{file_put_contents($this->stateFile,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX);@chmod($this->stateFile,0600);}
}
