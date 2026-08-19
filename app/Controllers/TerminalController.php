<?php
declare(strict_types=1);
final class TerminalController
{
    public function __construct(private AuthService $auth, private TerminalService $terminal) {}
    public function index(): void { $this->guard(); tms_view('terminal.index',['commands'=>$this->terminal->commands(),'result'=>$_SESSION['terminal_result']??null,'csrf'=>tms_csrf_token()]); unset($_SESSION['terminal_result']); }
    public function run(): void { $this->guard(); if(!tms_verify_csrf($_POST['csrf']??null)){tms_flash('error','Phiên làm việc không hợp lệ.');tms_redirect('/terminal');} $_SESSION['terminal_result']=$this->terminal->run((string)($_POST['command']??'')); tms_redirect('/terminal'); }
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
}
