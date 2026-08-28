#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
INSTALLER="$ROOT/scripts/install.sh"
PACKAGER="$ROOT/scripts/build-release-package.sh"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/tms-installer-storage-test.XXXXXX")"

cleanup() {
  rm -rf "$WORK"
}
trap cleanup EXIT

grep -Fq 'for part in app config public routes scripts;' "$INSTALLER"
grep -Fq 'mkdir -p "$SOURCE_DIR/storage/logs" "$SOURCE_DIR/storage/sessions" "$SOURCE_DIR/storage/cache"' "$INSTALLER"
grep -Fq '[ -d "$SOURCE_DIR/storage" ] && cp -a "$SOURCE_DIR/storage/." "$STAGING/storage/" || true' "$INSTALLER"

bash "$PACKAGER" "$WORK/TMS_OS_LATEST.zip" >/dev/null
if unzip -Z1 "$WORK/TMS_OS_LATEST.zip" | grep -Eq '^(storage/|tests/|docs/|install\.sh$|RELEASE\.json$|scripts/verify-uci-payload\.sh$)'; then
  echo 'release package must contain only runtime source, not storage, tests, docs or internal tooling' >&2
  exit 1
fi
unzip -Z1 "$WORK/TMS_OS_LATEST.zip" | grep -Eq '^app/'
unzip -Z1 "$WORK/TMS_OS_LATEST.zip" | grep -Eq '^config/'
unzip -Z1 "$WORK/TMS_OS_LATEST.zip" | grep -Eq '^public/'
unzip -Z1 "$WORK/TMS_OS_LATEST.zip" | grep -Eq '^routes/'
unzip -Z1 "$WORK/TMS_OS_LATEST.zip" | grep -Eq '^scripts/'

echo 'installer storage layout tests: OK'
