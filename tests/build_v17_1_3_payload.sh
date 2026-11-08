#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.3}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.3"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.3","build":"20261108-v17.1.3","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.3","version":"$VERSION",
  "notes":"V17.1.3: Sửa lỗi đẩy backup offsite lên cloud luôn báo 'Không tìm thấy tệp archive để đẩy' dù bản sao lưu đã tạo thành công — nguyên nhân là hàm tạo backup trả câu thông báo thay vì mã ID của bản sao lưu cho vòng backup tự động. Nay tạo backup trả đúng ID; thông báo khi tạo backup thủ công giữ nguyên như trước.",
  "features":["Backup tự động & Offsite: sửa lỗi vòng backup không tìm thấy tệp archive khi đẩy lên cloud (rclone) — bản sao lưu tạo xong nay được đẩy offsite đúng như cấu hình.","Giữ nguyên mọi hành vi còn lại của V17.1.2: Terminal lệnh thủ công, gói rclone, nút Kiểm tra kết nối rclone, nhãn Thùng rác, hàng Pin đã gộp."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.3",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
