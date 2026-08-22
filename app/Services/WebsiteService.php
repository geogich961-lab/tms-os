<?php
declare(strict_types=1);

final class WebsiteService
{
    private string $home;
    private string $prefix;
    private string $sitesDir;
    private string $localDomainFile;
    private string $gatewayConfig;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->prefix = getenv('PREFIX') ?: '/data/data/com.termux/files/usr';
        $this->sitesDir = $this->prefix . '/etc/nginx/sites-enabled';
        $this->localDomainFile = $this->home . '/.tms-os/local-domains.json';
        $this->gatewayConfig = $this->sitesDir . '/_tms-local-domains.conf';

        if (!is_dir($this->sitesDir) && !mkdir($this->sitesDir, 0700, true) && !is_dir($this->sitesDir)) {
            throw new RuntimeException('Không thể tạo thư mục cấu hình Nginx.');
        }
    }

    public function all(): array
    {
        $sites = [];
        $patterns = [
            $this->sitesDir . '/*.conf',
            $this->sitesDir . '/*.conf.disabled',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (basename($file) === '_tms-local-domains.conf') continue;
                $disabled = str_ends_with($file, '.disabled');
                $config = (string)@file_get_contents($file);
                preg_match('/listen\s+(?:0\.0\.0\.0:)?(\d+)\s*;/', $config, $portMatch);
                preg_match('/root\s+([^;]+);/', $config, $rootMatch);
                $port = (int)($portMatch[1] ?? 0);
                $valid = $this->balancedBraces($config);
                $running = !$disabled && $valid && $port > 0 && $this->portResponding($port);

                $base = basename($file);
                $name = preg_replace('/\.conf(?:\.disabled)?$/', '', $base) ?: $base;
                $status = !$valid ? 'error' : ($disabled ? 'stopped' : ($running ? 'running' : 'starting'));

                $domains = $this->domainRecord($name, $port);
                $sites[] = [
                    'name' => $name,
                    'port' => $port,
                    'root' => trim($rootMatch[1] ?? ''),
                    'config' => $file,
                    'valid' => $valid,
                    'enabled' => !$disabled,
                    'status' => $status,
                    'local_domain' => $domains['local_domain'],
                    'lan_domain' => $domains['lan_domain'],
                    'lan_smart_domain' => $domains['lan_smart_domain'],
                    'gateway_port' => $domains['gateway_port'],
                    'local_url' => $domains['local_url'],
                    'lan_url' => $domains['lan_url'],
                    'lan_smart_url' => $domains['lan_smart_url'],
                    'lan_direct_url' => $domains['lan_direct_url'],
                    'lan_ip' => $domains['lan_ip'],
                    'lan_available' => $domains['lan_available'],
                ];
            }
        }

        usort($sites, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $sites;
    }

    public function create(string $name, int $port): void
    {
        $name = trim($name);

        if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name)) {
            throw new RuntimeException('Tên website chỉ được gồm chữ, số, dấu gạch dưới hoặc gạch ngang.');
        }

        if ($port < 1024 || $port > 65535) {
            throw new RuntimeException('Cổng phải nằm trong khoảng 1024–65535.');
        }

        $configPath = $this->sitesDir . '/' . $name . '.conf';
        if (is_file($configPath)) {
            throw new RuntimeException('Website đã tồn tại.');
        }

        foreach ($this->all() as $site) {
            if ((int)$site['port'] === $port) {
                throw new RuntimeException('Cổng này đã được sử dụng bởi website khác.');
            }
        }

        if ($this->portInUse($port)) {
            throw new RuntimeException('Cổng ' . $port . ' đang được một tiến trình khác sử dụng.');
        }

        $root = $this->home . '/websites/' . $name . '/public';
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException('Không thể tạo thư mục website.');
        }

        $indexFile = $root . '/index.php';
        if (!is_file($indexFile)) {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            file_put_contents(
                $indexFile,
                "<?php\ndeclare(strict_types=1);\n?><!doctype html><html lang=\"vi\"><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$safeName}</title><body><h1>{$safeName}</h1><p>Website đã được tạo thành công trên TMS OS.</p></body></html>\n",
                LOCK_EX
            );
        }

        $config = $this->buildConfig($name, $port, $root);
        if (!$this->balancedBraces($config)) {
            throw new RuntimeException('TMS OS tạo cấu hình Nginx không hợp lệ.');
        }

        $tempPath = $configPath . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tempPath, $config, LOCK_EX) === false) {
            throw new RuntimeException('Không thể ghi cấu hình Nginx tạm thời.');
        }

        // Đưa file vào đúng đuôi .conf để nginx -t kiểm tra toàn bộ cấu hình.
        if (!rename($tempPath, $configPath)) {
            @unlink($tempPath);
            throw new RuntimeException('Không thể kích hoạt cấu hình website.');
        }

        try {
            $this->validateAndReload();
            $records = $this->readDomains();
            $safe = strtolower(str_replace('_', '-', $name));
            $records[$name] = ['local_domain'=>$safe.'.localhost','lan_domain'=>$safe.'.lan','port'=>$port,'updated_at'=>date('c')];
            $this->writeDomains($records);
            $this->rebuildLocalGateway($records);
        } catch (Throwable $e) {
            @unlink($configPath);
            $this->tryReloadAfterRollback();
            throw $e;
        }
    }

    public function delete(string $name, bool $deleteFiles): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name)) {
            throw new RuntimeException('Tên website không hợp lệ.');
        }

        $configPath = $this->sitesDir . '/' . $name . '.conf';
        if (!is_file($configPath)) {
            throw new RuntimeException('Website không tồn tại.');
        }

        $backupPath = $configPath . '.delete-backup';
        @unlink($backupPath);
        if (!rename($configPath, $backupPath)) {
            throw new RuntimeException('Không thể tạm dừng cấu hình website.');
        }

        try {
            $this->validateAndReload();
        } catch (Throwable $e) {
            @rename($backupPath, $configPath);
            $this->tryReloadAfterRollback();
            throw $e;
        }

        @unlink($backupPath);

        $this->removeDomain($name);

        if ($deleteFiles) {
            $this->remove($this->home . '/websites/' . $name);
        }
    }

    public function action(string $name, string $action): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name)) {
            throw new RuntimeException('Tên website không hợp lệ.');
        }

        $enabled = $this->sitesDir . '/' . $name . '.conf';
        $disabled = $enabled . '.disabled';

        if ($action === 'stop') {
            if (!is_file($enabled)) throw new RuntimeException('Website không ở trạng thái đang chạy.');
            if (!rename($enabled, $disabled)) throw new RuntimeException('Không thể dừng website.');
            try { $this->validateAndReload(); } catch (Throwable $e) {
                @rename($disabled, $enabled);
                $this->tryReloadAfterRollback();
                throw $e;
            }
            return;
        }

        if ($action === 'start') {
            if (!is_file($disabled)) throw new RuntimeException('Website không ở trạng thái đã dừng.');
            if (!rename($disabled, $enabled)) throw new RuntimeException('Không thể bật website.');
            try { $this->validateAndReload(); } catch (Throwable $e) {
                @rename($enabled, $disabled);
                $this->tryReloadAfterRollback();
                throw $e;
            }
            return;
        }

        if ($action === 'restart') {
            if (!is_file($enabled)) throw new RuntimeException('Website chưa được bật.');
            $this->validateAndReload();
            return;
        }

        throw new RuntimeException('Thao tác website không hợp lệ.');
    }

    public function cloneSite(string $source, string $name, int $port): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $source) || !preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name)) throw new RuntimeException('Tên website không hợp lệ.');
        if ($source === $name) throw new RuntimeException('Tên website mới phải khác website nguồn.');
        $sourceSite = null;
        foreach ($this->all() as $candidate) if ($candidate['name'] === $source) { $sourceSite = $candidate; break; }
        if (!$sourceSite) throw new RuntimeException('Website nguồn không tồn tại.');
        $targetRoot = $this->home . '/websites/' . $name;
        if (file_exists($targetRoot)) throw new RuntimeException('Thư mục website đích đã tồn tại.');
        $this->create($name, $port);
        $createdRoot = $this->home . '/websites/' . $name;
        $sourceBase = dirname((string)$sourceSite['root']);
        exec('rm -rf '.escapeshellarg($createdRoot).' && cp -a '.escapeshellarg($sourceBase).' '.escapeshellarg($createdRoot).' 2>&1', $out, $code);
        if ($code !== 0 || !is_dir($createdRoot)) {
            try { $this->delete($name, true); } catch (Throwable) {}
            throw new RuntimeException("Không thể sao chép source website:
" . implode("
", $out));
        }
        $this->updateDomains($name, strtolower(str_replace('_','-',$name)).'.localhost', strtolower(str_replace('_','-',$name)).'.lan');
    }

    public function updatePort(string $name, int $port): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name)) throw new RuntimeException('Tên website không hợp lệ.');
        if ($port < 1024 || $port > 65535) throw new RuntimeException('Cổng phải nằm trong khoảng 1024–65535.');

        $enabled = $this->sitesDir . '/' . $name . '.conf';
        $disabled = $enabled . '.disabled';
        $path = is_file($enabled) ? $enabled : (is_file($disabled) ? $disabled : '');
        if ($path === '') throw new RuntimeException('Website không tồn tại.');

        foreach ($this->all() as $site) {
            if ($site['name'] !== $name && (int)$site['port'] === $port) {
                throw new RuntimeException('Cổng này đã được website khác sử dụng.');
            }
        }
        if ($this->portInUse($port)) {
            $current = array_values(array_filter($this->all(), static fn(array $s): bool => $s['name'] === $name))[0] ?? null;
            if (!$current || (int)$current['port'] !== $port) {
                throw new RuntimeException('Cổng này đang được tiến trình khác sử dụng.');
            }
        }

        $old = (string)file_get_contents($path);
        $new = preg_replace('/listen\s+(?:0\.0\.0\.0:)?\d+\s*;/', 'listen 0.0.0.0:' . $port . ';', $old, 1);
        if (!is_string($new) || $new === $old) throw new RuntimeException('Không thể cập nhật cổng trong cấu hình.');

        file_put_contents($path, $new, LOCK_EX);
        if ($path === $enabled) {
            try { $this->validateAndReload(); } catch (Throwable $e) {
                file_put_contents($path, $old, LOCK_EX);
                $this->tryReloadAfterRollback();
                throw $e;
            }
        }
        $records = $this->readDomains();
        if (isset($records[$name])) {
            $records[$name]['port'] = $port;
            $records[$name]['updated_at'] = date('c');
            $this->writeDomains($records);
            $this->rebuildLocalGateway($records);
        }
    }

    public function updateDomains(string $name, string $localDomain, string $lanDomain): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name)) throw new RuntimeException('Tên website không hợp lệ.');
        $site = null;
        foreach ($this->all() as $candidate) if ($candidate['name'] === $name) { $site = $candidate; break; }
        if (!$site) throw new RuntimeException('Website không tồn tại.');
        $localDomain = $this->normalizeDomain($localDomain !== '' ? $localDomain : $name . '.localhost', true);
        $lanDomain = $this->normalizeDomain($lanDomain !== '' ? $lanDomain : $name . '.lan', false);
        $records = $this->readDomains();
        foreach ($records as $otherName => $record) {
            if ($otherName === $name) continue;
            if (($record['local_domain'] ?? '') === $localDomain || ($record['lan_domain'] ?? '') === $lanDomain) throw new RuntimeException('Tên miền đã được website khác sử dụng.');
        }
        $records[$name] = ['local_domain'=>$localDomain,'lan_domain'=>$lanDomain,'port'=>(int)$site['port'],'updated_at'=>date('c')];
        $this->writeDomains($records);
        $this->rebuildLocalGateway($records);
        $this->patchWordPressDynamicUrls((string)$site['root']);
    }

    public function hostsFile(): string
    {
        $ip = $this->lanIp();
        $lines = ['# TMS OS LAN domains', '# Copy these lines into the hosts file of LAN devices', ''];
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || str_starts_with($ip, '127.')) {
            return implode("\n", $lines) . "\n# Chưa phát hiện IPv4 LAN. Hãy kết nối Wi-Fi rồi tải lại file này.\n";
        }
        foreach ($this->readDomains() as $name => $record) $lines[] = $ip . "\t" . (string)($record['lan_domain'] ?? ($name . '.lan'));
        return implode("\n", $lines) . "\n";
    }

    public function logs(string $name): array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name)) throw new RuntimeException('Tên website không hợp lệ.');
        $logDir = $this->home . '/logs/nginx';
        return [
            'access' => $this->tail($logDir . '/' . $name . '-access.log'),
            'error' => $this->tail($logDir . '/' . $name . '-error.log'),
        ];
    }

    private function domainRecord(string $name, int $port): array
    {
        $records = $this->readDomains();
        $safe = strtolower(str_replace('_', '-', $name));
        $record = $records[$name] ?? ['local_domain'=>$safe.'.localhost','lan_domain'=>$safe.'.lan','port'=>$port];
        $gatewayPort = $this->gatewayPort();
        $lanIp = $this->lanIp();
        $lanAvailable = filter_var($lanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && !str_starts_with($lanIp, '127.');
        $smart = $lanAvailable ? ($safe . '.' . $lanIp . '.sslip.io') : '';
        $suffix = $gatewayPort === 80 ? '' : ':' . $gatewayPort;
        return [
            'local_domain'=>$record['local_domain'],
            'lan_domain'=>$record['lan_domain'],
            'lan_smart_domain'=>$smart,
            'gateway_port'=>$gatewayPort,
            'local_url'=>'http://'.$record['local_domain'].$suffix,
            'lan_url'=>'http://'.$record['lan_domain'].$suffix,
            // URL LAN trực tiếp là lựa chọn ngắn và ổn định nhất trên thiết bị khác.
            'lan_direct_url'=>$lanAvailable ? ('http://'.$lanIp.':'.$port) : '',
            'lan_smart_url'=>$lanAvailable ? ('http://'.$smart.$suffix) : '',
            'lan_ip'=>$lanAvailable ? $lanIp : '',
            'lan_available'=>$lanAvailable,
        ];
    }

    private function readDomains(): array
    {
        if (!is_file($this->localDomainFile)) return [];
        $data = json_decode((string)@file_get_contents($this->localDomainFile), true);
        return is_array($data) ? $data : [];
    }

    private function writeDomains(array $records): void
    {
        $dir = dirname($this->localDomainFile);
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (file_put_contents($this->localDomainFile, json_encode($records, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX) === false) throw new RuntimeException('Không thể lưu cấu hình tên miền local.');
        @chmod($this->localDomainFile, 0600);
    }

    private function removeDomain(string $name): void
    {
        $records = $this->readDomains();
        if (!isset($records[$name])) return;
        unset($records[$name]);
        $this->writeDomains($records);
        $this->rebuildLocalGateway($records);
    }

    private function normalizeDomain(string $domain, bool $localhost): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#[:/].*$#', '', $domain) ?? $domain;
        if (!preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $domain)) throw new RuntimeException('Tên miền không hợp lệ.');
        if ($localhost && !str_ends_with($domain, '.localhost')) throw new RuntimeException('Tên miền trên thiết bị phải kết thúc bằng .localhost.');
        return $domain;
    }

    private function gatewayPort(): int
    {
        $file = $this->home . '/.tms-os/local-gateway-port';
        if (is_file($file)) { $port=(int)trim((string)file_get_contents($file)); if ($port===80 || ($port>=1024 && $port<=65535)) return $port; }
        $port = $this->canBindPort(80) ? 80 : 8088;
        @file_put_contents($file, (string)$port, LOCK_EX);
        return $port;
    }

    private function canBindPort(int $port): bool
    {
        $errno=0;$error='';$server=@stream_socket_server('tcp://127.0.0.1:'.$port,$errno,$error);
        if (is_resource($server)) { fclose($server); return true; }
        return $this->portResponding($port);
    }

    private function rebuildLocalGateway(?array $records = null): void
    {
        $records ??= $this->readDomains();
        if ($records === []) { @unlink($this->gatewayConfig); $this->tryReloadAfterRollback(); return; }
        $port=$this->gatewayPort();$lanIp=$this->lanIp();$blocks=['# Generated by TMS OS Local Domain Center'];
        foreach ($records as $name=>$record) {
            if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/',(string)$name)) continue;
            $targetPort=(int)($record['port']??0); if($targetPort<1024||$targetPort>65535) continue;
            $local=$this->normalizeDomain((string)($record['local_domain']??($name.'.localhost')),true);
            $lan=$this->normalizeDomain((string)($record['lan_domain']??($name.'.lan')),false);
            $smart=filter_var($lanIp,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) && !str_starts_with($lanIp,'127.') ? strtolower(str_replace('_','-',(string)$name)).'.'.$lanIp.'.sslip.io' : '';
            $serverNames=trim($local.' '.$lan.' '.$smart);
            $blocks[]="server {\n    listen 0.0.0.0:{$port};\n    server_name {$serverNames};\n    location / {\n        proxy_pass http://127.0.0.1:{$targetPort};\n        proxy_http_version 1.1;\n        proxy_set_header Host \$host;\n        proxy_set_header X-Real-IP \$remote_addr;\n        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;\n        proxy_set_header X-Forwarded-Proto \$scheme;\n        proxy_set_header X-Forwarded-Host \$host;\n    }\n}";
        }
        $tmp=$this->gatewayConfig.'.tmp';file_put_contents($tmp,implode("\n\n",$blocks)."\n",LOCK_EX);rename($tmp,$this->gatewayConfig);
        try{$this->validateAndReload();}catch(Throwable $e){@unlink($this->gatewayConfig);$this->tryReloadAfterRollback();throw $e;}
    }

    private function lanIp(): string
    {
        $ip = (new NetworkService())->lanIp();
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '127.') ? $ip : '';
    }

    private function patchWordPressDynamicUrls(string $root): void
    {
        $config=rtrim($root,'/').'/wp-config.php';if(!is_file($config))return;$text=(string)file_get_contents($config);if(str_contains($text,'TMS_DYNAMIC_HOST'))return;
        $snippet="\n/* TMS_DYNAMIC_HOST: support local, LAN and Cloudflare domains */\nif (!empty(\$_SERVER['HTTP_HOST'])) {\n    \$tmsScheme = (!empty(\$_SERVER['HTTP_X_FORWARDED_PROTO']) ? \$_SERVER['HTTP_X_FORWARDED_PROTO'] : ((!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));\n    define('WP_HOME', \$tmsScheme . '://' . \$_SERVER['HTTP_HOST']);\n    define('WP_SITEURL', \$tmsScheme . '://' . \$_SERVER['HTTP_HOST']);\n}\n";
        $count=0;$text=preg_replace('/\/\* That\'s all, stop editing!.*?\*\//s',$snippet."\n$0",$text,1,$count)??$text;if($count===0)$text.=$snippet;file_put_contents($config,$text,LOCK_EX);
    }

    private function tail(string $file, int $lines = 120): string
    {
        if (!is_file($file)) return 'Chưa có dữ liệu.';
        $data = @file($file, FILE_IGNORE_NEW_LINES);
        if (!is_array($data)) return 'Không thể đọc log.';
        return implode("\n", array_slice($data, -$lines));
    }

    private function portResponding(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
        if (is_resource($socket)) { fclose($socket); return true; }
        return false;
    }

    private function portInUse(int $port): bool
    {
        return $this->portResponding($port);
    }

    private function buildConfig(string $name, int $port, string $root): string
    {
        $template = <<<'NGINX'
server {
    listen 0.0.0.0:{{PORT}};
    server_name _;

    root {{ROOT}};
    index index.php index.html;

    access_log {{HOME}}/logs/nginx/{{NAME}}-access.log tms_access;
    error_log  {{HOME}}/logs/nginx/{{NAME}}-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
    }

    # V15.0.6: cache browser 1 năm cho file tĩnh
    location ~* \.(jpg|jpeg|png|gif|webp|ico|svg|css|js|woff2?|ttf|eot|mp3|mp4|webm)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~ /\. {
        deny all;
    }
}
NGINX;

        return strtr($template, [
            '{{PORT}}' => (string)$port,
            '{{ROOT}}' => $root,
            '{{HOME}}' => $this->home,
            '{{NAME}}' => $name,
        ]) . "\n";
    }

    private function validateAndReload(): void
    {
        $output = [];
        $code = 0;
        exec('nginx -t 2>&1', $output, $code);

        if ($code !== 0) {
            throw new RuntimeException("Cấu hình Nginx lỗi:\n" . implode("\n", $output));
        }

        $reloadOutput = [];
        $reloadCode = 0;
        exec('nginx -s reload 2>&1', $reloadOutput, $reloadCode);

        if ($reloadCode !== 0) {
            throw new RuntimeException("Không thể nạp lại Nginx:\n" . implode("\n", $reloadOutput));
        }
    }

    private function tryReloadAfterRollback(): void
    {
        exec('nginx -t >/dev/null 2>&1 && nginx -s reload >/dev/null 2>&1');
    }

    private function balancedBraces(string $config): bool
    {
        $depth = 0;
        $length = strlen($config);

        for ($i = 0; $i < $length; $i++) {
            if ($config[$i] === '{') {
                $depth++;
            } elseif ($config[$i] === '}') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    private function remove(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $this->remove($path . '/' . $name);
            }
            @rmdir($path);
            return;
        }

        @unlink($path);
    }
}
