<?php
declare(strict_types=1);
final class TerminalService
{
    private UnifiedSystemCoreService $core;
    public function __construct(){ $this->core=new UnifiedSystemCoreService(); }
    public function commands(): array
    { return ['nginx_test'=>['label'=>'Kiểm tra Nginx','command'=>'nginx -t'],'php_version'=>['label'=>'Phiên bản PHP','command'=>'php -v'],'mariadb_version'=>['label'=>'Phiên bản MariaDB','command'=>'mariadb --version'],'disk'=>['label'=>'Dung lượng lưu trữ','command'=>'df -h "$HOME"'],'load'=>['label'=>'Tải hệ thống','command'=>'cat /proc/loadavg'],'uptime'=>['label'=>'Uptime hệ thống','command'=>'cat /proc/uptime'],'services'=>['label'=>'Tiến trình dịch vụ','command'=>null]]; }
    public function run(string $key): array
    {
        $commands=$this->commands();if(!isset($commands[$key]))return ['ok'=>false,'label'=>'Không hợp lệ','output'=>'Lệnh không được phép.'];
        if($key==='services'){$lines=[];foreach($this->core->all(false) as $s){if(!$s['installed'])continue;$lines[]=sprintf('%-12s %-8s PID %s',$s['id'],$s['running']?'RUNNING':'STOPPED',$s['pid']?:'—');}return ['ok'=>true,'label'=>$commands[$key]['label'],'output'=>implode("\n",$lines)?:'(không có dữ liệu)'];}
        $r=$this->core->run((string)$commands[$key]['command'],10);return ['ok'=>$r['code']===0,'label'=>$commands[$key]['label'],'output'=>$r['output']?:'(không có dữ liệu)'];
    }

    /**
     * Lệnh thủ công của quản trị viên (V17.1.2): chạy bash trong thư mục home,
     * timeout 60 giây, output cắt ở 100KB, mọi lệnh đều ghi nhật ký kiểm toán.
     * Lệnh tương tác (vi, top, hỏi mật khẩu giữa chừng) không được hỗ trợ vì
     * không có phiên stdin — lệnh sẽ bị ngắt khi hết timeout.
     */
    public function runCustom(string $command): array
    {
        $command=trim($command);
        if($command==='')return ['ok'=>false,'label'=>'Lệnh thủ công','output'=>'Chưa nhập lệnh.'];
        if(mb_strlen($command)>2000)return ['ok'=>false,'label'=>'Lệnh thủ công','output'=>'Lệnh quá dài (tối đa 2000 ký tự).'];
        $r=$this->core->run('cd "$HOME"; '.$command,60);
        $output=(string)$r['output'];
        if(strlen($output)>102400)$output=substr($output,0,102400)."\n… (đã cắt bớt phần output vượt quá 100KB)";
        if(!empty($r['timed_out']))$output.=($output!==''?"\n":'').'[Đã ngắt sau 60 giây — lệnh chạy quá lâu hoặc đang chờ nhập liệu. Lệnh tương tác (vi, top…) không dùng được ở đây.]';
        $this->audit($command,(int)$r['code'],!empty($r['timed_out']));
        return ['ok'=>$r['code']===0&&empty($r['timed_out']),'label'=>'$ '.$command,'output'=>$output!==''?$output:'(không có dữ liệu)'];
    }

    /** Nhật ký kiểm toán lệnh thủ công: ~/.tms-os/logs/terminal-audit.log */
    private function audit(string $command,int $code,bool $timedOut): void
    {
        try{
            $home=getenv('HOME')?:'/data/data/com.termux/files/home';
            $dir=$home.'/.tms-os/logs';
            if(!is_dir($dir))@mkdir($dir,0700,true);
            $line=date('c').' exit='.$code.($timedOut?' timeout=60s':'').' cmd='.str_replace(["\r","\n"],' ',$command)."\n";
            @file_put_contents($dir.'/terminal-audit.log',$line,FILE_APPEND|LOCK_EX);
        }catch(Throwable){}
    }
}
