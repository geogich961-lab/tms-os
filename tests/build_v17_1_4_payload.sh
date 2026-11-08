#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.4}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.4"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.4","build":"20261108-v17.1.4","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.4","version":"$VERSION",
  "notes":"V17.1.4: Nút 'Chạy backup tự động ngay' chuyển sang chạy ở chế độ nền. Trước đây vòng backup chạy ngay trong trang web: nén và đẩy bản sao lưu lớn lên cloud mất nhiều phút nên bị quá thời gian chờ (lỗi 524) và bị giết giữa chừng — bản sao lưu không lên được cloud, panel còn treo theo. Nay bấm nút là vòng backup chạy nền bằng tiến trình riêng (giống hệt vòng backup theo lịch), bấm xong rời trang thoải mái; kết quả hiện ở trang Backup và qua Telegram khi chạy xong. Thêm khóa chống hai vòng backup chạy chồng nhau; trang Backup hiển thị trạng thái đang chạy nền.",
  "features":["Backup tự động & Offsite: nút Chạy ngay nay chạy nền — hết lỗi quá thời gian chờ và bị giết giữa chừng khi đẩy bản sao lưu lớn lên cloud qua giao diện web.","Chống chạy trùng: vòng backup theo lịch, nút bấm tay và tiến trình nền dùng chung một khóa — không bao giờ nén/đẩy hai vòng cùng lúc; trang Backup hiện rõ trạng thái đang chạy nền và tạm vô hiệu nút chạy.","Giữ nguyên bản vá V17.1.3 (tạo backup trả đúng mã ID để vòng tự động đẩy offsite) và mọi tính năng của V17.1.2."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.4",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
