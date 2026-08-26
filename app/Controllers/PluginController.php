<?php
declare(strict_types=1);

final class PluginController
{
    public function __construct(private AuthService $auth, private PluginService $plugins) {}
    private function guard(): void { if (!$this->auth->check()) { tms_redirect('/login'); } }
    private function apiGuard(): bool { if ($this->auth->check()) { return true; } http_response_code(401); $this->json(['ok'=>false,'code'=>'AUTH_REQUIRED','error'=>'Phiên đăng nhập đã hết. Vui lòng đăng nhập lại để tiếp tục.']); return false; }
    private function isAjax(): bool { return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'); }
    private function verify(): void { $csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null); if (!tms_verify_csrf(is_string($csrf) ? $csrf : null)) { throw new RuntimeException('Phiên không hợp lệ.'); } }
    private function json(array $payload): void { header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store'); echo json_encode($payload, JSON_UNESCAPED_UNICODE); }

    public function index(): void { $this->guard(); tms_view('plugins.index', ['plugins'=>$this->plugins->catalog(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]); }
    public function status(): void { if (!$this->apiGuard()) { return; } try { $this->json($this->plugins->status()); } catch (Throwable $e) { http_response_code(500); $this->json(['ok'=>false,'error'=>'Không thể đọc trạng thái Runtime Package: '.$e->getMessage()]); } }

    public function install(): void
    {
        $ajax = $this->isAjax(); if ($ajax) { if (!$this->apiGuard()) { return; } } else { $this->guard(); }
        try {
            $this->verify(); $result = $this->plugins->enqueueInstall((string) ($_POST['id'] ?? ''));
            if ($ajax) { $this->json($result); return; }
            tms_flash('success', $result['message']);
        } catch (Throwable $e) {
            if ($ajax) { http_response_code(422); $this->json(['ok'=>false,'error'=>$e->getMessage()]); return; }
            tms_flash('error', $e->getMessage());
        }
        tms_redirect('/packages');
    }

    public function remove(): void { $this->guard(); try { $this->verify(); $r=$this->plugins->remove((string)($_POST['id']??'')); tms_flash($r['ok']?'success':'error',$r['message']); } catch(Throwable $e) { tms_flash('error',$e->getMessage()); } tms_redirect('/packages'); }
    public function update(): void { $this->guard(); try { $this->verify(); $r=$this->plugins->updatePackages(); tms_flash($r['ok']?'success':'error',$r['message']); } catch(Throwable $e) { tms_flash('error',$e->getMessage()); } tms_redirect('/packages'); }
}
