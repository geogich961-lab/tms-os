#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.0}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.0"; BUILD_DATE="2026-11-07"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.0","build":"20261107-v17.1.0","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.0","version":"$VERSION",
  "notes":"V17.1.0: phát hành chính thức dòng 17.1 — watchdog/Guardian tự phục hồi khi web treo (kiểm tra HTTP thật, leo thang restart PHP/Nginx, hết lỗi chết lặng do kế thừa flock), bot Telegram chuyển sang polling độc lập panel kèm lệnh /restart từ xa, và tách hai công tắc Telegram /status và /restart bật/tắt độc lập.",
  "features":["Watchdog có HTTP health check thật + nhịp tim Guardian, tự leo thang restart PHP/Nginx khi panel treo, FPM tự tái chế worker kẹt.","Bot Telegram nhận lệnh qua polling (PHP CLI + cron) nên vẫn hoạt động khi panel sập; lệnh /restart khởi động lại dịch vụ từ xa.","Hai công tắc Telegram độc lập trong trang Cron Jobs: Lệnh /status (gồm /help, /checkupdate) và Lệnh /restart.","Kèm toàn bộ tính năng dòng Beta 17.1: File Explorer P0, kiểm tra sức khoẻ website ở tiến trình nền tách biệt."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.0",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
