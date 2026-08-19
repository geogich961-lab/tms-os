<?php
declare(strict_types=1);
final class NetworkController
{
    public function __construct(private AuthService $auth, private NetworkService $network, private WebsiteService $websites) {}
    public function index(): void
    {
        $this->guard();
        tms_view('network.index', [
            'network' => $this->network->details($this->websites->all()),
            'flash' => tms_pull_flash(),
            'csrf' => tms_csrf_token(),
        ]);
    }
    private function guard(): void { if (!$this->auth->check()) tms_redirect('/login'); }
}
