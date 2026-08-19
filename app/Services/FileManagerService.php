<?php
declare(strict_types=1);

final class FileManagerService
{
    private array $roots;
    private array $editableExtensions = ['php','html','htm','css','js','json','xml','txt','md','ini','conf','env','sql','log','yml','yaml','sh'];

    public function __construct()
    {
        $home=getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->roots=['websites'=>$home.'/websites','backups'=>$home.'/backups','logs'=>$home.'/logs'];
        foreach($this->roots as $root) if(!is_dir($root)) @mkdir($root,0700,true);
    }
    public function roots(): array { return $this->roots; }
    public function browse(string $rootKey,string $relative=''): array
    {
        $base=$this->basePath($rootKey); $current=$this->resolveDirectory($base,$relative); $entries=@scandir($current);
        if($entries===false) throw new RuntimeException('Không thể đọc thư mục.');
        $items=[];
        foreach($entries as $name){ if($name==='.'||$name==='..')continue; $full=$current.'/'.$name; if(is_link($full))continue; $dir=is_dir($full);
            $items[]=['name'=>$name,'is_dir'=>$dir,'size'=>$dir?null:(@filesize($full)?:0),'modified'=>@filemtime($full)?:0,'relative'=>ltrim(trim($relative,'/').'/'.$name,'/'),'editable'=>!$dir&&$this->isEditable($name)]; }
        usort($items,fn($a,$b)=>$a['is_dir']!==$b['is_dir']?($a['is_dir']?-1:1):strnatcasecmp($a['name'],$b['name']));
        return ['root_key'=>$rootKey,'relative'=>trim($relative,'/'),'items'=>$items,'breadcrumbs'=>$this->breadcrumbs($rootKey,$relative)];
    }
    public function upload(string $rootKey,string $relative,array $file): string
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Tải tệp thất bại hoặc chưa chọn tệp.');
        $dir=$this->resolveDirectory($this->basePath($rootKey),$relative); $name=$this->validName((string)($file['name']??'')); $target=$dir.'/'.$name;
        if(file_exists($target)) throw new RuntimeException('Tệp đã tồn tại.');
        $tmp=(string)($file['tmp_name']??''); if(!is_uploaded_file($tmp)||!@move_uploaded_file($tmp,$target)) throw new RuntimeException('Không thể lưu tệp.'); @chmod($target,0600); return $name;
    }
    public function create(string $rootKey,string $relative,string $name,bool $directory): void
    {
        $dir=$this->resolveDirectory($this->basePath($rootKey),$relative); $name=$this->validName($name); $target=$dir.'/'.$name;
        if(file_exists($target)) throw new RuntimeException('Tên đã tồn tại.');
        if($directory){ if(!mkdir($target,0700))throw new RuntimeException('Không thể tạo thư mục.'); } else { if(file_put_contents($target,'')===false)throw new RuntimeException('Không thể tạo tệp.'); chmod($target,0600); }
    }
    public function rename(string $rootKey,string $relative,string $newName): void
    {
        [$path,$base]=$this->resolveExisting($rootKey,$relative); $newName=$this->validName($newName); $target=dirname($path).'/'.$newName;
        if(file_exists($target))throw new RuntimeException('Tên mới đã tồn tại.'); if(!@rename($path,$target))throw new RuntimeException('Không thể đổi tên.');
    }
    public function delete(string $rootKey,string $relative): void
    {
        [$path,$base]=$this->resolveExisting($rootKey,$relative); if($path===$base)throw new RuntimeException('Không được xóa thư mục gốc.');
        $this->removeRecursive($path);
    }
    public function read(string $rootKey,string $relative): array
    {
        [$path]=$this->resolveExisting($rootKey,$relative); if(!is_file($path)||!$this->isEditable($path))throw new RuntimeException('Loại tệp này không thể chỉnh sửa.');
        $size=@filesize($path)?:0; if($size>2*1024*1024)throw new RuntimeException('Tệp lớn hơn 2 MB.');
        return ['name'=>basename($path),'content'=>(string)file_get_contents($path),'relative'=>$relative];
    }
    public function save(string $rootKey,string $relative,string $content): void
    {
        [$path]=$this->resolveExisting($rootKey,$relative); if(!is_file($path)||!$this->isEditable($path))throw new RuntimeException('Không thể lưu loại tệp này.');
        if(strlen($content)>2*1024*1024)throw new RuntimeException('Nội dung vượt 2 MB.');
        $tmp=$path.'.tms.tmp'; if(file_put_contents($tmp,$content,LOCK_EX)===false)throw new RuntimeException('Không thể ghi tệp.'); chmod($tmp,0600); if(!rename($tmp,$path))throw new RuntimeException('Không thể thay thế tệp.');
    }
    public function archive(string $rootKey,string $relative): string
    {
        [$path]=$this->resolveExisting($rootKey,$relative); $zipPath=$path.'.zip'; if(file_exists($zipPath))throw new RuntimeException('Tệp ZIP đã tồn tại.');
        $zip=new ZipArchive(); if($zip->open($zipPath,ZipArchive::CREATE)!==true)throw new RuntimeException('Không thể tạo ZIP.');
        if(is_file($path)) $zip->addFile($path,basename($path)); else { $baseLen=strlen(dirname($path))+1; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS)); foreach($it as $f){ if($f->isLink())continue; $zip->addFile($f->getPathname(),substr($f->getPathname(),$baseLen)); } }
        $zip->close(); chmod($zipPath,0600); return basename($zipPath);
    }
    public function extract(string $rootKey,string $relative): void
    {
        [$path,$base]=$this->resolveExisting($rootKey,$relative); if(strtolower(pathinfo($path,PATHINFO_EXTENSION))!=='zip')throw new RuntimeException('Chỉ hỗ trợ ZIP.');
        $zip=new ZipArchive(); if($zip->open($path)!==true)throw new RuntimeException('Không mở được ZIP.'); $dest=dirname($path);
        for($i=0;$i<$zip->numFiles;$i++){ $name=$zip->getNameIndex($i); if($name===false||str_contains($name,'../')||str_starts_with($name,'/')){ $zip->close(); throw new RuntimeException('ZIP chứa đường dẫn không an toàn.'); } }
        if(!$zip->extractTo($dest)){ $zip->close(); throw new RuntimeException('Giải nén thất bại.'); } $zip->close();
    }

    public function search(string $rootKey,string $relative,string $query,int $limit=200): array
    {
        $query=trim($query); if($query==='') return [];
        $base=$this->basePath($rootKey); $start=$this->resolveDirectory($base,$relative);
        $results=[]; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($start,FilesystemIterator::SKIP_DOTS));
        foreach($it as $f){
            if($f->isLink())continue;
            $name=$f->getFilename();
            if(stripos($name,$query)!==false){
                $full=$f->getPathname();
                $results[]=['name'=>$name,'is_dir'=>$f->isDir(),'size'=>$f->isDir()?null:$f->getSize(),'modified'=>$f->getMTime(),'relative'=>ltrim(substr($full,strlen($base)),'/'),'editable'=>$f->isFile()&&$this->isEditable($name)];
                if(count($results)>=$limit)break;
            }
        }
        return $results;
    }
    public function chmod(string $rootKey,string $relative,string $mode): void
    {
        [$path]=$this->resolveExisting($rootKey,$relative);
        if(!preg_match('/^[0-7]{3,4}$/',$mode))throw new RuntimeException('Quyền không hợp lệ.');
        $oct=octdec($mode); if(!@chmod($path,$oct))throw new RuntimeException('Không thể thay đổi quyền.');
    }

    public function download(string $rootKey,string $relative): array
    {
        [$path]=$this->resolveExisting($rootKey,$relative); if(!is_file($path)||!is_readable($path))throw new RuntimeException('Tệp không thể tải.');
        return ['path'=>$path,'name'=>basename($path),'size'=>@filesize($path)?:0,'mime'=>function_exists('mime_content_type')?(mime_content_type($path)?:'application/octet-stream'):'application/octet-stream'];
    }
    private function basePath(string $key): string { if(!isset($this->roots[$key]))throw new RuntimeException('Khu vực không hợp lệ.'); $r=realpath($this->roots[$key]); if($r===false)throw new RuntimeException('Thiếu thư mục gốc.'); return $r; }
    private function resolveDirectory(string $base,string $rel): string { $c=trim(str_replace('\\','/',$rel),'/'); $r=realpath($c===''?$base:$base.'/'.$c); if($r===false||!$this->inside($r,$base)||!is_dir($r)||is_link($r))throw new RuntimeException('Đường dẫn không hợp lệ.'); return $r; }
    private function resolveExisting(string $rootKey,string $rel): array { $base=$this->basePath($rootKey); $r=realpath($base.'/'.ltrim(str_replace('\\','/',$rel),'/')); if($r===false||!$this->inside($r,$base)||is_link($r))throw new RuntimeException('Đường dẫn không hợp lệ.'); return [$r,$base]; }
    private function inside(string $p,string $b): bool { return $p===$b||str_starts_with($p,rtrim($b,'/').'/'); }
    private function validName(string $name): string { $name=trim(basename($name)); if($name===''||$name==='.'||$name==='..'||str_contains($name,"\0"))throw new RuntimeException('Tên không hợp lệ.'); return $name; }
    private function isEditable(string $path): bool { return in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),$this->editableExtensions,true)||basename($path)==='.htaccess'; }
    private function removeRecursive(string $path): void { if(is_dir($path)){ foreach(scandir($path)?:[] as $n){ if($n==='.'||$n==='..')continue; $this->removeRecursive($path.'/'.$n); } if(!rmdir($path))throw new RuntimeException('Không thể xóa thư mục.'); } elseif(!unlink($path))throw new RuntimeException('Không thể xóa tệp.'); }
    private function breadcrumbs(string $root,string $rel): array { $c=[['label'=>ucfirst($root),'path'=>'']]; $p=''; foreach(array_filter(explode('/',trim($rel,'/'))) as $x){$p=ltrim($p.'/'.$x,'/');$c[]=['label'=>$x,'path'=>$p];} return $c; }
}
