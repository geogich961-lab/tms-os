#!/data/data/com.termux/files/usr/bin/bash
HOME="${HOME:-/data/data/com.termux/files/home}"
nginx -s stop 2>/dev/null || true
bash "$HOME/tms-os/scripts/tms-php-engine.sh" stop
DBMODE="$(cat "$HOME/.tms-os/db-mode" 2>/dev/null || echo mariadb)"
if [ "$DBMODE" = "mariadb" ]; then
  pkill -f mariadbd 2>/dev/null || true
fi
pkill -x sshd 2>/dev/null || true
command -v termux-wake-unlock >/dev/null 2>&1 && termux-wake-unlock || true
echo "TMS OS đã dừng."
