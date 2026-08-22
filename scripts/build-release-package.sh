#!/usr/bin/env bash
# TMS OS release packaging: archive tracked source and require runtime layout.
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUTPUT="${1:-$ROOT/TMS_OS_LATEST.zip}"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/tms-os-release.XXXXXX")"

cleanup() {
  rm -rf "$WORK"
}
trap cleanup EXIT

git -C "$ROOT" diff --check
git -C "$ROOT" archive --format=tar HEAD | tar -xf - -C "$WORK"

for part in app config public routes scripts storage; do
  [ -d "$WORK/$part" ] || { echo "[LỖI] Gói release thiếu thư mục: $part" >&2; exit 1; }
done

mkdir -p "$WORK/storage/logs" "$WORK/storage/sessions" "$WORK/storage/cache"
rm -f "$OUTPUT"
(cd "$WORK" && zip -qr "$OUTPUT" .)

echo "[OK] Đã tạo $OUTPUT"
sha256sum "$OUTPUT"
