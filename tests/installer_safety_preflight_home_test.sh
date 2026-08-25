#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

export HOME="$WORK/home" PREFIX="$WORK/prefix" TMS_STATE_DIR="$WORK/state" TMS_REPORT="$WORK/preflight-report.txt" TMS_SAFETY_LOG="$WORK/safety.log"
mkdir -p "$HOME" "$PREFIX/bin" "$PREFIX/var/tmp" "$HOME/.tms-os"

cat > "$PREFIX/bin/pkg" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
cat > "$PREFIX/bin/getprop" <<'EOF'
#!/usr/bin/env bash
case "${1:-}" in
  ro.build.version.sdk) printf '25\n' ;;
  ro.product.cpu.abi) printf 'arm64-v8a\n' ;;
esac
EOF
cat > "$PREFIX/bin/php" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
cat > "$PREFIX/bin/nginx" <<'EOF'
#!/usr/bin/env bash
[ "${1:-}" = '-t' ] && exit 0
exit 1
EOF
chmod +x "$PREFIX/bin/pkg" "$PREFIX/bin/getprop" "$PREFIX/bin/php" "$PREFIX/bin/nginx"

export PATH="$PREFIX/bin:$PATH" TMS_PREFLIGHT_REQUIRE_NGINX=1 TMS_COMPAT_ENGINE=php-http
# shellcheck disable=SC1090
source "$ROOT/scripts/lib/installer-safety.sh"
tms_preflight
test -d "$HOME/.tms-os/preflight-tmp"
test ! -e "$HOME/tms-os"
grep -Fq "home=$HOME" "$TMS_REPORT"

printf 'installer safety preflight HOME tests: PASS\n'
