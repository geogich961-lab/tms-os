#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/bin" "$TMP/home/tms-os/scripts" "$TMP/prefix"
STATE="$TMP/state"

cat > "$TMP/bin/fuser" <<'FUSER'
#!/usr/bin/env bash
if [ "${1:-}" = '9000/tcp' ] && [ -f "${FAKE_SERVICE_STATE:?}" ]; then
  printf '9000/tcp: %s\n' "${FAKE_PHP_PID:-4242}"
  exit 0
fi
exit 1
FUSER
cat > "$TMP/bin/pgrep" <<'PGREP'
#!/usr/bin/env bash
# Hồi quy phải chỉ quan sát state mô phỏng, không nhận nhầm PHP của sandbox.
exit 1
PGREP
chmod +x "$TMP/bin/fuser" "$TMP/bin/pgrep"

cat > "$TMP/home/tms-os/scripts/tms-php-engine.sh" <<'ENGINE'
#!/usr/bin/env bash
set -euo pipefail
case "${1:-status}" in
  start) touch "${FAKE_SERVICE_STATE:?}" ;;
  stop) rm -f "${FAKE_SERVICE_STATE:?}" ;;
  restart) rm -f "${FAKE_SERVICE_STATE:?}"; touch "${FAKE_SERVICE_STATE:?}" ;;
  status) printf 'fastcgi\n' ;;
  *) exit 2 ;;
esac
ENGINE
chmod +x "$TMP/home/tms-os/scripts/tms-php-engine.sh"

run_core() {
  HOME="$TMP/home" PREFIX="$TMP/prefix" PATH="$TMP/bin:$PATH" \
    FAKE_SERVICE_STATE="$STATE" FAKE_PHP_PID=4242 \
    bash "$ROOT/scripts/tms-service-core.sh" php "$1"
}

if run_core status; then
  echo 'Service core regression: PHP must be stopped before port 9000 is available.' >&2
  exit 1
fi

run_core start
[ -f "$STATE" ] || { echo 'Service core regression: Start did not launch PHP engine.' >&2; exit 1; }
PID="$(run_core pid)"
[ "$PID" = '4242' ] || { echo "Service core regression: expected PHP PID 4242, got ${PID:-empty}." >&2; exit 1; }
run_core status

run_core restart
run_core status
echo 'Service core PHP control test: OK'
