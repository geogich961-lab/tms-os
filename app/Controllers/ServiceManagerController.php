<?php
declare(strict_types=1);
final class ServiceManagerController
{
    public function __construct(private AuthService $auth,private ServiceManagerService $services){}
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
    private function verify():void{if(!tms_verify_csrf($_POST['csrf']??null))throw new RuntimeException('Phiên không hợp lệ.');}
    public function index():void{$this->guard();$data=$this->services->snapshot();tms_view('services.index',['services'=>$data['services'],'summary'=>$data['summary'],'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);}
    public function action():void{$this->guard();try{$this->verify();$r=$this->services->action((string)($_POST['id']??''),(string)($_POST['action']??''));tms_flash($r['ok']?'success':'error',$r['message']);}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/services');}
    public function restartAll():void{$this->guard();try{$this->verify();$r=$this->services->restartAll();$lines=[$r['message']];foreach($r['results'] as $item)$lines[]=$item['name'].': '.(!empty($item['ok'])?'OK':'LỖI').' · '.$item['message'];tms_flash($r['ok']?'success':'error',implode("\n",$lines));}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/services');}
    public function autostart():void{$this->guard();try{$this->verify();$r=$this->services->setAutostart((string)($_POST['id']??''),!empty($_POST['enabled']));tms_flash('success',$r['message']);}catch(Throwable $e){tms_flash('error',$e->getMessage());}tms_redirect('/services');}
    public function log():void{$this->guard();header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');echo $this->services->log((string)($_GET['id']??''),(int)($_GET['lines']??120));}
    public function api():void{$this->guard();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($this->services->snapshot(),JSON_UNESCAPED_UNICODE);}
}
