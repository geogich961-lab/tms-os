<?php
declare(strict_types=1);

final class DashboardController
{
    public function __construct(
        private AuthService $auth,
        private SystemService $system,
        private WebsiteService $websites,
        private NetworkService $network
    ) {
    }

    public function landing(): void
    {
        tms_view('dashboard.landing', [
            'build' => tms_config('build', 'V16.0.6'),
        ]);
    }

    public function index(): void
    {
        $this->guard();
        $sites = $this->websites->all();
        tms_view('dashboard.index', [
            'services' => $this->system->serviceStatus(),
            'metrics' => $this->system->metrics(),
            'sites' => $sites,
            'network' => $this->network->details($sites),
            'flash' => tms_pull_flash(),
            'csrf' => tms_csrf_token(),
            'pwaStatus' => 'new',
        ]);
    }

    public function action(): void
    {
        $this->guard();
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) {
            tms_flash('error', 'Phiên làm việc không hợp lệ.');
            tms_redirect('/');
        }
        $result = $this->system->action((string)($_POST['action'] ?? ''));
        tms_flash($result['ok'] ? 'success' : 'error', $result['message']);
        tms_redirect('/');
    }

    private function guard(): void
    {
        if (!$this->auth->check()) {
            tms_redirect('/login');
        }
    }
}
