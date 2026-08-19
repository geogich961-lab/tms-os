#!/data/data/com.termux/files/usr/bin/bash
set -u
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"; HOME="${HOME:-/data/data/com.termux/files/home}"; TMS="$HOME/tms-os"
PASS=0; FAIL=0
check(){ if eval "$2" >/dev/null 2>&1; then printf '[PASS] %s\n' "$1"; PASS=$((PASS+1)); else printf '[FAIL] %s\n' "$1"; FAIL=$((FAIL+1)); fi; }
echo '============================================'; echo ' TMS OS - System Diagnostics'; echo '============================================'
check 'PHP' 'command -v php'
check 'PHP-CGI' 'command -v php-cgi'
check 'Nginx' 'command -v nginx'
check 'MariaDB' 'command -v mariadb'
check 'OpenSSH' 'command -v sshd'
check 'Source ~/tms-os' '[ -d "$TMS" ]'
check 'Admin secret' '[ -f "$HOME/.tms-os/config/panel-secret.php" ] || [ -f "$HOME/.redmi-mini-vps/config/panel-secret.php" ]'
check 'Nginx config' 'nginx -t'
check 'PHP Engine port 9000' 'curl -fsS --max-time 2 http://127.0.0.1:8888/login'
check 'MariaDB service' 'pgrep -f mariadbd'
echo '--------------------------------------------'; echo "PASS: $PASS | FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && echo '[OK] Hệ thống hoạt động bình thường.' || echo 'Có lỗi. Chạy: bash ~/tms-os/scripts/repair.sh'
exit "$FAIL"
