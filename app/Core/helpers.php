<?php
declare(strict_types=1);

function tms_config(string $key, mixed $default = null): mixed
{
    static $config;
    if ($config === null) {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
    }
    return $config[$key] ?? $default;
}

function tms_h(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    } elseif (!is_scalar($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tms_view(string $view, array $data = []): void
{
    $base = dirname(__DIR__) . '/Views/';
    $file = $base . str_replace('.', '/', $view) . '.php';

    if (!is_file($file)) {
        http_response_code(500);
        echo 'Không tìm thấy giao diện: ' . tms_h($view);
        return;
    }

    extract($data, EXTR_SKIP);
    require $file;
}

function tms_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function tms_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function tms_verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function tms_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    // Lưu bền vững ra file ngoài target để không mất khi target bị swap (rollback/update)
    $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
    $file = rtrim($home, '/') . '/.tms-os/flash.json';
    @file_put_contents($file, json_encode(['type' => $type, 'message' => $message], JSON_UNESCAPED_UNICODE));
    @chmod($file, 0600);
}

function tms_pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if (!is_array($flash)) {
        // Đọc lại từ file dự phòng (bền vững qua swap target)
        $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $file = rtrim($home, '/') . '/.tms-os/flash.json';
        if (is_file($file)) {
            $data = @json_decode((string)@file_get_contents($file), true);
            @unlink($file);
            if (is_array($data) && isset($data['type'])) {
                $flash = $data;
            }
        }
    }
    return is_array($flash) ? $flash : null;
}


function tms_url(string $path, array $query = []): string
{
    return $path . ($query ? '?' . http_build_query($query) : '');
}

function tms_format_bytes(int|float $bytes): string
{
    $bytes = max(0, (float)$bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $bytes > 0 ? min((int)floor(log($bytes, 1024)), count($units) - 1) : 0;
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2) . ' ' . $units[$power];
}


function tms_ui_defaults(): array
{
    return [
        'accent' => '#315ee8',
        'accent_secondary' => '#6b4dea',
        'pwa_background' => '#0a1220',
        'default_theme' => 'light',
    ];
}

function tms_ui_settings(): array
{
    $defaults = tms_ui_defaults();
    $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
    $file = $home . '/.tms-os/ui-settings.json';
    $saved = @json_decode((string)@file_get_contents($file), true);
    if (!is_array($saved)) {
        return $defaults;
    }
    return array_merge($defaults, array_intersect_key($saved, $defaults));
}

function tms_valid_hex_color(mixed $value, string $fallback): string
{
    $value = strtolower(trim((string)$value));
    return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $fallback;
}

function tms_hex_rgb(string $hex): string
{
    $hex = ltrim($hex, '#');
    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
}

/**
 * Trả về đường dẫn icon thương hiệu (logo tùy chỉnh nếu người dùng đã thay).
 */
function tms_brand_icon(string $size): string
{
    $sizes = ['192' => 'icon-192.png', '512' => 'icon-512.png', 'maskable-512' => 'icon-maskable-512.png', 'splash' => 'logo-splash.png', 'logo' => 'logo-tms-os.png'];
    $file = $sizes[$size] ?? $sizes['192'];
    $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
    $brandFile = $home . '/.tms-os/brand/' . $file;
    if (is_file($brandFile)) {
        // Kiểm theo document root (môi trường web) trước; fallback vào target public/
        $web = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/icons/' . $file;
        $alt = $home . '/tms-os/public/assets/icons/' . $file;
        if (is_file($web) || is_file($alt)) {
            $ts = (int)filemtime($brandFile);
            return '/assets/icons/' . $file . '?v=' . $ts;
        }
    }
    return '/assets/icons/' . $file . '?v=1';
}

/**
 * Validate kích thước logo upload: min/max dimensions, loại file, dung lượng.
 */
function tms_validate_logo_upload(array $file, int $maxBytes = 2097152): array
{
    if (!is_array($file) || !empty($file['error'])) {
        return ['ok' => false, 'message' => 'Vui lòng chọn một tệp hình ảnh.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'Tệp quá lớn: giới hạn 2 MB.'];
    }
    $info = getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'message' => 'Tệp không phải hình ảnh hợp lệ.'];
    }
    if (!in_array((int)$info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
        return ['ok' => false, 'message' => 'Chỉ chấp nhận PNG, JPG hoặc WebP.'];
    }
    $w = (int)$info[0];
    $h = (int)$info[1];
    $min = 128;
    $max = 2048;
    if ($w < $min || $h < $min) {
        return ['ok' => false, 'message' => "Hình quá nhỏ: kích thước tối thiểu {$min}x{$min}px (hiện tại {$w}x{$h}px). Khuyến nghị 512x512px."];
    }
    if ($w > $max || $h > $max) {
        return ['ok' => false, 'message' => "Hình quá lớn: kích thước tối đa {$max}x{$max}px (hiện tại {$w}x{$h}px)."];
    }
    return ['ok' => true, 'width' => $w, 'height' => $h, 'type' => (int)$info[2]];
}

/**
 * Phiên bản cache của tài nguyên (CSS/JS/manifest/icon).
 * Đọc từ platform build "Platform V14.x.y" trong config/app.php, kết hợp
 * với số bust thủ công lưu trong ~/.tms-os/asset-version.json (khi người dùng
 * bấm "Xóa cache" — tự tăng thêm 1 để trình duyệt bỏ cache ngay lập tức).
 */
function tms_asset_version(): string
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }
    $version = '1';
    $conf = @include dirname(__DIR__, 2) . '/config/app.php';
    if (is_array($conf) && isset($conf['build']) && preg_match('/V(\d+\.\d+\.\d+)$/', (string)$conf['build'], $m)) {
        $version = $m[1];
    }
    $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
    $bust = @json_decode((string)@file_get_contents($home . '/.tms-os/asset-version.json'), true);
    if (is_array($bust) && isset($bust['bust'])) {
        $version .= '.' . (int)$bust['bust'];
    }
    return $version;
}

/**
 * Xóa cache máy chủ: session cũ (giữ session đang đăng nhập), storage/cache,
 * flash file cũ, và opcache (nếu có). Sau đó tăng số bust thủ công để trình
 * duyệt/PWA tải lại toàn bộ CSS/JS/icon ở phiên bản mới.
 */
function tms_clear_cache(): array
{
    $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
    $appRoot = dirname(__DIR__, 2);
    $removed = ['sessions' => 0, 'cache' => 0, 'files' => 0];

    // 1. Session cũ: xóa mọi session ngoại trừ session đang đăng nhập
    $current = session_id();
    $sessionDir = $appRoot . '/storage/sessions';
    if (is_dir($sessionDir)) {
        foreach (glob($sessionDir . '/sess_*') ?: [] as $f) {
            if ($current === '' || basename($f) !== 'sess_' . $current) {
                @unlink($f);
                $removed['sessions']++;
            }
        }
    }
    // 2. Cache tạm
    $cacheDir = $appRoot . '/storage/cache';
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/*') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
                $removed['cache']++;
            }
        }
    }
    // 3. Flash file cũ
    @unlink($home . '/.tms-os/flash.json');

    // 4. OPcache (nếu có)
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    // 5. Tăng số bust thủ công để trình duyệt tải lại CSS/JS/icon mới
    $bustFile = $home . '/.tms-os/asset-version.json';
    $bust = @json_decode((string)@file_get_contents($bustFile), true);
    $next = (is_array($bust) && isset($bust['bust']) ? (int)$bust['bust'] : 0) + 1;
    @mkdir(dirname($bustFile), 0700, true);
    file_put_contents($bustFile, json_encode(['bust' => $next]), LOCK_EX);
    @chmod($bustFile, 0600);

    $total = $removed['sessions'] + $removed['cache'] + $removed['files'];
    return [
        'ok' => true,
        'removed' => $total,
        'asset_version' => tms_asset_version(),
    ];
}
