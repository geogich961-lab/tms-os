#!/data/data/com.termux/files/usr/bin/bash
# tms-watchdog.sh — Watchdog tầng cron cho TMS OS (V17.1.0-beta.4).
#
# Chạy mỗi phút bằng crond — thành phần đã chứng minh là sống sót qua mọi sự
# cố (kể cả sự cố 14/10/2026 khi Guardian, Tunnel, PHP-FPM, Nginx chết hàng
# loạt mà cron vẫn gửi báo cáo đều đặn).
#
# Nhiệm vụ: giám sát và tự khởi động lại các thành phần sống còn:
#   1. crond (nền tảng của chính watchdog)
#   2. php-fpm (PHP engine)
#   3. nginx (reverse proxy; tự đảm bảo PHP chạy trước)
#   4. guardian daemon (giám sát chuyên sâu chu kỳ 30s)
#   5. cloudflared tunnel (chỉ khi đã cấu hình)
#
# An toàn:
#   - Chống chạy chồng chéo bằng flock (cron có thể dồn job khi máy lag).
#   - Giới hạn số lần tự sửa mỗi giờ (tránh restart loop).
#   - Không restart PHP khi đang có update (external-update.lock).
#   - Ghi mọi hành động vào events.jsonl của Guardian để hiện trên panel.
#   - Không in token hay bí mật ra log.
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
ROOT="$HOME/tms-os"
STATE="$HOME/.tms-os"
LOGDIR="$HOME/logs/guardian"
EVENTS="$LOGDIR/events.jsonl"
STATUS_FILE="$STATE/watchdog-status.json"
REPAIR_STATE="$STATE/watchdog-repairs.json"
LOCKFILE="$STATE/watchdog.lock"
LOWMEM_FLAG="$STATE/watchdog-lowmem.warn"
EXTERNAL_UPDATE_LOCK="$STATE/external-update.lock"
TUNNEL_CFG="$STATE/cloudflare-hosting/config.json"
MAX_REPAIRS_PER_HOUR=6
CRON_MARKER="# TMS-OS-WATCHDOG"
mkdir -p "$STATE" "$LOGDIR"

json_escape(){ printf '%s' "$1" | sed 's/\\/\\\\/g;s/"/\\"/g;s/\t/\\t/g'; }
event(){
  local level="$1" service="$2" action="$3" message="$4" ok="${5:-1}"
  printf '{"time":"%s","level":"%s","service":"%s","action":"%s","ok":%s,"message":"%s"}\n' \
    "$(date -Iseconds)" "$level" "$service" "$action" "$ok" "$(json_escape "$message")" >> "$EVENTS"
  tail -n 1000 "$EVENTS" > "$EVENTS.tmp" 2>/dev/null && mv "$EVENTS.tmp" "$EVENTS" || true
}
maintenance_active(){
  [ -f "$EXTERNAL_UPDATE_LOCK" ] || return 1
  local now mtime age
  now="$(date +%s)"
  mtime="$(stat -c %Y "$EXTERNAL_UPDATE_LOCK" 2>/dev/null || stat -f %m "$EXTERNAL_UPDATE_LOCK" 2>/dev/null || echo 0)"
  case "$mtime" in ''|*[!0-9]*) mtime=0 ;; esac
  age=$((now-mtime))
  if [ "$age" -ge 0 ] && [ "$age" -le 300 ]; then return 0; fi
  rm -f "$EXTERNAL_UPDATE_LOCK" 2>/dev/null || true
  return 1
}
# Đếm số lần repair trong giờ hiện tại, chống restart loop.
repair_allowed(){
  local hour count now
  hour="$(date +%Y-%m-%dT%H)"
  now="$(date +%s)"
  count=0
  if [ -f "$REPAIR_STATE" ]; then
    local saved_hour saved_count saved_at
    saved_hour="$(sed -n 's/.*"hour":"\([^"]*\)".*/\1/p' "$REPAIR_STATE" 2>/dev/null || true)"
    saved_count="$(sed -n 's/.*"count":\([0-9]*\).*/\1/p' "$REPAIR_STATE" 2>/dev/null || true)"
    saved_at="$(sed -n 's/.*"at":\([0-9]*\).*/\1/p' "$REPAIR_STATE" 2>/dev/null || true)"
    case "$saved_count" in ''|*[!0-9]*) saved_count=0 ;; esac
    case "$saved_at" in ''|*[!0-9]*) saved_at=0 ;; esac
    if [ "$saved_hour" = "$hour" ]; then count="$saved_count"; fi
    # File state quá cũ (>2h) thì reset để không kẹt vì state hỏng.
    if [ $((now - saved_at)) -gt 7200 ]; then count=0; fi
  fi
  [ "$count" -lt "$MAX_REPAIRS_PER_HOUR" ]
}
repair_count_bump(){
  local hour count now
  hour="$(date +%Y-%m-%dT%H)"
  now="$(date +%s)"
  count=0
  if [ -f "$REPAIR_STATE" ]; then
    local saved_hour saved_count
    saved_hour="$(sed -n 's/.*"hour":"\([^"]*\)".*/\1/p' "$REPAIR_STATE" 2>/dev/null || true)"
    saved_count="$(sed -n 's/.*"count":\([0-9]*\).*/\1/p' "$REPAIR_STATE" 2>/dev/null || true)"
    case "$saved_count" in ''|*[!0-9]*) saved_count=0 ;; esac
    if [ "$saved_hour" = "$hour" ]; then count="$saved_count"; fi
  fi
  printf '{"hour":"%s","count":%d,"at":%d}\n' "$hour" $((count+1)) "$now" > "$REPAIR_STATE"
}
# Đảm bảo crontab có entry watchdog (dùng cho lần cài đầu tiên sau update;
# CronJobService::syncCrontab() cũng luôn chèn entry này nên không thể mất).
install_cron(){
  local bash_bin="$PREFIX/bin/bash"
  [ -x "$bash_bin" ] || bash_bin="$(command -v bash 2>/dev/null || echo /bin/bash)"
  local line="* * * * * $bash_bin $ROOT/scripts/tms-watchdog.sh"
  local current
  current="$(crontab -l 2>/dev/null || true)"
  if printf '%s\n' "$current" | grep -qF "$CRON_MARKER"; then
    echo '[OK] Watchdog cron đã tồn tại.'
    return 0
  fi
  {
    printf '%s\n' "$current" | grep -v '^[[:space:]]*$' || true
    printf '%s\n' "$CRON_MARKER"
    printf '%s\n' "$line"
  } | crontab - 2>/dev/null
  if printf '%s\n' "$(crontab -l 2>/dev/null || true)" | grep -qF "$CRON_MARKER"; then
    echo '[OK] Đã đăng ký watchdog cron (mỗi phút).'
    return 0
  fi
  echo '[LỖI] Không đăng ký được watchdog cron.' >&2
  return 1
}
proc_has(){ # proc_has <pid> <pattern>
  local pid="$1" pattern="$2" cmdline
  [ -n "$pid" ] && [ -d "/proc/$pid" ] || return 1
  cmdline="$(tr '\0' ' ' < "/proc/$pid/cmdline" 2>/dev/null || true)"
  case "$cmdline" in *"$pattern"*) return 0 ;; esac
  return 1
}
guardian_running(){
  local pid
  pid="$(cat "$STATE/guardian.pid" 2>/dev/null || true)"
  proc_has "$pid" "tms-guardian.sh daemon" || return 1
  return 0
}
cloudflared_running(){
  local pid cmdline d
  pid="$(cat "$STATE/cloudflare-hosting/tunnel.pid" 2>/dev/null || true)"
  if proc_has "$pid" "cloudflared"; then return 0; fi
  for d in /proc/[0-9]*; do
    cmdline="$(tr '\0' ' ' < "$d/cmdline" 2>/dev/null || true)"
    case "$cmdline" in *cloudflared*tunnel*|*cloudflared*connector*) return 0 ;; esac
  done
  return 1
}
svc_running(){ # svc_running <nginx|php>
  bash "$ROOT/scripts/tms-service-core.sh" "$1" status >/dev/null 2>&1
}
crond_running(){
  pgrep -u "$(id -u)" -x crond >/dev/null 2>&1
}
do_repair(){ # do_repair <service> <label> <command...>
  local service="$1" label="$2"; shift 2
  if ! repair_allowed; then
    event warn watchdog limit "Đạt giới hạn tự sửa ($MAX_REPAIRS_PER_HOUR/giờ); bỏ qua phục hồi $label." 0
    return 1
  fi
  event warn watchdog repair "Watchdog phát hiện $label không chạy; tự khởi động lại." 1
  if "$@" >>"$LOGDIR/guardian.log" 2>&1; then
    repair_count_bump
    return 0
  fi
  repair_count_bump
  event error watchdog repair "Khởi động lại $label thất bại." 0
  return 1
}
watch_once(){
  local actions="" ok=1
  # 1. crond — nền tảng của chính watchdog và mọi cron job.
  if ! crond_running; then
    if do_repair cron "Cron engine (crond)" bash "$ROOT/scripts/tms-cron-engine.sh" start; then
      actions="${actions}crond,"
      crond_running || ok=0
    else ok=0; fi
  fi
  # 2. PHP engine trước Nginx (Nginx từ chối khởi động khi PHP chưa nghe :9000).
  if ! svc_running php; then
    if maintenance_active; then
      event info watchdog defer "PHP không chạy nhưng đang có update; watchdog không restart." 1
      ok=0
    elif do_repair php "PHP engine (php-fpm)" bash "$ROOT/scripts/tms-php-engine.sh" start; then
      actions="${actions}php,"
      svc_running php || ok=0
    else ok=0; fi
  fi
  # 3. Nginx.
  if ! svc_running nginx; then
    if do_repair nginx "Nginx" bash "$ROOT/scripts/tms-service-core.sh" nginx start; then
      actions="${actions}nginx,"
      svc_running nginx || ok=0
    else ok=0; fi
  fi
  # 4. Guardian daemon — giám sát chuyên sâu chu kỳ 30s.
  if ! guardian_running; then
    if do_repair guardian "Guardian daemon" bash "$ROOT/scripts/tms-guardian.sh" start; then
      sleep 1
      actions="${actions}guardian,"
      guardian_running || ok=0
      if guardian_running; then event info watchdog recovered "Guardian daemon đã chạy lại." 1; fi
    else ok=0; fi
  fi
  # 5. Cloudflare Tunnel — chỉ khi người dùng đã cấu hình.
  if [ -f "$TUNNEL_CFG" ]; then
    if ! cloudflared_running; then
      if do_repair tunnel "Cloudflare Tunnel" bash "$ROOT/scripts/tms-cloudflare-tunnel.sh" start; then
        actions="${actions}tunnel,"
        if cloudflared_running; then event info watchdog recovered "Cloudflare Tunnel đã chạy lại." 1; else ok=0; fi
      else ok=0; fi
    fi
  fi
  # Cảnh báo RAM thấp (giới hạn 1 lần/giờ) — nguyên nhân nghi ngờ của các vụ OOM kill.
  local mem_avail last_warn now
  mem_avail="$(awk '/MemAvailable/{print $2}' /proc/meminfo 2>/dev/null || echo "")"
  if [ -n "$mem_avail" ] && [ "$mem_avail" -lt 102400 ]; then
    now="$(date +%s)"
    last_warn="$(cat "$LOWMEM_FLAG" 2>/dev/null || echo 0)"
    case "$last_warn" in ''|*[!0-9]*) last_warn=0 ;; esac
    if [ $((now - last_warn)) -gt 3600 ]; then
      event warn watchdog memory "RAM khả dụng thấp (~$((mem_avail/1024))MB). Nguy cơ tiến trình bị OOM kill." 0
      echo "$now" > "$LOWMEM_FLAG"
    fi
  fi
  printf '{"updated_at":"%s","ok":%s,"actions":"%s","checks":{"crond":%s,"php":%s,"nginx":%s,"guardian":%s,"tunnel":%s}}\n' \
    "$(date -Iseconds)" "$([ "$ok" = 1 ] && echo true || echo false)" "$actions" \
    "$(crond_running && echo true || echo false)" \
    "$(svc_running php && echo true || echo false)" \
    "$(svc_running nginx && echo true || echo false)" \
    "$(guardian_running && echo true || echo false)" \
    "$([ -f "$TUNNEL_CFG" ] && (cloudflared_running && echo true || echo false) || echo null)" \
    > "$STATUS_FILE"
}
case "${1:-once}" in
  install-cron) install_cron ;;
  once)
    if command -v flock >/dev/null 2>&1; then
      exec 9>"$LOCKFILE"
      flock -n 9 || exit 0
    fi
    watch_once
    ;;
  *) echo "Cách dùng: tms-watchdog.sh [once|install-cron]" >&2; exit 2 ;;
esac
