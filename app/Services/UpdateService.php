<?php
declare(strict_types=1);

final class UpdateService
{
    private string $home;
    private string $dir;
    public function __construct()
    {
        $this->home=getenv('HOME')?:'/data/data/com.termux/files/home';
        $this->dir=$this->home.'/.tms-os/updates';
        @mkdir($this->dir,0700,true);
    }

    public function staged(): array
    {
        $items=[];
        foreach(glob($this->dir.'/*.zip')?:[] as $file)$items[]=['name'=>basename($file),'size'=>filesize($file),'sha256'=>hash_file('sha256',$file),'time'=>filemtime($file)];
        usort($items,static fn($a,$b)=>$b['time']<=>$a['time']);return $items;
    }

    public function stage(array $upload): array
    {
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Tải gói cập nhật thất bại.');
        if(($upload['size']??0)>100*1024*1024)throw new RuntimeException('Gói cập nhật vượt quá 100 MB.');
        $tmp=(string)$upload['tmp_name'];
        $zip=new ZipArchive();if($zip->open($tmp)!==true)throw new RuntimeException('File không phải ZIP hợp lệ.');
        $required=['config/app.php','public/index.php','scripts/install.sh'];
        foreach($required as $r)if($zip->locateName($r)===false){$zip->close();throw new RuntimeException('Gói cập nhật thiếu '.$r);}
        for($i=0;$i<$zip->numFiles;$i++){ $n=$zip->getNameIndex($i);if(str_contains($n,'../')||str_starts_with($n,'/')){$zip->close();throw new RuntimeException('ZIP chứa đường dẫn không an toàn.');}}
        $zip->close();
        $name='tms-update-'.date('Ymd_His').'.zip';$dest=$this->dir.'/'.$name;
        if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('Không thể lưu gói cập nhật.');
        return ['ok'=>true,'message'=>'Đã kiểm tra và lưu gói cập nhật. Hãy dùng installer đi kèm để áp dụng an toàn.','name'=>$name];
    }

    public function delete(string $name): void
    {
        $name=basename($name);$file=$this->dir.'/'.$name;
        if(!is_file($file)||!str_ends_with($name,'.zip'))throw new RuntimeException('Gói cập nhật không tồn tại.');
        @unlink($file);
    }
}
