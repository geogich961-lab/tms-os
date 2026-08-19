<?php
declare(strict_types=1);

final class AuthService
{
    private array $credentials;

    public function __construct()
    {
        $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $newFile = $home . '/.tms-os/config/panel-secret.php';
        $legacyFile = $home . '/.redmi-mini-vps/config/panel-secret.php';

        // Đường dẫn mới ưu tiên; vẫn tương thích ngược với bản cài cũ.
        $file = is_file($newFile) ? $newFile : $legacyFile;

        if (!is_file($file)) {
            throw new RuntimeException('Thiếu tệp tài khoản quản trị.');
        }

        $credentials = require $file;
        if (!is_array($credentials)) {
            throw new RuntimeException('Tệp tài khoản quản trị không hợp lệ.');
        }

        // Tự động di chuyển (migration) từ đường dẫn cũ sang đường dẫn mới.
        if ($file === $legacyFile && is_file($legacyFile) && !is_file($newFile)) {
            $dir = dirname($newFile);
            @mkdir($dir, 0700, true);
            @chmod($dir, 0700);
            if (@copy($legacyFile, $newFile)) {
                @chmod($newFile, 0600);
            }
        }

        $this->credentials = $credentials;
    }

    public function attempt(string $username, string $password): bool
    {
        $valid = hash_equals((string)($this->credentials['username'] ?? ''), $username)
            && password_verify($password, (string)($this->credentials['password_hash'] ?? ''));

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['tms_authenticated'] = true;
            $_SESSION['tms_username'] = $username;
        }

        return $valid;
    }

    public function check(): bool
    {
        return !empty($_SESSION['tms_authenticated']);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }
        session_destroy();
    }
}
