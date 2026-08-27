<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$script = $root . '/scripts/tms-update-repair.sh';
$source = (string) file_get_contents($script);

function repairExpect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

repairExpect(str_contains($source, "RAW_BASE='https://raw.githubusercontent.com/geogich961-lab/tms-os/v17.0.5'"), 'Repair phải dùng tag GitHub cố định, không nhận URL tùy ý.');
repairExpect(str_contains($source, "'app/Services/UpdateService.php app/Views/updates/index.php scripts/tms-update-restart.sh scripts/tms-update-repair.sh'"), 'Repair chỉ được thay bốn tệp Update Center tối thiểu.');
repairExpect(!str_contains($source, 'install.sh'), 'Repair không được gọi installer toàn hệ thống.');
repairExpect(str_contains($source, '.tms-update-repair.bak'), 'Repair phải lưu một bản sao an toàn cho tệp đã tồn tại.');
repairExpect(str_contains($source, 'TMS_UPDATE_REPAIR_SKIP_RESTART'), 'Repair phải hỗ trợ mô phỏng không restart.');
repairExpect(str_contains($source, 'bash "$TARGET/scripts/start-tms.sh"'), 'Repair phải restart TMS OS bằng startup chính thức sau khi đồng bộ.');

$tmp = sys_get_temp_dir() . '/tms-update-repair-test-' . bin2hex(random_bytes(5));
$target = $tmp . '/target';
$bin = $tmp . '/bin';
@mkdir($target . '/app/Services', 0700, true);
@mkdir($target . '/app/Views/updates', 0700, true);
@mkdir($target . '/scripts', 0700, true);
@mkdir($bin, 0700, true);
file_put_contents($target . '/app/Services/UpdateService.php', '<?php echo "old";');
file_put_contents($target . '/app/Views/updates/index.php', '<?php echo "old";');
file_put_contents($target . '/scripts/start-tms.sh', "#!/usr/bin/env sh\nexit 0\n");
chmod($target . '/scripts/start-tms.sh', 0700);

$curl = <<<'SH'
#!/usr/bin/env sh
set -eu
out=''
url=''
while [ "$#" -gt 0 ]; do
  case "$1" in
    -o) out=$2; shift 2 ;;
    http*) url=$1; shift ;;
    *) shift ;;
  esac
done
rel=${url#https://raw.githubusercontent.com/geogich961-lab/tms-os/}
rel=${rel#*/}
mkdir -p "$(dirname "$out")"
cat "$TMS_REPAIR_FIXTURE_ROOT/$rel" > "$out"
SH;
file_put_contents($bin . '/curl', $curl);
chmod($bin . '/curl', 0700);

$command = 'PATH=' . escapeshellarg($bin . ':' . getenv('PATH'))
    . ' TMS_OS_TARGET=' . escapeshellarg($target)
    . ' TMS_REPAIR_FIXTURE_ROOT=' . escapeshellarg($root)
    . ' TMS_UPDATE_REPAIR_SKIP_RESTART=1 sh ' . escapeshellarg($script) . ' --apply 2>&1';
exec($command, $output, $exitCode);

repairExpect($exitCode === 0, 'Repair phải chạy được trong môi trường cô lập: ' . implode("\n", $output));
repairExpect(str_contains((string) file_get_contents($target . '/app/Services/UpdateService.php'), 'private function scheduleRestart(): bool'), 'Repair phải thay UpdateService bằng worker restart mới.');
repairExpect(str_contains((string) file_get_contents($target . '/app/Views/updates/index.php'), "status.phase === 'restart_failed'"), 'Repair phải thay giao diện polling có guard restart_failed.');
repairExpect(is_file($target . '/scripts/tms-update-restart.sh'), 'Repair phải đặt worker restart tách biệt.');
repairExpect(is_file($target . '/app/Services/UpdateService.php.tms-update-repair.bak'), 'Repair phải giữ bản sao UpdateService cũ.');
repairExpect((string) file_get_contents($target . '/app/Services/UpdateService.php.tms-update-repair.bak') === '<?php echo "old";', 'Repair không được làm thay đổi nội dung bản sao cũ.');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) {
    $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
}
@rmdir($tmp);

echo "PASS: update repair only replaces verified Update Center files with reversible backups.\n";
