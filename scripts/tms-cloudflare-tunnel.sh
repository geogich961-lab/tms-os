#!/data/data/com.termux/files/usr/bin/bash
# TMS OS — khôi phục connector Cloudflare Tunnel đã cấu hình.
# Không in token, Chat ID hay nội dung file cấu hình ra terminal/log.
set -u

HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
ROOT="$HOME/tms-os"
CONFIG="$HOME/.tms-os/cloudflare-hosting/config.json"
PHP_BIN="$PREFIX/bin/php"

[ -f "$CONFIG" ] || exit 0
[ -x "$PHP_BIN" ] || PHP_BIN="$(command -v php 2>/dev/null || true)"
[ -n "$PHP_BIN" ] || exit 0
[ -f "$ROOT/app/Services/CloudflareDomainService.php" ] || exit 0

ACTION="${1:-start}"
case "$ACTION" in
  start|stop)
    "$PHP_BIN" -r '
      require_once $argv[1];
      $service = new CloudflareDomainService();
      try {
        if ($argv[2] === "start") { $service->startTunnel(); }
        else { $service->stopTunnel(); }
      } catch (RuntimeException $e) {
        if ($argv[2] === "start" && $e->getMessage() === "Tunnel đang chạy.") {
          exit(0);
        }
        exit(1);
      }
    ' "$ROOT/app/Services/CloudflareDomainService.php" "$ACTION" >/dev/null 2>&1
    ;;
  *)
    exit 2
    ;;
esac
