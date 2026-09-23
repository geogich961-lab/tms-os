<?php
declare(strict_types=1);

function failChannels(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectChannels(bool $condition, string $message): void { if (!$condition) failChannels($message); }

$root = realpath(dirname(__DIR__));
$temp = sys_get_temp_dir() . '/tms-update-channels-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
@mkdir($target . '/config', 0700, true);
@mkdir($home . '/.tms-os', 0700, true);
putenv('HOME=' . $home);
file_put_contents($target . '/config/app.php', "<?php return ['build'=>'Platform V17.0.25'];\n");

require $root . '/app/Services/UpdateService.php';

const LATEST_URL = 'https://api.github.com/repos/geogich961-lab/tms-os/releases/latest';
const RELEASES_URL = 'https://api.github.com/repos/geogich961-lab/tms-os/releases?per_page=30';
const BETA_TAG_URL = 'https://api.github.com/repos/geogich961-lab/tms-os/releases/tags/v17.1.0-beta.1';

function releasePayload(string $tag, bool $prerelease): array
{
    return [
        'tag_name' => $tag,
        'body' => $prerelease ? 'Beta test' : 'Stable',
        'published_at' => '2026-09-23T12:00:00Z',
        'draft' => false,
        'prerelease' => $prerelease,
        'assets' => [[
            'name' => 'TMS_OS_LATEST.zip',
            'browser_download_url' => 'https://github.com/geogich961-lab/tms-os/releases/download/' . $tag . '/TMS_OS_LATEST.zip',
        ]],
    ];
}

$responses = [
    LATEST_URL => json_encode(releasePayload('v17.0.26', false)),
    RELEASES_URL => json_encode([
        releasePayload('v17.1.0-beta.1', true),
        releasePayload('v17.0.26', false),
        releasePayload('v17.0.25', false),
    ]),
    BETA_TAG_URL => json_encode(releasePayload('v17.1.0-beta.1', true)),
];

$client = static function (string $url) use (&$responses): array {
    return array_key_exists($url, $responses)
        ? ['body'=>(string)$responses[$url], 'error'=>'']
        : ['body'=>'', 'error'=>'missing fake endpoint ' . $url];
};

try {
    $service = new UpdateService(null, null, $client);

    expectChannels($service->updateChannel() === 'stable', 'Kênh mặc định phải là stable.');
    $stable = $service->check();
    expectChannels(($stable['channel'] ?? '') === 'stable', 'check() phải trả kênh stable.');
    expectChannels(($stable['available']['version'] ?? '') === '17.0.26', 'Stable phải chỉ thấy stable mới nhất.');

    $saved = $service->setUpdateChannel('beta');
    expectChannels(($saved['channel'] ?? '') === 'beta', 'Phải lưu được kênh beta.');
    expectChannels($service->updateChannel() === 'beta', 'Kênh beta phải tồn tại qua file state.');

    $beta = $service->check();
    expectChannels(($beta['available']['version'] ?? '') === '17.1.0-beta.1', 'Beta phải thấy prerelease mới nhất.');
    expectChannels(!empty($beta['available']['prerelease']), 'Release beta phải mang cờ prerelease.');

    $catalog = $service->releases(30);
    expectChannels(count($catalog) === 3, 'Release catalog phải giữ cả stable và beta tương thích.');
    expectChannels(($catalog[0]['channel'] ?? '') === 'beta', 'Prerelease phải được gắn channel beta.');
    expectChannels(($catalog[1]['channel'] ?? '') === 'stable', 'Release thường phải được gắn channel stable.');

    $specific = $service->releaseByTag('v17.1.0-beta.1');
    expectChannels(($specific['version'] ?? '') === '17.1.0-beta.1', 'Phải tra được release cụ thể theo tag.');

    $view = (string)file_get_contents($root . '/app/Views/updates/index.php');
    $routes = (string)file_get_contents($root . '/routes/web.php');
    $controller = (string)file_get_contents($root . '/app/Controllers/UpdateController.php');

    foreach ([
        'id="update-channel-card"',
        'Beta / Test — thử nghiệm',
        'id="release-history-card"',
        '/api/updates/releases',
        '/updates/release/apply',
        'Chuyển về bản này',
    ] as $needle) {
        expectChannels(str_contains($view, $needle), 'UI thiếu: ' . $needle);
    }
    foreach ([
        "'/api/updates/releases'",
        "'/updates/channel'",
        "'/updates/release/apply'",
    ] as $needle) {
        expectChannels(str_contains($routes, $needle), 'Routes thiếu: ' . $needle);
    }
    foreach ([
        'public function releases(): void',
        'public function channel(): void',
        'public function releaseApply(): void',
    ] as $needle) {
        expectChannels(str_contains($controller, $needle), 'Controller thiếu: ' . $needle);
    }

    echo "PASS: Update Center stable/beta channels and explicit release rollback\n";
} finally {
    if (is_dir($temp)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($temp);
    }
}
