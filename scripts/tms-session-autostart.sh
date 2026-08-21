#!/data/data/com.termux/files/usr/bin/bash
# ============================================================
# tms-session-autostart.sh — Tự khởi động TMS OS khi mở Termux
# Được gọi từ ~/.bashrc (hook session-login). Chỉ khởi động lại
# Nginx + PHP nếu chưa chạy; giới hạn 1 lần khởi động cho mỗi
# phiên đăng nhập của một ngày (dùng lockfile theo ngày).
#
# KHÔNG blocking: mọi thứ chạy ngầm, không in gì khi thành công.
# ============================================================
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
STATE="$HOME/.tms-os"
ROOT="$HOME/tms-os"
mkdir -p "$STATE"

# Khóa theo ngày: mỗi ngày chỉ khởi động 1 lần cho mỗi phiên login
DAY="$(date +%Y-%m-%d)"
LOCK="$STATE/.session-start-$DAY"
[ -f "$LOCK" ] && exit 0
: > "$LOCK" 2>/dev/null || exit 0

# 1) Nginx
if ! pgrep -x nginx >/dev/null 2>&1; then
  if nginx -t >/dev/null 2>&1; then
    (nginx >> "$HOME/logs/services/boot.log" 2>&1) &
  fi
fi

# 2) PHP engine
if [ -x "$ROOT/scripts/tms-php-engine.sh" ]; then
  if ! pgrep -f "php-cgi -b 127.0.0.1:9000" >/dev/null 2>&1; then
    (bash "$ROOT/scripts/tms-php-engine.sh" start >> "$HOME/logs/services/boot.log" 2>&1) &
  fi
fi

# 3) Database theo chế độ đã chọn
if [ -f "$STATE/db-mode" ]; then
  DBMODE="$(cat "$STATE/db-mode" 2>/dev/null || echo sqlite)"
  if [ "$DBMODE" = "mariadb" ] && ! pgrep -f mariadbd >/dev/null 2>&1; then
    (mariadbd-safe --datadir="$PREFIX/var/lib/mysql" >> "$HOME/logs/services/mariadb-session.log" 2>&1) &
  fi
fi

# 4) Giữ máy không ngủ (termux-wake-lock) nếu có app hỗ trợ
if command -v termux-wake-lock >/dev/null 2>&1; then
  termux-wake-lock 2>/dev/null || true
fi
exit 0
