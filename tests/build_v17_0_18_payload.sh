#!/usr/bin/env bash
# Đóng gói V17.0.18 trực tiếp từ source đã review trên main (không cần backup BASE).
# Payload chỉ chứa 5 source root (app|config|public|routes|scripts), không kèm
# verifier nội bộ, metadata tự tham chiếu hay dữ liệu runtime.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.18}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/release"
VERSION="17.0.18"
BUILD_DATE="2026-08-30"
PHP_BIN="${PHP_BIN:-php}"

[ -f "$ROOT/config/app.php" ] || { echo "Chạy script từ đúng repo (thiếu config/app.php)." >&2; exit 1; }

rm -rf -- "$BUILD_ROOT"
mkdir -p -- "$PAYLOAD" "$OUT"

# Chỉ 5 source root vào ZIP thiết bị; storage/tests/docs/assets stay out.
# Trích từ git archive (object store) để đảm bảo LF tuyệt đối — build trên máy
# Windows với autocrlf sẽ làm bash script CRLF vỡ trên Ubuntu/Termux.
rm -rf -- "$PAYLOAD"
mkdir -p -- "$PAYLOAD"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"

# Verifier nội bộ chỉ dùng cho CI, không lên thiết bị.
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
# File ẩn từ hệ điều hành/editor không vào payload.
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
# Ép LF cho file text khi nén do make_payload_zip.php (grep MSYS không dò được CRLF).

chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
chmod 700 "$PAYLOAD/scripts/tms-update-worker.php" "$PAYLOAD/scripts/tms-package-worker.php" 2>/dev/null || true

"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"

SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product": "TMS OS",
  "display_name": "TMS OS",
  "internal_version": "17.0.18",
  "build": "${BUILD_DATE//-/}-v17.0.18",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.18",
  "version": "$VERSION",
  "notes": "V17.0.18: cứng hoá bảo mật panel — đăng nhập bị khoá tạm sau 5 lần sai liên tiếp; mọi route mặc định yêu cầu đăng nhập trừ danh sách public trắng; thêm CommandRunner làm điểm gọi lệnh hệ thống chuẩn; routes/web.php tổ chức lại dễ đọc.",
  "features": [
    "Rate limit đăng nhập: sai 5 lần trong 15 phút bị khoá 15 phút, hiển thị thời gian chờ còn lại.",
    "Default-deny auth middleware ở Router: quên guard() trong controller không còn làm lộ endpoint; /login, /, /status, /telegram/webhook, /api/public-status, /api/updates/run là public có chủ đích.",
    "API chưa đăng nhập trả JSON 401 AUTH_REQUIRED thống nhất; trang thường redirect /login?next= giữ nguyên điểm đến sau đăng nhập.",
    "CommandRunner: điểm gọi lệnh hệ thống tập trung với escapeshellarg bắt buộc cho mọi dữ liệu từ request.",
    "Giữ nguyên các bản sửa V17.0.17: Update Center chịu lỗi kết nối GitHub, giới hạn upload 100M/110M."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.18",
  "changelog": "V17.0.18: bảo mật — khoá đăng nhập sau chuỗi sai, default-deny auth middleware, CommandRunner.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
