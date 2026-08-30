<?php
declare(strict_types=1);

/**
 * CommandRunner — điểm gọi lệnh hệ thống chuẩn của TMS OS.
 *
 * Toàn bộ shell_exec/proc_open/exec của service nên đi qua lớp này để có một
 * chokepoint duy nhất: bắt buộc escape qua self::arg(), dễ audit, dễ bật log
 * bảo mật sau này. Script hệ thống cố định có thể gọi trực tiếp, nhưng mọi
 * chuỗi lấy từ request PHẢI bọc self::arg().
 */
final class CommandRunner
{
    /** Escape một giá trị đưa vào lệnh shell — bắt buộc cho mọi dữ liệu từ request. */
    public static function arg(string $value): string
    {
        return escapeshellarg($value);
    }

    /** Chạy lệnh, thu output vào mảng và exit code; trả stdout+stderr gộp. */
    public static function exec(string $command, ?array &$output = null, ?int &$exitCode = null): string
    {
        $output = [];
        $exitCode = 0;
        $last = exec($command, $output, $exitCode);
        return (string)$last;
    }

    /** shell_exec có ép kiểu: luôn trả string, rỗng khi thất bại thay vì null. */
    public static function shell(string $command): string
    {
        return (string)@shell_exec($command);
    }

    /**
     * Chạy tiến trình với timeout giây, không bị treo khi process bỏ ống.
     * Trả ['out' => string, 'code' => int]; code -1 khi vượt timeout.
     * Lưu ý: timeout hoạt động đúng trên Linux/Termux; trên Windows, PHP pipe
     * không hỗ trợ non-blocking nên đọc sẽ chờ đến khi process kết thúc.
     *
     * @param array<int,string> $command argv thật (từng phần tử riêng, không ghép chuỗi)
     */
    public static function proc(array $command, int $timeoutSeconds = 30, string $stdin = ''): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return ['out' => '', 'code' => -1];
        }
        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $out = '';
        $start = microtime(true);
        while (true) {
            $out .= (string)stream_get_contents($pipes[1]);
            $out .= (string)stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Đọc nốt phần output đệm sau khi process thoát.
                $out .= (string)stream_get_contents($pipes[1]);
                $out .= (string)stream_get_contents($pipes[2]);
                proc_close($process);
                return ['out' => $out, 'code' => $status['exitcode']];
            }
            if ((microtime(true) - $start) > $timeoutSeconds) {
                proc_terminate($process, 9);
                proc_close($process);
                return ['out' => $out, 'code' => -1];
            }
            usleep(50000);
        }
    }
}
