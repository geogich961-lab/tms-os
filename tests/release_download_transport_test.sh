#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
INSTALLER="$ROOT/install.sh"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

bash -n "$INSTALLER"
grep -Fq 'tms_download_release_asset()' "$INSTALLER"
grep -Fq 'curl -f -sS -L -4 --http1.1' "$INSTALLER"
grep -Fq 'curl -f -sS -L --connect-timeout 20' "$INSTALLER"
grep -Fq '"${destination}.part"' "$INSTALLER"
grep -Fq 'Manifest chữ ký' "$INSTALLER"

mkdir -p "$WORK/bin"
cat > "$WORK/bin/curl" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
output=""
ipv4=0
while [ "$#" -gt 0 ]; do
  case "$1" in
    -4) ipv4=1 ;;
    -o) output="$2"; shift ;;
  esac
  shift
done
if [ "$ipv4" -eq 1 ]; then
  exit 56
fi
printf 'fallback payload' > "$output"
EOF
chmod +x "$WORK/bin/curl"
sed -n '/^tms_download_release_asset() {/,/^# ---------- Bước 3:/p' "$INSTALLER" | sed '$d' > "$WORK/download-function.sh"
PATH="$WORK/bin:$PATH" bash -c '
  source "$1"
  tms_download_release_asset "https://invalid.example/payload" "$2" "Tệp kiểm thử"
  test "$(cat "$2")" = "fallback payload"
  test ! -e "$2.part"
' bash "$WORK/download-function.sh" "$WORK/payload.bin"

printf 'release download transport tests: PASS\n'
