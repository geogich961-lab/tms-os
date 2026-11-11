#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.12}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.12"; BUILD_DATE="2026-11-11"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.12","build":"20261111-v17.1.12","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.12","version":"$VERSION",
  "notes":"V17.1.12: Tạo backup thủ công nay chạy ở chế độ nền và báo cáo qua Telegram khi xong. Sau khi V17.1.11 sửa xong phần chọn website, việc bấm Tạo backup cho website nặng như volam vẫn lỗi 524 vì quá trình nén chạy đồng bộ ngay trong yêu cầu web, quá ~100 giây thì bị Cloudflare cắt. Nay nút Tạo backup ngay chỉ đăng ký yêu cầu rồi trả về ngay; worker tách rời scripts/tms-manual-backup.php (cùng mẫu với backup tự động chạy nền của V17.1.4) thực hiện việc nén không giới hạn thời gian. Trạng thái đang tạo và kết quả lần tạo gần nhất hiện ngay trên trang Sao lưu, và khi hoàn tất — thành công hay thất bại — hệ thống gửi báo cáo Telegram (thành công kèm phạm vi/website và dung lượng, thất bại kèm lý do).",
  "features":["Backup: tạo backup thủ công chạy nền — bấm tạo là trả về ngay, hết lỗi 524 khi sao lưu website nặng như volam.","Backup: báo cáo qua Telegram khi tạo backup thủ công hoàn tất (kèm phạm vi/website + dung lượng) hoặc thất bại (kèm lý do).","Backup: trang Sao lưu hiện trạng thái đang tạo ở chế độ nền và kết quả lần tạo thủ công gần nhất."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.12",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
