#!/usr/bin/env sh
# Worker xác nhận sau Update Center. V17.0.22 ưu tiên continuity:
# không restart PHP/Nginx nếu panel local đã chạy source mới bình thường.
set -u

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
STATE_FILE=${TMS_UPDATE_STATE_FILE:-}
QUEUE_FILE=${TMS_UPDATE_QUEUE_FILE:-}
EXPECTED_VERSION=${TMS_UPDATE_EXPECTED_VERSION:-}
HEALTH_ATTEMPTS=${TMS_RESTART_HEALTH_ATTEMPTS:-12}
PANEL_URL=${TMS_UPDATE_PANEL_URL:-http://127.0.0.1:8888/login}

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
      $state["message"] = getenv("TMS_RESTART_MESSAGE") ?: "Không thể xác nhận trạng thái sau cập nhật.";
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

panel_ok() {
  curl -fsS --max-time 3 "$PANEL_URL" >/dev/null 2>&1
}

ensure_tunnel() {
  helper="$SCRIPT_DIR/tms-cloudflare-tunnel.sh"
  [ -x "$helper" ] || return 0
  sh "$helper" start >/dev/null 2>&1 || true
}

sleep 2
write_state 'restarting' 'Đang xác nhận source mới mà không làm gián đoạn panel hoặc Cloudflare Tunnel.'

# Giữ bản sửa Nginx của V17.0.21 nhưng chỉ reload khi cấu hình hợp lệ.
if [ -f "$SCRIPT_DIR/tms-nginx-compat.php" ]; then
  if ! php "$SCRIPT_DIR/tms-nginx-compat.php" >/dev/null 2>&1; then
    write_state 'restart_failed' 'Source mới đã áp dụng nhưng không thể sửa cấu hình Nginx tương thích.'
    clear_queue
    exit 1
  fi
fi
if command -v nginx >/dev/null 2>&1; then
  if ! nginx -t >/dev/null 2>&1; then
    write_state 'restart_failed' 'Source mới đã áp dụng nhưng nginx.conf chưa hợp lệ.'
    clear_queue
    exit 1
  fi
  nginx -s reload >/dev/null 2>&1 || true
fi

# Không restart PHP nếu panel đã phản hồi. PHP của TMS OS đọc source mới ở request kế tiếp;
# tránh khoảng trống 502/1033 đối với người đang cập nhật từ xa qua Cloudflare.
ensure_tunnel
if panel_ok; then
  write_state 'completed' 'Đã cập nhật thành công và panel vẫn online; không cần restart dịch vụ.'
  clear_queue
  exit 0
fi

# Chỉ khi panel local thực sự không phản hồi mới restart riêng PHP engine.
write_state 'restarting' 'Panel local chưa phản hồi; đang khôi phục riêng PHP engine và giữ Nginx/Tunnel hoạt động.'
if ! sh "$SCRIPT_DIR/tms-php-engine.sh" restart >/dev/null 2>&1; then
  ensure_tunnel
  write_state 'restart_failed' 'Source mới đã áp dụng nhưng PHP engine không tự khôi phục được. Nginx và Cloudflare Tunnel không bị dừng.'
  clear_queue
  exit 1
fi
ensure_tunnel

attempt=1
while [ "$attempt" -le "$HEALTH_ATTEMPTS" ]; do
  if panel_ok; then
    ensure_tunnel
    write_state 'completed' 'Đã áp dụng cập nhật và xác nhận panel local hoạt động; Cloudflare Tunnel được giữ online.'
    clear_queue
    exit 0
  fi
  attempt=$((attempt + 1))
  sleep 2
done

# Không tự gọi start-tms.sh ở đây vì script đó dừng Nginx và có thể làm mất đường
# quản trị từ xa. Giữ origin/tunnel hiện có để người dùng vẫn có cơ hội truy cập panel.
ensure_tunnel
write_state 'restart_failed' 'Source mới đã áp dụng nhưng panel local chưa phản hồi. Update Center đã tránh restart toàn stack để không làm rớt Cloudflare Tunnel.'
clear_queue
exit 1
