<?php
declare(strict_types=1);

/**
 * Nén payload thành ZIP bằng ZipArchive cho máy build không có lệnh `zip`.
 * Usage: php make_payload_zip.php <payload-dir> <out-zip>
 * - Bỏ qua file ẩn (dotfile) để ZIP không kèm artifact hệ điều hành/editor.
 * - File text được ép LF tuyệt đối khi nén: build trên Windows với autocrlf
 *   sinh CRLF làm bash script vỡ trên Ubuntu/Termux. MSYS grep không phát hiện
 *   được CRLF (đọc text mode), nên việc ép LF phải làm ở đây bằng PHP native.
 */

const TEXT_EXT = ['sh' => 1, 'php' => 1, 'js' => 1, 'css' => 1, 'json' => 1, 'md' => 1, 'txt' => 1, 'html' => 1, 'svg' => 1];

$payload = $argv[1] ?? '';
$out = $argv[2] ?? '';
if ($payload === '' || $out === '' || !is_dir($payload)) {
    fwrite(STDERR, "Usage: php make_payload_zip.php <payload-dir> <out-zip>\n");
    exit(1);
}
if (is_file($out)) {
    @unlink($out);
}
$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Không tạo được ZIP: {$out}\n");
    exit(1);
}
$rootLen = strlen(rtrim($payload, '/\\')) + 1;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($payload, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
$count = 0;
$normalized = 0;
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if ($file->getFilename()[0] === '.') {
        continue;
    }
    $rel = str_replace('\\', '/', substr($file->getPathname(), $rootLen));
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (isset(TEXT_EXT[$ext])) {
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            fwrite(STDERR, "Không đọc được file: {$rel}\n");
            exit(1);
        }
        $clean = str_replace("\r\n", "\n", $content);
        if ($clean !== $content) {
            $normalized++;
        }
        if (!$zip->addFromString($rel, $clean)) {
            fwrite(STDERR, "Không thêm được vào ZIP: {$rel}\n");
            exit(1);
        }
    } else {
        if (!$zip->addFile($file->getPathname(), $rel)) {
            fwrite(STDERR, "Không thêm được vào ZIP: {$rel}\n");
            exit(1);
        }
    }
    $count++;
}
if ($count === 0) {
    fwrite(STDERR, "Payload rỗng.\n");
    exit(1);
}
$zip->close();
echo "ZIP: {$out} ({$count} files, {$normalized} normalized to LF)\n";
