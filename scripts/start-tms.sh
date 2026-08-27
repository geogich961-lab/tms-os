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
# Giải phóng các cổng do Nginx/PHP của TMS OS quản lý nếu vẫn bị kẹt.
for port in 80 8081 8082 8888 9000; do
  fuser -k "${port}/tcp" 2>/dev/null || true
done

bash "$HOME/tms-os/scripts/tms-php-engine.sh" start
# Khởi động Nginx và đảm bảo nó chạy được
if ! nginx 2>/dev/null; then
  pkill -9 -x nginx 2>/dev/null || true
  for port in 80 8081 8082 8888; do
    fuser -k "${port}/tcp" 2>/dev/null || true
  done
  sleep 1
  if ! nginx; then
    echo '[LỖI] Nginx không thể khởi động do cổng vẫn bị chiếm hoặc cấu hình không hợp lệ.' >&2
    exit 1
  fi
fi
DBMODE="$(cat "$HOME/.tms-os/db-mode" 2>/dev/null || echo mariadb)"
if [ "$DBMODE" = "mariadb" ]; then
  pgrep -f mariadbd >/dev/null 2>&1 || mariadbd-safe --datadir="$PREFIX/var/lib/mysql" >"$HOME/logs/services/mariadb.log" 2>&1 &
fi
pgrep -x sshd >/dev/null 2>&1 || sshd
# Redis là dịch vụ tùy chọn. Chỉ khôi phục sau reboot khi người dùng đã cài
# redis-server; thất bại của Redis không được làm gián đoạn Panel, PHP, Nginx
# hoặc database SQLite/MariaDB.
if command -v redis-server >/dev/null 2>&1; then
  bash "$HOME/tms-os/scripts/tms-service-core.sh" redis start >>"$HOME/logs/services/redis.log" 2>&1 || \
    printf '%s\n' '[WARN] Redis không khởi động được; các dịch vụ TMS OS còn lại vẫn tiếp tục hoạt động.' >>"$HOME/logs/services/redis.log"
fi
[ -f "$HOME/tms-os/scripts/tms-guardian.sh" ] && bash "$HOME/tms-os/scripts/tms-guardian.sh" start || true
# Cron Jobs chạy bằng crond trong user-space Termux; thiếu cronie không làm panel dừng.
[ -f "$HOME/tms-os/scripts/tms-cron-engine.sh" ] && bash "$HOME/tms-os/scripts/tms-cron-engine.sh" start >>"$HOME/logs/services/cron.log" 2>&1 || true
# Khôi phục File Browser đã được cài từ các phiên bản cũ; từng script tự tránh khởi động trùng.
for service_script in "$HOME"/.tms-os/scripts/start-filebrowser-*.sh; do
  [ -f "$service_script" ] || continue
  bash "$service_script" >>"$HOME/logs/services/managed-services.log" 2>&1 || true
done
# Khôi phục Cloudflare Tunnel nếu người dùng đã cấu hình; helper không in bí mật.
[ -x "$HOME/tms-os/scripts/tms-cloudflare-tunnel.sh" ] && bash "$HOME/tms-os/scripts/tms-cloudflare-tunnel.sh" start >>"$HOME/logs/services/cloudflare-tunnel.log" 2>&1 || true
command -v termux-wake-lock >/dev/null 2>&1 && termux-wake-lock || true
echo "TMS OS đang chạy: http://127.0.0.1:8888 (database: $DBMODE)"
