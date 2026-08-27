<?php
declare(strict_types=1);

final class AuthController
{
    public function __construct(private AuthService $auth)
    {
    }

    public function loginForm(): void
    {
        $next = $this->safeNextPath($_GET['next'] ?? null);
        if ($this->auth->check()) {
            tms_redirect($next);
        }

        tms_view('auth.login', [
            'error' => '',
            'csrf' => tms_csrf_token(),
            'next' => $next,
            'notice' => ($_GET['reason'] ?? '') === 'update-restart'
                ? 'TMS OS đã khởi động lại sau cập nhật. Vui lòng đăng nhập lại để tiếp tục.'
                : '',
        ]);
    }

    public function login(): void
    {
        $next = $this->safeNextPath($_POST['next'] ?? null);
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) {
            tms_view('auth.login', [
                'error' => 'Phiên đăng nhập đã hết hạn.',
                'csrf' => tms_csrf_token(),
                'next' => $next,
                'notice' => '',
            ]);
            return;
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($this->auth->attempt($username, $password)) {
            tms_redirect($next);
        }

        tms_view('auth.login', [
            'error' => 'Sai tài khoản hoặc mật khẩu.',
            'csrf' => tms_csrf_token(),
            'next' => $next,
            'notice' => '',
        ]);
    }

    private function safeNextPath(mixed $next): string
    {
        $path = trim((string)$next);
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, "\r") || str_contains($path, "\n")) {
            return '/dashboard';
        }
        return $path;
    }

    public function logout(): void
    {
        if (tms_verify_csrf($_POST['csrf'] ?? null)) {
            $this->auth->logout();
        }
        tms_redirect('/login');
    }
}
