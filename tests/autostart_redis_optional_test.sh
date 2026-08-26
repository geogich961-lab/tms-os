#!/usr/bin/env bash
# Hồi quy V17: Auto-start chỉ khôi phục Redis khi binary tồn tại và không làm
# hỏng đường khởi động cốt lõi khi Redis không được cài.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT

mkdir -p "$TMP/bin" "$TMP/home/tms-os/scripts" "$TMP/home/.tms-os" "$TMP/prefix"
CALLS="$TMP/calls.log"

for cmd in pkill fuser pgrep sshd termux-wake-lock; do
  cat > "$TMP/bin/$cmd" <<'SH'
#!/usr/bin/env bash
exit 1
SH
  chmod +x "$TMP/bin/$cmd"
done
cat > "$TMP/bin/nginx" <<'SH'
#!/usr/bin/env bash
exit 0
SH
cat > "$TMP/bin/redis-server" <<'SH'
#!/usr/bin/env bash
exit 0
SH
cat > "$TMP/home/tms-os/scripts/tms-php-engine.sh" <<'SH'
#!/usr/bin/env bash
exit 0
SH
cat > "$TMP/home/tms-os/scripts/tms-service-core.sh" <<'SH'
#!/usr/bin/env bash
printf '%s %s\n' "${1:-}" "${2:-}" >> "${FAKE_CALLS:?}"
exit 0
SH
chmod +x "$TMP/bin/nginx" "$TMP/bin/redis-server" "$TMP/home/tms-os/scripts/tms-php-engine.sh" "$TMP/home/tms-os/scripts/tms-service-core.sh"
printf 'sqlite\n' > "$TMP/home/.tms-os/db-mode"

run_start() {
  HOME="$TMP/home" PREFIX="$TMP/prefix" PATH="$TMP/bin:$PATH" FAKE_CALLS="$CALLS" \
    bash "$ROOT/scripts/start-tms.sh" >/dev/null
}

run_start
grep -Fxq 'redis start' "$CALLS" || { echo 'Auto-start regression: Redis installed but was not restored.' >&2; exit 1; }

: > "$CALLS"
mv "$TMP/bin/redis-server" "$TMP/bin/redis-server.disabled"
run_start
if grep -Fq 'redis ' "$CALLS"; then
  echo 'Auto-start regression: Redis was started although redis-server is unavailable.' >&2
  exit 1
fi

cat > "$TMP/bin/pm" <<'SH'
#!/usr/bin/env bash
if [ "${FAKE_BOOT_INSTALLED:-0}" = 1 ]; then printf '%s\n' 'package:com.termux.boot'; fi
SH
cat > "$TMP/bin/termux-open" <<'SH'
#!/usr/bin/env bash
printf '%s\n' "${1:-}" >> "${FAKE_OPENED:?}"
SH
chmod +x "$TMP/bin/pm" "$TMP/bin/termux-open"

BOOT_HOME="$TMP/boot-home"
mkdir -p "$BOOT_HOME/tms-os/scripts"
printf '%s\n' '#!/usr/bin/env bash' 'exit 0' > "$BOOT_HOME/tms-os/scripts/start-tms.sh"
chmod +x "$BOOT_HOME/tms-os/scripts/start-tms.sh"
FAKE_OPENED="$TMP/opened.log" HOME="$BOOT_HOME" PREFIX="$TMP/prefix" PATH="$TMP/bin:$PATH" \
  FAKE_BOOT_INSTALLED=1 bash "$ROOT/scripts/tms-boot.sh" on >/dev/null
[ -x "$BOOT_HOME/.termux/boot/tms-os.sh" ] || { echo 'Auto-start regression: boot script was not created.' >&2; exit 1; }
BOOT_STATUS="$(HOME="$BOOT_HOME" PREFIX="$TMP/prefix" PATH="$TMP/bin:$PATH" FAKE_BOOT_INSTALLED=1 bash "$ROOT/scripts/tms-boot.sh" status)"
printf '%s\n' "$BOOT_STATUS" | grep -Fq 'Trạng thái Termux:Boot: Đã cài.' || { echo 'Auto-start regression: installed Termux:Boot state is unclear.' >&2; exit 1; }

echo 'Auto-start Redis optional regression test: OK'
