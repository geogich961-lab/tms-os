<?php
declare(strict_types=1);

function failSwCache(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectSwCache(bool $condition, string $message): void { if (!$condition) failSwCache($message); }

$root = realpath(dirname(__DIR__));
require $root . '/app/Core/NginxCompatibility.php';

expectSwCache(function_exists('tms_repair_nginx_service_worker_cache'), 'Thiếu hàm repair cache service worker.');

$tmp = sys_get_temp_dir() . '/tms-nginx-sw-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0700, true);
$config = $tmp . '/nginx.conf';

// Fixture mô phỏng nginx.conf do install.sh sinh ra: block panel 8888 chứa
// location regex cache tĩnh immutable nhưng chưa có location service worker.
$fixture = <<<'CONF'
worker_processes auto;
events { worker_connections 1024; }
http {
  include mime.types;
  include /data/data/com.termux/files/usr/etc/nginx/sites-enabled/*.conf;
server { listen 127.0.0.1:8888; server_name localhost; root /data/data/com.termux/files/home/tms-os/public; index index.php;
  location / { try_files $uri $uri/ /index.php?$query_string; }
  # Cache browser cho file tĩnh
  location ~* \.(jpg|jpeg|png|gif|webp|ico|svg|css|js|woff2?|ttf|eot|mp3|mp4|webm)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
  }
  location ~ /\. { deny all; } } }
CONF;

try {
    file_put_contents($config, $fixture);
    $first = tms_repair_nginx_service_worker_cache($config);
    expectSwCache(!empty($first['ok']) && !empty($first['changed']), 'Lần đầu phải chèn location service worker.');
    $content = (string)file_get_contents($config);
    expectSwCache(substr_count($content, 'location = /service-worker.js') === 1, 'Phải có đúng 1 location service worker.');
    expectSwCache(str_contains($content, 'add_header Cache-Control "no-cache";'), 'Location service worker phải đặt no-cache.');
    $swPos = strpos($content, 'location = /service-worker.js');
    $regexPos = strpos($content, 'location ~*');
    expectSwCache($swPos !== false && $regexPos !== false && $swPos < $regexPos, 'Location service worker phải nằm trước location regex cache tĩnh.');
    expectSwCache(str_contains($content, 'add_header Cache-Control "public, immutable";'), 'Không được phá rule cache tĩnh của các file khác.');

    $second = tms_repair_nginx_service_worker_cache($config);
    expectSwCache(!empty($second['ok']) && empty($second['changed']), 'Repair phải idempotent.');
    expectSwCache(substr_count((string)file_get_contents($config), 'location = /service-worker.js') === 1, 'Không được chèn trùng location service worker.');

    // Không có block panel -> báo lỗi rõ ràng và tuyệt đối không sửa file.
    $noPanel = $tmp . '/nopanel.conf';
    file_put_contents($noPanel, "http {\n}\n");
    $before = (string)file_get_contents($noPanel);
    $res = tms_repair_nginx_service_worker_cache($noPanel);
    expectSwCache(empty($res['ok']), 'Thiếu block panel phải báo không ok.');
    expectSwCache((string)file_get_contents($noPanel) === $before, 'Không được sửa file khi không tìm thấy block panel.');
    @unlink($noPanel);

    // Template cài mới: block panel phải có sẵn location service worker.
    $install = (string)file_get_contents($root . '/scripts/install.sh');
    $listenPos = strpos($install, 'listen 127.0.0.1:8888');
    expectSwCache($listenPos !== false, 'install.sh thiếu block panel 8888.');
    $swTplPos = strpos($install, 'location = /service-worker.js', $listenPos);
    expectSwCache($swTplPos !== false, 'install.sh phải có location service worker trong block panel.');
    $regexTplPos = strpos($install, 'location ~*', $swTplPos);
    expectSwCache($regexTplPos !== false && $swTplPos < $regexTplPos, 'Trong install.sh, location service worker phải nằm trước location regex cache tĩnh của panel.');

    // CLI compat phải chạy repair này cùng repair server_names_hash.
    $cli = (string)file_get_contents($root . '/scripts/tms-nginx-compat.php');
    expectSwCache(str_contains($cli, 'tms_repair_nginx_service_worker_cache'), 'CLI compat phải chạy repair service worker.');

    // Banner PWA: chỉ hiện khi bản SW thật sự mới hơn + có nút Bỏ qua nhớ theo phiên bản.
    $appJs = (string)file_get_contents($root . '/public/assets/app.js');
    expectSwCache(str_contains($appJs, 'tmsPwaVersionNewer'), 'app.js phải so phiên bản SW trước khi hiện banner.');
    expectSwCache(str_contains($appJs, 'tms-pwa-dismissed'), 'app.js phải nhớ lần Bỏ qua theo phiên bản.');
    $footer = (string)file_get_contents($root . '/app/Views/layouts/footer.php');
    expectSwCache(str_contains($footer, 'data-pwa-dismiss'), 'Banner phải có nút Bỏ qua.');

    echo "PASS: Service worker thoát rule cache 1 năm; banner PWA chỉ hiện khi bản mới thật sự mới hơn.\n";
} finally { @unlink($config); @rmdir($tmp); }
