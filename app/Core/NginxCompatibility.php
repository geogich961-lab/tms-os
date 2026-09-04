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
