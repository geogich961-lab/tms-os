#!/usr/bin/env bash
# TMS OS — Universal Compatibility Installer payload verifier
# Chỉ đọc/giải nén vào thư mục tạm; không cài package và không sửa dữ liệu người dùng.
set -Eeuo pipefail

ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PAYLOAD="${PAYLOAD_ZIP:-$ROOT/.build/v16.1.21-uci/downloaded.zip}"
RELEASE_JSON="${RELEASE_JSON:-$ROOT/.build/v16.1.21-uci/out/RELEASE.json}"
REPORT=""
RUN_MATRIX=1
DEVICE_MODE=0

usage() {
  cat <<'USAGE'
Cách dùng:
  bash scripts/verify-uci-payload.sh [tùy chọn]

Tùy chọn:
  --payload FILE       Payload ZIP cần kiểm tra
  --release-json FILE  Metadata có checksum_sha256
  --report FILE        Lưu báo cáo kết quả vào FILE
  --no-matrix          Chỉ kiểm tra integrity tĩnh, không chạy matrix mô phỏng
  --device             Chạy thêm preflight trên thiết bị Termux hiện tại
  -h, --help           Hiển thị trợ giúp

Mặc định kiểm tra payload v16.1.21 local và chạy matrix mô phỏng API/ABI.
Tập lệnh không tự cài package, không khởi động dịch vụ và không thay đổi dữ liệu TMS OS.
USAGE
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --payload) [ "$#" -ge 2 ] || { echo "Thiếu FILE sau --payload" >&2; exit 2; }; PAYLOAD="$2"; shift 2 ;;
    --release-json) [ "$#" -ge 2 ] || { echo "Thiếu FILE sau --release-json" >&2; exit 2; }; RELEASE_JSON="$2"; shift 2 ;;
    --report) [ "$#" -ge 2 ] || { echo "Thiếu FILE sau --report" >&2; exit 2; }; REPORT="$2"; shift 2 ;;
    --no-matrix) RUN_MATRIX=0; shift ;;
    --device) DEVICE_MODE=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Tùy chọn không hợp lệ: $1" >&2; usage >&2; exit 2 ;;
  esac
done

if [ -z "$REPORT" ]; then
  REPORT="$(dirname -- "$PAYLOAD")/uci-integrity-$(date -u +%Y%m%dT%H%M%SZ).log"
fi
mkdir -p "$(dirname -- "$REPORT")"

TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT
EXTRACT="$TMP/payload"
mkdir -p "$EXTRACT"

say() {
  printf '%s\n' "$*" | tee -a "$REPORT"
}
fail() {
  say "[FAIL] $*"
  exit 1
}
pass() {
  say "[PASS] $*"
}

: > "$REPORT"
say "=== TMS OS UCI PAYLOAD INTEGRITY ==="
say "Thời điểm UTC: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
say "Payload: $PAYLOAD"

[ -f "$PAYLOAD" ] || fail "Không tìm thấy payload ZIP"
command -v unzip >/dev/null 2>&1 || fail "Thiếu unzip"
command -v awk >/dev/null 2>&1 || fail "Thiếu awk"
command -v sed >/dev/null 2>&1 || fail "Thiếu sed"
command -v grep >/dev/null 2>&1 || fail "Thiếu grep"

if command -v sha256sum >/dev/null 2>&1; then
  ACTUAL_SHA="$(sha256sum "$PAYLOAD" | awk '{print $1}')"
elif command -v sha256 >/dev/null 2>&1; then
  ACTUAL_SHA="$(sha256 -q "$PAYLOAD")"
else
  fail "Không có sha256sum hoặc sha256"
fi
say "SHA-256 thực tế: $ACTUAL_SHA"

if [ -f "$RELEASE_JSON" ]; then
  EXPECTED_SHA="$(sed -n 's/.*"checksum_sha256"[[:space:]]*:[[:space:]]*"\([0-9A-Fa-f]\{64\}\)".*/\1/p' "$RELEASE_JSON" | sed -n '1p')"
  [ -n "$EXPECTED_SHA" ] || fail "Không đọc được checksum_sha256 từ RELEASE.json"
  if [ "$ACTUAL_SHA" = "$EXPECTED_SHA" ]; then
    pass "Checksum khớp RELEASE.json: $EXPECTED_SHA"
  else
    fail "Checksum không khớp: expected=$EXPECTED_SHA actual=$ACTUAL_SHA"
  fi
else
  say "[WARN] Không có RELEASE.json; bỏ qua đối chiếu checksum metadata"
fi

unzip -tq "$PAYLOAD" >/dev/null || fail "ZIP integrity không đạt"
pass "ZIP integrity đạt"
unzip -q "$PAYLOAD" -d "$EXTRACT" || fail "Không thể giải nén payload"

required_files='install.sh
scripts/install.sh
scripts/lib/installer-compatibility.sh
scripts/lib/installer-safety.sh
scripts/installer-rollback.sh
scripts/tms-php-engine.sh
HUONG_DAN_CAI_DAT.md'
while IFS= read -r rel; do
  [ -n "$rel" ] || continue
  [ -f "$EXTRACT/$rel" ] || fail "Thiếu file bắt buộc: $rel"
done <<EOF
$required_files
EOF
pass "Đủ các file UCI bắt buộc"

for shell_file in "$EXTRACT/install.sh" "$EXTRACT/scripts/install.sh" "$EXTRACT/scripts/lib/installer-compatibility.sh" "$EXTRACT/scripts/lib/installer-safety.sh" "$EXTRACT/scripts/installer-rollback.sh" "$EXTRACT/scripts/tms-php-engine.sh"; do
  bash -n "$shell_file" || fail "Bash syntax lỗi: ${shell_file#$EXTRACT/}"
done
pass "Bash syntax của các installer/runtime script đạt"

if unzip -Z1 "$PAYLOAD" | grep -E '(^|/)(\.env|\.env\.|.*\.pem$|.*\.key$)' >/dev/null 2>&1; then
  fail "Payload chứa file có khả năng là secret"
fi
pass "Không phát hiện .env, private key hoặc certificate private trong ZIP"

if grep -R -n -E '(/home/ubuntu|/tmp/tmp\.|BUILT_IN_FORGE_API_KEY|JWT_SECRET)' "$EXTRACT" >/dev/null 2>&1; then
  fail "Payload chứa dấu vết secret/path sandbox không được phép"
fi
pass "Không phát hiện secret/path sandbox trong payload"

if [ "$RUN_MATRIX" -eq 1 ]; then
  MATRIX="$ROOT/tests/v16_1_21_uci_matrix_live_test.sh"
  [ -x "$MATRIX" ] || [ -f "$MATRIX" ] || fail "Không tìm thấy matrix test UCI"
  MATRIX_LOG="$TMP/matrix.log"
  if PAYLOAD_ZIP="$PAYLOAD" bash "$MATRIX" > "$MATRIX_LOG" 2>&1; then
    cat "$MATRIX_LOG" | tee -a "$REPORT"
    pass "Matrix Android API/ABI và engine policy đạt"
  else
    cat "$MATRIX_LOG" | tee -a "$REPORT"
    fail "Matrix Android/ABI thất bại"
  fi
fi

if [ "$DEVICE_MODE" -eq 1 ]; then
  DEVICE_LOG="$TMP/device.log"
  set +e
  HOME="${HOME:-}" PREFIX="${PREFIX:-}" \
    TMS_COMPAT_STATE_DIR="$TMP/device-state" \
    TMS_COMPAT_REPORT="$TMP/device-state/compatibility.env" \
    TMS_COMPAT_HUMAN_REPORT="$TMP/device-state/compatibility-report.txt" \
    TMS_COMPAT_TMP="$TMP/device-tmp" \
    bash "$EXTRACT/scripts/lib/installer-compatibility.sh" sqlite > "$DEVICE_LOG" 2>&1
  DEVICE_RC=$?
  set -e
  cat "$DEVICE_LOG" | tee -a "$REPORT"
  say "Device preflight exit code: $DEVICE_RC"
  if [ "$DEVICE_RC" -eq 0 ]; then
    pass "Thiết bị hiện tại có PHP engine hoạt động"
  elif [ "$DEVICE_RC" -eq 10 ]; then
    say "[BLOCKED] Android API dưới mức tối thiểu"
  elif [ "$DEVICE_RC" -eq 30 ]; then
    say "[BLOCKED] Không có PHP server engine hoạt động; cần xem diagnostic report"
  else
    say "[BLOCKED] Preflight thiết bị thất bại với mã $DEVICE_RC"
  fi
fi

say "RESULT=PASS"
say "Report: $REPORT"
