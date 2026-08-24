#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PAYLOAD_ZIP="${PAYLOAD_ZIP:-$ROOT/.build/v16.1.21-uci/downloaded.zip}"
WORK="$(mktemp -d)"
trap 'rm -rf -- "$WORK"' EXIT

[ -f "$PAYLOAD_ZIP" ] || { echo "Missing live payload: $PAYLOAD_ZIP" >&2; exit 1; }

printf 'payload=%s\n' "$PAYLOAD_ZIP"
printf 'payload_sha256=%s\n' "$(sha256sum "$PAYLOAD_ZIP" | awk '{print $1}')"

make_stub() {
  local file="$1" body="$2"
  printf '%s\n' '#!/usr/bin/env bash' "$body" > "$file"
  chmod +x "$file"
}

run_case() {
  local api="$1" abi="$2" mode="$3"
  local bin="$WORK/bin-$api-$abi-$mode" home="$WORK/home-$api-$abi-$mode"
  local prefix="$WORK/prefix-$api-$abi-$mode" state="$WORK/state-$api-$abi-$mode"
  local tmp="$WORK/tmp-$api-$abi-$mode" out="$WORK/out-$api-$abi-$mode"
  local rc engine expected
  mkdir -p "$bin" "$home" "$prefix/etc/apt/sources.list.d" "$state" "$tmp"

  make_stub "$bin/getprop" "case \"\${1:-}\" in
  ro.build.version.sdk) printf '%s\\n' '$api' ;;
  ro.product.cpu.abi) printf '%s\\n' '$abi' ;;
  ro.product.cpu.abilist) printf '%s\\n' '$abi' ;;
esac"
  make_stub "$bin/termux-info" "printf '%s\\n' 'Termux version: 0.118'"
  make_stub "$bin/pkg" "exit 0"
  make_stub "$bin/dpkg" "exit 0"
  make_stub "$bin/nginx" "exit 0"
  make_stub "$bin/php-gd" "exit 0"
  make_stub "$bin/zip" "exit 0"
  make_stub "$bin/unzip" "exit 0"
  make_stub "$bin/openssh" "exit 0"
  make_stub "$bin/procps" "exit 0"
  make_stub "$bin/coreutils" "exit 0"
  make_stub "$bin/findutils" "exit 0"
  make_stub "$bin/gawk" "exit 0"
  make_stub "$bin/which" "exit 0"
  make_stub "$bin/openssl" "exit 0"
  make_stub "$bin/cronie" "exit 0"
  make_stub "$bin/curl" "printf '%s\\n' 'TMS-COMPAT-OK'"

  case "$mode" in
    fastcgi)
      make_stub "$bin/php" 'for arg in "$@"; do [ "$arg" = "-S" ] && exit 1; done; exit 0'
      make_stub "$bin/php-fpm" 'while :; do sleep 1; done'
      expected=0
      ;;
    php-http)
      make_stub "$bin/php" 'for arg in "$@"; do [ "$arg" = "-S" ] && { while :; do sleep 1; done; }; done; exit 0'
      make_stub "$bin/php-fpm" 'exit 1'
      make_stub "$bin/php-cgi" 'exit 1'
      expected=0
      ;;
    no-engine)
      make_stub "$bin/php" 'exit 1'
      make_stub "$bin/php-fpm" 'exit 1'
      make_stub "$bin/php-cgi" 'exit 1'
      expected=30
      ;;
    *)
      echo "Unknown mode: $mode" >&2
      return 1
      ;;
  esac

  if [ "$api" -lt 24 ]; then
    expected=10
  fi

  set +e
  HOME="$home" PREFIX="$prefix" PATH="$bin:/usr/bin:/bin" \
    TMS_COMPAT_STATE_DIR="$state" \
    TMS_COMPAT_REPORT="$state/compatibility.env" \
    TMS_COMPAT_HUMAN_REPORT="$state/compatibility-report.txt" \
    TMS_COMPAT_TMP="$tmp" \
    bash "$WORK/payload/scripts/lib/installer-compatibility.sh" sqlite > "$out" 2>&1
  rc=$?
  set -e

  engine="$(sed -n 's/^ENGINE=//p' "$state/compatibility.env" 2>/dev/null | tail -n 1 | tr -d "'\"")"
  if [ "$mode" = fastcgi ]; then
    [ "$engine" = fastcgi ] || { echo "Expected fastcgi for API $api, got $engine" >&2; return 1; }
  elif [ "$mode" = php-http ]; then
    [ "$engine" = php-http ] || { echo "Expected php-http for API $api, got $engine" >&2; return 1; }
  else
    [ "$engine" = none ] || { echo "Expected none for API $api, got $engine" >&2; return 1; }
  fi
  [ "$rc" -eq "$expected" ] || { echo "Expected rc=$expected for API $api/$mode, got $rc" >&2; cat "$out" >&2; return 1; }

  printf '%s\t%s\t%s\t%s\trc=%s\n' "$api" "$abi" "$mode" "$engine" "$rc"
}

cp -a "$WORK"/does-not-exist "$WORK"/unused 2>/dev/null || true
mkdir -p "$WORK/payload"
unzip -q "$PAYLOAD_ZIP" -d "$WORK/payload"
bash -n "$WORK/payload/scripts/lib/installer-compatibility.sh"
printf 'payload_syntax=PASS\n'
printf 'api\tabi\tprofile\tselected_engine\tdetail\n'
run_case 23 arm64-v8a fastcgi
run_case 24 arm64-v8a fastcgi
run_case 25 armeabi-v7a fastcgi
run_case 26 arm64-v8a fastcgi
run_case 28 x86_64 php-http
run_case 30 arm64-v8a no-engine
run_case 35 arm64-v8a fastcgi
printf 'minimum_api_gate=24\n'
printf 'no_engine_policy=rc30\n'
printf 'live_payload_uci_matrix=PASS\n'
