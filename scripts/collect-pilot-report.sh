#!/data/data/com.termux/files/usr/bin/bash
# TMS OS — Pilot report collector for real Android/Termux devices.
# Chỉ đọc thông tin và chạy compatibility preflight trong thư mục tạm.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="${TMS_PILOT_REPORT_DIR:-${HOME:-$PWD}/tms-os-pilot-reports}"
PAYLOAD=""
RELEASE_JSON=""
SKIP_PREFLIGHT=0

usage() {
  cat <<'USAGE'
Cách dùng:
  bash scripts/collect-pilot-report.sh [tùy chọn]

Tùy chọn:
  --output DIR         Thư mục lưu báo cáo (mặc định: ~/tms-os-pilot-reports)
  --payload FILE       Payload ZIP để chạy verify-uci-payload.sh (tùy chọn)
  --release-json FILE  RELEASE.json đi cùng --payload (tùy chọn)
  --skip-preflight     Không chạy PHP/compatibility probe
  -h, --help           Hiển thị trợ giúp

Tập lệnh không cài package, không khởi động TMS OS và không thu thập
database, file website, token, mật khẩu hoặc cấu hình Cloudflare.
USAGE
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --output) [ "$#" -ge 2 ] || { echo 'Thiếu DIR sau --output' >&2; exit 2; }; OUTPUT_DIR="$2"; shift 2 ;;
    --payload) [ "$#" -ge 2 ] || { echo 'Thiếu FILE sau --payload' >&2; exit 2; }; PAYLOAD="$2"; shift 2 ;;
    --release-json) [ "$#" -ge 2 ] || { echo 'Thiếu FILE sau --release-json' >&2; exit 2; }; RELEASE_JSON="$2"; shift 2 ;;
    --skip-preflight) SKIP_PREFLIGHT=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Tùy chọn không hợp lệ: $1" >&2; usage >&2; exit 2 ;;
  esac
done

if [ -n "$RELEASE_JSON" ] && [ -z "$PAYLOAD" ]; then
  echo 'Chỉ dùng --release-json cùng --payload.' >&2
  exit 2
fi

for required in tar sed grep awk date; do
  command -v "$required" >/dev/null 2>&1 || { echo "Thiếu lệnh bắt buộc: $required" >&2; exit 20; }
done

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
report_dir="$OUTPUT_DIR/tms-os-pilot-$timestamp"
mkdir -p "$report_dir/raw" "$report_dir/sanitized"

cleanup() {
  rm -rf -- "$report_dir/raw" 2>/dev/null || true
}
trap cleanup EXIT

sanitize_file() {
  local source="$1" target="$2"
  [ -f "$source" ] || return 0
  sed -E \
    -e '/(token|password|passwd|secret|api[_-]?key|authorization|cookie)[[:space:]]*[=:]/Id' \
    -e 's#(https?://)[^/@[:space:]]+@#\1[REDACTED]@#g' \
    -e 's#(token|access_token|api_key|apikey)=([^&[:space:]]+)#\1=[REDACTED]#Ig' \
    "$source" > "$target"
}

run_capture() {
  local name="$1"
  shift
  {
    printf '$ '
    printf '%q ' "$@"
    printf '\n'
    "$@"
  } > "$report_dir/raw/$name" 2>&1 || true
  sanitize_file "$report_dir/raw/$name" "$report_dir/sanitized/$name"
}

{
  printf 'TMS OS Pilot Report\n'
  printf 'generated_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'collector_version=1\n'
  printf 'root=%s\n' "$ROOT"
  printf 'payload_requested=%s\n' "$([ -n "$PAYLOAD" ] && echo yes || echo no)"
  printf 'preflight_requested=%s\n' "$([ "$SKIP_PREFLIGHT" -eq 0 ] && echo yes || echo no)"
} > "$report_dir/summary.txt"

run_capture device-properties sh -c '
  printf "android_api=%s\\n" "$(getprop ro.build.version.sdk 2>/dev/null || echo unknown)"
  printf "android_release=%s\\n" "$(getprop ro.build.version.release 2>/dev/null || echo unknown)"
  printf "abi=%s\\n" "$(getprop ro.product.cpu.abi 2>/dev/null || echo unknown)"
  printf "abi_list=%s\\n" "$(getprop ro.product.cpu.abilist 2>/dev/null || echo unknown)"
  printf "manufacturer=%s\\n" "$(getprop ro.product.manufacturer 2>/dev/null || echo unknown)"
  printf "model=%s\\n" "$(getprop ro.product.model 2>/dev/null || echo unknown)"
  printf "termux_prefix=%s\\n" "${PREFIX:-unknown}"
  printf "home=%s\\n" "${HOME:-unknown}"
'
run_capture termux-info termux-info
run_capture package-status sh -c 'for cmd in pkg dpkg php php-fpm php-cgi nginx curl unzip sha256sum; do printf "%s=" "$cmd"; command -v "$cmd" 2>/dev/null || echo missing; done'
run_capture php-version php -v
run_capture disk-memory sh -c 'df -Pk "${HOME:-.}"; free 2>/dev/null || true'

preflight_rc='not-run'
if [ "$SKIP_PREFLIGHT" -eq 0 ]; then
  compat_state="$report_dir/raw/compatibility-state"
  mkdir -p "$compat_state" "$report_dir/raw/compatibility-tmp"
  set +e
  TMS_COMPAT_STATE_DIR="$compat_state" \
  TMS_COMPAT_REPORT="$compat_state/compatibility.env" \
  TMS_COMPAT_HUMAN_REPORT="$compat_state/compatibility-report.txt" \
  TMS_COMPAT_TMP="$report_dir/raw/compatibility-tmp" \
    bash "$ROOT/scripts/lib/installer-compatibility.sh" sqlite > "$report_dir/raw/compatibility-preflight.log" 2>&1
  preflight_rc=$?
  set -e
  sanitize_file "$report_dir/raw/compatibility-preflight.log" "$report_dir/sanitized/compatibility-preflight.log"
  for log in compatibility.env compatibility-report.txt php-cli.stderr php-http.stderr php-fpm.stderr php-cgi.stderr nginx.stderr; do
    sanitize_file "$compat_state/$log" "$report_dir/sanitized/$log"
  done
  printf 'preflight_exit_code=%s\n' "$preflight_rc" >> "$report_dir/summary.txt"
fi

if [ -n "$PAYLOAD" ]; then
  verify_report="$report_dir/raw/payload-integrity.log"
  set +e
  if [ -n "$RELEASE_JSON" ]; then
    bash "$ROOT/scripts/verify-uci-payload.sh" --payload "$PAYLOAD" --release-json "$RELEASE_JSON" --report "$verify_report" --no-matrix
  else
    bash "$ROOT/scripts/verify-uci-payload.sh" --payload "$PAYLOAD" --report "$verify_report" --no-matrix
  fi
  verify_rc=$?
  set -e
  sanitize_file "$verify_report" "$report_dir/sanitized/payload-integrity.log"
  printf 'payload_verifier_exit_code=%s\n' "$verify_rc" >> "$report_dir/summary.txt"
fi

# Không đưa log raw vào archive; chỉ giữ các file đã qua sanitize.
rm -rf -- "$report_dir/raw"
find "$report_dir/sanitized" -maxdepth 1 -type f -printf '%f\n' | sort > "$report_dir/collected-files.txt"
if command -v sha256sum >/dev/null 2>&1; then
  (cd "$report_dir" && find . -maxdepth 1 -type f -print0 | sort -z | xargs -0 sha256sum) > "$report_dir/SHA256SUMS"
elif command -v sha256 >/dev/null 2>&1; then
  (cd "$report_dir" && find . -maxdepth 1 -type f -print0 | sort -z | xargs -0 -n1 sha256 -q) > "$report_dir/SHA256SUMS"
fi

archive="$OUTPUT_DIR/tms-os-pilot-$timestamp.tar.gz"
tar -C "$OUTPUT_DIR" -czf "$archive" "$(basename "$report_dir")"
archive_sha='unavailable'
if command -v sha256sum >/dev/null 2>&1; then
  archive_sha="$(sha256sum "$archive" | awk '{print $1}')"
elif command -v sha256 >/dev/null 2>&1; then
  archive_sha="$(sha256 -q "$archive")"
fi

printf 'archive=%s\n' "$archive"
printf 'archive_sha256=%s\n' "$archive_sha"
printf 'preflight_exit_code=%s\n' "$preflight_rc"
printf 'Lưu ý: Chỉ gửi file .tar.gz; kiểm tra summary.txt trước khi chia sẻ.\n'
