#!/data/data/com.termux/files/usr/bin/bash
set -u
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"; HOME="${HOME:-/data/data/com.termux/files/home}"
echo '================================================='
echo ' TMS OS - FACTORY RESET'
echo '================================================='
echo 'SẼ XÓA: TMS OS, website, database, admin, log, backup, plugin và cấu hình.'
echo 'KHÔNG XÓA: Termux và các gói đã cài.'
printf 'Nhập chính xác FACTORY RESET để tiếp tục: '; read -r CONFIRM
[ "$CONFIRM" = 'FACTORY RESET' ] || { echo 'Đã hủy.'; exit 0; }
pkill -f 'php-fpm: master process' 2>/dev/null || true
pkill -f 'php-cgi.*127.0.0.1:9000' 2>/dev/null || true
pkill -x nginx 2>/dev/null || true
pkill -f mariadbd 2>/dev/null || true
pkill -x sshd 2>/dev/null || true
rm -rf "$HOME/tms" "$HOME/tms-os" "$HOME/.tms-os/config" "$HOME/.tms-os" "$HOME/.tms-os-staging-"* "$HOME/.redmi-mini-vps" "$HOME/websites" "$HOME/logs" "$HOME/backups"
rm -rf "$PREFIX/var/lib/mysql"
rm -f "$PREFIX/etc/php/conf.d/99-tms-os.ini" "$PREFIX/etc/nginx/sites-enabled/default.conf"
echo '[OK] Factory Reset hoàn tất. Thiết bị sẵn sàng cài lại TMS OS.'
