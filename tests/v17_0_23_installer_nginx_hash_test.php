<?php
declare(strict_types=1);

function failV17023(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV17023(bool $condition, string $message): void { if (!$condition) failV17023($message); }

$root = realpath(dirname(__DIR__));
$lib = $root . '/scripts/lib/installer-safety.sh';
expectV17023(is_file($lib), 'Thiếu installer-safety.sh');

$tmp = sys_get_temp_dir() . '/tms-v17023-' . bin2hex(random_bytes(4));
$prefix = $tmp . '/prefix';
$home = $tmp . '/home';
$bin = $tmp . '/bin';
@mkdir($prefix . '/etc/nginx', 0777, true);
@mkdir($home, 0777, true);
@mkdir($bin, 0777, true);

$nginxConf = $prefix . '/etc/nginx/nginx.conf';
file_put_contents($nginxConf, "worker_processes auto;\nevents { worker_connections 1024; }\nhttp {\n  include mime.types;\n}\n");
$fake = $bin . '/nginx';
file_put_contents($fake, "#!/usr/bin/env bash\necho fake-nginx \"\$@\" >/dev/null\nexit 0\n");
chmod($fake, 0755);

$cmd = 'PREFIX=' . escapeshellarg($prefix)
    . ' HOME=' . escapeshellarg($home)
    . ' PATH=' . escapeshellarg($bin . ':' . getenv('PATH'))
    . ' bash -c ' . escapeshellarg('source ' . escapeshellarg($lib) . '; nginx -t; nginx -t');
exec($cmd, $out, $rc);
expectV17023($rc === 0, 'Wrapper nginx của installer không chạy thành công.');

$conf = (string)file_get_contents($nginxConf);
expectV17023(substr_count($conf, 'server_names_hash_bucket_size 128;') === 1, 'bucket_size phải được chèn đúng 1 lần.');
expectV17023(substr_count($conf, 'server_names_hash_max_size 4096;') === 1, 'max_size phải được chèn đúng 1 lần.');

file_put_contents($nginxConf, "events {}\nhttp {\n  server_names_hash_bucket_size 32;\n  server_names_hash_max_size 512;\n}\n");
exec($cmd, $out2, $rc2);
expectV17023($rc2 === 0, 'Wrapper nginx không sửa được cấu hình giá trị cũ.');
$conf2 = (string)file_get_contents($nginxConf);
expectV17023(str_contains($conf2, 'server_names_hash_bucket_size 128;'), 'Không nâng bucket_size từ 32 lên 128.');
expectV17023(str_contains($conf2, 'server_names_hash_max_size 4096;'), 'Không nâng max_size từ 512 lên 4096.');
expectV17023(!str_contains($conf2, 'server_names_hash_bucket_size 32;'), 'Giá trị bucket_size cũ vẫn còn.');

$config = (string)file_get_contents($root . '/config/app.php');
$worker = (string)file_get_contents($root . '/public/service-worker.js');
expectV17023(str_contains($config, "'build' => 'Platform V17.0.23'"), 'config/app.php chưa bump V17.0.23.');
expectV17023(str_contains($worker, "const VERSION='tms-os-v17.0.23';"), 'Service Worker chưa bump V17.0.23.');

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($tmp);

echo "PASS: V17.0.23 installer Nginx hash safety is idempotent.\n";
