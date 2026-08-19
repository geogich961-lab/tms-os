<?php
declare(strict_types=1);
final class LogController
{
 public function __construct(private AuthService $auth,private LogService $logs){}
 public function index():void{if(!$this->auth->check())tms_redirect('/login');$selected=(string)($_GET['file']??'');try{$content=$selected!==''?$this->logs->read($selected):'';$error=null;}catch(Throwable $e){$content='';$error=$e->getMessage();}tms_view('logs.index',['files'=>$this->logs->files(),'selected'=>$selected,'content'=>$content,'error'=>$error,'csrf'=>tms_csrf_token()]);}
}
