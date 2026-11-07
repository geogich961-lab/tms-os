#!/data/data/com.termux/files/usr/bin/bash
# tms-telegram-poll.sh — Wrapper cron cho kênh lệnh Telegram polling
# (V17.1.0-beta.5). Chạy mỗi phút bằng crond, độc lập hoàn toàn với
# Nginx/PHP-FPM của panel: panel sập thì bot vẫn nhận lệnh /restart.
#
#   tms-telegram-poll.sh             -> poll một vòng
#   tms-telegram-poll.sh install-cron-> đăng ký cron (idempotent)
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
ROOT="$HOME/tms-os"
STATE="$HOME/.tms-os"
LOGDIR="$HOME/logs/guardian"
LOCKFILE="$STATE/telegram-poll.lock"
CRON_MARKER="# TMS-OS-TELEGRAM-POLL"
mkdir -p "$STATE" "$LOGDIR"

install_cron(){
  local bash_bin="$PREFIX/bin/bash"
  [ -x "$bash_bin" ] || bash_bin="$(command -v bash 2>/dev/null || echo /bin/bash)"
  local line="* * * * * $bash_bin $ROOT/scripts/tms-telegram-poll.sh"
  local current
  current="$(crontab -l 2>/dev/null || true)"
  if printf '%s\n' "$current" | grep -qF "$CRON_MARKER"; then
    echo '[OK] Telegram poll cron đã tồn tại.'
    return 0
  fi
  {
    printf '%s\n' "$current" | grep -v '^[[:space:]]*$' || true
    printf '%s\n' "$CRON_MARKER"
    printf '%s\n' "$line"
  } | crontab - 2>/dev/null
  if printf '%s\n' "$(crontab -l 2>/dev/null || true)" | grep -qF "$CRON_MARKER"; then
    echo '[OK] Đã đăng ký telegram poll cron (mỗi phút).'
    return 0
  fi
  echo '[LỖI] Không đăng ký được telegram poll cron.' >&2
  return 1
}

case "${1:-once}" in
  install-cron) install_cron ;;
  once)
    if command -v flock >/dev/null 2>&1; then
      exec 9>"$LOCKFILE"
      flock -n 9 || exit 0
    fi
    php_bin="$PREFIX/bin/php"
    [ -x "$php_bin" ] || php_bin="$(command -v php 2>/dev/null || true)"
    [ -n "$php_bin" ] || exit 0
    # Đóng fd 9 cho poller: /restart sinh daemon (php-fpm/nginx) — nếu các
    # daemon kế thừa fd khóa của wrapper, flock bị giữ vĩnh viễn và poller
    # không bao giờ chạy lại được sau lần /restart đầu tiên.
    "$php_bin" "$ROOT/scripts/tms-telegram-poll.php" >>"$LOGDIR/telegram-poll.log" 2>&1 9>&- || true
    ;;
  *) echo "Cách dùng: tms-telegram-poll.sh [once|install-cron]" >&2; exit 2 ;;
esac
