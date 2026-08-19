<?php
declare(strict_types=1);
final class GuardianController
{
    public function __construct(private AuthService $auth,private GuardianService $guardian){}
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
    private function verify():void{if(!tms_verify_csrf($_POST['csrf']??null))throw new RuntimeException('Phiên không hợp lệ.');}
    public function index():void{$this->guard();tms_view('guardian.index',['data'=>$this->guardian->dashboard(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);}
    public function api():void{$this->guard();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($this->guardian->api(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    public function action():void{$this->guard();try{$this->verify();$r=$this->guardian->action((string)($_POST['action']??''));tms_flash($r['ok']?'success':'error',$r['message']);}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/guardian');}
    public function settings():void{$this->guard();try{$this->verify();$r=$this->guardian->saveConfig($_POST);tms_flash('success',$r['message']);}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/guardian');}
}
