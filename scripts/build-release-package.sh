#!/usr/bin/env bash
# TMS OS release packaging: archive tracked source and require runtime layout.
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUTPUT="${1:-$ROOT/TMS_OS_LATEST.zip}"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/tms-os-release.XXXXXX")"
SOURCE="$WORK/source"
PAYLOAD="$WORK/payload"

cleanup() {
  rm -rf "$WORK"
}
trap cleanup EXIT

git -C "$ROOT" diff --check
mkdir -p "$SOURCE" "$PAYLOAD"
git -C "$ROOT" archive --format=tar HEAD | tar -xf - -C "$SOURCE"

# Asset cập nhật chỉ chứa source runtime cần để swap an toàn. Không đưa tests,
# docs, metadata phát hành, verifier nội bộ hoặc dữ liệu runtime của thiết bị vào ZIP.
for part in app config public routes scripts; do
  [ -d "$SOURCE/$part" ] || { echo "[LỖI] Gói release thiếu thư mục: $part" >&2; exit 1; }
  cp -a "$SOURCE/$part" "$PAYLOAD/$part"
done
rm -f "$PAYLOAD/scripts/verify-uci-payload.sh"
rm -f "$OUTPUT"
(cd "$PAYLOAD" && zip -qr "$OUTPUT" .)

echo "[OK] Đã tạo $OUTPUT"
sha256sum "$OUTPUT"
