#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
SUB="$ROOT/scripts/install.sh"
BOOTSTRAP="$ROOT/install.sh"

fail() { echo "FAIL: $*" >&2; exit 1; }

# The sub-installer must use a non-zero, documented cancellation result after
# clearing the transaction marker.  Returning 0 would let the root bootstrap
# configure Termux:Boot and print a false success message.
grep -Fq 'exit 64' "$SUB" || fail 'sub-installer does not signal clean-install cancellation'
grep -Fq '[ĐÃ HỦY] Cài mới không được xác nhận' "$SUB" || fail 'sub-installer cancellation message missing'

# The bootstrap must consume that cancellation result before the auto-start
# section; this contract prevents it from reaching steps 6/7 after a cancel.
CANCEL_LINE="$(grep -nF 'if [ "$RC" -eq 64 ]; then' "$BOOTSTRAP" | head -n1 | cut -d: -f1)"
AUTOSTART_LINE="$(grep -nF 'Bước 6/7: Tự khởi động TMS OS' "$BOOTSTRAP" | head -n1 | cut -d: -f1)"
[ -n "$CANCEL_LINE" ] || fail 'bootstrap does not handle cancellation result 64'
[ -n "$AUTOSTART_LINE" ] || fail 'bootstrap auto-start section missing'
[ "$CANCEL_LINE" -lt "$AUTOSTART_LINE" ] || fail 'cancellation is handled after auto-start'
sed -n "${CANCEL_LINE},$((CANCEL_LINE + 5))p" "$BOOTSTRAP" | grep -Fq 'exit 0' || fail 'bootstrap does not stop cleanly after cancellation'
grep -Fq '[ĐÃ HỦY] Bạn chưa gõ YES' "$BOOTSTRAP" || fail 'bootstrap cancellation output missing'

# Optional auto-start must not print a success message when tms-boot rejects
# a missing start script (the exact situation caused by the cancelled install).
grep -Fq 'if bash "$SRC/scripts/tms-boot.sh" on; then' "$BOOTSTRAP" || fail 'auto-start result is not checked'
grep -Fq '[CẢNH BÁO] Không thể thiết lập auto-start' "$BOOTSTRAP" || fail 'auto-start failure warning missing'

echo 'installer clean cancel tests: PASS'
