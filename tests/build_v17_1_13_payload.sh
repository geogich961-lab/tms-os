#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.13}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.13"; BUILD_DATE="2026-11-11"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.13","build":"20261111-v17.1.13","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.13","version":"$VERSION",
  "notes":"V17.1.13: Đổi cơ chế tải backup — hết lỗi 520/502 khi tải file backup lớn. Trước đây nút Tải xuống cho PHP đọc file rồi bơm qua trình duyệt bằng readfile(); với file gần 1 GB, toàn bộ dữ liệu phải chảy qua đúng một tiến trình php-cgi, chạy vài phút là bị ngắt giữa chừng (bản 815 MB báo 502, bản 998 MB của volam báo 520). Nay khi bấm Tải xuống, panel tạo một liên kết tĩnh dùng một lần (thư mục mã ngẫu nhiên khó đoán, tự dọn sau 2 giờ) trỏ tới file backup rồi chuyển trình duyệt sang đó; nginx phục vụ file trực tiếp ở tốc độ tối đa và hỗ trợ tải tiếp (Range) khi mạng chập chờn. Nếu không tạo được liên kết, hệ thống tự quay về cách tải cũ nên các file nhỏ không bị ảnh hưởng.",
  "features":["Backup: tải bản backup lớn (~1 GB) không còn lỗi 520/502 — nginx phục vụ trực tiếp qua liên kết tĩnh dùng một lần.","Backup: hỗ trợ tải tiếp (Range) — mạng đứt giữa chừng thì trình duyệt nối tiếp được thay vì hỏng cả lượt tải.","Backup: liên kết tải tự dọn sau 2 giờ; không tạo được liên kết thì tự quay về cách tải cũ."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.13",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
