#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.10}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.10"; BUILD_DATE="2026-11-11"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.10","build":"20261111-v17.1.10","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.10","version":"$VERSION",
  "notes":"V17.1.10: Vá banner 'Có phiên bản giao diện mới' hiện lặp vô hạn ở panel. Nguyên nhân: template Nginx gắn cache 1 năm immutable cho mọi file .js, kể cả service-worker.js — chính là file quyết định việc phát hiện bản giao diện mới — nên sau đợt phát hành dồn dập, trình duyệt và Cloudflare ghim các bản service worker khác nhau khiến banner bật lại mãi dù đã bấm Cập nhật. Nay service-worker.js có location riêng với Cache-Control no-cache (áp dụng cho cả cài mới qua install.sh và máy đã cài qua công cụ repair tms-nginx-compat), đồng thời banner chỉ hiện khi bản service worker thật sự mới hơn bản đang chạy và có thêm nút Bỏ qua ghi nhớ theo từng phiên bản.",
  "features":["Panel: service-worker.js không còn bị cache 1 năm immutable — location riêng no-cache trong template Nginx + hàm repair tms_repair_nginx_service_worker_cache cho máy đã cài (chạy qua scripts/tms-nginx-compat.php, có hiệu lực khi Nginx nạp lại cấu hình).","Banner PWA: chỉ hiện khi phiên bản service worker mới thật sự lớn hơn phiên bản đang chạy (so qua VERSION đọc tươi), hết hiện lặp do cache cũ lật qua lật lại.","Banner PWA: thêm nút Bỏ qua — ẩn banner và ghi nhớ theo đúng phiên bản đó, các bản mới hơn sau vẫn báo như thường."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.10",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
