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
}
