#!/data/data/com.termux/files/usr/bin/bash
set -Eeuo pipefail
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
HOME="${HOME:-/data/data/com.termux/files/home}"
BACKUP_ROOT="$HOME/.tms-os/backups"
[ -d "$BACKUP_ROOT" ] || BACKUP_ROOT="$HOME/.redmi-mini-vps/backups"

LATEST="$(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d | sort -r | head -n 1)"
[ -n "$LATEST" ] || { echo "Không tìm thấy bản sao lưu."; exit 1; }

if [ -d "$LATEST/tms-os" ]; then
    rm -rf "$HOME/tms-os"
    cp -a "$LATEST/tms-os" "$HOME/tms-os"
fi

if [ -f "$LATEST/nginx.conf" ]; then
    cp "$LATEST/nginx.conf" "$PREFIX/etc/nginx/nginx.conf"
fi

nginx -t
nginx -s reload 2>/dev/null || nginx

echo "Đã khôi phục từ: $LATEST"
