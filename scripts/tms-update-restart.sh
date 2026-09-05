#!/usr/bin/env sh
# TMS OS V17.0.24 — hậu kiểm update theo nguyên tắc zero-downtime.
# Tuyệt đối không restart/reload Nginx, PHP Engine hay Cloudflare Tunnel trong luồng hot update.
set -u

STATE_FILE=${TMS_UPDATE_STATE_FILE:-}
QUEUE_FILE=${TMS_UPDATE_QUEUE_FILE:-}
EXPECTED_VERSION=${TMS_UPDATE_EXPECTED_VERSION:-}
HEALTH_ATTEMPTS=${TMS_RESTART_HEALTH_ATTEMPTS:-15}
PANEL_URL=${TMS_UPDATE_PANEL_URL:-http://127.0.0.1:8888/login}
HOME_DIR=${HOME:-/data/data/com.termux/files/home}
TARGET="$HOME_DIR/tms-os"
PREVIOUS="$HOME_DIR/tms-os.previous"

case "$HEALTH_ATTEMPTS" in
  ''|*[!0-9]*) HEALTH_ATTEMPTS=15 ;;
esac
[ "$HEALTH_ATTEMPTS" -ge 1 ] || HEALTH_ATTEMPTS=1

write_state() {
  phase=$1
  message=$2
  [ -n "$STATE_FILE" ] || return 0
  TMS_RESTART_STATE_FILE="$STATE_FILE" \
  TMS_RESTART_PHASE="$phase" \
  TMS_RESTART_MESSAGE="$message" \
  TMS_RESTART_VERSION="$EXPECTED_VERSION" \
  php -n -r '
    $file=getenv("TMS_RESTART_STATE_FILE")?:"";
    if($file==="") exit(0);
    $state=json_decode((string)@file_get_contents($file),true);
    if(!is_array($state)) $state=[];
    $phase=getenv("TMS_RESTART_PHASE")?:"restart_failed";
    $state["applying"]=false;
    $state["ok"]=$phase==="completed";
    $state["phase"]=$phase;
    $state["message"]=getenv("TMS_RESTART_MESSAGE")?:"Không thể xác nhận trạng thái sau cập nhật.";
    $state["finished_at"]=date("c");
    $version=getenv("TMS_RESTART_VERSION")?:"";
    if($version!=="") $state["current"]=$version;
    $dir=dirname($file);
    $tmp=tempnam($dir,".restart-state-");
    if($tmp!==false){
      file_put_contents($tmp,json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      @chmod($tmp,0600);
      @rename($tmp,$file);
    }
  ' >/dev/null 2>&1 || true
}

clear_queue() {
  [ -n "$QUEUE_FILE" ] && rm -f "$QUEUE_FILE" || true
}

panel_ok() {
  curl -fsS --max-time 3 "$PANEL_URL" >/dev/null 2>&1
}

rollback_source() {
  # Rollback chỉ source. Không chạm Nginx/PHP/Tunnel để tránh biến lỗi ứng dụng thành 502 toàn hệ thống.
  [ -d "$PREVIOUS" ] || return 1
  for part in app config public routes scripts; do
    if [ -e "$PREVIOUS/$part" ]; then
      rm -rf "$TARGET/$part.rollback-new" 2>/dev/null || true
      if [ -e "$TARGET/$part" ]; then mv "$TARGET/$part" "$TARGET/$part.rollback-new" || return 1; fi
      if ! mv "$PREVIOUS/$part" "$TARGET/$part"; then
        [ -e "$TARGET/$part.rollback-new" ] && mv "$TARGET/$part.rollback-new" "$TARGET/$part" 2>/dev/null || true
        return 1
      fi
      rm -rf "$TARGET/$part.rollback-new" 2>/dev/null || true
    fi
  done
  return 0
}

sleep 1
write_state 'verifying' 'Đang hậu kiểm source mới. Hot update không restart Nginx, PHP Engine hoặc Cloudflare Tunnel.'

attempt=1
while [ "$attempt" -le "$HEALTH_ATTEMPTS" ]; do
  if panel_ok; then
    write_state 'completed' 'Cập nhật thành công. Panel vẫn online; không có dịch vụ nào bị restart/reload.'
    clear_queue
    exit 0
  fi
  attempt=$((attempt + 1))
  sleep 1
done

# Source mới không phục vụ được: tự trả source cũ ngay, vẫn không restart dịch vụ.
if rollback_source; then
  sleep 1
  if panel_ok; then
    write_state 'rolled_back' 'Source mới không qua health-check nên đã tự rollback về bản trước; dịch vụ và tunnel được giữ nguyên.'
    clear_queue
    exit 1
  fi
  write_state 'rollback_failed' 'Đã rollback source nhưng panel vẫn chưa phản hồi. Không restart tự động để tránh làm mất đường quản trị từ xa.'
  clear_queue
  exit 1
fi

write_state 'restart_failed' 'Source mới không qua health-check và không tìm thấy bản previous để rollback. Không restart tự động Nginx/PHP/Tunnel.'
clear_queue
exit 1
