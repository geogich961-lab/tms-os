<?php
declare(strict_types=1);

final class AuthController
{
    public function __construct(private AuthService $auth)
    {
    }

    public function loginForm(): void
    {
        if ($this->auth->check()) {
            tms_redirect('/dashboard');
        }

        tms_view('auth.login', [
            'error' => '',
            'csrf' => tms_csrf_token(),
        ]);
    }

    public function login(): void
    {
        if (!tms_verify_csrf($_POST['csrf'] ?? null)) {
            tms_view('auth.login', [
                'error' => 'Phiên đăng nhập đã hết hạn.',
                'csrf' => tms_csrf_token(),
            ]);
            return;
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($this->auth->attempt($username, $password)) {
            tms_redirect('/dashboard');
        }

        tms_view('auth.login', [
            'error' => 'Sai tài khoản hoặc mật khẩu.',
            'csrf' => tms_csrf_token(),
        ]);
    }

    public function logout(): void
    {
        if (tms_verify_csrf($_POST['csrf'] ?? null)) {
            $this->auth->logout();
        }
        tms_redirect('/login');
    }
}
