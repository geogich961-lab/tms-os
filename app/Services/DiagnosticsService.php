<?php
declare(strict_types=1);
final class DiagnosticsService
{
    private string $home; private string $prefix; private UnifiedSystemCoreService $core;
    public function __construct(){ $this->home=getenv('HOME')?:'/data/data/com.termux/files/home';$this->prefix=getenv('PREFIX')?:'/data/data/com.termux/files/usr';$this->core=new UnifiedSystemCoreService(); }
    public function checks(): array
    {
        $checks=[];
        $checks[]=$this->item('PHP runtime',version_compare(PHP_VERSION,'8.1.0','>='),'PHP '.PHP_VERSION);
        $checks[]=$this->item('Thư mục websites',is_dir($this->home.'/websites'),$this->home.'/websites');
        $checks[]=$this->item('Quyền ghi websites',is_writable($this->home.'/websites'),'Cần quyền ghi để upload và tạo website');
        $checks[]=$this->item('Thư mục Nginx sites-enabled',is_dir($this->prefix.'/etc/nginx/sites-enabled'),$this->prefix.'/etc/nginx/sites-enabled');
        // V14.1.5: duyệt dịch vụ theo danh mục động của Unified Core — MariaDB chỉ xuất hiện khi chế độ database là mariadb.
        foreach($this->core->definitions() as $id=>$def){$s=$this->core->service($id,false);$checks[]=$this->item($def['name'],$s['running'],$s['running']?'Tiến trình đang chạy · PID '.$s['pid']:'Không phát hiện tiến trình qua Unified Core');}
        $r=$this->core->run('nginx -t',8);$checks[]=$this->item('Cấu hình Nginx',$r['code']===0,$r['output']);
        $checks[]=$this->item('Phiên PHP Session',is_dir(dirname(__DIR__,2).'/storage/sessions')&&is_writable(dirname(__DIR__,2).'/storage/sessions'),'storage/sessions');
        return $checks;
    }
    public function repair(): array
    { $dirs=[$this->home.'/websites',dirname(__DIR__,2).'/storage/sessions',dirname(__DIR__,2).'/storage/logs',dirname(__DIR__,2).'/storage/cache',$this->prefix.'/etc/nginx/sites-enabled'];foreach($dirs as $dir){if(!is_dir($dir)&&!@mkdir($dir,0700,true))return ['ok'=>false,'message'=>'Không thể tạo: '.$dir];@chmod($dir,0700);}return ['ok'=>true,'message'=>'Đã tạo lại thư mục bắt buộc và cập nhật quyền ghi.']; }
    private function item(string $name,bool $ok,string $detail):array{return ['name'=>$name,'ok'=>$ok,'detail'=>$detail];}
}
