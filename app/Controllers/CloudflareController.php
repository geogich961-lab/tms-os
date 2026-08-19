<?php
declare(strict_types=1);

final class CloudflareController
{
    public function __construct(private AuthService $auth, private CloudflareService $cloudflare, private WebsiteService $websites) {}
    private function guard(): void { if(!$this->auth->check()) tms_redirect('/login'); }
    public function index(): void
    {
        $this->guard();
        tms_view('cloudflare.index', ['status'=>$this->cloudflare->status(),'sites'=>$this->websites->all(),'flash'=>tms_pull_flash(),'csrf'=>tms_csrf_token()]);
    }
    public function start(): void
    {
        $this->guard();
        if(!tms_verify_csrf($_POST['csrf']??null)){tms_flash('error','Phiên không hợp lệ.');tms_redirect('/internet-access');}
        try{
            $this->cloudflare->start((string)($_POST['target']??''),(string)($_POST['provider']??'auto'),(string)($_POST['protocol']??'auto'));
            tms_flash('success','Đã khởi động Internet Access Engine. Hệ thống sẽ tự chuyển nhà cung cấp nếu kết nối hiện tại thất bại.');
        }catch(Throwable $e){tms_flash('error',$e->getMessage());}
        tms_redirect('/internet-access');
    }
    public function stop(): void
    {
        $this->guard(); if(!tms_verify_csrf($_POST['csrf']??null))tms_redirect('/internet-access');
        $this->cloudflare->stop(); tms_flash('success','Đã dừng kết nối Internet.'); tms_redirect('/internet-access');
    }
    public function settings(): void
    {
        $this->guard(); if(!tms_verify_csrf($_POST['csrf']??null))tms_redirect('/internet-access');
        $this->cloudflare->saveSettings($_POST); tms_flash('success','Đã lưu cấu hình Ngrok và TMS Relay.'); tms_redirect('/internet-access');
    }
    public function status(): void
    {
        $this->guard(); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        echo json_encode($this->cloudflare->status(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
