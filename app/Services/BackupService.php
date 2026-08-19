<?php
declare(strict_types=1);
final class BackupService
{
    private string $home;
    private string $root;
    private string $archiveDir;
    private string $metaDir;

    public function __construct()
    {
        $this->home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $this->root=$this->home.'/.tms-os/backups';
        $this->archiveDir=$this->root.'/archives';
        $this->metaDir=$this->root.'/metadata';
        foreach([$this->root,$this->archiveDir,$this->metaDir] as $dir){if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Không thể tạo thư mục Backup Center.');}
    }

    public function all():array
    {
        $rows=[];
        foreach(glob($this->metaDir.'/*.json')?:[] as $metaFile){
            $meta=json_decode((string)@file_get_contents($metaFile),true);
            if(!is_array($meta))continue;
            $archive=$this->archiveDir.'/'.basename((string)($meta['archive']??''));
            if(!is_file($archive))continue;
            $meta['size']=(int)(filesize($archive)?:0);
            $meta['modified']=(int)(filemtime($archive)?:0);
            $rows[]=$meta;
        }
        usort($rows,static fn(array $a,array $b):int=>($b['created_ts']??0)<=>($a['created_ts']??0));
        return $rows;
    }

    public function create(string $scope='system',string $website='',string $note='',bool $locked=false):string
    {
        $scope=in_array($scope,['system','website','config'],true)?$scope:'system';
        $website=$this->safeSite($website,$scope==='website');
        $stamp=date('Ymd_His');
        $id=$stamp.'_'.bin2hex(random_bytes(3));
        $label=$scope==='website'?'website-'.$website:$scope;
        $archiveName='tms-'.$label.'-'.$id.'.tar.gz';
        $archive=$this->archiveDir.'/'.$archiveName;
        $paths=$this->pathsFor($scope,$website);
        if($paths===[])throw new RuntimeException('Không tìm thấy dữ liệu để sao lưu.');

        $manifest=['id'=>$id,'scope'=>$scope,'website'=>$website,'note'=>trim($note),'locked'=>$locked,'archive'=>$archiveName,'created_at'=>date('c'),'created_ts'=>time(),'version'=>'10.2.0','paths'=>$paths];
        $tmpManifest=$this->root.'/manifest-'.$id.'.json';
        file_put_contents($tmpManifest,json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
        $relative=[];
        foreach($paths as $path){$relative[]=ltrim($path,'/');}
        $relative[]=ltrim($tmpManifest,'/');
        $cmd='cd / && tar -czf '.escapeshellarg($archive).' --ignore-failed-read';
        foreach($relative as $item)$cmd.=' '.escapeshellarg($item);
        exec($cmd.' 2>&1',$out,$code);
        @unlink($tmpManifest);
        if($code!==0||!is_file($archive)){@unlink($archive);throw new RuntimeException("Tạo backup thất bại:\n".implode("\n",$out));}
        file_put_contents($this->metaDir.'/'.$id.'.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
        $this->log('CREATE',$manifest);
        return 'Đã tạo '.($scope==='website'?'snapshot website '.$website:'bản sao lưu '.$scope).'.';
    }

    public function quickWebsiteSnapshot(string $website,string $note):void
    {
        try{$this->create('website',$website,$note,false);}catch(Throwable $e){$this->log('AUTO_SNAPSHOT_FAILED',['website'=>$website,'error'=>$e->getMessage()]);}
    }

    public function restore(string $id):string
    {
        $meta=$this->meta($id);
        $archive=$this->archiveDir.'/'.basename((string)$meta['archive']);
        if(!is_file($archive))throw new RuntimeException('Tệp backup không tồn tại.');
        $safety='Trước khi restore '.$id;
        if(($meta['scope']??'')==='website'&&!empty($meta['website']))$this->quickWebsiteSnapshot((string)$meta['website'],$safety);
        else $this->create('config','',$safety,true);
        $cmd='tar -xzf '.escapeshellarg($archive).' -C / 2>&1';
        exec($cmd,$out,$code);
        if($code!==0)throw new RuntimeException("Khôi phục thất bại:\n".implode("\n",$out));
        $this->reloadNginx();
        $this->log('RESTORE',$meta);
        return 'Đã khôi phục snapshot '.$id.'.';
    }

    public function toggleLock(string $id):bool
    {
        $meta=$this->meta($id);$meta['locked']=!((bool)($meta['locked']??false));
        file_put_contents($this->metaDir.'/'.$id.'.json',json_encode($meta,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
        return (bool)$meta['locked'];
    }

    public function file(string $id):string
    {
        $meta=$this->meta($id);$path=$this->archiveDir.'/'.basename((string)$meta['archive']);
        if(!is_file($path))throw new RuntimeException('Backup không tồn tại.');return $path;
    }

    public function delete(string $id):void
    {
        $meta=$this->meta($id);if(!empty($meta['locked']))throw new RuntimeException('Snapshot đang được khóa. Hãy mở khóa trước khi xóa.');
        @unlink($this->archiveDir.'/'.basename((string)$meta['archive']));
        if(!@unlink($this->metaDir.'/'.$id.'.json'))throw new RuntimeException('Không thể xóa metadata backup.');
        $this->log('DELETE',$meta);
    }

    private function meta(string $id):array
    {
        if(!preg_match('/^[A-Za-z0-9_-]{6,64}$/',$id))throw new RuntimeException('Mã snapshot không hợp lệ.');
        $file=$this->metaDir.'/'.$id.'.json';$meta=json_decode((string)@file_get_contents($file),true);
        if(!is_array($meta))throw new RuntimeException('Snapshot không tồn tại.');return $meta;
    }

    private function pathsFor(string $scope,string $website):array
    {
        $paths=[];$add=function(string $path)use(&$paths):void{if(file_exists($path))$paths[]=$path;};
        if($scope==='website'){
            $add($this->home.'/websites/'.$website);
            $add((getenv('PREFIX')?:'/data/data/com.termux/files/usr').'/etc/nginx/sites-enabled/'.$website.'.conf');
            $add((getenv('PREFIX')?:'/data/data/com.termux/files/usr').'/etc/nginx/sites-enabled/'.$website.'.conf.disabled');
            $add($this->home.'/.tms-os/local-domains.json');
            return array_values(array_unique($paths));
        }
        if($scope==='config'){
            $add($this->home.'/.tms-os');$add((getenv('PREFIX')?:'/data/data/com.termux/files/usr').'/etc/nginx/sites-enabled');$add($this->home.'/storage');return array_values(array_unique($paths));
        }
        $add($this->home.'/websites');$add($this->home.'/.tms-os');$add($this->home.'/storage');$add((getenv('PREFIX')?:'/data/data/com.termux/files/usr').'/etc/nginx/sites-enabled');
        return array_values(array_unique($paths));
    }

    private function safeSite(string $name,bool $required):string
    {
        $name=trim($name);if(!$required&&$name==='')return '';
        if(!preg_match('/^[A-Za-z0-9_-]{2,40}$/',$name))throw new RuntimeException('Tên website không hợp lệ.');return $name;
    }

    private function reloadNginx():void
    {
        $prefix=getenv('PREFIX')?:'/data/data/com.termux/files/usr';$nginx=$prefix.'/bin/nginx';if(!is_file($nginx))return;
        exec(escapeshellarg($nginx).' -t 2>&1',$out,$code);if($code!==0)throw new RuntimeException("Cấu hình Nginx sau restore không hợp lệ:\n".implode("\n",$out));
        exec(escapeshellarg($nginx).' -s reload 2>&1');
    }

    private function log(string $action,array $data):void
    {
        $dir=$this->home.'/storage/logs';if(!is_dir($dir))@mkdir($dir,0700,true);
        @file_put_contents($dir.'/backup-snapshot.log',date('c').' '.$action.' '.json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);
    }
}
