#!/usr/bin/env bash
# V17.1.7 — Test hồi quy: hạn mức tự sửa (MAX_REPAIRS_PER_HOUR) phải tính
# THEO TỪNG DỊCH VỤ ở cả watchdog và Guardian. Bản cũ dùng một bộ đếm
# chung: một dịch vụ lỗi lặp nuốt hết hạn mức khiến PHP engine chết mà
# không ai được sửa (sự cố 08/11/2026, log watchdog toàn dòng "Đạt giới
# hạn tự sửa; bỏ qua phục hồi PHP engine"). Test trích xuất đúng các hàm
# đếm hạn mức từ 2 script thật và kiểm tra hành vi của chúng.
set -u
cd "$(dirname "$0")/.."
ROOT="$PWD"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "FAIL: $1" >&2; exit 1; }

# ---------- Watchdog: repair_count_get / repair_allowed / repair_count_bump ----------
awk '/^repair_count_get\(\)/{f=1} f{print} /^repair_count_bump\(\)/{b=1} b&&/^\}$/{exit}' \
  "$ROOT/scripts/tms-watchdog.sh" > "$TMP/watchdog-fns.sh"
grep -q "repair_count_get" "$TMP/watchdog-fns.sh" || fail "không trích xuất được hàm đếm của watchdog"
grep -q "repair_count_bump" "$TMP/watchdog-fns.sh" || fail "thiếu repair_count_bump trong trích xuất watchdog"

(
  MAX_REPAIRS_PER_HOUR=6
  REPAIR_STATE="$TMP/watchdog-repairs.json"
  . "$TMP/watchdog-fns.sh"

  repair_allowed php || exit 11
  for i in 1 2 3 4 5 6; do repair_count_bump guardian; done
  repair_allowed guardian && exit 12          # guardian đã dùng hết phần của mình
  repair_allowed php || exit 13               # php không bị guardian nuốt hạn mức
  [ "$(repair_count_get guardian)" = "6" ] || exit 14
  [ "$(repair_count_get php)" = "0" ] || exit 15
  repair_count_bump php
  [ "$(repair_count_get php)" = "1" ] || exit 16
  [ "$(repair_count_get guardian)" = "6" ] || exit 17   # bump php không đụng đếm guardian

  # State hỏng/định dạng JSON cũ -> đếm lại từ 0, không kẹt vĩnh viễn.
  printf '{"hour":"cu","count":99,"at":1}\n' > "$REPAIR_STATE"
  [ "$(repair_count_get php)" = "0" ] || exit 18
  repair_allowed php || exit 19
) || fail "watchdog: hạn mức theo dịch vụ sai (mã $?)"

# ---------- Guardian: repair_allowed ----------
awk '/^repair_allowed\(\)/{f=1} f{print} f&&/^\}$/{exit}' \
  "$ROOT/scripts/tms-guardian.sh" > "$TMP/guardian-fns.sh"
grep -q "repair_allowed" "$TMP/guardian-fns.sh" || fail "không trích xuất được repair_allowed của guardian"

(
  MAX_REPAIRS_PER_HOUR=6
  EVENTS="$TMP/events.jsonl"
  HOUR="$(date +%Y-%m-%dT%H)"
  : > "$EVENTS"
  for i in 1 2 3 4 5 6; do
    printf '{"time":"%s:0%d:00+07:00","level":"warn","service":"php","action":"repair","ok":1,"message":"x"}\n' "$HOUR" "$i" >> "$EVENTS"
  done
  for i in 1 2; do
    printf '{"time":"%s:1%d:00+07:00","level":"warn","service":"tunnel","action":"repair","ok":1,"message":"x"}\n' "$HOUR" "$i" >> "$EVENTS"
  done
  # Event của watchdog (service=watchdog) không được tính vào phần của php.
  printf '{"time":"%s:20:00+07:00","level":"warn","service":"watchdog","action":"repair","ok":1,"message":"x"}\n' "$HOUR" >> "$EVENTS"
  . "$TMP/guardian-fns.sh"

  repair_allowed php && exit 21               # php đủ 6 lần -> khóa
  repair_allowed tunnel || exit 22            # tunnel mới 2 lần -> vẫn mở
  repair_allowed cron || exit 23              # cron chưa sửa lần nào -> vẫn mở
) || fail "guardian: hạn mức theo dịch vụ sai (mã $?)"

echo "PASS: hạn mức tự sửa tính theo từng dịch vụ ở cả watchdog và Guardian."
