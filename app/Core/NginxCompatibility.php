<?php
declare(strict_types=1);

/**
 * Bổ sung cấu hình hash cho server_name trên các bản TMS OS cũ.
 *
 * V17.0.20 có thể tạo nhiều hostname .localhost/.lan/sslip.io nhưng nginx.conf
 * chưa đặt server_names_hash_bucket_size, khiến nginx -t lỗi trên một số máy.
 */
function tms_repair_nginx_server_names_hash(?string $configPath = null): array
{
    $prefix = getenv('PREFIX') ?: '/data/data/com.termux/files/usr';
    $configPath ??= $prefix . '/etc/nginx/nginx.conf';

    if (!is_file($configPath)) {
        return ['ok' => false, 'changed' => false, 'message' => 'Không tìm thấy nginx.conf.'];
    }

    $content = @file_get_contents($configPath);
    if (!is_string($content) || $content === '') {
        return ['ok' => false, 'changed' => false, 'message' => 'Không thể đọc nginx.conf.'];
    }

    $needBucket = preg_match('/\bserver_names_hash_bucket_size\s+\d+\s*;/', $content) !== 1;
    $needMax = preg_match('/\bserver_names_hash_max_size\s+\d+\s*;/', $content) !== 1;
    if (!$needBucket && !$needMax) {
        return ['ok' => true, 'changed' => false, 'message' => 'Cấu hình server_names_hash đã sẵn sàng.'];
    }

    $lines = [];
    if ($needBucket) {
        $lines[] = '  server_names_hash_bucket_size 128;';
    }
    if ($needMax) {
        $lines[] = '  server_names_hash_max_size 4096;';
    }

    $replacement = "$0\n" . implode("\n", $lines);
    $updated = preg_replace('/\bhttp\s*\{/', $replacement, $content, 1, $count);
    if (!is_string($updated) || $count !== 1) {
        return ['ok' => false, 'changed' => false, 'message' => 'Không tìm thấy block http trong nginx.conf.'];
    }

    $dir = dirname($configPath);
    $tmp = $dir . '/.nginx.conf.tms-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $updated, LOCK_EX) === false) {
        return ['ok' => false, 'changed' => false, 'message' => 'Không thể ghi nginx.conf tạm thời.'];
    }

    $mode = @fileperms($configPath);
    if (is_int($mode)) {
        @chmod($tmp, $mode & 0777);
    }
    if (!@rename($tmp, $configPath)) {
        @unlink($tmp);
        return ['ok' => false, 'changed' => false, 'message' => 'Không thể thay thế nginx.conf.'];
    }

    return ['ok' => true, 'changed' => true, 'message' => 'Đã bổ sung server_names_hash cho Nginx.'];
}

/**
 * Gỡ service-worker.js của panel khỏi rule cache file tĩnh 1 năm.
 *
 * Block panel trong nginx.conf gắn "expires 1y + immutable" cho mọi file .js,
 * kể cả /service-worker.js. File này quyết định trình duyệt có thấy "bản giao
 * diện mới" hay không nên tuyệt đối không được cache dài: khi các lớp cache
 * (trình duyệt, Cloudflare) ghim những bản service worker khác nhau, panel sẽ
 * bật banner "Có phiên bản giao diện mới" lặp vô hạn. Hàm này chèn một
 * location khớp chính xác với Cache-Control "no-cache" ngay trước location
 * regex cache tĩnh trong block panel (listen 127.0.0.1:8888). Idempotent:
 * đã có location riêng thì không chèn lại.
 */
function tms_repair_nginx_service_worker_cache(?string $configPath = null): array
{
    $prefix = getenv('PREFIX') ?: '/data/data/com.termux/files/usr';
    $configPath ??= $prefix . '/etc/nginx/nginx.conf';

    if (!is_file($configPath)) {
        return ['ok' => false, 'changed' => false, 'message' => 'Không tìm thấy nginx.conf.'];
    }

    $content = @file_get_contents($configPath);
    if (!is_string($content) || $content === '') {
        return ['ok' => false, 'changed' => false, 'message' => 'Không thể đọc nginx.conf.'];
    }

    if (str_contains($content, 'location = /service-worker.js')) {
        return ['ok' => true, 'changed' => false, 'message' => 'Cache cho service-worker.js đã đúng (no-cache).'];
    }

    $panelPos = strpos($content, 'listen 127.0.0.1:8888');
    if ($panelPos === false) {
        return ['ok' => false, 'changed' => false, 'message' => 'Không tìm thấy block panel (listen 127.0.0.1:8888) trong nginx.conf.'];
    }

    $locPos = strpos($content, 'location ~*', $panelPos);
    if ($locPos === false) {
        return ['ok' => false, 'changed' => false, 'message' => 'Không tìm thấy location cache file tĩnh trong block panel.'];
    }

    // Location tìm được phải vẫn nằm trong block panel (không có server block
    // mới nào xen giữa) và đúng là rule immutable 1 năm.
    $between = substr($content, $panelPos, $locPos - $panelPos);
    if (str_contains($between, "\nserver {")) {
        return ['ok' => false, 'changed' => false, 'message' => 'Location cache file tĩnh không nằm trong block panel.'];
    }
    if (!str_contains(substr($content, $locPos, 400), 'immutable')) {
        return ['ok' => false, 'changed' => false, 'message' => 'Location cache file tĩnh của panel không phải rule immutable.'];
    }

    $lineStart = strrpos(substr($content, 0, $locPos), "\n");
    $insertAt = $lineStart === false ? $locPos : $lineStart + 1;

    $block = "  # Service worker phải luôn tươi, không được cache 1 năm như file tĩnh.\n"
        . "  location = /service-worker.js {\n"
        . "    add_header Cache-Control \"no-cache\";\n"
        . "    access_log off;\n"
        . "  }\n";
    $updated = substr($content, 0, $insertAt) . $block . substr($content, $insertAt);

    $dir = dirname($configPath);
    $tmp = $dir . '/.nginx.conf.tms-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $updated, LOCK_EX) === false) {
        return ['ok' => false, 'changed' => false, 'message' => 'Không thể ghi nginx.conf tạm thời.'];
    }

    $mode = @fileperms($configPath);
    if (is_int($mode)) {
        @chmod($tmp, $mode & 0777);
    }
    if (!@rename($tmp, $configPath)) {
        @unlink($tmp);
        return ['ok' => false, 'changed' => false, 'message' => 'Không thể thay thế nginx.conf.'];
    }

    return ['ok' => true, 'changed' => true, 'message' => 'Đã thêm location no-cache cho service-worker.js vào block panel.'];
}
