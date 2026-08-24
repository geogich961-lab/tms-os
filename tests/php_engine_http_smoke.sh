#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'bash "$ROOT/scripts/tms-php-engine.sh" stop >/dev/null 2>&1 || true; rm -rf "$WORK"' EXIT
mkdir -p "$WORK/home/tms-os/public" "$WORK/prefix/var/tmp"
printf '%s\n' '<?php echo "TMS-HTTP-OK";' > "$WORK/home/tms-os/public/index.php"
printf '%s\n' 'static' > "$WORK/home/tms-os/public/health.txt"
HOME="$WORK/home" PREFIX="$WORK/prefix" TMS_WEB_ROOT="$WORK/home/tms-os/public" TMS_COMPAT_ENGINE=php-http \
  bash "$ROOT/scripts/tms-php-engine.sh" start
HEADER="X-TMS-Root: $WORK/home/tms-os/public"
curl -fsS -H "$HEADER" http://127.0.0.1:9000/ > "$WORK/response"
RESPONSE=$(cat "$WORK/response")
[ "$RESPONSE" = 'TMS-HTTP-OK' ]
curl -fsS -H "$HEADER" http://127.0.0.1:9000/health.txt > "$WORK/response"
RESPONSE=$(cat "$WORK/response")
[ "$RESPONSE" = 'static' ]
! curl -fsS http://127.0.0.1:9000/ >/dev/null 2>&1
HOME="$WORK/home" PREFIX="$WORK/prefix" TMS_WEB_ROOT="$WORK/home/tms-os/public" TMS_COMPAT_ENGINE=php-http \
  bash "$ROOT/scripts/tms-php-engine.sh" stop
printf 'PHP Engine HTTP smoke test: OK\n'
