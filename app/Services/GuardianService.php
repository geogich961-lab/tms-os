<?php
declare(strict_types=1);

final class GuardianService
{
    private string $home;
    private string $state;
    private string $events;
    private string $script;
    private UnifiedSystemCoreService $core;

    public function __construct()
    {
        $this->home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $this->state=$this->home.'/.tms-os';
        $this->events=$this->home.'/logs/guardian/events.jsonl';
        $this->script=$this->home.'/tms-os/scripts/tms-guardian.sh';
        $this->core=new UnifiedSystemCoreService();
        @mkdir($this->state,0700,true);
        @mkdir(dirname($this->events),0700,true);
        $this->ensureConfig();
    }

    public function dashboard(): array
    {
        return [
            'running'=>$this->running(),
            'pid'=>$this->pid(),
            'config'=>$this->config(),
            'status'=>$this->status(),
            'events'=>$this->events(80),
            'repair_count_hour'=>$this->repairCountHour(),
        ];
    }

    public function action(string $action): array
    {
        if(!in_array($action,['start','stop','restart','check','repair-php'],true)){
            throw new RuntimeException('Thao tác Guardian không hợp lệ.');
        }
        if(!is_file($this->script)) throw new RuntimeException('Không tìm thấy script TMS Guardian.');
        $command=match($action){
            'check'=>'bash '.escapeshellarg($this->script).' once',
            'repair-php'=>'bash '.escapeshellarg($this->home.'/tms-os/scripts/tms-service-core.sh').' php restart && nginx -t && nginx -s reload',
            default=>'bash '.escapeshellarg($this->script).' '.$action,
        };
        $output=[];$code=0;exec($command.' 2>&1',$output,$code);
        usleep(500000);
        $ok=$code===0;
        if($action==='start'||$action==='restart')$ok=$this->running();
        if($action==='stop')$ok=!$this->running();
        if($action==='repair-php'){
            $this->appendEvent('info','php','manual-repair',$ok?'Đã thực hiện phục hồi PHP thủ công.':'Phục hồi PHP thủ công chưa thành công.',$ok);
        }
        return ['ok'=>$ok,'message'=>trim(implode("\n",array_slice($output,-20)))?:($ok?'Hoàn tất.':'Thao tác chưa thành công.')];
    }

    public function saveConfig(array $input): array
    {
        $interval=max(15,min(300,(int)($input['interval']??30)));
        $max=max(1,min(20,(int)($input['max_repairs']??6)));
        $config=[
            'ENABLED'=>!empty($input['enabled'])?1:0,
            'INTERVAL'=>$interval,
            'AUTO_REPAIR'=>!empty($input['auto_repair'])?1:0,
            'CHECK_PANEL'=>!empty($input['check_panel'])?1:0,
            'CHECK_WEBSITE'=>!empty($input['check_website'])?1:0,
            'CHECK_DATABASE'=>!empty($input['check_database'])?1:0,
            'MAX_REPAIRS_PER_HOUR'=>$max,
        ];
        $lines=[];foreach($config as $key=>$value)$lines[]=$key.'='.$value;
        file_put_contents($this->state.'/guardian.conf',implode("\n",$lines)."\n",LOCK_EX);
        @chmod($this->state.'/guardian.conf',0600);
        if($config['ENABLED']===1){$this->action('restart');}else{$this->action('stop');}
        return ['ok'=>true,'message'=>'Đã lưu cấu hình TMS Guardian.'];
    }

    public function api(): array
    {
        return [
            'running'=>$this->running(),
            'pid'=>$this->pid(),
            'status'=>$this->status(),
            'events'=>$this->events(20),
            'repair_count_hour'=>$this->repairCountHour(),
        ];
    }

    private function config(): array
    {
        $result=[];
        foreach(@file($this->state.'/guardian.conf',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
            if(!str_contains($line,'='))continue;
            [$key,$value]=explode('=',$line,2);$result[trim($key)]=(int)trim($value);
        }
        return array_merge(['ENABLED'=>1,'INTERVAL'=>30,'AUTO_REPAIR'=>1,'CHECK_PANEL'=>1,'CHECK_WEBSITE'=>1,'CHECK_DATABASE'=>1,'MAX_REPAIRS_PER_HOUR'=>6],$result);
    }

    private function ensureConfig(): void
    {
        $file=$this->state.'/guardian.conf';
        if(is_file($file))return;
        file_put_contents($file,"ENABLED=1\nINTERVAL=30\nAUTO_REPAIR=1\nCHECK_PANEL=1\nCHECK_WEBSITE=1\nCHECK_DATABASE=1\nMAX_REPAIRS_PER_HOUR=6\n",LOCK_EX);
        @chmod($file,0600);
    }

    private function running(): bool
    {
        $pid=$this->pid();
        if($pid==='')return false;
        if(function_exists('posix_kill'))return @posix_kill((int)$pid,0);
        exec('kill -0 '.escapeshellarg($pid).' >/dev/null 2>&1',$out,$code);return $code===0;
    }

    private function pid(): string
    {
        $value=trim((string)@file_get_contents($this->state.'/guardian.pid'));
        return preg_match('/^\d+$/',$value)?$value:'';
    }

    private function status(): array
    {
        $data=@json_decode((string)@file_get_contents($this->state.'/guardian-status.json'),true);
        if(!is_array($data))$data=['updated_at'=>'','panel'=>'—','website'=>'—'];
        $services=$this->core->all(false);
        $data['nginx']=$services['nginx']['running'];
        $data['php']=$services['php']['running'];
        $data['mariadb']=$services['mariadb']['running'];
        $data['ssh']=$services['ssh']['running'];
        $data['source']='Unified Core V13';
        return $data;
    }

    private function events(int $limit): array
    {
        if(!is_file($this->events))return [];
        $lines=@file($this->events,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];
        $result=[];
        foreach(array_reverse(array_slice($lines,-max(1,min(500,$limit)))) as $line){
            $item=json_decode($line,true);if(is_array($item))$result[]=$item;
        }
        return $result;
    }

    private function repairCountHour(): int
    {
        $cutoff=time()-3600;$count=0;
        foreach($this->events(500) as $event){
            $time=strtotime((string)($event['time']??''));
            if($time!==false&&$time>=$cutoff&&in_array((string)($event['action']??''),['repair','manual-repair'],true))$count++;
        }
        return $count;
    }

    private function appendEvent(string $level,string $service,string $action,string $message,bool $ok): void
    {
        $row=['time'=>date(DATE_ATOM),'level'=>$level,'service'=>$service,'action'=>$action,'ok'=>$ok,'message'=>$message];
        file_put_contents($this->events,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);
    }
}
