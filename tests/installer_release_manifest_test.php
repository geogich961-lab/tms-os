<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$installer = (string)file_get_contents($root . '/install.sh');
$manifest = json_decode((string)file_get_contents($root . '/RELEASE.json'), true);

$required = [
    'RELEASE_MANIFEST_URL="https://github.com/${REPO}/releases/latest/download/RELEASE.json"',
    'tms_download_release_asset "${RELEASE_MANIFEST_URL}?nocache=$RANDOM" "$RELEASE_MANIFEST"',
    '\\([a-f0-9]\\{64\\}\\)',
    'ZIP hoặc RELEASE.json (try $VERIFY_ATTEMPT) chưa đồng bộ trên GitHub.',
];
foreach ($required as $needle) {
    if (!str_contains($installer, $needle)) {
        fwrite(STDERR, "Missing release-manifest installer guard: {$needle}\n");
        exit(1);
    }
}

if (str_contains($installer, 'EMBED_SHA256=') || str_contains($installer, '941315a2c1bd2ee258beb7954cc5aad2787f5c1339f6097c68d4abc7ec83f65b')) {
    fwrite(STDERR, "Installer still contains a stale embedded checksum fallback.\n");
    exit(1);
}

if (!is_array($manifest) || !preg_match('/^[a-f0-9]{64}$/', (string)($manifest['checksum_sha256'] ?? '')) || empty($manifest['version'])) {
    fwrite(STDERR, "Root RELEASE.json is missing a valid version/checksum manifest.\n");
    exit(1);
}

echo "Installer release manifest test passed.\n";
