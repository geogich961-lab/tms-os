#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.26}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.0.26"; BUILD_DATE="2026-09-23"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.0.26","build":"20260923-v17.0.26","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.0.26","version":"$VERSION",
  "notes":"V17.0.26: nâng cấp Update Center với kênh Stable/Beta, danh sách release và khả năng quay về release cũ trực tiếp trong Web Panel.",
  "features":["Chọn kênh Stable hoặc Beta/Test ngay trong Update Center.","Beta/Test đọc GitHub prerelease; Stable chỉ đề xuất release chính thức.","Hiển thị danh sách release tương thích và cho phép chuyển tới release cụ thể.","Mọi chuyển version vẫn bắt buộc SHA-256, backup source, health check và rollback an toàn."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.0.26",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
