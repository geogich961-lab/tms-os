#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.11}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.11"; BUILD_DATE="2026-11-11"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.11","build":"20261111-v17.1.11","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.11","version":"$VERSION",
  "notes":"V17.1.11: Vá hộp thoại Tạo backup không chọn được website. Ô chọn website trong hộp thoại vốn bị ẩn và không có mã nào hiện nó lên khi chọn phạm vi 'Một website', nên bản sao lưu luôn lặng lẽ lấy website đầu tiên trong danh sách (site mặc định, chỉ vài KB) thay vì website người dùng muốn. Nay ô chọn website hiện đúng khi chọn phạm vi 'Một website', có lựa chọn trống '-- Chọn website --' để tránh gửi nhầm giá trị mặc định, và server từ chối tạo snapshot khi chưa chọn hoặc chọn website không tồn tại, kèm thông báo lỗi rõ ràng.",
  "features":["Backup: ô chọn website trong hộp thoại Tạo backup nay hiện đúng khi chọn phạm vi 'Một website' — trước đây ô này bị ẩn vĩnh viễn nên không cách nào chọn được website cần sao lưu.","Backup: select website có lựa chọn trống '-- Chọn website --' và bắt buộc chọn; server báo lỗi rõ ràng khi chưa chọn thay vì lặng lẽ sao lưu website đầu tiên trong danh sách."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.11",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
