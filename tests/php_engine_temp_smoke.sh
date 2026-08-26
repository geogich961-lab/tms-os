#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/bin" "$WORK/home"
cat > "$WORK/bin/php-cgi" <<'STUB'
#!/usr/bin/env bash
case " $* " in
  *" -b 127.0.0.1:9000 "*) sleep 20 ;;
  *) exit 0 ;;
esac
STUB
chmod +x "$WORK/bin/php-cgi"
# LD_PRELOAD mô phỏng biến môi trường có thể còn sót trên một số phiên Android.
# Runtime phải chủ động bỏ biến này như compatibility probe.
HOME="$WORK/home" PREFIX="$WORK/prefix" PATH="$WORK/bin:/usr/bin:/bin" LD_PRELOAD='/nonexistent/tms-test.so' bash "$ROOT/scripts/tms-php-engine.sh" start 2>/dev/null
[ -d "$WORK/prefix/var/tmp" ]
[ -d "$WORK/home/.tms-os/tmp" ]
[ -f "$WORK/home/.tms-os/php-cgi.pid" ]
grep -q 'env -u LD_PRELOAD' "$ROOT/scripts/tms-php-engine.sh"
bash "$ROOT/scripts/tms-php-engine.sh" stop || true
printf 'PHP Engine temp smoke test: OK\n'
