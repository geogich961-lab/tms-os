<?php
declare(strict_types=1);
final class AppInstallerController
{
    public function __construct(private AuthService $auth,private AppInstallerService $installer){}
    private function guard():void{if(!$this->auth->check())tms_redirect('/login');}
    public function index():void{$this->guard();tms_view('apps.index',['catalog'=>$this->installer->catalog(),'installed'=>$this->installer->installed(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);}
    public function install():void
    {
        $this->guard();
        try{
            if(!tms_verify_csrf($_POST['csrf']??null))throw new RuntimeException('Phiên không hợp lệ.');
            $r=$this->installer->install($_POST);tms_flash('success',$r['message']);
        }catch(Throwable $e){tms_flash('error',$e->getMessage());}
        tms_redirect('/apps');
    }
}
