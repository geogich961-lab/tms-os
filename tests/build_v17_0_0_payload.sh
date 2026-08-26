#!/usr/bin/env bash
# Đóng gói V17.0.0 từ backup V16.1.21 bất biến và danh sách overlay có kiểm soát.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_ROOT="${BACKUP_ROOT:-/home/ubuntu/backups/tms-os-v16.1.21-20260824T061646Z}"
BASE="$BACKUP_ROOT/source"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.0.0}"
PAYLOAD="$BUILD_ROOT/payload"
OUT="$BUILD_ROOT/out"
VERSION="17.0.0"
BUILD_DATE="2026-08-26"

[ -d "$BASE" ] || { echo "Missing immutable V16.1.21 source: $BASE" >&2; exit 1; }
rm -rf -- "$BUILD_ROOT"
mkdir -p -- "$PAYLOAD" "$OUT"
cp -a "$BASE"/. "$PAYLOAD"/

# Chỉ overlay các thành phần UCI/V17 đã được review; tránh mang theo dữ liệu,
# release metadata cũ hoặc thay đổi chưa kiểm chứng từ worktree.
for rel in \
  scripts/install.sh \
  scripts/tms-php-engine.sh \
  scripts/tms-service-core.sh \
  scripts/tms-boot.sh \
  scripts/start-tms.sh \
  scripts/lib/installer-compatibility.sh \
  scripts/lib/installer-safety.sh \
  scripts/installer-rollback.sh \
  config/app.php \
  public/service-worker.js \
  HUONG_DAN_CAI_DAT.md; do
  [ -f "$ROOT/$rel" ] || { echo "Missing overlay file: $ROOT/$rel" >&2; exit 1; }
  mkdir -p -- "$PAYLOAD/$(dirname -- "$rel")"
  cp -f -- "$ROOT/$rel" "$PAYLOAD/$rel"
done

# RELEASE.json phải là manifest ngoài ZIP để checksum không tạo vòng lặp.
rm -f -- "$PAYLOAD/RELEASE.json"
find "$PAYLOAD/scripts" -type f -name '*.sh' -exec chmod 700 {} +

cat > "$PAYLOAD/docs/universal-installer-v17.0.0.md" <<'DOC'
# TMS OS V17.0.0 — Universal Compatibility Installer

V17.0.0 kế thừa Universal Compatibility Installer, preflight và rollback an toàn từ V16.1.21. Bản này chuẩn hóa Auto-start với Termux:Boot và chỉ khôi phục Redis sau reboot khi `redis-server` đã được người dùng cài. Redis luôn là dịch vụ tùy chọn; lỗi hoặc việc thiếu Redis không chặn PHP, Nginx, SQLite/MariaDB hay Panel.
DOC

( cd "$PAYLOAD" && zip -qr9 "$OUT/TMS_OS_LATEST.zip" . )
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product": "TMS OS",
  "display_name": "TMS OS",
  "internal_version": "17.0.0",
  "build": "20260826-v17",
  "channel": "stable",
  "version_visible_to_user": true,
  "released_at": "$BUILD_DATE",
  "name": "TMS OS V17.0.0",
  "version": "$VERSION",
  "notes": "V17.0.0: Auto-start minh bạch với Termux:Boot và tự khôi phục Redis nếu Redis đã được cài; giữ nguyên UCI, Repair, rollback và dữ liệu người dùng.",
  "features": [
    "Auto-start phân biệt rõ cấu hình đã tạo và trạng thái app Termux:Boot.",
    "Khôi phục Redis sau reboot chỉ khi redis-server tồn tại.",
    "Redis là optional: lỗi Redis không chặn PHP, Nginx, SQLite/MariaDB hoặc Panel.",
    "Kế thừa preflight, xác minh SHA-256, backup và rollback của Universal Compatibility Installer."
  ],
  "checksum_sha256": "$SHA256",
  "build_date": "$BUILD_DATE",
  "version_display": "TMS OS V17.0.0",
  "changelog": "V17.0.0: hoàn thiện Auto-start Termux:Boot và khôi phục Redis tùy chọn sau reboot; giữ Repair an toàn và Universal Compatibility Installer.",
  "download_url": "https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip",
  "release_date": "$BUILD_DATE"
}
JSON

printf '%s\n' "BUILD_ROOT=$BUILD_ROOT" "PAYLOAD=$PAYLOAD" "ZIP=$OUT/TMS_OS_LATEST.zip" "RELEASE_JSON=$OUT/RELEASE.json" "SHA256=$SHA256"
