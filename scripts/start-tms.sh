#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"; PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
mkdir -p "$HOME/logs/services"
bash "$HOME/tms-os/scripts/tms-php-engine.sh" start
pgrep -x nginx >/dev/null 2>&1 || nginx
pgrep -f mariadbd >/dev/null 2>&1 || mariadbd-safe --datadir="$PREFIX/var/lib/mysql" >"$HOME/logs/services/mariadb.log" 2>&1 &
pgrep -x sshd >/dev/null 2>&1 || sshd
[ -f "$HOME/tms-os/scripts/tms-guardian.sh" ] && bash "$HOME/tms-os/scripts/tms-guardian.sh" start || true
command -v termux-wake-lock >/dev/null 2>&1 && termux-wake-lock || true
echo "TMS OS đang chạy: http://127.0.0.1:8888"
