#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.8}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.8"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.8","build":"20261108-v17.1.8","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.8","version":"$VERSION",
  "notes":"V17.1.8: Sửa lỗi nút Áp dụng trong mục Gói đã lưu trữ của Update Center. Form xóa hàng loạt từng bọc cả bảng danh sách trong khi mỗi gói có form Áp dụng riêng — HTML cấm form lồng nhau nên trình duyệt bỏ qua form con, biến nút Áp dụng thành nút xóa: bấm Áp dụng là gói bị xóa (user gặp 08/11/2026 khi cập nhật thủ công). Nay checkbox liên kết form xóa qua thuộc tính form=, các form Áp dụng/Xóa từng gói đứng độc lập đúng nghĩa.",
  "features":["Update Center: nút Áp dụng ở mục Gói đã lưu trữ hoạt động đúng — hết lỗi form lồng nhau khiến bấm Áp dụng lại xóa gói.","Cập nhật thủ công bằng file ZIP tải lên nay dùng được trọn vẹn: Kiểm tra và lưu -> Áp dụng, không phụ thuộc đường tải GitHub."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.8",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
