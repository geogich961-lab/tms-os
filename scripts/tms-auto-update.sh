#!/data/data/com.termux/files/usr/bin/bash
# tms-auto-update.sh — Tự động kiểm tra và cập nhật TMS OS (cần V14.1.3+)
#
# Cách hoạt động:
#   1. Đọc token API từ ~/.tms-os/update-token
#   2. Gọi GET /api/updates/check để xem có bản mới hơn không
#   3. Nếu có: gọi POST /api/updates/run để cập nhật 1 chạm (backup + swap + rollback nếu lỗi)
#   4. Ghi log vào ~/.tms-os/logs/auto-update.log (xoay log, giữ 30 ngày)
#
# Thiết lập chạy hàng ngày (Termux:Boot hoặc cron):
#   chmod +x ~/tms-os/scripts/tms-auto-update.sh
#   mkdir -p ~/.termux/boot && ln -sf ~/tms-os/scripts/tms-auto-update.sh ~/.termux/boot/tms-auto-update.sh
# Hoặc thêm vào crontab (crond qua Termux): 0 3 * * * ~/tms-os/scripts/tms-auto-update.sh
#
# Lưu ý: cần panel đang chạy (nginx + php-cgi) trước khi script chạy.
# Script tự chờ panel sẵn sàng tối đa 120 giây (dành cho khởi động máy).

set -uo pipefail

TMS_HOME="${HOME:-/data/data/com.termux/files/home}"
TOKEN_FILE="$TMS_HOME/.tms-os/update-token"
LOG_DIR="$TMS_HOME/.tms-os/logs"
LOG_FILE="$LOG_DIR/auto-update.log"
PANEL_PORT=8888
PANEL="http://127.0.0.1:$PANEL_PORT"
WAIT_MAX=120   # chờ panel sẵn sàng tối đa (giây)
WAIT_STEP=5

log() {
  mkdir -p "$LOG_DIR"
  printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$LOG_FILE"
}

rotate_log() {
  # Giữ tối đa 30 file log cũ, mỗi file >1MB sẽ xoay
  if [ -f "$LOG_FILE" ]; then
    local size
    size=$(wc -c < "$LOG_FILE" 2>/dev/null || echo 0)
    if [ "$size" -gt 1048576 ]; then
      mv -f "$LOG_FILE" "$LOG_FILE.$(date +%s).old"
    fi
  fi
  # Xóa log cũ hơn 30 ngày
  find "$LOG_DIR" -name 'auto-update.log.*.old' -mtime +30 -delete 2>/dev/null || true
}

wait_panel() {
  local waited=0
  while [ "$waited" -lt "$WAIT_MAX" ]; do
    if curl -sf -o /dev/null "$PANEL/login" >/dev/null 2>&1; then
      return 0
    fi
    sleep "$WAIT_STEP"
    waited=$((waited + WAIT_STEP))
  done
  return 1
}

main() {
  rotate_log
  log "=== Bắt đầu kiểm tra cập nhật TMS OS ==="

  # 1. Kiểm tra token API
  if [ ! -f "$TOKEN_FILE" ]; then
    log "ERROR: Không tìm thấy token tại $TOKEN_FILE. Bản hiện tại có thể cũ hơn V14.1.3 — nâng cấp thủ công 1 lần."
    exit 1
  fi
  TOKEN=$(cat "$TOKEN_FILE" | tr -d '[:space:]')
  if [ ${#TOKEN} -ne 64 ]; then
    log "ERROR: Token không hợp lệ (độ dài ${#TOKEN}, cần 64)."
    exit 1
  fi

  # 2. Chờ panel sẵn sàng (đặc biệt khi chạy lúc khởi động máy)
  if ! wait_panel; then
    log "ERROR: Panel không phản hồi sau ${WAIT_MAX}s tại $PANEL. Không cập nhật."
    exit 1
  fi

  # 3. Gọi cập nhật 1 chạm — backend tự kiểm tra phiên bản, bỏ qua nếu đã mới nhất
  local result
  result=$(curl -s --max-time 900 -X POST "$PANEL/api/updates/run" -d "token=$TOKEN")
  if [ -z "$result" ]; then
    log "WARN: API không phản hồi. Bỏ qua lần này."
    exit 0
  fi
  local ok skipped
  ok=$(printf '%s' "$result" | python3 -c "import json,sys; d=json.load(sys.stdin); print('true' if d.get('ok') else 'false')" 2>/dev/null || echo false)
  skipped=$(printf '%s' "$result" | python3 -c "import json,sys; d=json.load(sys.stdin); print('true' if d.get('skipped') else 'false')" 2>/dev/null || echo false)

  if [ "$ok" = "true" ] && [ "$skipped" = "true" ]; then
    log "OK: Đã là phiên bản mới nhất. Không cần cập nhật."
    exit 0
  fi
  if [ "$ok" = "true" ]; then
    log "SUCCESS: Đã cập nhật lên phiên bản mới nhất."
    exit 0
  fi
  local err
  err=$(printf '%s' "$result" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('error','lỗi không xác định'))" 2>/dev/null || echo "lỗi không xác định")
  if [ "$err" = "Token không hợp lệ." ]; then
    log "ERROR: Token không hợp lệ — $err"
    exit 2
  fi
  log "FAIL: Cập nhật thất bại: $err — bản hiện tại vẫn được giữ nguyên (backup an toàn)."
  exit 1
}

main "$@"
