#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"; PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
mkdir -p "$HOME/logs/services"
# Tự động dọn dẹp các tiến trình bị treo hoặc cũ để tránh lỗi "Address already in use" và 504
# Kill PHP-CGI và PHP-FPM cũ
pkill -9 -f 'php-cgi -b 127.0.0.1:9000' 2>/dev/null || true
pkill -9 -f 'php-fpm' 2>/dev/null || true
# Kill Nginx cũ và giải phóng cổng
pkill -9 -x nginx 2>/dev/null || true
# Giải phóng cổng 8888 cưỡng bức nếu vẫn bị kẹt
fuser -k 8888/tcp 2>/dev/null || true
fuser -k 9000/tcp 2>/dev/null || true

bash "$HOME/tms-os/scripts/tms-php-engine.sh" start
# Khởi động Nginx và đảm bảo nó chạy được
if ! nginx 2>/dev/null; then
  pkill -9 -x nginx 2>/dev/null || true
  fuser -k 8888/tcp 2>/dev/null || true
  sleep 1
  nginx
fi
DBMODE="$(cat "$HOME/.tms-os/db-mode" 2>/dev/null || echo mariadb)"
if [ "$DBMODE" = "mariadb" ]; then
  pgrep -f mariadbd >/dev/null 2>&1 || mariadbd-safe --datadir="$PREFIX/var/lib/mysql" >"$HOME/logs/services/mariadb.log" 2>&1 &
fi
pgrep -x sshd >/dev/null 2>&1 || sshd
[ -f "$HOME/tms-os/scripts/tms-guardian.sh" ] && bash "$HOME/tms-os/scripts/tms-guardian.sh" start || true
command -v termux-wake-lock >/dev/null 2>&1 && termux-wake-lock || true
echo "TMS OS đang chạy: http://127.0.0.1:8888 (database: $DBMODE)"
