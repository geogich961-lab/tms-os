<?php
declare(strict_types=1);
final class ModuleController
{
    public function __construct(private AuthService $auth, private ModuleService $modules) {}
    private function guard(): void { if (!$this->auth->check()) tms_redirect('/login'); }
    private function verify(): void { if (!tms_verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('Phiên không hợp lệ.'); }
    public function index(): void
    {
        $this->guard();
        tms_view('modules.index', ['modules'=>$this->modules->catalog(),'summary'=>$this->modules->summary(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);
    }
    public function toggle(): void
    {
        $this->guard();
        try { $this->verify(); $result=$this->modules->setEnabled((string)($_POST['id']??''), (string)($_POST['enabled']??'0')==='1'); tms_flash('success',$result['message']); }
        catch(Throwable $e){ tms_flash('error',$e->getMessage()); }
        tms_redirect('/modules');
    }
    public function repair(): void
    {
        $this->guard();
        try { $this->verify(); $result=$this->modules->repairState(); tms_flash('success',$result['message']); }
        catch(Throwable $e){ tms_flash('error',$e->getMessage()); }
        tms_redirect('/modules');
    }
}
