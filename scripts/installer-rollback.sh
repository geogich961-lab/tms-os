#!/data/data/com.termux/files/usr/bin/bash
# Khôi phục transaction TMS OS gần nhất. Không nhận đường dẫn tùy ý từ người dùng.
set -Eeuo pipefail
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
HOME="${HOME:-/data/data/com.termux/files/home}"
LIB="$(cd "$(dirname "$0")" && pwd)/lib/installer-safety.sh"
[ -r "$LIB" ] || { echo '[LỖI] Thiếu installer-safety.sh.' >&2; exit 50; }
export TMS_STATE_DIR="$HOME/.tms-os-installer-state"
# shellcheck disable=SC1090
. "$LIB"
tms_safety_init

if [ "${1:-}" = "--list" ]; then
  printf 'Các backup TMS OS:\n'
  find "$HOME/.tms-os-installer-backups" -mindepth 1 -maxdepth 1 -type d -print 2>/dev/null | sort -r || true
  exit 0
fi

if [ "${1:-}" != "--yes" ]; then
  echo 'Lệnh này sẽ khôi phục giao dịch installer chưa commit gần nhất.'
  printf 'Gõ YES để tiếp tục: '
  read -r confirm
  [ "$confirm" = YES ] || { echo 'Đã hủy.'; exit 2; }
fi

tms_rollback_active
echo '[OK] Đã xử lý rollback. Kiểm tra panel bằng: curl -i http://127.0.0.1:8888/login'
