<?php
declare(strict_types=1);

final class WebsiteHealthService
{
    public function probe(int $port, string $path = '/'): array
    {
        if ($port < 1 || $port > 65535) {
            return $this->result('offline', 0, false, 0, 'Port không hợp lệ.');
        }

        $started = microtime(true);
        $errno = 0;
        $error = '';
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 1.0);
        if (!is_resource($socket)) {
            return $this->result('offline', 0, false, $this->latency($started), $error !== '' ? $error : 'Không kết nối được cổng local.');
        }

        stream_set_timeout($socket, 2);
        $path = '/' . ltrim($path, '/');
        $request = "GET {$path} HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\nUser-Agent: TMS-OS-Health/17.1\r\nAccept: */*\r\n\r\n";
        @fwrite($socket, $request);
        $statusLine = (string)@fgets($socket, 1024);
        $meta = stream_get_meta_data($socket);
        fclose($socket);

        $code = 0;
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', trim($statusLine), $match)) {
            $code = (int)$match[1];
        }

        if (!empty($meta['timed_out'])) {
            return $this->result('offline', $code, true, $this->latency($started), 'HTTP health check hết thời gian chờ.');
        }
        if ($code >= 200 && $code < 400) {
            return $this->result('healthy', $code, true, $this->latency($started), 'Website phản hồi bình thường.');
        }
        if ($code >= 400 && $code < 500) {
            return $this->result('warning', $code, true, $this->latency($started), 'Website phản hồi lỗi phía client.');
        }
        if ($code >= 500) {
            return $this->result('error', $code, true, $this->latency($started), 'Website phản hồi lỗi server.');
        }

        return $this->result('warning', 0, true, $this->latency($started), 'Cổng mở nhưng không đọc được HTTP status.');
    }

    private function latency(float $started): int
    {
        return max(0, (int)round((microtime(true) - $started) * 1000));
    }

    private function result(string $status, int $httpStatus, bool $reachable, int $latencyMs, string $message): array
    {
        return [
            'status' => $status,
            'http_status' => $httpStatus,
            'reachable' => $reachable,
            'latency_ms' => $latencyMs,
            'message' => $message,
            'checked_at' => date('c'),
        ];
    }
}
