<?php
declare(strict_types=1);
final class BackupController
{
 public function __construct(private AuthService $auth,private BackupService $backups,private WebsiteService $sites,private AutoBackupService $auto){}
 private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
 private function csrf():void{if(!tms_verify_csrf($_POST['csrf']??null))throw new RuntimeException('Phiên không hợp lệ.');}
 public function index():void{$this->guard();tms_view('backups.index',['backups'=>$this->backups->all(),'sites'=>$this->sites->all(),'auto'=>$this->auto->status(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);}
 public function autoSave():void{$this->guard();try{$this->csrf();$config=$this->auto->saveConfig($_POST);tms_flash('success',$config['enabled']?'Đã bật backup tự động lúc '.$config['time'].' hằng ngày.':'Đã tắt backup tự động.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/backups');}
 public function autoRun():void{$this->guard();try{$this->csrf();$r=$this->auto->runNow();tms_flash($r['ok']?'success':'error',(string)($r['message']??'Kết quả không xác định.'));}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/backups');}
 public function create():void{$this->guard();try{$this->csrf();$m=$this->backups->create((string)($_POST['scope']??'system'),(string)($_POST['website']??''),(string)($_POST['note']??''),!empty($_POST['locked']));tms_flash('success',$m);}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/backups');}
 public function restore():void{$this->guard();try{$this->csrf();tms_flash('success',$this->backups->restore((string)($_POST['id']??'')));}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/backups');}
 public function lock():void{$this->guard();try{$this->csrf();$locked=$this->backups->toggleLock((string)($_POST['id']??''));tms_flash('success',$locked?'Đã khóa snapshot.':'Đã mở khóa snapshot.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/backups');}
 public function delete():void{$this->guard();try{$this->csrf();$this->backups->delete((string)($_POST['id']??''));tms_flash('success','Đã xóa backup.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/backups');}
 public function download():void{$this->guard();try{$p=$this->backups->file((string)($_GET['id']??''));header('Content-Type: application/gzip');header('Content-Length: '.filesize($p));header('Content-Disposition: attachment; filename="'.rawurlencode(basename($p)).'"');readfile($p);exit;}catch(Throwable $e){tms_flash('error',$e->getMessage());tms_redirect('/backups');}}
}
