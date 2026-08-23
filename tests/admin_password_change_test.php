<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-os-admin-password-' . bin2hex(random_bytes(4));
$home = $base . '/home';
mkdir($home . '/.tms-os/config', 0700, true);
putenv('HOME=' . $home);

function tms_verify_csrf(mixed $token): bool { return $token === 'test-csrf'; }
function tms_flash(string $type, string $message): void { $GLOBALS['tms_test_flash'] = [$type, $message]; }
function tms_redirect(string $path): void { $GLOBALS['tms_test_redirect'] = $path; }

$initial = password_hash('old-password-for-test', PASSWORD_DEFAULT);
file_put_contents(
    $home . '/.tms-os/config/panel-secret.php',
    "<?php\nreturn ['username'=>'taone','password_hash'=>" . var_export($initial, true) . "];\n"
);
chmod($home . '/.tms-os/config/panel-secret.php', 0600);

require $root . '/app/Services/AuthService.php';
require $root . '/app/Controllers/SettingsController.php';

session_start();
$_SESSION = ['tms_authenticated' => true, 'tms_username' => 'taone'];
$_POST = [
    'csrf' => 'test-csrf',
    'new_password' => 'new-password-for-test',
    'confirm_password' => 'new-password-for-test',
];

(new SettingsController(new AuthService()))->password();
$fresh = new AuthService();
$keptUsername = $fresh->attempt('taone', 'new-password-for-test');
$wrongUsername = (new AuthService())->attempt('admin', 'new-password-for-test');
$oldPassword = (new AuthService())->attempt('taone', 'old-password-for-test');
$flash = $GLOBALS['tms_test_flash'] ?? [];
$redirect = $GLOBALS['tms_test_redirect'] ?? '';
$secretFile = $home . '/.tms-os/config/panel-secret.php';
$stored = (string)file_get_contents($secretFile);
$mode = fileperms($secretFile) & 0777;
$temporaryFiles = glob($secretFile . '.tmp-*') ?: [];

exec('rm -rf ' . escapeshellarg($base));
$ok = $keptUsername && !$wrongUsername && !$oldPassword
    && $flash === ['success', 'Đã đổi mật khẩu.'] && $redirect === '/settings' && $mode === 0600
    && !str_contains($stored, 'old-password-for-test') && !str_contains($stored, 'new-password-for-test')
    && $temporaryFiles === [];
if (!$ok) {
    fwrite(STDERR, "Admin password change test failed.\n");
    exit(1);
}
echo "Admin password change test passed.\n";
