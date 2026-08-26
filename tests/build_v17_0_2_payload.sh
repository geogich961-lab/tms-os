#!/usr/bin/env bash
# Đóng gói V17.0.2 từ backup V16.1.21 bất biến và overlay có kiểm soát.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_ROOT="${BACKUP_ROOT:-/home/ubuntu/backups/tms-os-v16.1.21-20260824T061646Z}"
BASE="$BACKUP_ROOT/source"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.2}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/out"
VERSION="17.0.2"
BUILD_DATE="2026-08-26"

[ -d "$BASE" ] || { echo "Missing immutable V16.1.21 source: $BASE" >&2; exit 1; }
rm -rf -- "$BUILD_ROOT"
mkdir -p -- "$PAYLOAD" "$OUT"
cp -a "$BASE"/. "$PAYLOAD"/

# Chỉ overlay source đã review; không mang runtime data, log hay artifact vào ZIP.
for rel in \
  scripts/install.sh \
  scripts/tms-php-engine.sh \
  scripts/tms-service-core.sh \
  scripts/tms-boot.sh \
  scripts/start-tms.sh \
  scripts/tms-update-worker.php \
  scripts/tms-package-worker.php \
  scripts/lib/installer-compatibility.sh \
  scripts/lib/installer-safety.sh \
  scripts/installer-rollback.sh \
  app/Controllers/UpdateController.php \
  app/Controllers/PluginController.php \
  app/Services/UpdateService.php \
  app/Services/PluginService.php \
  app/Views/updates/index.php \
  app/Views/plugins/index.php \
  routes/web.php \
  config/app.php \
  public/service-worker.js \
  public/tms-pwa-v21.json \
  HUONG_DAN_CAI_DAT.md; do
  [ -f "$ROOT/$rel" ] || { echo "Missing overlay file: $ROOT/$rel" >&2; exit 1; }
  mkdir -p -- "$PAYLOAD/$(dirname -- "$rel")"
  cp -f -- "$ROOT/$rel" "$PAYLOAD/$rel"
done

# RELEASE.json ở ngoài ZIP để checksum không tự tham chiếu.
rm -f -- "$PAYLOAD/RELEASE.json"
find "$PAYLOAD/scripts" -type f -name '*.sh' -exec chmod 700 {} +
chmod 700 "$PAYLOAD/scripts/tms-update-worker.php" "$PAYLOAD/scripts/tms-package-worker.php"

cat > "$PAYLOAD/docs/runtime-package-v17.0.2.md" <<'DOC'
# TMS OS V17.0.2 — Runtime Package Background Worker Hotfix

V17.0.2 sửa việc cài Cloudflared/Cloudflare Tunnel từ Package Manager có thể
chặn request PHP. Panel chỉ xác thực và ghi job nền, sau đó trả JSON ngay. Worker
độc lập xử lý đúng package từ danh mục allowlist, giới hạn thời gian chờ, xác minh
binary sau cài đặt và ghi kết quả an toàn để giao diện polling hiển thị. Worker
không dừng, restart hoặc sửa PHP Engine, Nginx, SQLite, MariaDB hay Redis.
DOC

( cd "$PAYLOAD" && zip -qr9 "$OUT/TMS_OS_LATEST.zip" . )
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product": "TMS OS",
  "display_name": "TMS OS",
  "internal_version": "17.0.2",
  "build": "20260826-v17.0.2",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.2",
  "version": "$VERSION",
  "notes": "V17.0.2: sửa Runtime Package để cài Cloudflared chạy nền có timeout, phản hồi trạng thái rõ ràng và không làm treo panel.",
  "features": [
    "Cài Runtime Package được xếp hàng và xử lý bởi worker nền, không chặn request PHP/Nginx.",
    "Cloudflared được kiểm tra theo allowlist, có timeout và xác minh binary trước khi báo thành công.",
    "Package Manager polling JSON có CSRF/session guard, trạng thái đang cài và lỗi có thể thử lại.",
    "Worker Runtime Package không dừng hoặc khởi động lại PHP Engine, Nginx, SQLite, MariaDB hay Redis.",
    "Giữ nguyên Update Center một chạm, Universal Compatibility Installer, Repair và dữ liệu người dùng."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.2",
  "changelog": "V17.0.2: hotfix Cloudflared Runtime Package chạy nền, giới hạn thời gian chờ và polling trạng thái an toàn.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
