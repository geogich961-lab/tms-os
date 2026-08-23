<?php
declare(strict_types=1);

$script = (string)file_get_contents(__DIR__ . '/../scripts/tms-cloudflare-tunnel.sh');
foreach ([
    'Không tìm thấy cấu hình Cloudflare cục bộ.',
    'Không tìm thấy PHP Termux để điều khiển Cloudflare Tunnel.',
    'Cloudflare connector đã khởi động (PID',
    'Cloudflare connector đang chạy.',
    'fwrite(STDERR, "[LỖI] " . $e->getMessage()',
] as $required) {
    if (!str_contains($script, $required)) {
        fwrite(STDERR, "Thiếu tín hiệu helper Cloudflare: {$required}\n");
        exit(1);
    }
}
if (str_contains($script, '>/dev/null 2>&1')) {
    fwrite(STDERR, "Helper Cloudflare không được che toàn bộ lỗi khởi động.\n");
    exit(1);
}
echo "Cloudflare tunnel helper regression test passed\n";
