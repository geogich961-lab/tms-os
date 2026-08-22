#!/data/data/com.termux/files/usr/bin/bash
# TMS OS Cron Engine — chạy crond trong phạm vi người dùng Termux, không cần root.
set -u

HOME="${HOME:-/data/data/com.termux/files/home}"
STATE_DIR="$HOME/.tms-os"
LOG_DIR="$HOME/logs/services"
LOG_FILE="$LOG_DIR/cron.log"

mkdir -p "$STATE_DIR" "$LOG_DIR"

is_running() {
  pgrep -u "$(id -u)" -x crond >/dev/null 2>&1
}

start_engine() {
  command -v crond >/dev/null 2>&1 || {
    echo '[LỖI] Chưa cài cronie. Chạy: pkg install cronie'
    return 1
  }
  if is_running; then
    echo '[OK] Cron engine đang chạy.'
    return 0
  fi
  crond >>"$LOG_FILE" 2>&1 || true
  for _ in 1 2 3 4 5; do
    is_running && { echo '[OK] Đã khởi động Cron engine.'; return 0; }
    sleep 1
  done
  echo "[LỖI] Cron engine không khởi động. Xem log: $LOG_FILE"
  return 1
}

stop_engine() {
  pkill -u "$(id -u)" -x crond 2>/dev/null || true
  echo '[OK] Đã dừng Cron engine.'
}

case "${1:-start}" in
  start) start_engine ;;
  stop) stop_engine ;;
  restart) stop_engine; sleep 1; start_engine ;;
  status) is_running && echo 'running' || { echo 'stopped'; exit 1; } ;;
  *) echo 'Cách dùng: tms-cron-engine.sh [start|stop|restart|status]'; exit 2 ;;
esac
