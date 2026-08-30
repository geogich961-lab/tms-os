<?php
declare(strict_types=1);

final class AuthService
{
    private array $credentials;
    private string $credentialsFile;
    private string $throttleFile;
    private const THROTTLE_MAX_ATTEMPTS = 5;
    private const THROTTLE_WINDOW_SECONDS = 900;
    private const THROTTLE_LOCK_SECONDS = 900;

    public function __construct()
    {
        $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $newFile = $home . '/.tms-os/config/panel-secret.php';
        $legacyFile = $home . '/.redmi-mini-vps/config/panel-secret.php';
        $this->throttleFile = $home . '/.tms-os/config/login-throttle.json';

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
                $file = $newFile;
            }
        }

        $this->credentials = $credentials;
        $this->credentialsFile = $file;
    }

    public function attempt(string $username, string $password): bool
    {
        // Khoá vững cả khi ai đó gọi thẳng attempt() bỏ qua view đăng nhập.
        if ($this->lockedFor() > 0) {
            return false;
        }
        $valid = hash_equals((string)($this->credentials['username'] ?? ''), $username)
            && password_verify($password, (string)($this->credentials['password_hash'] ?? ''));

        if ($valid) {
            $this->writeThrottle(['count' => 0, 'window_started_at' => 0, 'locked_until' => 0]);
            session_regenerate_id(true);
            $_SESSION['tms_authenticated'] = true;
            $_SESSION['tms_username'] = $username;
            return true;
        }

        $this->recordFailedAttempt();
        return false;
    }

    /** Số giây còn bị khoá đăng nhập sau chuỗi sai liên tiếp; 0 nếu không bị khoá. */
    public function lockedFor(): int
    {
        return max(0, (int)($this->readThrottle()['locked_until'] ?? 0) - time());
    }

    private function recordFailedAttempt(): void
    {
        $state = $this->readThrottle();
        $now = time();
        $count = (int)($state['count'] ?? 0);
        $startedAt = (int)($state['window_started_at'] ?? 0);
        if ($count <= 0 || ($now - $startedAt) > self::THROTTLE_WINDOW_SECONDS) {
            $count = 0;
            $startedAt = $now;
        }
        $count++;
        $state = [
            'count' => $count,
            'window_started_at' => $startedAt,
            'locked_until' => $count >= self::THROTTLE_MAX_ATTEMPTS ? $now + self::THROTTLE_LOCK_SECONDS : 0,
        ];
        $this->writeThrottle($state);
    }

    private function readThrottle(): array
    {
        $data = @json_decode((string)@file_get_contents($this->throttleFile), true);
        return is_array($data) ? $data : [];
    }

    private function writeThrottle(array $data): void
    {
        $dir = dirname($this->throttleFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $tmp = $this->throttleFile . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($data) . "\n", LOCK_EX) !== false) {
            @chmod($tmp, 0600);
            @rename($tmp, $this->throttleFile);
        } else {
            @unlink($tmp);
        }
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

    /**
     * Đổi mật khẩu nhưng giữ nguyên username đã được cài đặt. Việc ghi dùng file
     * tạm và rename cùng filesystem để tránh tạo file secret dở dang khi mất điện.
     */
    public function changePassword(string $password): void
    {
        $username = (string)($this->credentials['username'] ?? '');
        if ($username === '') {
            throw new RuntimeException('Tài khoản quản trị không hợp lệ.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Không thể tạo hash mật khẩu mới.');
        }

        $data = "<?php\nreturn ['username'=>" . var_export($username, true)
            . ",'password_hash'=>" . var_export($hash, true) . "];\n";
        $tmp = $this->credentialsFile . '.tmp-' . bin2hex(random_bytes(8));
        if (@file_put_contents($tmp, $data, LOCK_EX) === false) {
            throw new RuntimeException('Không thể lưu mật khẩu mới.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $this->credentialsFile)) {
            @unlink($tmp);
            throw new RuntimeException('Không thể kích hoạt mật khẩu mới.');
        }
        @chmod($this->credentialsFile, 0600);
        $this->credentials['password_hash'] = $hash;
    }
}
