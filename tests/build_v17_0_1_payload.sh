#!/usr/bin/env bash
# Đóng gói V17.0.1 từ backup V16.1.21 bất biến và danh sách overlay có kiểm soát.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_ROOT="${BACKUP_ROOT:-/home/ubuntu/backups/tms-os-v16.1.21-20260824T061646Z}"
BASE="$BACKUP_ROOT/source"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.1}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/out"
VERSION="17.0.1"
BUILD_DATE="2026-08-26"

[ -d "$BASE" ] || { echo "Missing immutable V16.1.21 source: $BASE" >&2; exit 1; }
rm -rf -- "$BUILD_ROOT"
mkdir -p -- "$PAYLOAD" "$OUT"
cp -a "$BASE"/. "$PAYLOAD"/

# Overlay rõ ràng: không kéo theo dữ liệu runtime hoặc thay đổi chưa kiểm chứng.
for rel in \
  scripts/install.sh \
  scripts/tms-php-engine.sh \
  scripts/tms-service-core.sh \
  scripts/tms-boot.sh \
  scripts/start-tms.sh \
  scripts/tms-update-worker.php \
  scripts/lib/installer-compatibility.sh \
  scripts/lib/installer-safety.sh \
  scripts/installer-rollback.sh \
  app/Controllers/UpdateController.php \
  app/Services/UpdateService.php \
  app/Views/updates/index.php \
  routes/web.php \
  config/app.php \
  public/service-worker.js \
  HUONG_DAN_CAI_DAT.md; do
  [ -f "$ROOT/$rel" ] || { echo "Missing overlay file: $ROOT/$rel" >&2; exit 1; }
  mkdir -p -- "$PAYLOAD/$(dirname -- "$rel")"
  cp -f -- "$ROOT/$rel" "$PAYLOAD/$rel"
done

# RELEASE.json ở ngoài ZIP để checksum không tự tham chiếu.
rm -f -- "$PAYLOAD/RELEASE.json"
find "$PAYLOAD/scripts" -type f -name '*.sh' -exec chmod 700 {} +
chmod 700 "$PAYLOAD/scripts/tms-update-worker.php"

cat > "$PAYLOAD/docs/universal-installer-v17.0.1.md" <<'DOC'
# TMS OS V17.0.1 — Stable Update Center Hotfix

V17.0.1 cho phép panel V17.0.0 nhận và áp dụng cập nhật một chạm an toàn. Request web chỉ ghi job rồi trả JSON; worker nền mới tải, kiểm tra SHA-256, backup, swap, health-check và restart. Giao diện polling trạng thái job qua API JSON để tự xác minh phiên bản sau restart. Redis vẫn là tùy chọn và chỉ được khôi phục nếu `redis-server` tồn tại.
DOC

( cd "$PAYLOAD" && zip -qr9 "$OUT/TMS_OS_LATEST.zip" . )
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product": "TMS OS",
  "display_name": "TMS OS",
  "internal_version": "17.0.1",
  "build": "20260826-v17.0.1",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.1",
  "version": "$VERSION",
  "notes": "V17.0.1: khôi phục Cập nhật nhanh một chạm trực tiếp từ panel V17.0.0. Request trả JSON trước khi worker nền áp dụng gói và giao diện tự xác minh sau restart.",
  "features": [
    "Update Center ghi job và trả JSON trước khi PHP/Nginx restart.",
    "Worker nền tải gói, xác minh SHA-256, backup, swap, health-check và rollback nếu cần.",
    "Panel polling trạng thái job qua JSON, tương thích với giao diện V17.0.0.",
    "Kế thừa Auto-start Termux:Boot và Redis tùy chọn của V17.0.0.",
    "Giữ nguyên Universal Compatibility Installer, Repair và dữ liệu người dùng."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.1",
  "changelog": "V17.0.1: sửa Cập nhật nhanh trực tiếp trong panel bằng hàng đợi worker nền và polling JSON bền vững qua restart.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
