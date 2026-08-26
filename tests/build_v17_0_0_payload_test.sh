#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT

BUILD_ROOT="$TMP/build" bash "$ROOT/tests/build_v17_0_0_payload.sh" >/dev/null
ZIP="$TMP/build/out/TMS_OS_LATEST.zip"
MANIFEST="$TMP/build/out/RELEASE.json"
EXTRACT="$TMP/extract"

unzip -tq "$ZIP" >/dev/null
[ "$(sha256sum "$ZIP" | awk '{print $1}')" = "$(sed -n 's/.*"checksum_sha256"[[:space:]]*:[[:space:]]*"\([a-f0-9]\{64\}\)".*/\1/p' "$MANIFEST")" ] || { echo 'V17 build regression: checksum mismatch.' >&2; exit 1; }
grep -Fq '"version": "17.0.0"' "$MANIFEST" || { echo 'V17 build regression: version metadata is wrong.' >&2; exit 1; }
unzip -q "$ZIP" -d "$EXTRACT"
grep -Fq 'Platform V17.0.0' "$EXTRACT/config/app.php" || { echo 'V17 build regression: app version was not overlaid.' >&2; exit 1; }
grep -Fq 'command -v redis-server' "$EXTRACT/scripts/start-tms.sh" || { echo 'V17 build regression: optional Redis restore missing.' >&2; exit 1; }
grep -Fq 'Trạng thái Termux:Boot: Đã cài.' "$EXTRACT/scripts/tms-boot.sh" || { echo 'V17 build regression: Termux:Boot status clarity missing.' >&2; exit 1; }
[ ! -e "$EXTRACT/RELEASE.json" ] || { echo 'V17 build regression: stale RELEASE.json inside payload.' >&2; exit 1; }
echo 'V17 payload build regression test: OK'
