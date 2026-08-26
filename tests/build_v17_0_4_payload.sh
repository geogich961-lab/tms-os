#!/usr/bin/env bash
# Đóng gói V17.0.4 từ backup V16.1.21 bất biến và overlay có kiểm soát.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_ROOT="${BACKUP_ROOT:-/home/ubuntu/backups/tms-os-v16.1.21-20260824T061646Z}"
BASE="$BACKUP_ROOT/source"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.4}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/out"
VERSION="17.0.4"
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

cat > "$PAYLOAD/docs/update-center-v17.0.4.md" <<'DOC'
# TMS OS V17.0.4 — Update Center Completion Hotfix

V17.0.4 hoàn tất hotfix Cập nhật nhanh trên Android: worker ghi trạng thái completed
trước khi restart PHP/Nginx; khi source đã đổi đúng version, status tự hoàn tất và dọn
job còn sót; job không còn worker sẽ tự kết thúc quá hạn để không khóa lần cập nhật sau.
Giao diện đối chiếu version đích của chính yêu cầu thay vì chờ một trạng thái cũ.
DOC

( cd "$PAYLOAD" && zip -qr9 "$OUT/TMS_OS_LATEST.zip" . )
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product": "TMS OS",
  "display_name": "TMS OS",
  "internal_version": "17.0.4",
  "build": "20260826-v17.0.4",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.4",
  "version": "$VERSION",
  "notes": "V17.0.4: hoàn tất job cập nhật trước restart, tự nhận diện source đã đổi version và tự dọn hàng đợi quá hạn.",
  "features": [
    "Worker ghi kết quả completed trước khi lên lịch restart PHP-CGI/Nginx.",
    "Status tự xác nhận và dọn job còn sót khi source đã đổi đúng version mục tiêu.",
    "Job không còn worker quá 15 phút tự chuyển lỗi và dọn queue để người dùng thử lại.",
    "Giao diện đối chiếu version đích của yêu cầu để không kẹt xác minh state cũ.",
    "Giữ nguyên Runtime Package Cloudflared nền, Universal Compatibility Installer, Repair và dữ liệu người dùng."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.4",
  "changelog": "V17.0.4: hotfix Update Center — hoàn tất state trước restart, tự dọn job kẹt và xác nhận version đích.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
