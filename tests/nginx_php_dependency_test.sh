#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/bin" "$TMP/home/tms-os/scripts" "$TMP/prefix"
PHP_STATE="$TMP/php-up"; NGINX_STATE="$TMP/nginx-up"; ORDER="$TMP/order"

cat > "$TMP/bin/fuser" <<'FUSER'
#!/usr/bin/env bash
if [ "${1:-}" = '9000/tcp' ] && [ -f "${FAKE_PHP_STATE:?}" ]; then printf '9000/tcp: 4242\n'; exit 0; fi
exit 1
FUSER
cat > "$TMP/bin/pgrep" <<'PGREP'
#!/usr/bin/env bash
if [ "${1:-}" = '-f' ] && [[ "${2:-}" == *'nginx: master process'* ]] && [ -f "${FAKE_NGINX_STATE:?}" ]; then printf '5151\n'; exit 0; fi
exit 1
PGREP
cat > "$TMP/bin/nginx" <<'NGINX'
#!/usr/bin/env bash
case "${1:-}" in
  -t) printf 'NGINX_TEST\n' >> "${FAKE_ORDER:?}"; exit 0 ;;
  -s) [ "${2:-}" = reload ] && [ -f "${FAKE_PHP_STATE:?}" ] && printf 'NGINX_RELOAD\n' >> "$FAKE_ORDER"; exit 0 ;;
  *) [ -f "${FAKE_PHP_STATE:?}" ] || exit 1; printf 'NGINX_START\n' >> "${FAKE_ORDER:?}"; touch "${FAKE_NGINX_STATE:?}" ;;
esac
NGINX
cat > "$TMP/home/tms-os/scripts/tms-php-engine.sh" <<'ENGINE'
#!/usr/bin/env bash
if [ "${1:-}" = start ]; then
  [ "${FAKE_PHP_FAIL:-0}" = 1 ] && exit 1
  printf 'PHP_START\n' >> "${FAKE_ORDER:?}"; touch "${FAKE_PHP_STATE:?}"; exit 0
fi
exit 2
ENGINE
chmod +x "$TMP/bin/fuser" "$TMP/bin/pgrep" "$TMP/bin/nginx" "$TMP/home/tms-os/scripts/tms-php-engine.sh"

run_core(){
  HOME="$TMP/home" PREFIX="$TMP/prefix" PATH="$TMP/bin:$PATH" FAKE_PHP_STATE="$PHP_STATE" FAKE_NGINX_STATE="$NGINX_STATE" FAKE_ORDER="$ORDER" \
    bash "$ROOT/scripts/tms-service-core.sh" nginx "$1"
}

run_core start
[ -f "$PHP_STATE" ] && [ -f "$NGINX_STATE" ]
[ "$(sed -n '1p' "$ORDER")" = 'PHP_START' ] || { echo 'Nginx started before PHP runtime.' >&2; exit 1; }
grep -qx 'NGINX_START' "$ORDER"

rm -f "$PHP_STATE" "$NGINX_STATE"; : > "$ORDER"
if HOME="$TMP/home" PREFIX="$TMP/prefix" PATH="$TMP/bin:$PATH" FAKE_PHP_STATE="$PHP_STATE" FAKE_NGINX_STATE="$NGINX_STATE" FAKE_ORDER="$ORDER" FAKE_PHP_FAIL=1 \
  bash "$ROOT/scripts/tms-service-core.sh" nginx start; then
  echo 'Nginx must not start if PHP runtime fails.' >&2; exit 1
fi
[ ! -f "$NGINX_STATE" ] || { echo 'Nginx started despite PHP failure.' >&2; exit 1; }
printf 'Nginx PHP dependency test: OK\n'
