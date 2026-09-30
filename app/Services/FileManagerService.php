<?php
declare(strict_types=1);

final class FileManagerService
{
    private array $roots;
    private string $uploadPartsDir;
    private string $trashDir;
    private array $editableExtensions = ['php','html','htm','css','js','json','xml','txt','md','ini','conf','env','sql','log','yml','yaml','sh'];
    /** Phần mở rộng cho phép xem trước inline (không có svg vì nguy cơ XSS). */
    private array $previewExtensions = [
        'image' => ['jpg','jpeg','png','gif','webp','bmp','ico'],
        'video' => ['mp4','webm','ogv'],
        'audio' => ['mp3','wav','ogg','oga','m4a','flac'],
    ];

    public function __construct()
    {
        $home=getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->roots=['websites'=>$home.'/websites','backups'=>$home.'/backups','logs'=>$home.'/logs'];
        $this->uploadPartsDir=$home.'/.tms-os/upload-parts';
        $this->trashDir=$home.'/.tms-os/trash';
        foreach($this->roots as $root) if(!is_dir($root)) @mkdir($root,0700,true);
        if(!is_dir($this->uploadPartsDir)) @mkdir($this->uploadPartsDir,0700,true);
        if(!is_dir($this->trashDir)) @mkdir($this->trashDir,0700,true);
        $this->cleanupUploadParts();
    }
    public function roots(): array { return $this->roots; }
    public function browse(string $rootKey,string $relative=''): array
    {
        $base=$this->basePath($rootKey); $current=$this->resolveDirectory($base,$relative); $entries=@scandir($current);
        if($entries===false) throw new RuntimeException('Không thể đọc thư mục.');
        $items=[];
        foreach($entries as $name){ if($name==='.'||$name==='..')continue; $full=$current.'/'.$name; if(is_link($full))continue; $dir=is_dir($full);
            $items[]=['name'=>$name,'is_dir'=>$dir,'size'=>$dir?null:(@filesize($full)?:0),'modified'=>@filemtime($full)?:0,'relative'=>ltrim(trim($relative,'/').'/'.$name,'/'),'editable'=>!$dir&&$this->isEditable($name),'previewable'=>!$dir&&$this->previewKind($name)!==null]; }
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
        $dir=$this->ensureRelativeDir($this->basePath($rootKey),$relative);
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
        $destination=$this->ensureRelativeDir($this->basePath((string)$meta['root']),(string)$meta['relative']);
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
                $results[]=['name'=>$name,'is_dir'=>$f->isDir(),'size'=>$f->isDir()?null:$f->getSize(),'modified'=>$f->getMTime(),'relative'=>ltrim(substr($full,strlen($base)),'/'),'editable'=>$f->isFile()&&$this->isEditable($name),'previewable'=>$f->isFile()&&$this->previewKind($name)!==null];
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
    /* ================= Thùng rác (xóa mềm) ================= */

    public function trash(string $rootKey,string $relative): string
    {
        [$path,$base]=$this->resolveExisting($rootKey,$relative);
        if($path===$base) throw new RuntimeException('Không được xóa thư mục gốc.');
        $id=date('Ymd_His').'_'.bin2hex(random_bytes(8));
        $dest=$this->trashDir.'/'.$id;
        if(!@mkdir($dest,0700,true)) throw new RuntimeException('Không thể tạo thùng rác.');
        $name=basename($path);
        $isDir=is_dir($path);
        if(!@rename($path,$dest.'/'.$name)){ @rmdir($dest); throw new RuntimeException('Không thể chuyển vào thùng rác.'); }
        $meta=['id'=>$id,'root'=>$rootKey,'relative'=>ltrim(trim(str_replace('\\','/',$relative),'/'),'/'),'name'=>$name,'is_dir'=>$isDir,'size'=>$isDir?null:(@filesize($dest.'/'.$name)?:0),'deleted_at'=>time()];
        @file_put_contents($dest.'/meta.json',json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
        @chmod($dest.'/meta.json',0600);
        return $id;
    }

    public function trashBatch(string $rootKey,array $relatives): int
    {
        $n=0;
        foreach($relatives as $rel){ if(trim((string)$rel)==='') continue; $this->trash($rootKey,(string)$rel); $n++; }
        if($n===0) throw new RuntimeException('Chưa chọn mục để xóa.');
        return $n;
    }

    public function listTrash(): array
    {
        $items=[];
        foreach(@scandir($this->trashDir)?:[] as $id){
            if($id==='.'||$id==='..'||!preg_match('/^\d{8}_\d{6}_[a-f0-9]{16}$/',$id)) continue;
            $meta=$this->readTrashMeta($id);
            if($meta!==null) $items[]=$meta;
        }
        usort($items,fn($a,$b)=>$b['deleted_at']<=>$a['deleted_at']);
        return $items;
    }

    public function restoreTrash(string $id): string
    {
        $meta=$this->readTrashMeta($id);
        if($meta===null) throw new RuntimeException('Mục trong thùng rác không tồn tại.');
        $itemPath=$this->trashDir.'/'.$id.'/'.$meta['name'];
        if(!file_exists($itemPath)) throw new RuntimeException('Dữ liệu trong thùng rác đã bị mất.');
        $base=$this->basePath((string)$meta['root']);
        $parent=dirname((string)$meta['relative']);
        $destDir=($parent==='.'||$parent==='') ? $base : $this->ensureRelativeDir($base,$parent);
        $target=$destDir.'/'.$meta['name'];
        if(file_exists($target)) $target=$this->uniqueRestoreName($destDir,(string)$meta['name']);
        if(!@rename($itemPath,$target)) throw new RuntimeException('Không thể khôi phục.');
        $this->removeRecursive($this->trashDir.'/'.$id);
        return basename($target);
    }

    public function deleteTrashItem(string $id): void
    {
        $meta=$this->readTrashMeta($id);
        if($meta===null) throw new RuntimeException('Mục trong thùng rác không tồn tại.');
        $this->removeRecursive($this->trashDir.'/'.$id);
    }

    public function emptyTrash(): int
    {
        $n=0;
        foreach(@scandir($this->trashDir)?:[] as $id){
            if($id==='.'||$id==='..'||!preg_match('/^\d{8}_\d{6}_[a-f0-9]{16}$/',$id)) continue;
            $this->removeRecursive($this->trashDir.'/'.$id); $n++;
        }
        return $n;
    }

    public function trashCount(): int
    {
        $n=0;
        foreach(@scandir($this->trashDir)?:[] as $id){ if(preg_match('/^\d{8}_\d{6}_[a-f0-9]{16}$/',$id)) $n++; }
        return $n;
    }

    private function readTrashMeta(string $id): ?array
    {
        if(!preg_match('/^\d{8}_\d{6}_[a-f0-9]{16}$/',$id)) throw new RuntimeException('Mã thùng rác không hợp lệ.');
        $meta=json_decode((string)@file_get_contents($this->trashDir.'/'.$id.'/meta.json'),true);
        if(!is_array($meta)||($meta['id']??'')!==$id) return null;
        return $meta;
    }

    private function uniqueRestoreName(string $dir,string $name): string
    {
        $ext=pathinfo($name,PATHINFO_EXTENSION); $base=$ext!==''?substr($name,0,-strlen($ext)-1):$name;
        $stem=$base.' (khôi phục)'; $candidate=$dir.'/'.$stem.($ext!==''?'.'.$ext:''); $i=1;
        while(file_exists($candidate)){ $i++; $candidate=$dir.'/'.$stem.' '.$i.($ext!==''?'.'.$ext:''); }
        return $candidate;
    }

    /** Tạo (nếu thiếu) và trả về đường dẫn tuyệt đối của thư mục con trong root, chống traversal. */
    private function ensureRelativeDir(string $base,string $relative): string
    {
        $dir=$base;
        foreach(array_filter(explode('/',trim(str_replace('\\','/',$relative),'/'))) as $seg){
            $seg=$this->validName((string)$seg);
            $dir.='/'.$seg;
            if(!is_dir($dir)&&!@mkdir($dir,0700)) throw new RuntimeException('Không thể tạo thư mục.');
        }
        $real=realpath($dir);
        if($real===false||!$this->inside($real,$base)) throw new RuntimeException('Đường dẫn không hợp lệ.');
        return $real;
    }

    /* ================= Xem trước ảnh/media ================= */

    public function previewKind(string $name): ?string
    {
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        foreach($this->previewExtensions as $kind=>$list) if(in_array($ext,$list,true)) return $kind;
        return null;
    }

    public function preview(string $rootKey,string $relative): array
    {
        [$path]=$this->resolveExisting($rootKey,$relative);
        if(!is_file($path)||!is_readable($path)) throw new RuntimeException('Tệp không thể xem trước.');
        $kind=$this->previewKind($path);
        if($kind===null) throw new RuntimeException('Loại tệp này không hỗ trợ xem trước.');
        $size=@filesize($path)?:0;
        if($size>200*1024*1024) throw new RuntimeException('Tệp quá lớn để xem trước (giới hạn 200 MB).');
        return ['path'=>$path,'name'=>basename($path),'size'=>$size,'mime'=>$this->previewMime($path),'kind'=>$kind];
    }

    private function previewMime(string $path): string
    {
        $map=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp','ico'=>'image/x-icon','mp4'=>'video/mp4','webm'=>'video/webm','ogv'=>'video/ogg','mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','oga'=>'audio/ogg','m4a'=>'audio/mp4','flac'=>'audio/flac'];
        return $map[strtolower(pathinfo($path,PATHINFO_EXTENSION)) ]??'application/octet-stream';
    }

    /* ================= Remote download (tải từ URL) ================= */

    /**
     * Tải tệp từ URL về server. Chống SSRF: chỉ http/https, kiểm tra DNS/IP
     * từng hop redirect, chặn dải private/reserved. Giới hạn kích thước qua $maxBytes.
     */
    public function remoteDownload(string $rootKey,string $relative,string $url,int $maxBytes=536870912): array
    {
        $url=trim($url);
        if($url==='') throw new RuntimeException('Chưa nhập URL.');
        if(strlen($url)>2048) throw new RuntimeException('URL quá dài.');
        if(!function_exists('curl_init')) throw new RuntimeException('Máy chủ thiếu cURL.');
        $dir=$this->ensureRelativeDir($this->basePath($rootKey),$relative);
        if(!is_writable($dir)) throw new RuntimeException('Thư mục đích không có quyền ghi.');

        $current=$url; $seen=[];
        for($hop=0;$hop<6;$hop++){
            $this->assertSafeRemoteUrl($current);
            if(in_array($current,$seen,true)) throw new RuntimeException('URL chuyển hướng vòng lặp.');
            $seen[]=$current;
            $tmp=$dir.'/.'.bin2hex(random_bytes(8)).'.tms-remote';
            $headers=[];
            $ch=$this->remoteCurl($current,$tmp,$headers,$maxBytes);
            $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if($code>=300&&$code<400&&isset($headers['location'])){
                @unlink($tmp);
                $current=$this->resolveRedirectUrl($current,$headers['location']);
                continue;
            }
            if($code!==200){ @unlink($tmp); throw new RuntimeException('Tải thất bại (HTTP '.$code.').'); }
            $name=$this->remoteFileName($headers,$current);
            $target=$dir.'/'.$name;
            if(file_exists($target)) $target=$this->uniqueName($dir,$name);
            if(!@rename($tmp,$target)){ @unlink($tmp); throw new RuntimeException('Không thể lưu tệp tải về.'); }
            @chmod($target,0600);
            return ['name'=>basename($target),'size'=>@filesize($target)?:0];
        }
        throw new RuntimeException('URL chuyển hướng quá nhiều lần.');
    }

    private function remoteCurl(string $url,string $tmp,array &$headers,int $maxBytes)
    {
        $out=@fopen($tmp,'wb');
        if($out===false) throw new RuntimeException('Không thể tạo tệp tạm.');
        $ch=curl_init($url);
        $headers=[];
        $tooBig=false;
        curl_setopt_array($ch,[
            CURLOPT_FILE=>$out,
            CURLOPT_HEADERFUNCTION=>function($ch,$line) use (&$headers){
                $p=strpos($line,':');
                if($p!==false){
                    $k=strtolower(trim(substr($line,0,$p)));
                    if($k==='location'||$k==='content-disposition'||$k==='content-length') $headers[$k]=trim(substr($line,$p+1));
                }
                return strlen($line);
            },
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>20,
            CURLOPT_TIMEOUT=>600,
            CURLOPT_USERAGENT=>'TMS-OS RemoteDownload/1.0',
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
            CURLOPT_NOPROGRESS=>false,
            CURLOPT_PROGRESSFUNCTION=>function($ch,$dlTotal,$dlNow) use ($maxBytes,&$tooBig){ if($dlNow>$maxBytes){ $tooBig=true; return 1; } return 0; },
        ]);
        $ok=curl_exec($ch);
        fclose($out);
        if($tooBig){ curl_close($ch); @unlink($tmp); throw new RuntimeException('Tệp vượt giới hạn kích thước cho phép (512 MB).'); }
        if($ok===false){ $err=(string)curl_error($ch); curl_close($ch); @unlink($tmp); throw new RuntimeException('Không tải được URL: '.($err!==''?$err:'lỗi mạng')); }
        return $ch;
    }

    private function assertSafeRemoteUrl(string $url): void
    {
        $parts=parse_url($url);
        if(!is_array($parts)) throw new RuntimeException('URL không hợp lệ.');
        $scheme=strtolower((string)($parts['scheme']??''));
        if($scheme!=='http'&&$scheme!=='https') throw new RuntimeException('Chỉ hỗ trợ URL http/https.');
        $host=(string)($parts['host']??'');
        if($host==='') throw new RuntimeException('URL thiếu tên máy chủ.');
        if(isset($parts['user'])||isset($parts['pass'])) throw new RuntimeException('URL không được chứa thông tin đăng nhập.');
        $port=(int)($parts['port']??($scheme==='https'?443:80));
        if($port<1||$port>65535) throw new RuntimeException('Cổng URL không hợp lệ.');
        $this->assertPublicHost($host);
    }

    private function assertPublicHost(string $host): void
    {
        if(getenv('TMS_TEST_ALLOW_PRIVATE_IP')==='1') return; // chỉ dùng trong kiểm thử
        $ips=[];
        if(filter_var($host,FILTER_VALIDATE_IP)){
            $ips=[$host];
        }else{
            if(!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i',$host)) throw new RuntimeException('Tên máy chủ không hợp lệ.');
            $records=@dns_get_record($host,DNS_A|DNS_AAAA);
            if(empty($records)) throw new RuntimeException('Không phân giải được tên máy chủ.');
            foreach($records as $r){ if(!empty($r['ip'])) $ips[]=$r['ip']; if(!empty($r['ipv6'])) $ips[]=$r['ipv6']; }
            if(empty($ips)) throw new RuntimeException('Không phân giải được địa chỉ IP.');
        }
        foreach($ips as $ip){
            if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false)
                throw new RuntimeException('URL trỏ tới địa chỉ nội bộ/bảo lưu, bị chặn vì an toàn.');
        }
    }

    private function resolveRedirectUrl(string $base,string $location): string
    {
        $location=trim($location);
        if($location==='') throw new RuntimeException('Chuyển hướng không hợp lệ.');
        if(preg_match('#^https?://#i',$location)) return $location;
        $p=parse_url($base);
        $scheme=(string)($p['scheme']??'http'); $host=(string)($p['host']??''); $port=isset($p['port'])?':'.$p['port']:'';
        if(str_starts_with($location,'//')) return $scheme.':'.$location;
        if(str_starts_with($location,'/')) return $scheme.'://'.$host.$port.$location;
        $dir=rtrim(dirname((string)($p['path']??'/')),'/');
        return $scheme.'://'.$host.$port.$dir.'/'.$location;
    }

    private function remoteFileName(array $headers,string $url): string
    {
        $name='';
        if(!empty($headers['content-disposition'])){
            $cd=$headers['content-disposition'];
            if(preg_match("/filename\\*\\s*=\\s*UTF-8''([^;]+)/i",$cd,$m)) $name=rawurldecode(trim($m[1],"\"' "));
            elseif(preg_match('/filename\\s*=\\s*"([^"]+)"/i',$cd,$m)) $name=$m[1];
            elseif(preg_match('/filename\\s*=\\s*([^;]+)/i',$cd,$m)) $name=trim($m[1],"\"' ");
        }
        if($name==='') $name=basename((string)(parse_url($url,PHP_URL_PATH)??''));
        try{ $name=$this->validName($name); }catch(Throwable){ $name='tai-xuong'; }
        return $name;
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

