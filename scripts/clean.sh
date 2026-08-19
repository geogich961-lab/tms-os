#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
echo '============================================'; echo ' TMS OS - Clean Setup'; echo '============================================'
echo 'Lệnh này xóa bản cài lỗi nhưng GIỮ các gói Termux và file ZIP trong Download.'
printf 'Nhập CLEAN để tiếp tục: '; read -r CONFIRM
[ "$CONFIRM" = 'CLEAN' ] || { echo 'Đã hủy.'; exit 0; }
pkill -f 'php-fpm: master process' 2>/dev/null || true
pkill -f 'php-cgi.*127.0.0.1:9000' 2>/dev/null || true
pkill -x nginx 2>/dev/null || true
DBMODE="$(cat "$HOME/.tms-os/db-mode" 2>/dev/null || echo mariadb)"
if [ "$DBMODE" = "mariadb" ]; then
  pkill -f mariadbd 2>/dev/null || true
fi
pkill -x sshd 2>/dev/null || true
rm -rf "$HOME/tms" "$HOME/tms-os" "$HOME/.tms-os/config" "$HOME/.tms-os" "$HOME/.tms-os-staging-"* "$HOME/.redmi-mini-vps" "$HOME/websites" "$HOME/logs" "$HOME/backups"
rm -f "$HOME/start-tms.sh" "$HOME/stop-tms.sh"
echo '[OK] Đã dọn sạch. Có thể giải nén và cài lại từ đầu.'
