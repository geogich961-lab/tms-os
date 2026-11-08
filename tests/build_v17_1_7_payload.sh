#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.7}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.7"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.7","build":"20261108-v17.1.7","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.7","version":"$VERSION",
  "notes":"V17.1.7: Sửa triệt để lỗi panel + website PHP sập sau vài phút trên máy không có php-fpm. Nguyên nhân: engine dự phòng php-cgi chạy trần — mặc định tự thoát sau 500 request và chỉ có 1 tiến trình nối tiếp; watchdog/Guardian sửa liên tục đến cạn hạn mức chung 6 lần/giờ rồi buông. Nay php-cgi chạy dạng pool tự quản (tự hồi sinh, không tự thoát), và hạn mức tự sửa tính theo từng dịch vụ.",
  "features":["PHP engine: php-cgi chạy pool 4 children tự hồi sinh (PHP_FCGI_CHILDREN) và tắt giới hạn tự thoát sau 500 request (PHP_FCGI_MAX_REQUESTS=0) — panel và website PHP không còn sập sau vài phút.","PHP engine: máy không có binary php-fpm thì đi thẳng PHP-CGI, bỏ vòng thử khởi động vô ích và WARN lặp trong log.","Tự phục hồi: hạn mức tự sửa 6 lần/giờ tính theo TỪNG dịch vụ ở cả watchdog và Guardian — một dịch vụ lỗi lặp không còn nuốt hết hạn mức của PHP engine."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.7",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
