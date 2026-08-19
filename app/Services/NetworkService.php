<?php
declare(strict_types=1);

final class NetworkService
{
    public function lanIp(): string
    {
        $candidates = [];

        // Cách 1: địa chỉ được hệ điều hành chọn cho kết nối ra ngoài.
        $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $error, 1);
        if (is_resource($socket)) {
            $name = @stream_socket_get_name($socket, false);
            fclose($socket);
            if (is_string($name) && $name !== '') {
                $host = str_contains($name, ':') ? substr($name, 0, strrpos($name, ':')) : $name;
                $candidates[] = $host;
            }
        }

        // Cách 2: route đang hoạt động trên Android/Termux.
        foreach ([
            "ip -4 route get 1.1.1.1 2>/dev/null | sed -n 's/.* src \([0-9.]*\).*/\1/p' | head -n1",
            "ip -4 addr show wlan0 2>/dev/null | awk '/inet /{print \$2}' | cut -d/ -f1 | head -n1",
            "ip -4 addr show 2>/dev/null | awk '/inet /{print \$2}' | cut -d/ -f1",
        ] as $command) {
            $output = [];
            @exec($command, $output);
            foreach ($output as $value) $candidates[] = trim((string)$value);
        }

        // Cách 3: thuộc tính mạng Android, hữu ích khi lệnh ip bị giới hạn.
        foreach (['dhcp.wlan0.ipaddress', 'dhcp.wifi.ipaddress'] as $key) {
            $candidates[] = trim((string)@shell_exec('getprop ' . escapeshellarg($key) . ' 2>/dev/null'));
        }

        $host = gethostbyname(gethostname() ?: 'localhost');
        $candidates[] = $host;

        foreach (array_unique($candidates) as $ip) {
            if ($this->isUsableLanIpv4((string)$ip)) return (string)$ip;
        }
        return 'Không phát hiện';
    }

    private function isUsableLanIpv4(string $ip): bool
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
        if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.') || $ip === '0.0.0.0') return false;
        return true;
    }

    public function gateway(): string
    {
        $raw = @file_get_contents('/proc/net/route');
        if (is_string($raw)) {
            foreach (preg_split('/\R/', trim($raw)) ?: [] as $i => $line) {
                if ($i === 0) continue;
                $cols = preg_split('/\s+/', trim($line));
                if (($cols[1] ?? '') !== '00000000' || empty($cols[2])) continue;
                $hex = str_pad((string)$cols[2], 8, '0', STR_PAD_LEFT);
                $parts = array_reverse(str_split($hex, 2));
                $gateway = implode('.', array_map('hexdec', $parts));
                if (filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $gateway;
            }
        }

        foreach (['dhcp.wlan0.gateway', 'dhcp.wifi.gateway', 'net.dns1'] as $key) {
            $value = trim((string)@shell_exec('getprop ' . escapeshellarg($key) . ' 2>/dev/null'));
            if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $value;
        }

        $ip = $this->lanIp();
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '1';
            return implode('.', $parts) . ' (ước tính)';
        }

        return 'Không phát hiện';
    }

    public function details(array $sites): array
    {
        $ip = $this->lanIp();
        $urls = [];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach ($sites as $site) {
                $port = (int)($site['port'] ?? 0);
                if ($port > 0) {
                    $urls[] = [
                        'name' => (string)($site['name'] ?? 'website'),
                        'port' => $port,
                        'url' => 'http://' . $ip . ':' . $port,
                        'status' => (string)($site['status'] ?? 'unknown'),
                    ];
                }
            }
        }
        return [
            'lan_ip' => $ip,
            'gateway' => $this->gateway(),
            'hostname' => gethostname() ?: 'Android Server',
            'urls' => $urls,
            'packages' => [
                'Nginx' => $this->commandExists('nginx'),
                'PHP' => $this->commandExists('php'),
                'MariaDB' => $this->commandExists('mariadb') || $this->commandExists('mysql'),
                'SSH' => $this->commandExists('sshd'),
                'Git' => $this->commandExists('git'),
                'Composer' => $this->commandExists('composer'),
                'Cloudflared' => $this->commandExists('cloudflared'),
                'Termux:API CLI' => $this->commandExists('termux-wifi-connectioninfo'),
            ],
        ];
    }

    private function commandExists(string $command): bool
    {
        exec('command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1', $output, $code);
        return $code === 0;
    }
}
