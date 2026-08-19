#!/data/data/com.termux/files/usr/bin/bash
set -Eeuo pipefail
HOME="${HOME:-/data/data/com.termux/files/home}"
STAMP="$(date +%Y%m%d_%H%M%S)"
OUT="$HOME/backups/tms_quick_$STAMP.zip"
mkdir -p "$HOME/backups"
cd "$HOME"
zip -qr "$OUT" tms-os websites logs 2>/dev/null || zip -qr "$OUT" tms-os websites
echo "Đã tạo: $OUT"
