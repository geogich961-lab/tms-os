#!/usr/bin/env bash
# Đóng gói V17.0.17 trực tiếp từ source đã review trên main (không cần backup BASE).
# Payload chỉ chứa 5 source root (app|config|public|routes|scripts), không kèm
# verifier nội bộ, metadata tự tham chiếu hay dữ liệu runtime.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.17}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/release"
VERSION="17.0.17"
BUILD_DATE="2026-08-30"
PHP_BIN="${PHP_BIN:-php}"

[ -f "$ROOT/config/app.php" ] || { echo "Chạy script từ đúng repo (thiếu config/app.php)." >&2; exit 1; }

rm -rf -- "$BUILD_ROOT"
mkdir -p -- "$PAYLOAD" "$OUT"

# Chỉ 5 source root vào ZIP thiết bị; storage/tests/docs/assets stay out.
for rel in app config public routes scripts; do
  cp -a "$ROOT/$rel" "$PAYLOAD/$rel"
done

# Verifier nội bộ chỉ dùng cho CI, không lên thiết bị.
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
# File ẩn từ hệ điều hành/editor không vào payload.
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete

chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
chmod 700 "$PAYLOAD/scripts/tms-update-worker.php" "$PAYLOAD/scripts/tms-package-worker.php" 2>/dev/null || true

"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"

SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product": "TMS OS",
  "display_name": "TMS OS",
  "internal_version": "17.0.17",
  "build": "${BUILD_DATE//-/}-v17.0.17",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.17",
  "version": "$VERSION",
  "notes": "V17.0.17: Update Center chịu lỗi kết nối GitHub — báo đúng nguyên nhân từng endpoint, ép IPv4 dự phòng, thêm Chẩn đoán kết nối; PHP engine nâng giới hạn upload 100M/110M để upload ZIP thủ công không bị chặn.",
  "features": [
    "Update Center báo rõ nguyên nhân lỗi từng endpoint GitHub (DNS, TLS, HTTP 403) thay vì thông báo chung chung.",
    "Ép IPv4 và bỏ endpoint hỏng — chịu lỗi mạng IPv6/DNS phổ biến trên Android.",
    "Nút Chẩn đoán kết nối GitHub và API /api/updates/diagnose để xác định bước kết nối bị kẹt.",
    "PHP-CGI, PHP HTTP và PHP-FPM nâng upload_max_filesize/post_max_size lên 100M/110M — gói TMS_OS_LATEST.zip tải thủ công không còn bị từ chối.",
    "Fallback metadata RELEASE.json và kiểm tra checksum SHA-256 giữ nguyên, bảo toàn storage/Cloudflare khi nâng cấp."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.17",
  "changelog": "V17.0.17: chịu lỗi kết nối GitHub Update Center + nâng giới hạn upload PHP engine cho đường nâng thủ công.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
