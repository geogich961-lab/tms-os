<?php
declare(strict_types=1);
final class PluginController
{
    public function __construct(private AuthService $auth, private PluginService $plugins) {}
    private function guard(): void { if (!$this->auth->check()) tms_redirect('/login'); }
    private function verify(): void { if (!tms_verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('Phiên không hợp lệ.'); }

    public function index(): void
    {
        $this->guard();
        tms_view('plugins.index', ['plugins'=>$this->plugins->catalog(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);
    }

    public function install(): void
    {
        $this->guard();
        try {
            $this->verify();
            $result=$this->plugins->install((string)($_POST['id']??''));
            tms_flash($result['ok']?'success':'error',$result['message']);
        } catch(Throwable $e) { tms_flash('error',$e->getMessage()); }
        tms_redirect('/packages');
    }

    public function remove(): void
    {
        $this->guard();
        try { $this->verify(); $result=$this->plugins->remove((string)($_POST['id']??'')); tms_flash($result['ok']?'success':'error',$result['message']); }
        catch(Throwable $e){ tms_flash('error',$e->getMessage()); }
        tms_redirect('/packages');
    }

    public function update(): void
    {
        $this->guard();
        try {
            $this->verify();
            $result=$this->plugins->updatePackages();
            tms_flash($result['ok']?'success':'error',$result['message']);
        } catch(Throwable $e) { tms_flash('error',$e->getMessage()); }
        tms_redirect('/packages');
    }
}
