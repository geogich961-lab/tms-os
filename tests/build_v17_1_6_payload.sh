#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.6}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.6"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.6","build":"20261108-v17.1.6","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.6","version":"$VERSION",
  "notes":"V17.1.6: Sửa tương thích PHP HTTP cho Website Control Center. Khi thiết bị phải chạy PHP built-in HTTP server, website tạo bởi TMS nay proxy đúng giao thức HTTP và truyền document root riêng; không còn gửi FastCGI nhầm gây 502 cho api/index.php. Nạp lại website cũ để TMS tự chuyển vhost an toàn.",
  "features":["Website: tự chọn handler theo php-engine-policy (FastCGI hoặc php-http), bảo toàn hai chế độ cài đặt.","PHP HTTP router: thực thi đúng PHP và trả file tĩnh từ document root của từng website thay vì chỉ root panel.","Website cũ: nút Nạp lại tự chuyển vhost FastCGI cũ sang php-http khi thiết bị đang dùng engine này, có rollback nếu Nginx không hợp lệ."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.6",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
