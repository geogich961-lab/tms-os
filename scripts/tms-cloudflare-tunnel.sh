#!/data/data/com.termux/files/usr/bin/bash
# TMS OS — khôi phục connector Cloudflare Tunnel đã cấu hình.
# Không in token, Chat ID hay nội dung file cấu hình ra terminal/log.
set -u

HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
ROOT="$HOME/tms-os"
CONFIG="$HOME/.tms-os/cloudflare-hosting/config.json"
PHP_BIN="$PREFIX/bin/php"

if [ ! -f "$CONFIG" ]; then
  echo '[LỖI] Không tìm thấy cấu hình Cloudflare cục bộ. Không tạo tunnel mới; hãy kiểm tra lại bản sao lưu cấu hình TMS OS.' >&2
  exit 10
fi
[ -x "$PHP_BIN" ] || PHP_BIN="$(command -v php 2>/dev/null || true)"
if [ -z "$PHP_BIN" ]; then
  echo '[LỖI] Không tìm thấy PHP Termux để điều khiển Cloudflare Tunnel.' >&2
  exit 11
fi
if [ ! -f "$ROOT/app/Services/CloudflareDomainService.php" ]; then
  echo '[LỖI] Không tìm thấy mã TMS OS để điều khiển Cloudflare Tunnel.' >&2
  exit 12
fi

ACTION="${1:-start}"
case "$ACTION" in
  start|stop)
    RESULT="$("$PHP_BIN" -r '
      require_once $argv[1];
      $service = new CloudflareDomainService();
      try {
        if ($argv[2] === "start") {
          $result = $service->startTunnel();
          echo "started:" . (string)($result["pid"] ?? "");
        } else {
          $service->stopTunnel();
          echo "stopped";
        }
      } catch (RuntimeException $e) {
        if ($argv[2] === "start" && $e->getMessage() === "Tunnel đang chạy.") {
          echo "already-running";
          exit(0);
        }
        fwrite(STDERR, "[LỖI] " . $e->getMessage() . PHP_EOL);
        exit(1);
      }
    ' "$ROOT/app/Services/CloudflareDomainService.php" "$ACTION")" || exit $?
    case "$RESULT" in
      started:*) echo "[OK] Cloudflare connector đã khởi động (PID ${RESULT#started:})." ;;
      already-running) echo '[OK] Cloudflare connector đang chạy.' ;;
      stopped) echo '[OK] Đã dừng Cloudflare connector.' ;;
      *) echo '[LỖI] Không nhận được trạng thái hợp lệ từ Cloudflare connector.' >&2; exit 13 ;;
    esac
    ;;
  *)
    echo 'Cách dùng: bash ~/tms-os/scripts/tms-cloudflare-tunnel.sh [start|stop]' >&2
    exit 2
    ;;
esac
