#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.9}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.9"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.9","build":"20261108-v17.1.9","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.9","version":"$VERSION",
  "notes":"V17.1.9: Thêm đường tải dự phòng cho Update Center. Khi đường tải gói qua github.com lỗi (sự cố 504 diện rộng 08/11/2026 khiến không cập nhật được dù api.github.com vẫn thông), UpdateService tự chuyển sang tải qua API asset của GitHub (kèm header Accept: application/octet-stream bắt buộc) cho cả file ZIP và RELEASE.json kiểm checksum. Cả hai đường cùng lỗi mới báo thất bại như trước.",
  "features":["Update Center: tự chuyển đường tải dự phòng qua GitHub API khi đường tải chính github.com lỗi (504/timeout) — áp dụng cho cả nút Cập nhật ngay, quay về bản cũ và cập nhật qua Telegram.","Tải checksum RELEASE.json cũng có đường dự phòng tương ứng; vẫn bắt buộc khớp SHA-256 mới cho cài."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.9",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
