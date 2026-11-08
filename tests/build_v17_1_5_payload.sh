#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.5}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.5"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.5","build":"20261108-v17.1.5","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.5","version":"$VERSION",
  "notes":"V17.1.5: Chặn hiệu ứng 'tuyết lăn' dung lượng của bản sao lưu. Trước đây mỗi bản sao lưu toàn hệ thống lại đóng gói cả kho chứa các bản sao lưu trước vào trong, nên dung lượng phình không ngừng (thực tế trên máy: 246MB → 744MB → 2,18GB) và thời gian đẩy lên cloud dài theo từng vòng. Nay gói sao lưu loại trừ thư mục chứa kho backup — mỗi bản chỉ gồm dữ liệu hệ thống thật, dung lượng và thời gian đẩy ổn định trở lại. Khôi phục hệ thống không bị ảnh hưởng (metadata vẫn được giữ trong gói).",
  "features":["Backup: loại trừ kho chứa bản sao lưu cũ khỏi gói sao lưu mới — hết phình dung lượng qua từng vòng, tạo và đẩy offsite nhanh ổn định như lần đầu.","Giữ nguyên V17.1.4 (nút Chạy backup ngay chạy ở chế độ nền + khóa chống hai vòng chạy trùng) và V17.1.3 (vòng tự động đẩy offsite đúng bản sao lưu vừa tạo)."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.5",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
