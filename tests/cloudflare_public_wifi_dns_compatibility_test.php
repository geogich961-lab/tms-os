<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$base = sys_get_temp_dir() . '/tms-cloudflare-public-wifi-' . bin2hex(random_bytes(4));
$home = $base . '/home';
$prefix = $base . '/prefix';
$resolver = $prefix . '/etc/resolv.conf';
@mkdir($home . '/.tms-os/cloudflare-hosting', 0700, true);
@mkdir($home . '/bin', 0700, true);
@mkdir($prefix . '/bin', 0700, true);
@mkdir($prefix . '/etc', 0700, true);
file_put_contents($resolver, "nameserver 9.9.9.9\n");
chmod($resolver, 0644);
file_put_contents($home . '/bin/cloudflared', "#!/bin/sh\nsleep 30\n");
chmod($home . '/bin/cloudflared', 0700);

$configFile = $home . '/.tms-os/cloudflare-hosting/config.json';
$originalConfig = [
    'tunnel_token' => 'test-token-not-real',
    'tunnel_id' => 'keep-this-tunnel-id',
    'hostnames' => [['hostname' => 'example.test', 'service' => 'http://127.0.0.1:8080']],
    'unrelated_setting' => ['must' => 'survive'],
];
file_put_contents($configFile, json_encode($originalConfig, JSON_UNESCAPED_SLASHES));
chmod($configFile, 0600);

putenv('HOME=' . $home);
putenv('PREFIX=' . $prefix);
require_once $root . '/app/Services/CloudflareDomainService.php';
$service = new CloudflareDomainService();
$enabled = $service->setPublicWifiDnsCompatibility(true);
$configAfterEnable = json_decode((string)file_get_contents($configFile), true);
$beforeStartUnchanged = $configAfterEnable['tunnel_id'] === $originalConfig['tunnel_id']
    && $configAfterEnable['hostnames'] === $originalConfig['hostnames']
    && $configAfterEnable['unrelated_setting'] === $originalConfig['unrelated_setting']
    && !is_file($home . '/.tms-os/cloudflare-hosting/termux-resolv.conf.before-public-wifi')
    && trim((string)file_get_contents($resolver)) === 'nameserver 9.9.9.9';

$started = $service->startTunnel();
$backup = $home . '/.tms-os/cloudflare-hosting/termux-resolv.conf.before-public-wifi';
$configAfterStart = json_decode((string)file_get_contents($configFile), true);
$startedSafely = !empty($started['running'])
    && trim((string)file_get_contents($resolver)) === "nameserver 1.1.1.1\nnameserver 1.0.0.1"
    && trim((string)file_get_contents($backup)) === 'nameserver 9.9.9.9'
    && $configAfterStart['tunnel_id'] === $originalConfig['tunnel_id']
    && $configAfterStart['hostnames'] === $originalConfig['hostnames']
    && $configAfterStart['unrelated_setting'] === $originalConfig['unrelated_setting'];

$service->stopTunnel();
$disabled = $service->setPublicWifiDnsCompatibility(false);
$configAfterDisable = json_decode((string)file_get_contents($configFile), true);
$restoredSafely = trim((string)file_get_contents($resolver)) === 'nameserver 9.9.9.9'
    && (fileperms($resolver) & 0777) === 0644
    && !is_file($backup)
    && !array_key_exists('public_wifi_dns_compatibility', $configAfterDisable)
    && !array_key_exists('public_wifi_dns_backup_had_file', $configAfterDisable)
    && !array_key_exists('public_wifi_dns_backup_mode', $configAfterDisable)
    && $configAfterDisable['tunnel_id'] === $originalConfig['tunnel_id']
    && $configAfterDisable['hostnames'] === $originalConfig['hostnames']
    && $configAfterDisable['unrelated_setting'] === $originalConfig['unrelated_setting'];

$source = (string)file_get_contents($root . '/app/Services/CloudflareDomainService.php');
$startSource = (string)strstr($source, 'public function startTunnel(): array');
$doesNotPassUnsupportedResolverFlag = !str_contains($source, 'dns-resolver-addrs');
$doesNotCallCloudflareApiOnStart = !str_contains((string)strstr($startSource, 'public function stopTunnel(): array', true), '->api(');
exec('rm -rf ' . escapeshellarg($base));

if ($enabled['message'] === '' || !$beforeStartUnchanged || !$startedSafely || $disabled['message'] === '' || !$restoredSafely || !$doesNotPassUnsupportedResolverFlag || !$doesNotCallCloudflareApiOnStart) {
    fwrite(STDERR, "Cloudflare public Wi-Fi DNS compatibility test failed.\n");
    exit(1);
}
echo "Cloudflare public Wi-Fi DNS compatibility test passed.\n";
