#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.2}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.2"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.2","build":"20261108-v17.1.2","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.2","version":"$VERSION",
  "notes":"V17.1.2: Terminal hỗ trợ lệnh thủ công cho quản trị viên (timeout 60 giây, ghi nhật ký kiểm toán); thêm gói rclone vào Runtime Packages; Backup offsite không còn chặn lưu cấu hình khi chưa cài rclone, có thêm nút Kiểm tra kết nối rclone và hướng dẫn điền remote; nút Thùng rác trong File Manager có thêm chữ; gộp hàng Pin bị trùng trong Resource Monitor.",
  "features":["Terminal: thêm mục Lệnh thủ công — chạy lệnh bash trong thư mục home, timeout 60 giây, output tối đa 100KB, ghi nhật ký kiểm toán; lệnh chẩn đoán preset giữ nguyên.","Runtime Packages: thêm gói rclone (cài một chạm) phục vụ Backup offsite lên Google Drive.","Backup tự động & Offsite: chưa cài rclone/chưa nhập remote không còn chặn lưu cấu hình — backup nội bộ vẫn chạy, offsite báo trạng thái chưa sẵn sàng; thêm chú thích cách điền rclone remote và nút Kiểm tra kết nối rclone.","File Manager: nút vào Thùng rác có thêm chữ 'Thùng rác' bên cạnh icon cho dễ nhận biết.","Resource Monitor: gộp hai hàng 'Pin' bị trùng (chữ trạng thái và thanh %) thành một hàng duy nhất."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.2",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
