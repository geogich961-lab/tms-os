<?php
declare(strict_types=1);
final class UpdateController
{
    public function __construct(private AuthService $auth,private UpdateService $updates){}
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
    private function verify():void{if(!tms_verify_csrf($_POST['csrf']??null))throw new RuntimeException('Phiên không hợp lệ.');}
    public function index():void{$this->guard();tms_view('updates.index',['items'=>$this->updates->staged(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);}
    public function stage():void{$this->guard();try{$this->verify();$r=$this->updates->stage($_FILES['package']??[]);tms_flash('success',$r['message']);}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/updates');}
    public function delete():void{$this->guard();try{$this->verify();$this->updates->delete((string)($_POST['name']??''));tms_flash('success','Đã xóa gói cập nhật.');}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/updates');}
}
