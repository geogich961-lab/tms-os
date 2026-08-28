<?php
declare(strict_types=1);

function failV1714(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expectV1714(bool $condition, string $message): void { if (!$condition) { failV1714($message); } }
function removeV1714Tree(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') { removeV1714Tree($path . '/' . $item); }
        }
        @rmdir($path);
        return;
    }
    @unlink($path);
}

$root = realpath(dirname(__DIR__));
$baseZip = $root . '/.build/v17.0.13/release/TMS_OS_LATEST.zip';
$releaseZip = $root . '/.build/v17.0.14/release/TMS_OS_LATEST.zip';
$metadataFile = $root . '/.build/v17.0.14/release/RELEASE.json';
$temp = sys_get_temp_dir() . '/tms-v1714-payload-' . bin2hex(random_bytes(5));
$home = $temp . '/home';
$target = $home . '/tms-os';
$oldHome = getenv('HOME');

try {
    expectV1714(is_file($baseZip) && is_file($releaseZip) && is_file($metadataFile), 'Thiếu artifact V17.0.13 hoặc V17.0.14 để mô phỏng nâng cấp.');
    $metadata = json_decode((string)file_get_contents($metadataFile), true);
    expectV1714(is_array($metadata) && ($metadata['version'] ?? '') === '17.0.14', 'RELEASE.json phải khai báo V17.0.14.');
    expectV1714(hash_file('sha256', $releaseZip) === ($metadata['checksum_sha256'] ?? ''), 'Checksum V17.0.14 không khớp RELEASE.json.');

    $archive = new ZipArchive();
    expectV1714($archive->open($releaseZip) === true, 'Không mở được payload V17.0.14.');
    $entries = [];
    for ($index = 0; $index < $archive->numFiles; $index++) { $entries[] = (string)$archive->getNameIndex($index); }
    $archive->close();
    foreach ($entries as $entry) {
        expectV1714((bool)preg_match('#^(app|config|public|routes|scripts)/#', $entry), 'Payload chỉ được chứa các source root cho phép.');
    }
    expectV1714(!in_array('scripts/verify-uci-payload.sh', $entries, true), 'Payload thiết bị không được kèm verifier nội bộ.');
    expectV1714(!in_array('RELEASE.json', $entries, true) && !in_array('storage/', $entries, true), 'Payload không được kèm metadata tự tham chiếu hoặc dữ liệu runtime.');

    @mkdir($target, 0700, true);
    $base = new ZipArchive();
    expectV1714($base->open($baseZip) === true && $base->extractTo($target), 'Không giải nén được payload V17.0.13.');
    $base->close();
    @mkdir($target . '/storage', 0700, true);
    file_put_contents($target . '/storage/v1714-persistent.txt', 'storage-must-survive');
    $cloudflareDir = $home . '/.tms-os/cloudflare-hosting';
    @mkdir($cloudflareDir, 0700, true);
    $cloudflareConfig = "{\n  \"tunnel_id\": \"unchanged\"\n}\n";
    file_put_contents($cloudflareDir . '/config.json', $cloudflareConfig);

    $updates = $home . '/.tms-os/updates';
    @mkdir($updates, 0700, true);
    $staged = $updates . '/tms-update-v17.0.14.zip';
    expectV1714(copy($releaseZip, $staged), 'Không thể stage ZIP V17.0.14.');
    putenv('HOME=' . $home);
    putenv('TMS_UPDATE_SKIP_RESTART=1');
    require $target . '/app/Services/UpdateService.php';
    $service = new UpdateService();
    $result = $service->apply(basename($staged));

    expectV1714(!empty($result['ok']) && $service->currentVersion() === 'V17.0.14', 'UpdateService V17.0.13 không áp dụng được V17.0.14.');
    expectV1714((string)file_get_contents($target . '/storage/v1714-persistent.txt') === 'storage-must-survive', 'Update đã thay đổi storage.');
    expectV1714((string)file_get_contents($cloudflareDir . '/config.json') === $cloudflareConfig, 'Update đã thay đổi cấu hình Cloudflare.');
    $serviceCode = (string)file_get_contents($target . '/app/Services/UpdateService.php');
    $telegramCode = (string)file_get_contents($target . '/app/Services/TelegramCommandService.php');
    $view = (string)file_get_contents($target . '/app/Views/updates/index.php');
    $worker = (string)file_get_contents($target . '/public/service-worker.js');
    expectV1714(str_contains($serviceCode, 'authorizeTelegramUpdate') && str_contains($serviceCode, 'TELEGRAM_CHALLENGE_MAX_ATTEMPTS = 3'), 'Payload thiếu luồng xác thực Telegram giới hạn ba lần sai.');
    expectV1714(str_contains($telegramCode, '/checkupdate') && str_contains($telegramCode, 'callback_query') && str_contains($telegramCode, 'answerCallbackQuery'), 'Payload thiếu command, callback hoặc xác nhận spinner Telegram.');
    expectV1714(str_contains($view, 'Mật khẩu nâng cấp Telegram') && !str_contains($view, 'value="<?= htmlspecialchars($updatePasswordHash'), 'Giao diện không được hiển thị hash mật khẩu.');
    expectV1714(str_contains($worker, "const VERSION='tms-os-v17.0.14';"), 'Payload chưa làm mới cache Service Worker.');

    echo "PASS: V17.0.13 → V17.0.14 bảo toàn storage/Cloudflare, thêm xác thực Telegram và giữ ZIP source sạch.\n";
} finally {
    putenv('HOME' . ($oldHome === false ? '' : '=' . $oldHome));
    removeV1714Tree($temp);
}
