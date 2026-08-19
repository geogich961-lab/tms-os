#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
echo 'Gỡ TMS OS nhưng giữ website, database và backup.'
printf 'Nhập UNINSTALL để tiếp tục: '; read -r C
[ "$C" = UNINSTALL ] || { echo 'Đã hủy.'; exit 0; }
pkill -f 'php-fpm: master process' 2>/dev/null || true
pkill -f 'php-cgi.*127.0.0.1:9000' 2>/dev/null || true
pkill -x nginx 2>/dev/null || true
rm -rf "$HOME/tms-os" "$HOME/.tms-os/config" "$HOME/.redmi-mini-vps"
echo '[OK] Đã gỡ TMS OS. Website, database và backup vẫn được giữ.'
