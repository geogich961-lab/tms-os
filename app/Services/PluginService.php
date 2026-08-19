<?php
declare(strict_types=1);

final class PluginService
{
    private string $home;
    private string $stateFile;
    public function __construct()
    {
        $this->home=getenv('HOME')?:'/data/data/com.termux/files/home';
        @mkdir($this->home.'/.tms-os',0700,true);
        $this->stateFile=$this->home.'/.tms-os/packages-v11.json';
    }

    public function catalog(): array
    {
        $state=$this->readState();$items=$this->catalogRaw();
        foreach($items as &$item){
            $item['installed']=$this->commandExists($item['command']);
            $item['version']=$item['installed']?$this->version($item['version_command']??($item['command'].' --version')):'';
            $item['busy']=(($state['busy']??'')===$item['id']);
            $item['last_result']=$state['results'][$item['id']]??'';
        }unset($item);
        return $items;
    }

    public function install(string $id): array {return $this->runPackageAction($id,'install');}
    public function remove(string $id): array {return $this->runPackageAction($id,'remove');}

    public function updatePackages(): array
    {
        $out=[];$code=0;exec('pkg update -y 2>&1',$out,$code);
        return ['ok'=>$code===0,'message'=>trim(implode("\n",array_slice($out,-50)))?:'Hoàn tất.'];
    }

    private function runPackageAction(string $id,string $action): array
    {
        $item=$this->find($id);if(!$item)throw new RuntimeException('Gói không tồn tại trong danh mục.');
        if($action==='install'&&$this->commandExists($item['command']))return ['ok'=>true,'message'=>$item['name'].' đã được cài.'];
        if($action==='remove'&&!$this->commandExists($item['command']))return ['ok'=>true,'message'=>$item['name'].' chưa được cài.'];
        if($action==='remove'&&!empty($item['protected']))throw new RuntimeException('Gói lõi đang được TMS OS sử dụng và không thể gỡ từ giao diện.');

        $state=$this->readState();$state['busy']=$id;$this->writeState($state);
        $verb=$action==='install'?'install -y':'uninstall -y';
        $out=[];$code=0;exec('pkg '.$verb.' '.escapeshellarg($item['package']).' 2>&1',$out,$code);
        $installed=$this->commandExists($item['command']);
        $ok=$code===0&&($action==='install'?$installed:!$installed);
        $state=$this->readState();$state['busy']='';$state['results'][$id]=($ok?'Thành công':'Thất bại').' · '.date('d/m/Y H:i');$this->writeState($state);
        return ['ok'=>$ok,'message'=>trim(implode("\n",array_slice($out,-40)))?:($ok?'Hoàn tất.':'Thao tác thất bại.')];
    }

    private function catalogRaw(): array
    {
        return [
            ['id'=>'php','name'=>'PHP','description'=>'Runtime PHP cho website và công cụ hệ thống.','command'=>'php','package'=>'php','group'=>'Web','protected'=>true],
            ['id'=>'nginx','name'=>'Nginx','description'=>'Web server và reverse proxy nhẹ.','command'=>'nginx','package'=>'nginx','group'=>'Web','protected'=>true],
            ['id'=>'mariadb','name'=>'MariaDB','description'=>'Máy chủ cơ sở dữ liệu tương thích MySQL.','command'=>'mariadbd','package'=>'mariadb','group'=>'Database','protected'=>true],
            ['id'=>'composer','name'=>'Composer','description'=>'Trình quản lý thư viện PHP.','command'=>'composer','package'=>'composer','group'=>'Development'],
            ['id'=>'nodejs','name'=>'Node.js LTS','description'=>'Runtime JavaScript và npm.','command'=>'node','package'=>'nodejs-lts','group'=>'Development'],
            ['id'=>'python','name'=>'Python','description'=>'Runtime Python và pip.','command'=>'python','package'=>'python','group'=>'Development'],
            ['id'=>'git','name'=>'Git','description'=>'Quản lý mã nguồn.','command'=>'git','package'=>'git','group'=>'Development'],
            ['id'=>'redis','name'=>'Redis','description'=>'Bộ nhớ đệm và hàng đợi tốc độ cao.','command'=>'redis-server','package'=>'redis','group'=>'Database'],
            ['id'=>'postgresql','name'=>'PostgreSQL','description'=>'Cơ sở dữ liệu quan hệ nâng cao.','command'=>'postgres','package'=>'postgresql','group'=>'Database'],
            ['id'=>'openssh','name'=>'OpenSSH','description'=>'SSH server và client.','command'=>'sshd','package'=>'openssh','group'=>'Network'],
            ['id'=>'cloudflared','name'=>'Cloudflared','description'=>'Cloudflare Tunnel client.','command'=>'cloudflared','package'=>'cloudflared','group'=>'Network'],
            ['id'=>'curl','name'=>'cURL','description'=>'HTTP client và trình tải dữ liệu.','command'=>'curl','package'=>'curl','group'=>'Utilities','protected'=>true],
            ['id'=>'wget','name'=>'Wget','description'=>'Tải tệp qua HTTP/HTTPS.','command'=>'wget','package'=>'wget','group'=>'Utilities'],
            ['id'=>'jq','name'=>'jq','description'=>'Xử lý JSON bằng dòng lệnh.','command'=>'jq','package'=>'jq','group'=>'Utilities'],
            ['id'=>'nano','name'=>'Nano','description'=>'Trình sửa văn bản terminal.','command'=>'nano','package'=>'nano','group'=>'Utilities'],
            ['id'=>'ffmpeg','name'=>'FFmpeg','description'=>'Xử lý âm thanh và video.','command'=>'ffmpeg','package'=>'ffmpeg','group'=>'Media'],
            ['id'=>'imagemagick','name'=>'ImageMagick','description'=>'Xử lý và chuyển đổi hình ảnh.','command'=>'magick','package'=>'imagemagick','group'=>'Media'],
            ['id'=>'termux-api','name'=>'Termux:API CLI','description'=>'Đọc pin, Wi-Fi và cảm biến Android.','command'=>'termux-battery-status','package'=>'termux-api','group'=>'Android'],
        ];
    }
    private function find(string $id):?array{foreach($this->catalogRaw() as $i)if($i['id']===$id)return $i;return null;}
    private function commandExists(string $c):bool{exec('command -v '.escapeshellarg($c).' >/dev/null 2>&1',$o,$code);return $code===0;}
    private function version(string $cmd):string{$v=trim((string)shell_exec($cmd.' 2>/dev/null | head -n1'));return function_exists('mb_substr')?mb_substr($v,0,100):substr($v,0,100);}
    private function readState():array{$d=@json_decode((string)@file_get_contents($this->stateFile),true);return is_array($d)?$d:['busy'=>'','results'=>[]];}
    private function writeState(array $d):void{file_put_contents($this->stateFile,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX);@chmod($this->stateFile,0600);}
}
