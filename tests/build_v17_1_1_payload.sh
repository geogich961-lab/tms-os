#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.1}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.1"; BUILD_DATE="2026-11-08"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.1","build":"20261108-v17.1.1","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.1","version":"$VERSION",
  "notes":"V17.1.1: bản vá nội dung — cập nhật câu chữ trang giới thiệu (landing); giữ nguyên toàn bộ V17.1.0: watchdog/Guardian tự phục hồi khi web treo, bot Telegram polling kèm lệnh /restart từ xa, hai công tắc Telegram /status và /restart độc lập.",
  "features":["Cập nhật nội dung trang giới thiệu (landing): câu mô tả và đoạn giới thiệu mới.","Giữ nguyên V17.1.0: watchdog có HTTP health check thật + nhịp tim Guardian, tự leo thang restart PHP/Nginx khi panel treo.","Bot Telegram polling độc lập panel, lệnh /restart từ xa, hai công tắc /status và /restart bật/tắt độc lập trong trang Cron Jobs."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.1",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
