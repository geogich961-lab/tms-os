<?php
declare(strict_types=1);

final class AppInstallerService
{
    private string $home;
    private string $appsFile;
    private string $storageApps;

    public function __construct(private WebsiteService $websites, private DatabaseService $databases)
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        @mkdir($this->home . '/.tms-os', 0700, true);
        $this->appsFile = $this->home . '/.tms-os/apps.json';
        $this->storageApps = $this->home . '/tms-os/storage/apps';
    }

    public function catalog(): array
    {
        return [
            ['id' => 'wordpress', 'name' => 'WordPress', 'description' => 'CMS phổ biến cho blog và website doanh nghiệp. Hỗ trợ cấu hình tự động.', 'requirements' => 'PHP, MariaDB', 'database' => true, 'type' => 'web'],
            ['id' => 'typecho-vn', 'name' => 'Typecho VN', 'description' => 'Bản Typecho Việt Hóa bởi THCGaming. Siêu nhẹ, phù hợp cho mini VPS.', 'requirements' => 'PHP, SQLite', 'database' => true, 'type' => 'web'],
            ['id' => 'file-browser', 'name' => 'File Browser', 'description' => 'Quản lý file qua giao diện web hiện đại, hỗ trợ nhiều người dùng.', 'requirements' => 'Binary (ARM64)', 'database' => false, 'type' => 'service'],
            ['id' => 'adminer', 'name' => 'Adminer', 'description' => 'Quản trị MariaDB bằng một file PHP nhỏ gọn.', 'requirements' => 'PHP', 'database' => false, 'type' => 'web'],
            ['id' => 'phpinfo', 'name' => 'PHP Info', 'description' => 'Trang kiểm tra cấu hình PHP trên website riêng.', 'requirements' => 'PHP', 'database' => false, 'type' => 'web'],
        ];
    }

    public function installed(): array
    {
        $data = @json_decode((string)@file_get_contents($this->appsFile), true);
        if (!is_array($data)) return [];
        $items = array_values($data);
        foreach ($items as &$item) {
            if (($item['type'] ?? '') !== 'service') continue;
            $port = (int)($item['port'] ?? 0);
            $item['health'] = $port > 0 && $this->isPortAccepting($port) ? 'running' : 'stopped';
            $item['access_url'] = $port > 0 ? 'http://127.0.0.1:' . $port : '';
        }
        unset($item);
        return $items;
    }

    public function install(array $input): array
    {
        $app = preg_replace('/[^a-z0-9_-]/', '', (string)($input['app'] ?? ''));
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($input['name'] ?? ''));
        $port = (int)($input['port'] ?? 0);
        
        if (strlen($name) < 2) throw new RuntimeException('Tên ứng dụng không hợp lệ.');
        if ($port < 1024 || $port > 65535) throw new RuntimeException('Cổng không hợp lệ.');

        $catalog = $this->catalog();
        $appInfo = null;
        foreach ($catalog as $item) {
            if ($item['id'] === $app) {
                $appInfo = $item;
                break;
            }
        }
        if (!$appInfo) throw new RuntimeException('Ứng dụng không tồn tại trong danh mục.');

        try {
            $resultMessage = 'Đã cài ứng dụng ' . $name . '.';
            
            if ($appInfo['type'] === 'web') {
                $this->websites->create($name, $port);
                $root = $this->home . '/websites/' . $name . '/public';
                
                if ($app === 'phpinfo') {
                    file_put_contents($root . '/index.php', "<?php phpinfo();\n", LOCK_EX);
                } elseif ($app === 'adminer') {
                    $this->download('https://github.com/vrana/adminer/releases/latest/download/adminer.php', $root . '/index.php');
                } elseif ($app === 'wordpress') {
                    $this->installWordPress($root, $name, $port, $input);
                } elseif ($app === 'typecho-vn') {
                    $this->installTypechoVN($root, $name, $port, $input);
                }
            } elseif ($appInfo['type'] === 'service') {
                if ($this->isPortAccepting($port)) {
                    throw new RuntimeException('Cổng ' . $port . ' đang được dịch vụ khác sử dụng. Hãy chọn cổng khác.');
                }
                if ($app === 'file-browser') {
                    $this->installFileBrowser($name, $port);
                }
            }

            $items = $this->readApps();
            $items[$name] = [
                'app' => $app,
                'name' => $name,
                'port' => $port,
                'installed_at' => date('c'),
                'health' => $appInfo['type'] === 'service' ? 'running' : 'ready',
                'type' => $appInfo['type']
            ];
            file_put_contents($this->appsFile, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            return ['ok' => true, 'message' => $resultMessage];
            
        } catch (Throwable $e) {
            if ($appInfo['type'] === 'web') {
                try { $this->websites->delete($name, true); } catch (Throwable) {}
            }
            throw $e;
        }
    }

    private function installWordPress(string $root, string $name, int $port, array $input): void
    {
        $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($input['db_name'] ?? ''));
        $dbUser = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($input['db_user'] ?? ''));
        $dbPass = (string)($input['db_pass'] ?? '');
        
        if (strlen($dbName) < 2 || strlen($dbUser) < 2 || strlen($dbPass) < 8) {
            throw new RuntimeException('Thông tin database chưa hợp lệ; mật khẩu cần ít nhất 8 ký tự.');
        }
        
        $this->databases->create($dbName);
        $this->databases->createUserForDatabase($dbName, $dbUser, $dbPass);
        
        $zip = $this->home . '/.tms-os/wordpress.zip';
        $this->download('https://wordpress.org/latest.zip', $zip);
        $this->extractZip($zip, $root);
        $nested = $root . '/wordpress';
        if (is_dir($nested)) $this->moveContents($nested, $root);
        @unlink($zip);
        
        $sample = $root . '/wp-config-sample.php';
        $cfg = (string)file_get_contents($sample);
        $cfg = str_replace(
            ['database_name_here', 'username_here', 'password_here', "define( 'DB_HOST', 'localhost' );"],
            [$dbName, $dbUser, $dbPass, "define( 'DB_HOST', '127.0.0.1:3306' );"],
            $cfg
        );
        $cfg = $this->injectWordPressSalts($cfg);
        file_put_contents($root . '/wp-config.php', $cfg, LOCK_EX);
    }

    private function installTypechoVN(string $root, string $name, int $port, array $input): void
    {
        $zip = $this->storageApps . '/typecho_vh.zip';
        if (!is_file($zip)) throw new RuntimeException('Không tìm thấy file Typecho VN trong bộ nhớ hệ thống.');
        
        $this->extractZip($zip, $root);
        @chmod($root . '/usr', 0777);
    }

    private function installFileBrowser(string $name, int $port): void
    {
        $dir = $this->home . '/services/filebrowser-' . $name;
        @mkdir($dir, 0700, true);
        
        $url = "https://github.com/filebrowser/filebrowser/releases/latest/download/linux-arm64-filebrowser.tar.gz";
        $tar = $dir . '/fb.tar.gz';
        $this->download($url, $tar);
        
        exec("cd " . escapeshellarg($dir) . " && tar -xzf fb.tar.gz && rm fb.tar.gz");
        @chmod($dir . '/filebrowser', 0700);
        
        $db = $dir . '/filebrowser.db';
        $startScript = $this->home . '/.tms-os/scripts/start-filebrowser-' . $name . '.sh';
        $content = "#!/bin/bash\ncd " . $dir . " && ./filebrowser -d " . $db . " -p " . $port . " -a 0.0.0.0 > fb.log 2>&1 &\n";
        file_put_contents($startScript, $content);
        @chmod($startScript, 0700);
        
        exec("bash " . escapeshellarg($startScript));
    }

    private function download(string $url, string $dest): void
    {
        $ch = curl_init($url);
        $fp = fopen($dest, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'TMS-OS-V16'
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = (string)curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $status < 200 || $status >= 300 || !is_file($dest) || (int)@filesize($dest) < 1024) {
            @unlink($dest);
            throw new RuntimeException('Tải xuống gói ứng dụng thất bại' . ($error !== '' ? ': ' . $error : '.') );
        }
    }

    private function extractZip(string $zip, string $dest): void
    {
        $z = new ZipArchive();
        if ($z->open($zip) !== true) throw new RuntimeException('Không mở được file ZIP.');
        $z->extractTo($dest);
        $z->close();
    }

    private function moveContents(string $from, string $to): void
    {
        foreach (scandir($from) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            @rename($from . '/' . $item, $to . '/' . $item);
        }
        @rmdir($from);
    }

    private function injectWordPressSalts(string $cfg): string
    {
        $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
        foreach ($keys as $key) {
            $value = base64_encode(random_bytes(48));
            $cfg = preg_replace("/define\(\s*'" . $key . "'\s*,\s*'put your unique phrase here'\s*\);/", "define( '" . $key . "', '" . $value . "' );", $cfg) ?? $cfg;
        }
        return $cfg;
    }

    private function readApps(): array
    {
        $d = @json_decode((string)@file_get_contents($this->appsFile), true);
        return is_array($d) ? $d : [];
    }

    private function isPortAccepting(int $port): bool
    {
        if ($port < 1024 || $port > 65535) return false;
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.35);
        if (!is_resource($socket)) return false;
        fclose($socket);
        return true;
    }

    private function waitForHttpPort(int $port, int $seconds): bool
    {
        $until = microtime(true) + max(1, $seconds);
        do {
            if ($this->isPortAccepting($port)) return true;
            usleep(250000);
        } while (microtime(true) < $until);
        return false;
    }

    private function serviceLogHint(string $path): string
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $last = trim((string)end($lines));
        if ($last === '') return 'Hãy xem log dịch vụ trong Service Manager sau khi cập nhật.';
        return 'Chi tiết gần nhất: ' . mb_substr(preg_replace('/\s+/', ' ', $last) ?: '', 0, 180);
    }
}
