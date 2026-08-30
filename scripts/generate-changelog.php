<?php
declare(strict_types=1);

/**
 * Sinh mục CHANGELOG từ RELEASE.json và ghép vào đầu CHANGELOG.md.
 * Usage: php scripts/generate-changelog.php <RELEASE.json>
 * Không ghi đè nội dung cũ — chỉ chèn mục mới nếu version chưa tồn tại.
 */

$releaseJson = $argv[1] ?? '';
$changelog = dirname(__DIR__) . '/CHANGELOG.md';
if ($releaseJson === '' || !is_file($releaseJson) || !is_file($changelog)) {
    fwrite(STDERR, "Usage: php scripts/generate-changelog.php <RELEASE.json>\n");
    exit(1);
}
$release = json_decode((string)file_get_contents($releaseJson), true);
if (!is_array($release) || empty($release['version'])) {
    fwrite(STDERR, "RELEASE.json không hợp lệ.\n");
    exit(1);
}
$version = ltrim((string)$release['version'], 'vV');
$date = (string)($release['release_date'] ?? date('Y-m-d'));
$existing = (string)file_get_contents($changelog);
if (preg_match('/##\s*\[?' . preg_quote($version, '/') . '\]?/', $existing)) {
    echo "CHANGELOG đã có mục {$version} — không ghi đè.\n";
    exit(0);
}

$lines = [];
$lines[] = "## [{$version}] — {$date}";
if (!empty($release['notes'])) {
    $lines[] = '';
    $lines[] = (string)$release['notes'];
}
foreach ((array)($release['features'] ?? []) as $feature) {
    $lines[] = '- ' . (string)$feature;
}
$section = implode("\n", $lines) . "\n";

// Chèn sau phần tiêu đề đầu file (trước mục đầu tiên)
$headEnd = strpos($existing, "\n## ");
$head = $headEnd === false ? $existing : substr($existing, 0, $headEnd + 1);
$body = $headEnd === false ? '' : substr($existing, $headEnd + 1);
file_put_contents($changelog, rtrim($head) . "\n" . $section . "\n" . ltrim($body), LOCK_EX);
echo "Đã thêm mục [{$version}] vào CHANGELOG.md\n";
