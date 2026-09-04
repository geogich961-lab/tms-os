#!/usr/bin/env bash
# Đóng gói V17.0.21 trực tiếp từ source đã review trên main.
# Payload chỉ chứa app|config|public|routes|scripts và ép LF cho file text.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.21}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/release"
VERSION="17.0.21"
BUILD_DATE="2026-09-04"
PHP_BIN="${PHP_BIN:-php}"

[ -f "$ROOT/config/app.php" ] || { echo "Chạy script từ đúng repo (thiếu config/app.php)." >&2; exit 1; }

rm -rf -- "$BUILD_ROOT"
mkdir -p -- "$PAYLOAD" "$OUT"

git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
chmod 700 "$PAYLOAD/scripts/tms-update-worker.php" "$PAYLOAD/scripts/tms-package-worker.php" "$PAYLOAD/scripts/tms-nginx-compat.php" 2>/dev/null || true

"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"

SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product": "TMS OS",
  "display_name": "TMS OS",
  "internal_version": "17.0.21",
  "build": "${BUILD_DATE//-/}-v17.0.21",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.21",
  "version": "$VERSION",
  "notes": "V17.0.21: sửa lỗi Website Control Center trên Nginx khi server_names_hash bucket mặc định quá nhỏ; tự repair nginx.conf khi nâng cấp và khi mở panel; sửa HTML pattern tên website cho Chrome mới.",
  "features": [
    "Tự bổ sung server_names_hash_bucket_size 128 và server_names_hash_max_size 4096 vào nginx.conf cũ theo cách idempotent.",
    "Update Center tự repair, chạy nginx -t và reload Nginx an toàn trước khi xác nhận cập nhật hoàn tất.",
    "Panel tự repair cấu hình Nginx còn thiếu để các máy nâng cấp từ V17.0.20 không cần cài lại TMS OS.",
    "Sửa pattern tên website/clone website để tương thích biểu thức chính quy Unicode v trên Chrome mới.",
    "Làm mới cache PWA bằng Service Worker V17.0.21."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.21",
  "changelog": "V17.0.21: sửa server_names_hash Nginx và lỗi pattern Website Control Center.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
