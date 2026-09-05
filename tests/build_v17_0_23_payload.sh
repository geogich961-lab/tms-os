#!/usr/bin/env bash
# Đóng gói V17.0.23 trực tiếp từ source đã review trên main.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.23}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/release"
VERSION="17.0.23"
BUILD_DATE="2026-09-05"
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
  "internal_version": "17.0.23",
  "build": "${BUILD_DATE//-/}-v17.0.23",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.23",
  "version": "$VERSION",
  "notes": "V17.0.23: harden installer để tự sửa Nginx server_names_hash trước mọi kiểm tra, repair và reload.",
  "features": [
    "Tự thêm server_names_hash_bucket_size 128 và server_names_hash_max_size 4096 khi thiếu.",
    "Tự nâng cấu hình cũ 32/512 lên giá trị an toàn trước nginx -t.",
    "Repair idempotent, không chèn trùng directive khi cài/sửa chữa nhiều lần.",
    "Giữ nguyên cơ chế backup/rollback, website, database và cấu hình Cloudflare.",
    "Làm mới cache PWA bằng Service Worker V17.0.23."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.23",
  "changelog": "V17.0.23: installer Nginx hash safety và regression coverage.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON
printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
