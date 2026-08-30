#!/usr/bin/env bash
# Đóng gói V17.0.20 trực tiếp từ source đã review trên main (không cần backup BASE).
# Payload chỉ chứa 5 source root (app|config|public|routes|scripts), không kèm
# verifier nội bộ, metadata tự tham chiếu hay dữ liệu runtime.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.20}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/release"
VERSION="17.0.20"
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
  "internal_version": "17.0.20",
  "build": "${BUILD_DATE//-/}-v17.0.20",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.20",
  "version": "$VERSION",
  "notes": "V17.0.20: cảnh báo vận hành qua Telegram theo ngưỡng (bộ nhớ, RAM, pin 100% quá lâu, nhiệt độ, Tunnel rớt) kiểm tra mỗi 15 phút; Guardian tự heal Cloudflare Tunnel và crond; cấu hình trong trang Thông báo.",
  "features": [
    "Cảnh báo Telegram theo ngưỡng: bộ nhớ trống thấp, RAM cạn, pin sạc 100% quá lâu (nguy cơ phồng pin), nhiệt độ pin cao và Cloudflare Tunnel rớt.",
    "Kiểm tra mỗi 15 phút qua cron job tms-alerts-check; mỗi loại cảnh báo chỉ nhắc lại sau cooldown cấu hình được.",
    "Cấu hình ngưỡng và chạy thử ngay trong trang Thông báo; đo pin/nhiệt độ cần termux-api.",
    "Guardian tự khởi động lại cloudflared khi tunnel rớt và crond khi có cron job bật (tuỳ chọn trong cấu hình Guardian).",
    "Kèm bảo mật V17.0.18 và backup tự động + offsite rclone V17.0.19."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.20",
  "changelog": "V17.0.20: bảo mật — khoá đăng nhập sau chuỗi sai, default-deny auth middleware, CommandRunner.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
