#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

make_env() {
  local bin="$1" cgi_mode="$2"
  mkdir -p "$WORK/$bin" "$WORK/home" "$WORK/prefix/var/tmp"
  cat > "$WORK/$bin/pkg" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
  cat > "$WORK/$bin/getprop" <<'EOF'
#!/usr/bin/env bash
case "${1:-}" in
  ro.build.version.sdk) echo 35 ;;
  ro.product.cpu.abi) echo arm64-v8a ;;
esac
EOF
  if [ "$cgi_mode" = working ]; then
    cat > "$WORK/$bin/php-cgi" <<'EOF'
#!/usr/bin/env bash
case " $* " in *" -b 127.0.0.1:19174 "*|*" -b 127.0.0.1:19173 "*) sleep 20 ;; *) exit 0 ;; esac
EOF
  else
    cat > "$WORK/$bin/php-cgi" <<'EOF'
#!/usr/bin/env bash
exit 1
EOF
  fi
  chmod +x "$WORK/$bin"/*
  export HOME="$WORK/home" PREFIX="$WORK/prefix" PATH="$WORK/$bin:/usr/bin:/bin"
  export TMS_COMPAT_STATE_DIR="$WORK/state-$cgi_mode" TMS_COMPAT_TMP="$WORK/tmp-$cgi_mode"
  mkdir -p "$TMS_COMPAT_STATE_DIR" "$TMS_COMPAT_TMP"
}

make_env fastcgi working
# shellcheck disable=SC1090
. "$ROOT/scripts/lib/installer-compatibility.sh"
compat_full_preflight sqlite >/dev/null
[ "$(sed -n 's/^ENGINE=//p' "$TMS_COMPAT_REPORT" | tail -n1)" = fastcgi ]

make_env http failing
# shellcheck disable=SC1090
. "$ROOT/scripts/lib/installer-compatibility.sh"
compat_full_preflight sqlite >/dev/null
[ "$(sed -n 's/^ENGINE=//p' "$TMS_COMPAT_REPORT" | tail -n1)" = php-http ]
make_env none failing
cat > "$WORK/none/php" <<'EOF'
#!/usr/bin/env bash
exit 1
EOF
chmod +x "$WORK/none/php"
# shellcheck disable=SC1090
. "$ROOT/scripts/lib/installer-compatibility.sh"
if compat_full_preflight sqlite >/dev/null; then
  echo 'expected compatibility block for no-engine profile' >&2
  exit 1
else
  COMPAT_RC=$?
fi
[ "$COMPAT_RC" -eq 30 ]
printf 'installer compatibility tests: PASS\n'
