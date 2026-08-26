#!/usr/bin/env bash
# Đóng gói V17.0.3 từ backup V16.1.21 bất biến và overlay có kiểm soát.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_ROOT="${BACKUP_ROOT:-/home/ubuntu/backups/tms-os-v16.1.21-20260824T061646Z}"
BASE="$BACKUP_ROOT/source"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.3}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/out"
VERSION="17.0.3"
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

cat > "$PAYLOAD/docs/update-center-v17.0.3.md" <<'DOC'
# TMS OS V17.0.3 — Update Center Verified Swap Hotfix

V17.0.3 sửa luồng Cập nhật nhanh trên thiết bị Android: phản hồi AJAX luôn trả
job để polling theo đúng yêu cầu; source mới được chuyển thư mục an toàn vào vùng
chạy thay vì xóa source cũ trước; và worker chỉ báo thành công sau khi config/app.php
xác nhận đúng version mục tiêu. Nếu chuyển source hoặc version verification thất bại,
hệ thống phục hồi source trước và trả lỗi rõ ràng, không báo cập nhật thành công giả.
DOC

( cd "$PAYLOAD" && zip -qr9 "$OUT/TMS_OS_LATEST.zip" . )
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product": "TMS OS",
  "display_name": "TMS OS",
  "internal_version": "17.0.3",
  "build": "20260826-v17.0.3",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.3",
  "version": "$VERSION",
  "notes": "V17.0.3: sửa Cập nhật nhanh để worker trả job, chuyển source an toàn và chỉ báo thành công sau khi xác nhận version thực tế.",
  "features": [
    "Cập nhật nhanh trả về job nền đầy đủ để giao diện polling chính xác trên PHP-CGI Android.",
    "Source mới được kích hoạt bằng chuyển thư mục có vùng khôi phục, không xóa source chạy trước.",
    "Worker xác minh config/app.php đã đổi đúng version mục tiêu trước khi báo cập nhật thành công.",
    "Khi swap hoặc xác minh version thất bại, source trước được phục hồi và lỗi hiển thị rõ ràng.",
    "Giữ nguyên Runtime Package Cloudflared nền, Universal Compatibility Installer, Repair và dữ liệu người dùng."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.3",
  "changelog": "V17.0.3: hotfix Update Center — worker trả job, swap source an toàn và xác nhận version thực tế trước khi thành công.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
