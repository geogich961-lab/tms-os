<?php
declare(strict_types=1);
final class FileManagerController
{
    public function __construct(private AuthService $auth,private FileManagerService $files){}
    private function guard(): void { if(!$this->auth->check())tms_redirect('/login'); }
    public function index(): void { $this->guard();$root=(string)($_GET['root']??'websites');$path=(string)($_GET['path']??'');$q=trim((string)($_GET['q']??''));try{$listing=$this->files->browse($root,$path);if($q!=='')$listing['items']=$this->files->search($root,$path,$q);tms_view('files.index',['listing'=>$listing,'roots'=>$this->files->roots(),'query'=>$q,'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);}catch(Throwable $e){tms_flash('error',$e->getMessage());tms_redirect('/files');}}
    public function editor(): void { $this->guard();$root=(string)($_GET['root']??'websites');$file=(string)($_GET['file']??'');try{$doc=$this->files->read($root,$file);tms_view('files.editor',['document'=>$doc,'root'=>$root,'csrf'=>tms_csrf_token(),'flash'=>tms_pull_flash()]);}catch(Throwable $e){tms_flash('error',$e->getMessage());tms_redirect('/files');}}
    public function upload(): void { $this->mutate(function(){ $r=(string)($_POST['root']??'websites');$p=(string)($_POST['path']??'');$n=$this->files->upload($r,$p,$_FILES['upload']??[]);return ['Đã tải lên: '.$n,$r,$p];}); }
    public function uploadChunk(): void
    {
        $this->guard();
        if(!tms_verify_csrf($_POST['csrf']??null)){ $this->json(['ok'=>false,'message'=>'Phiên không hợp lệ.'],419); return; }
        try{
            $result=$this->files->uploadChunk((string)($_POST['root']??'websites'),(string)($_POST['path']??''),$_FILES['chunk']??[],(string)($_POST['upload_id']??''),(int)($_POST['chunk_index']??-1),(int)($_POST['total_chunks']??0),(string)($_POST['name']??''),(int)($_POST['total_size']??-1));
            $this->json(['ok'=>true]+$result);
        }catch(Throwable $e){ $this->json(['ok'=>false,'message'=>$e->getMessage()],400); }
    }
    public function completeUpload(): void
    {
        $this->guard();
        if(!tms_verify_csrf($_POST['csrf']??null)){ $this->json(['ok'=>false,'message'=>'Phiên không hợp lệ.'],419); return; }
        try{ $this->json(['ok'=>true]+$this->files->completeUpload((string)($_POST['upload_id']??''))); }
        catch(Throwable $e){ $this->json(['ok'=>false,'message'=>$e->getMessage()],400); }
    }
    public function create(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$p=(string)$_POST['path'];$this->files->create($r,$p,(string)$_POST['name'],(string)($_POST['kind']??'file')==='folder');return ['Đã tạo thành công.',$r,$p];}); }
    public function rename(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$rel=(string)$_POST['relative'];$this->files->rename($r,$rel,(string)$_POST['new_name']);return ['Đã đổi tên.',$r,dirname($rel)==='.'?'':dirname($rel)];}); }
    public function delete(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$rels=(array)($_POST['relatives']??[]); if(empty($rels))$rels[]=(string)($_POST['relative']??''); foreach($rels as $rel) if($rel!=='') $this->files->delete($r,$rel); return ['Đã xóa '.(count($rels)>1?count($rels).' mục':'thành công'),$r,dirname($rels[0]??'')==='.'?'':dirname($rels[0]??'')];}); }
    public function archive(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$rels=(array)($_POST['relatives']??[]); if(empty($rels))$rels[]=(string)($_POST['relative']??''); $n=$this->files->archiveBatch($r,$rels); return ['Đã tạo '.$n,$r,dirname($rels[0]??'')==='.'?'':dirname($rels[0]??'')];}); }
    public function extract(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$rel=(string)$_POST['relative'];$this->files->extract($r,$rel);return ['Đã giải nén.',$r,dirname($rel)==='.'?'':dirname($rel)];}); }
    public function chmod(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$rels=(array)($_POST['relatives']??[]); if(empty($rels))$rels[]=(string)($_POST['relative']??''); $rec=(string)($_POST['recursive']??'')==='1'; foreach($rels as $rel) if($rel!=='') $this->files->chmod($r,$rel,(string)$_POST['mode'],$rec); return ['Đã cập nhật quyền cho '.count($rels).' mục.',$r,dirname($rels[0]??'')==='.'?'':dirname($rels[0]??'')];}); }
    private function isDir(string $root,string $rel): bool { try{ $r=$this->files->browse($root,$rel); return $r['relative']===$rel && count($r['items'])>=0; }catch(Throwable $e){ return false; } }
    public function copy(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$rels=(array)($_POST['relatives']??[]); if(empty($rels))$rels[]=(string)($_POST['relative']??''); $target=(string)($_POST['target_path']??''); $ovr=(string)($_POST['overwrite']??'')==='1'; foreach($rels as $rel) if($rel!=='') $this->files->copy($r,$rel,$target,$ovr); return ['Đã sao chép '.count($rels).' mục.',$r,dirname($rels[0]??'')==='.'?'':dirname($rels[0]??'')];}); }
    public function move(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$rels=(array)($_POST['relatives']??[]); if(empty($rels))$rels[]=(string)($_POST['relative']??''); $dstRoot=(string)($_POST['target_root']??$r); $target=(string)($_POST['target_path']??''); foreach($rels as $rel){ if($rel==='')continue; if($dstRoot!==$r) $this->files->moveBetweenRoots($r,$rel,$dstRoot,$target); else $this->files->move($r,$rel,$target); } return ['Đã di chuyển '.count($rels).' mục.',$dstRoot,$target];}); }
    public function perms(): void { $this->guard();$r=(string)($_GET['root']??'websites');$rel=(string)($_GET['relative']??'');try{$p=$this->files->permissions($r,$rel);tms_view('files.perms',['root'=>$r,'relative'=>$rel,'perms'=>$p,'csrf'=>tms_csrf_token(),'flash'=>tms_pull_flash()]);}catch(Throwable $e){tms_flash('error',$e->getMessage());tms_redirect(tms_url('/files',['root'=>$r,'path'=>dirname($rel)==='.'?'':dirname($rel)]));} }
    public function applyPerms(): void { $this->mutate(function(){ $r=(string)$_POST['root'];$rel=(string)$_POST['relative'];$rec=(string)($_POST['recursive']??'')==='1'; $this->files->chmod($r,$rel,(string)$_POST['mode'],$rec); return ['Đã cập nhật quyền.',$r,dirname($rel)==='.'?'':dirname($rel)];}); }
    public function save(): void { $this->guard();if(!tms_verify_csrf($_POST['csrf']??null)){tms_flash('error','Phiên không hợp lệ.');tms_redirect('/files');}$r=(string)$_POST['root'];$f=(string)$_POST['file'];try{$this->files->save($r,$f,(string)($_POST['content']??''));tms_flash('success','Đã lưu tệp.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect(tms_url('/files/editor',['root'=>$r,'file'=>$f])); }
    public function download(): void { $this->guard();try{$d=$this->files->download((string)($_GET['root']??'websites'),(string)($_GET['file']??''));header('Content-Type: '.$d['mime']);header('Content-Length: '.$d['size']);header('Content-Disposition: attachment; filename="'.rawurlencode($d['name']).'"');readfile($d['path']);exit;}catch(Throwable $e){tms_flash('error',$e->getMessage());tms_redirect('/files');}}
    private function json(array $payload,int $status=200): void { http_response_code($status); header('Content-Type: application/json; charset=UTF-8'); echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    private function mutate(callable $cb): void { $this->guard();if(!tms_verify_csrf($_POST['csrf']??null)){tms_flash('error','Phiên không hợp lệ.');tms_redirect('/files');}try{[$m,$r,$p]=$cb();tms_flash('success',$m);}catch(Throwable $e){$r=(string)($_POST['root']??'websites');$p=(string)($_POST['path']??'');tms_flash('error',$e->getMessage());}tms_redirect(tms_url('/files',['root'=>$r,'path'=>$p])); }
}
