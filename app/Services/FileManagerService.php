<?php
declare(strict_types=1);

final class FileManagerService
{
    private array $roots;
    private string $uploadPartsDir;
    private array $editableExtensions = ['php','html','htm','css','js','json','xml','txt','md','ini','conf','env','sql','log','yml','yaml','sh'];

    public function __construct()
    {
        $home=getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->roots=['websites'=>$home.'/websites','backups'=>$home.'/backups','logs'=>$home.'/logs'];
        $this->uploadPartsDir=$home.'/.tms-os/upload-parts';
        foreach($this->roots as $root) if(!is_dir($root)) @mkdir($root,0700,true);
        if(!is_dir($this->uploadPartsDir)) @mkdir($this->uploadPartsDir,0700,true);
        $this->cleanupUploadParts();
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
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK){
            $message=match($error){UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'Tệp vượt giới hạn upload của PHP.',UPLOAD_ERR_PARTIAL=>'Tệp chỉ được tải lên một phần.',UPLOAD_ERR_NO_FILE=>'Chưa chọn tệp.',default=>'Tải tệp thất bại.'};
            throw new RuntimeException($message);
        }
        $dir=$this->resolveDirectory($this->basePath($rootKey),$relative);
        if(!is_writable($dir)) throw new RuntimeException('Thư mục đích không có quyền ghi. Hãy mở Quyền tệp và đặt thư mục ở 700 hoặc 755.');
        $name=$this->validName((string)($file['name']??'')); $target=$dir.'/'.$name;
        if(file_exists($target)) throw new RuntimeException('Tệp đã tồn tại.');
        $tmp=(string)($file['tmp_name']??'');
        if(!is_uploaded_file($tmp)||!@move_uploaded_file($tmp,$target)) throw new RuntimeException('Không thể lưu tệp vào thư mục đích.');
        @chmod($target,0600); return $name;
    }
    /** Lưu một phần upload nhỏ để tránh request kéo dài qua Cloudflare Tunnel. */
    public function uploadChunk(string $rootKey,string $relative,array $file,string $uploadId,int $chunkIndex,int $totalChunks,string $name,int $totalSize): array
    {
        $this->validateUploadPlan($uploadId,$chunkIndex,$totalChunks,$name,$totalSize);
        $dir=$this->resolveDirectory($this->basePath($rootKey),$relative);
        if(!is_writable($dir)) throw new RuntimeException('Thư mục đích không có quyền ghi. Hãy mở Quyền tệp và đặt thư mục ở 700 hoặc 755.');
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK) throw new RuntimeException($error===UPLOAD_ERR_INI_SIZE?'Phần tệp vượt giới hạn PHP. Hãy chạy repair rồi thử lại.':'Không thể nhận phần tệp.');
        $tmp=(string)($file['tmp_name']??''); if(!is_uploaded_file($tmp)) throw new RuntimeException('Phần tệp upload không hợp lệ.');
        $size=(int)(@filesize($tmp)?:0); if($size>8*1024*1024) throw new RuntimeException('Mỗi phần upload không được vượt 8 MB.');
        $work=$this->uploadWorkDir($uploadId); $metaPath=$work.'/meta.json';
        $meta=['root'=>$rootKey,'relative'=>trim($relative,'/'),'name'=>$this->validName($name),'total_chunks'=>$totalChunks,'total_size'=>$totalSize,'owner'=>$this->uploadOwner()];
        if(is_file($metaPath)){
            $old=json_decode((string)@file_get_contents($metaPath),true);
            if(!is_array($old)||$old!==$meta) throw new RuntimeException('Thông tin upload không khớp. Hãy chọn lại tệp.');
        } else {
            $tmpMeta=$metaPath.'.tmp';
            if(@file_put_contents($tmpMeta,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)===false||!@rename($tmpMeta,$metaPath)){ @unlink($tmpMeta); throw new RuntimeException('Không thể tạo phiên upload.'); }
            @chmod($metaPath,0600);
        }
        $part=$work.'/part-'.str_pad((string)$chunkIndex,6,'0',STR_PAD_LEFT);
        if(is_file($part)){
            if((int)(@filesize($part)?:-1)!==$size) throw new RuntimeException('Phần upload đã tồn tại nhưng không hợp lệ.');
        } elseif(!@move_uploaded_file($tmp,$part)) throw new RuntimeException('Không thể lưu phần upload.');
        @chmod($part,0600);
        return ['chunk_index'=>$chunkIndex,'received_bytes'=>$this->uploadedBytes($work,$totalChunks),'total_size'=>$totalSize];
    }

    public function completeUpload(string $uploadId): array
    {
        $this->validateUploadId($uploadId); $work=$this->uploadWorkDir($uploadId); $metaPath=$work.'/meta.json';
        $meta=json_decode((string)@file_get_contents($metaPath),true);
        if(!is_array($meta)||($meta['owner']??'')!==$this->uploadOwner()) throw new RuntimeException('Phiên upload không hợp lệ hoặc đã hết hạn.');
        $totalChunks=(int)($meta['total_chunks']??0); $totalSize=(int)($meta['total_size']??-1);
        $destination=$this->resolveDirectory($this->basePath((string)$meta['root']),(string)$meta['relative']);
        if(!is_writable($destination)) throw new RuntimeException('Thư mục đích không có quyền ghi.');
        $name=$this->validName((string)($meta['name']??'')); $target=$destination.'/'.$name;
        if(file_exists($target)) throw new RuntimeException('Tệp đã tồn tại.');
        $temp=$destination.'/.'.bin2hex(random_bytes(8)).'.tms-upload'; $out=@fopen($temp,'wb');
        if($out===false) throw new RuntimeException('Không thể tạo tệp đích tạm thời.');
        $bytes=0;
        try{
            for($i=0;$i<$totalChunks;$i++){
                $part=$work.'/part-'.str_pad((string)$i,6,'0',STR_PAD_LEFT); if(!is_file($part)) throw new RuntimeException('Upload chưa đủ phần, vui lòng thử lại.');
                $in=@fopen($part,'rb'); if($in===false||stream_copy_to_stream($in,$out)===false){if(is_resource($in))fclose($in);throw new RuntimeException('Không thể ghép tệp upload.');}
                $bytes+=(int)(@filesize($part)?:0); fclose($in);
            }
            fflush($out); fclose($out); $out=null;
            if($bytes!==$totalSize){@unlink($temp);throw new RuntimeException('Kích thước tệp sau khi ghép không khớp.');}
            if(!@rename($temp,$target)){@unlink($temp);throw new RuntimeException('Không thể đưa tệp vào thư mục website.');}
            @chmod($target,0600); $this->removeRecursive($work);
            return ['name'=>$name,'size'=>$bytes];
        } catch(Throwable $e){ if(is_resource($out))fclose($out); @unlink($temp); throw $e; }
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
    public function archive(string $rootKey,string $relative): string { return $this->archiveBatch($rootKey,[$relative]); }
    public function archiveBatch(string $rootKey,array $relatives): string
    {
        if(empty($relatives)) throw new RuntimeException('Chưa chọn mục để nén.');
        $base=$this->basePath($rootKey);
        $firstPath=$this->resolveExisting($rootKey,$relatives[0])[0];
        $zipName=(count($relatives)>1?'Archive_'.date('Ymd_His'):basename($firstPath)).'.zip';
        $zipPath=dirname($firstPath).'/'.$zipName;
        if(file_exists($zipPath)) $zipPath=dirname($firstPath).'/Archive_'.time().'.zip';
        
        $zip=new ZipArchive(); if($zip->open($zipPath,ZipArchive::CREATE)!==true)throw new RuntimeException('Không thể tạo ZIP.');
        foreach($relatives as $rel){
            [$path]=$this->resolveExisting($rootKey,$rel);
            if(is_file($path)) $zip->addFile($path,basename($path));
            else {
                $baseLen=strlen(dirname($path))+1;
                $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS));
                foreach($it as $f){ if(!$f->isLink()) $zip->addFile($f->getPathname(),substr($f->getPathname(),$baseLen)); }
            }
        }
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
    public function chmod(string $rootKey,string $relative,string $mode,bool $recursive=false): void
    {
        [$path]=$this->resolveExisting($rootKey,$relative);
        if(!preg_match('/^[0-7]{3,4}$/',$mode))throw new RuntimeException('Quyền không hợp lệ.');
        $oct=octdec($mode); if(!@chmod($path,$oct))throw new RuntimeException('Không thể thay đổi quyền.');
        if($recursive&&is_dir($path)){
            $dirMode=(int)substr($mode,-3,3)|0700;
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
            foreach($it as $f){ if(!$f->isLink()) @chmod($f->getPathname(),$f->isDir()?$dirMode:$oct); }
        }
    }

    public function copy(string $rootKey,string $relative,string $targetRelative,bool $overwrite=false): string
    {
        [$path,$base]=$this->resolveExisting($rootKey,$relative);
        $destDir=$this->resolveDirectory($base,$targetRelative);
        if($path===$base)throw new RuntimeException('Không được sao chép thư mục gốc.');
        if(str_starts_with($destDir.'/',$path.'/')||$destDir===$path)throw new RuntimeException('Không thể sao chép vào chính thư mục con của mục đó.');
        $name=basename($path); $target=$destDir.'/'.$name;
        if(file_exists($target)){
            if(!$overwrite) throw new RuntimeException('Đã có mục cùng tên ở thư mục đích.');
        } else { $target=$this->uniqueName($destDir,$name); }
        if(is_dir($path)){ $this->copyRecursive($path,$target); } else { if(!@copy($path,$target))throw new RuntimeException('Không thể sao chép tệp.'); @chmod($target,0600); }
        return basename($target);
    }

    public function move(string $rootKey,string $relative,string $targetRelative): string
    {
        [$path,$base]=$this->resolveExisting($rootKey,$relative);
        $destDir=$this->resolveDirectory($base,$targetRelative);
        if($path===$base)throw new RuntimeException('Không được di chuyển thư mục gốc.');
        if(str_starts_with($destDir.'/',$path.'/')||$destDir===$path)throw new RuntimeException('Không thể di chuyển vào chính thư mục con của mục đó.');
        $name=basename($path); $target=$destDir.'/'.$name;
        if(file_exists($target))throw new RuntimeException('Đã có mục cùng tên ở thư mục đích.');
        if(!@rename($path,$target))throw new RuntimeException('Không thể di chuyển mục.');
        return basename($target);
    }

    public function moveBetweenRoots(string $srcRootKey,string $relative,string $dstRootKey,string $targetRelative): string
    {
        [$srcBase,$dstBase]=[$this->basePath($srcRootKey),$this->basePath($dstRootKey)];
        [$path,$srcBase]=$this->resolveExisting($srcRootKey,$relative);
        $destDir=$this->resolveDirectory($dstBase,$targetRelative);
        if($path===$srcBase)throw new RuntimeException('Không được di chuyển thư mục gốc.');
        $name=basename($path); $target=$destDir.'/'.$name;
        if(file_exists($target))throw new RuntimeException('Đã có mục cùng tên ở thư mục đích.');
        if(@rename($path,$target))return basename($target);
        if(is_dir($path)){ $this->copyRecursive($path,$target); $this->removeRecursive($path); return basename($target); }
        if(!@copy($path,$target))throw new RuntimeException('Không thể di chuyển tệp.'); @unlink($path); @chmod($target,0600);
        return basename($target);
    }

    private function validateUploadId(string $uploadId): void
    {
        if(!preg_match('/^[a-zA-Z0-9_-]{16,80}$/',$uploadId)) throw new RuntimeException('Mã phiên upload không hợp lệ.');
    }
    private function validateUploadPlan(string $uploadId,int $chunkIndex,int $totalChunks,string $name,int $totalSize): void
    {
        $this->validateUploadId($uploadId); $this->validName($name);
        if($totalChunks<1||$totalChunks>4096||$chunkIndex<0||$chunkIndex>=$totalChunks) throw new RuntimeException('Số phần upload không hợp lệ.');
        if($totalSize<0||$totalSize>4*1024*1024*1024) throw new RuntimeException('Kích thước tệp không hợp lệ.');
    }
    private function uploadWorkDir(string $uploadId): string
    {
        $this->validateUploadId($uploadId); $dir=$this->uploadPartsDir.'/'.$uploadId;
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)) throw new RuntimeException('Không thể tạo vùng tạm upload.');
        return $dir;
    }
    private function uploadOwner(): string { return hash('sha256',(string)session_id()); }
    private function uploadedBytes(string $work,int $totalChunks): int
    {
        $bytes=0; for($i=0;$i<$totalChunks;$i++){ $part=$work.'/part-'.str_pad((string)$i,6,'0',STR_PAD_LEFT); if(is_file($part))$bytes+=(int)(@filesize($part)?:0); } return $bytes;
    }
    private function cleanupUploadParts(): void
    {
        foreach(@scandir($this->uploadPartsDir)?:[] as $id){
            if($id==='.'||$id==='..')continue; $dir=$this->uploadPartsDir.'/'.$id;
            if(is_dir($dir)&&(@filemtime($dir)?:time())<time()-86400){ try{$this->removeRecursive($dir);}catch(Throwable $e){} }
        }
    }
    private function uniqueName(string $dir,string $name): string
    {
        $ext=pathinfo($name,PATHINFO_EXTENSION); $base=$ext!==''?substr($name,0,-strlen($ext)-1):$name;
        $i=1; $candidate=$dir.'/'.($base?'Bản sao '.$base:$name).($ext?'.'.$ext:'');
        while(file_exists($candidate)){ $i++; $candidate=$dir.'/'.($base?'Bản sao '.$base.' '.$i:$name.' '.$i).($ext?'.'.$ext:''); }
        return $candidate;
    }

    private function copyRecursive(string $src,string $dest): void
    {
        if(!@mkdir($dest,0700))throw new RuntimeException('Không thể tạo thư mục đích.');
        foreach(scandir($src)?:[] as $n){ if($n==='.'||$n==='..')continue;
            $s=$src.'/'.$n; if(is_link($s))continue;
            if(is_dir($s)){ $this->copyRecursive($s,$dest.'/'.$n); } else { if(!@copy($s,$dest.'/'.$n))throw new RuntimeException('Không thể sao chép tệp '.$n.'.'); @chmod($dest.'/'.$n,0600); }
        }
    }

    public function download(string $rootKey,string $relative): array
    {
        [$path]=$this->resolveExisting($rootKey,$relative); if(!is_file($path)||!is_readable($path))throw new RuntimeException('Tệp không thể tải.');
        return ['path'=>$path,'name'=>basename($path),'size'=>@filesize($path)?:0,'mime'=>function_exists('mime_content_type')?(mime_content_type($path)?:'application/octet-stream'):'application/octet-stream'];
    }

    public function permissions(string $rootKey,string $relative): array
    {
        [$path]=$this->resolveExisting($rootKey,$relative);
        $mode=fileperms($path); if($mode===false)throw new RuntimeException('Không đọc được quyền.');
        $m=sprintf('%o',substr(sprintf('%o',$mode),-4));
        return ['octal'=>$m,'readable'=>is_readable($path),'writable'=>is_writable($path)];
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
