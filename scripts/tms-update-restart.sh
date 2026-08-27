#!/usr/bin/env sh
# Worker restart tách riêng cho Update Center. start-tms.sh là điểm quản lý
# duy nhất có trách nhiệm dọn PHP-CGI/Nginx rồi khởi động lại stack.
set -u

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
STATE_FILE=${TMS_UPDATE_STATE_FILE:-}
QUEUE_FILE=${TMS_UPDATE_QUEUE_FILE:-}
EXPECTED_VERSION=${TMS_UPDATE_EXPECTED_VERSION:-}
HEALTH_ATTEMPTS=${TMS_RESTART_HEALTH_ATTEMPTS:-12}

case "$HEALTH_ATTEMPTS" in
  ''|*[!0-9]*) HEALTH_ATTEMPTS=12 ;;
esac
if [ "$HEALTH_ATTEMPTS" -lt 1 ]; then
  HEALTH_ATTEMPTS=1
fi

write_state() {
  phase=$1
  message=$2
  [ -n "$STATE_FILE" ] || return 0
  TMS_RESTART_STATE_FILE="$STATE_FILE" \
    TMS_RESTART_PHASE="$phase" \
    TMS_RESTART_MESSAGE="$message" \
    TMS_RESTART_VERSION="$EXPECTED_VERSION" \
    php -n -r '
      $file = getenv("TMS_RESTART_STATE_FILE") ?: "";
      if ($file === "") { exit(0); }
      $state = json_decode((string)@file_get_contents($file), true);
      if (!is_array($state)) { $state = []; }
      $phase = getenv("TMS_RESTART_PHASE") ?: "restart_failed";
      $state["applying"] = false;
      $state["ok"] = $phase === "completed";
      $state["phase"] = $phase;
      $state["message"] = getenv("TMS_RESTART_MESSAGE") ?: "Khởi động lại TMS OS không thành công.";
      $state["finished_at"] = date("c");
      $version = getenv("TMS_RESTART_VERSION") ?: "";
      if ($version !== "") { $state["current"] = $version; }
      $dir = dirname($file);
      $tmp = tempnam($dir, ".restart-state-");
      if ($tmp !== false) {
        file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @chmod($tmp, 0600);
        @rename($tmp, $file);
      }
    ' >/dev/null 2>&1 || true
}

clear_queue() {
  [ -n "$QUEUE_FILE" ] && rm -f "$QUEUE_FILE" || true
}

sleep 3
write_state 'restarting' 'Đang khởi động lại PHP của TMS OS và xác nhận panel local.'
# Payload Update Center chỉ thay app/config/public/routes/scripts. Không được gọi
# start-tms.sh ở đây vì full-stack restart sẽ dừng Nginx và Cloudflare Tunnel,
# khiến browser từ hostname ngoài nhận 502 dù source đã được áp dụng đúng.
if ! bash "$SCRIPT_DIR/tms-php-engine.sh" restart; then
  write_state 'restart_failed' 'Cập nhật đã áp dụng nhưng PHP của TMS OS không khởi động lại được. Hãy chạy start-tms.sh một lần từ Termux.'
  clear_queue
  exit 1
fi

attempt=1
while [ "$attempt" -le "$HEALTH_ATTEMPTS" ]; do
  if curl -fsS --max-time 3 http://127.0.0.1:8888/login >/dev/null 2>&1; then
    write_state 'completed' 'Đã áp dụng cập nhật và xác nhận panel local đang hoạt động.'
    clear_queue
    exit 0
  fi
  attempt=$((attempt + 1))
  sleep 2
done

write_state 'restart_failed' 'Cập nhật đã áp dụng nhưng panel local chưa phản hồi sau khi khởi động lại. Hãy chạy start-tms.sh một lần từ Termux.'
clear_queue
exit 1
