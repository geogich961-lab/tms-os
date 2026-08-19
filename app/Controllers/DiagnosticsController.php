<?php
declare(strict_types=1);
final class DiagnosticsController
{
 public function __construct(private AuthService $auth,private DiagnosticsService $diagnostics){}
 public function index():void{$this->guard();tms_view('diagnostics.index',['checks'=>$this->diagnostics->checks(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);}
 public function repair():void{$this->guard();if(!tms_verify_csrf($_POST['csrf']??null)){tms_flash('error','Phiên không hợp lệ.');tms_redirect('/diagnostics');}$r=$this->diagnostics->repair();tms_flash($r['ok']?'success':'error',$r['message']);tms_redirect('/diagnostics');}
 private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
}
