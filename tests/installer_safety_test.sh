#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
export HOME="$TMP/home" PREFIX="$TMP/prefix" TMS_STATE_DIR="$TMP/state" TMS_REPORT="$TMP/report.txt" TMS_SAFETY_LOG="$TMP/safety.log"
mkdir -p "$HOME" "$PREFIX/bin" "$PREFIX/var/tmp" "$PREFIX/var/run" "$HOME/tms-os" "$HOME/.tms-os/tmp"
# Không gọi package thật; chỉ kiểm tra các primitive backup/transaction.
# shellcheck disable=SC1090
source "$ROOT/scripts/lib/installer-safety.sh"
tms_safety_init
printf 'old-data\n' > "$HOME/tms-os/data.txt"
printf 'old-runtime\n' > "$HOME/.tms-os/runtime.txt"
BACKUP="$TMP/backup"
tms_create_backup "$BACKUP" "$HOME/tms-os" "$PREFIX/etc/nginx/nginx.conf" "$HOME/.tms-os"
[ -f "$BACKUP/MANIFEST.sha256" ]
[ -f "$BACKUP/tms-os/data.txt" ]
[ -f "$BACKUP/.tms-os/runtime.txt" ]
printf 'new-data\n' > "$HOME/tms-os/data.txt"
printf 'new-runtime\n' > "$HOME/.tms-os/runtime.txt"
TMS_TXN_ID=test TMS_TXN_MODE=repair TMS_TXN_BACKUP="$BACKUP" TMS_TXN_STAGING="$TMP/staging" TMS_TXN_TARGET="$HOME/tms-os" TMS_TXN_NGINX="$PREFIX/etc/nginx/nginx.conf" TMS_TXN_RUNTIME="$HOME/.tms-os" TMS_TXN_PHASE=activated TMS_TXN_COMMITTED=0
tms_write_txn
tms_rollback_active
grep -qx 'old-data' "$HOME/tms-os/data.txt"
grep -qx 'old-runtime' "$HOME/.tms-os/runtime.txt"
! tms_restore_backup "$BACKUP" "$TMP/outside" "$PREFIX/etc/nginx/nginx.conf" "$HOME/.tms-os"
printf 'installer safety tests: PASS\n'
