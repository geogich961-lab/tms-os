#!/usr/bin/env sh
# Bootstrap repair cho Update Center V17.0.4. Script chỉ đồng bộ các tệp cần
# thiết để worker restart mới có thể hoạt động; không gọi installer và không
# chạm cấu hình, dữ liệu, website, Cloudflare hay credentials.
set -eu

TARGET=${TMS_OS_TARGET:-"$HOME/tms-os"}
RAW_BASE='https://raw.githubusercontent.com/geogich961-lab/tms-os/v17.0.5'
SKIP_RESTART=${TMS_UPDATE_REPAIR_SKIP_RESTART:-0}

usage() {
  cat <<'EOF'
Sử dụng: bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/v17.0.5/scripts/tms-update-repair.sh) --apply

Script chỉ sửa bootstrap Update Center để các bản V17.0.4 cũ có thể tự khởi
động lại an toàn sau cập nhật. Nó không cài lại TMS OS và không chạm dữ liệu.
EOF
}

if [ "${1:-}" != '--apply' ] || [ "$#" -ne 1 ]; then
  usage >&2
  exit 64
fi

if [ ! -d "$TARGET/app" ] || [ ! -f "$TARGET/scripts/start-tms.sh" ]; then
  printf '%s\n' "[LỖI] Không tìm thấy TMS OS tại $TARGET. Dừng để tránh ghi nhầm vị trí." >&2
  exit 1
fi

STAGE=$(mktemp -d "${TMPDIR:-/tmp}/tms-update-repair.XXXXXX")
trap 'rm -rf "$STAGE"' EXIT HUP INT TERM

FILES='app/Services/UpdateService.php app/Views/updates/index.php scripts/tms-update-restart.sh scripts/tms-update-repair.sh'

for rel in $FILES; do
  dest="$STAGE/$rel"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL --connect-timeout 12 --max-time 90 --retry 2 "$RAW_BASE/$rel" -o "$dest"
done

php -l "$STAGE/app/Services/UpdateService.php" >/dev/null
php -l "$STAGE/app/Views/updates/index.php" >/dev/null
sh -n "$STAGE/scripts/tms-update-restart.sh"
sh -n "$STAGE/scripts/tms-update-repair.sh"

for rel in $FILES; do
  src="$STAGE/$rel"
  dest="$TARGET/$rel"
  parent=$(dirname "$dest")
  mkdir -p "$parent"
  if [ -f "$dest" ] && [ ! -f "$dest.tms-update-repair.bak" ]; then
    cp -p "$dest" "$dest.tms-update-repair.bak"
  fi
  tmp=$(mktemp "$parent/.tms-update-repair.XXXXXX")
  cat "$src" > "$tmp"
  if [ -e "$dest" ]; then
    chmod "$(stat -c '%a' "$dest" 2>/dev/null || printf '644')" "$tmp" || true
  elif [ "${rel#scripts/}" != "$rel" ]; then
    chmod 700 "$tmp"
  else
    chmod 644 "$tmp"
  fi
  mv -f "$tmp" "$dest"
done

printf '%s\n' '[OK] Đã thay bootstrap Update Center và lưu tối đa một bản .tms-update-repair.bak cho từng tệp cũ.'
if [ "$SKIP_RESTART" = '1' ]; then
  exit 0
fi

printf '%s\n' '[INFO] Đang khởi động lại TMS OS để nạp worker mới...'
if ! bash "$TARGET/scripts/start-tms.sh"; then
  printf '%s\n' '[LỖI] Tệp sửa đã được đặt an toàn nhưng TMS OS chưa tự khởi động lại. Hãy chạy start-tms.sh một lần từ Termux.' >&2
  exit 1
fi

attempt=1
while [ "$attempt" -le 10 ]; do
  if curl -fsS --max-time 3 http://127.0.0.1:8888/login >/dev/null 2>&1; then
    printf '%s\n' '[OK] Panel local đã phản hồi. Bạn có thể dùng Update Center cho phiên bản kế tiếp.'
    exit 0
  fi
  attempt=$((attempt + 1))
  sleep 2
done

printf '%s\n' '[LỖI] Worker mới đã được cài nhưng panel local chưa phản hồi. Hãy chạy start-tms.sh một lần và không bấm cập nhật lại.' >&2
exit 1
