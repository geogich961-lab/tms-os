#!/data/data/com.termux/files/usr/bin/bash
set -Eeuo pipefail
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
HOME="${HOME:-/data/data/com.termux/files/home}"
TMS="$HOME/tms-os"
SITES="$PREFIX/etc/nginx/sites-enabled"
PHP_CONF_DIR="$PREFIX/etc/php/conf.d"
QUARANTINE="$HOME/.tms-os/quarantine/$(date +%Y%m%d_%H%M%S)"

echo '============================================'
echo ' TMS OS - Repair System'
echo '============================================'
[ -d "$TMS" ] || { echo '[LỖI] Chưa tìm thấy TMS OS tại ~/tms-os'; exit 1; }
mkdir -p "$TMS/storage/logs" "$TMS/storage/sessions" "$TMS/storage/cache" "$HOME/logs/nginx" "$HOME/logs/services" "$HOME/backups" "$SITES" "$PHP_CONF_DIR" "$QUARANTINE"
chmod -R 700 "$TMS/storage"
cat > "$PHP_CONF_DIR/99-tms-os.ini" <<INI
memory_limit=256M
upload_max_filesize=512M
post_max_size=520M
max_execution_time=300
max_input_time=300
display_errors=Off
log_errors=On
session.save_path="$TMS/storage/sessions"
INI
find "$TMS" -type f -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done
nginx -t
bash "$TMS/scripts/tms-php-engine.sh" restart
nginx -s reload 2>/dev/null || nginx
DBMODE="$(cat "$HOME/.tms-os/db-mode" 2>/dev/null || echo mariadb)"
if [ "$DBMODE" = "mariadb" ]; then
  pgrep -f mariadbd >/dev/null 2>&1 || mariadbd-safe --datadir="$PREFIX/var/lib/mysql" >"$HOME/logs/services/mariadb.log" 2>&1 &
fi
pgrep -x sshd >/dev/null 2>&1 || sshd
sleep 2
curl -fsS http://127.0.0.1:8888/login >/dev/null
printf '[OK] Sửa chữa hoàn tất.\nPanel: http://127.0.0.1:8888\n'


# Khôi phục cấu hình kết nối MariaDB cho TMS OS
mkdir -p "$HOME/.tms-os"
cat > "$HOME/.tms-os/mariadb-client.cnf" <<CNF
[client]
user=root
host=localhost
protocol=socket
CNF
chmod 600 "$HOME/.tms-os/mariadb-client.cnf"

# Migration: chuyển tài khoản quản trị sang thư mục cấu hình mới.
mkdir -p "$HOME/.tms-os/config"; chmod 700 "$HOME/.tms-os/config"
if [ -f "$HOME/.redmi-mini-vps/config/panel-secret.php" ] && [ ! -f "$HOME/.tms-os/config/panel-secret.php" ]; then
  cp "$HOME/.redmi-mini-vps/config/panel-secret.php" "$HOME/.tms-os/config/panel-secret.php"
  chmod 600 "$HOME/.tms-os/config/panel-secret.php"
  echo '[OK] Đã chuyển tài khoản quản trị sang thư mục cấu hình mới (.tms-os).'
fi
