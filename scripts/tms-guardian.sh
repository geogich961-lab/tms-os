#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
ROOT="$HOME/tms-os"
STATE="$HOME/.tms-os"
LOGDIR="$HOME/logs/guardian"
PIDFILE="$STATE/guardian.pid"
LOCKFILE="$STATE/guardian.lock"
CFG="$STATE/guardian.conf"
EVENTS="$LOGDIR/events.jsonl"
mkdir -p "$STATE" "$LOGDIR"
[ -f "$CFG" ] || cat > "$CFG" <<CFG
ENABLED=1
INTERVAL=30
AUTO_REPAIR=1
CHECK_PANEL=1
CHECK_WEBSITE=1
CHECK_DATABASE=1
CHECK_TUNNEL=1
CHECK_CRON=1
MAX_REPAIRS_PER_HOUR=6
CFG
# shellcheck disable=SC1090
. "$CFG" 2>/dev/null || true
INTERVAL="${INTERVAL:-30}"; AUTO_REPAIR="${AUTO_REPAIR:-1}"; MAX_REPAIRS_PER_HOUR="${MAX_REPAIRS_PER_HOUR:-6}"
json_escape(){ printf '%s' "$1" | sed 's/\\/\\\\/g;s/"/\\"/g;s/	/\\t/g'; }
event(){
  local level="$1" service="$2" action="$3" message="$4" ok="${5:-1}"
  printf '{"time":"%s","level":"%s","service":"%s","action":"%s","ok":%s,"message":"%s"}\n' "$(date -Iseconds)" "$level" "$service" "$action" "$ok" "$(json_escape "$message")" >> "$EVENTS"
  tail -n 1000 "$EVENTS" > "$EVENTS.tmp" 2>/dev/null && mv "$EVENTS.tmp" "$EVENTS" || true
}
http_code(){ curl -sS -L --max-time 8 -o /dev/null -w '%{http_code}' "$1" 2>/dev/null || printf '000'; }
repair_allowed(){
  local count prefix
  prefix="$(date +%Y-%m-%dT%H):"
  count=$(grep -F '"action":"repair"' "$EVENTS" 2>/dev/null | grep -F '"time":"'"$prefix" 2>/dev/null | wc -l | tr -d ' ')
  [ "${count:-0}" -lt "$MAX_REPAIRS_PER_HOUR" ]
}
repair_php(){
  repair_allowed || { event warn php repair 'Đã đạt giới hạn tự sửa trong một giờ.' 0; return 1; }
  event warn php repair 'PHP upstream không phản hồi, bắt đầu phục hồi.' 1
  bash "$ROOT/scripts/tms-php-engine.sh" restart >>"$LOGDIR/guardian.log" 2>&1 || true
  nginx -t >>"$LOGDIR/guardian.log" 2>&1 && nginx -s reload >>"$LOGDIR/guardian.log" 2>&1 || true
  sleep 1
  local p w
  p=$(http_code 'http://127.0.0.1:8888/')
  w=$(http_code 'http://127.0.0.1:8080/')
  if [ "$p" != 000 ] && [ "$p" != 502 ] && [ "$p" != 504 ] && [ "$w" != 502 ] && [ "$w" != 504 ]; then
    event info php recovered "Đã phục hồi PHP-FPM; panel=$p website=$w." 1; return 0
  fi
  event error php repair "Phục hồi chưa thành công; panel=$p website=$w." 0; return 1
}
# pgrep không chắc có trên Termux: dò trực tiếp /proc cho cloudflared.
cloudflared_running(){
  local pid cmdline
  pid="$(cat "$STATE/cloudflare-hosting/tunnel.pid" 2>/dev/null || true)"
  if [ -n "$pid" ] && [ -d "/proc/$pid" ]; then
    cmdline="$(tr '\0' ' ' < "/proc/$pid/cmdline" 2>/dev/null || true)"
    case "$cmdline" in *cloudflared*) return 0 ;; esac
  fi
  for d in /proc/[0-9]*; do
    cmdline="$(tr '\0' ' ' < "$d/cmdline" 2>/dev/null || true)"
    case "$cmdline" in *cloudflared*connector*|*cloudflared*tunnel*) return 0 ;; esac
  done
  return 1
}
repair_tunnel(){
  repair_allowed || { event warn tunnel repair 'Đã đạt giới hạn tự sửa trong một giờ.' 0; return 1; }
  event warn tunnel repair 'Cloudflare Tunnel đã cấu hình nhưng cloudflared không chạy, tự khởi động lại.' 1
  bash "$ROOT/scripts/tms-cloudflare-tunnel.sh" start >>"$LOGDIR/guardian.log" 2>&1 || true
  sleep 2
  if cloudflared_running; then event info tunnel recovered 'Cloudflare Tunnel đã chạy lại.' 1; return 0; fi
  event error tunnel repair 'Khởi động lại Tunnel chưa thành công (có thể do mạng/DNS).' 0; return 1
}
repair_crond(){
  repair_allowed || return 1
  event warn cron repair 'crond không chạy dù có cron job bật, tự khởi động lại.' 1
  bash "$ROOT/scripts/tms-cron-engine.sh" start >>"$LOGDIR/guardian.log" 2>&1 || true
  return 0
}
check_once(){
  local panel website
  if ! bash "$ROOT/scripts/tms-service-core.sh" nginx status >/dev/null 2>&1; then
    event error nginx down 'Không tìm thấy tiến trình Nginx.' 0
    if [ "$AUTO_REPAIR" = 1 ] && nginx -t >/dev/null 2>&1; then nginx >>"$LOGDIR/guardian.log" 2>&1 && event info nginx recovered 'Đã khởi động lại Nginx.' 1; fi
  fi
  panel=skip; website=skip
  [ "${CHECK_PANEL:-1}" = 1 ] && panel=$(http_code 'http://127.0.0.1:8888/')
  [ "${CHECK_WEBSITE:-1}" = 1 ] && website=$(http_code 'http://127.0.0.1:8080/')
  if [ "$panel" = 502 ] || [ "$panel" = 504 ] || [ "$panel" = 000 ] || [ "$website" = 502 ] || [ "$website" = 504 ]; then
    event error php unhealthy "Upstream lỗi; panel=$panel website=$website." 0
    [ "$AUTO_REPAIR" = 1 ] && repair_php || true
  fi
  DBMODE_G="$(cat "$HOME/.tms-os/db-mode" 2>/dev/null || echo mariadb)"
  if [ "${CHECK_DATABASE:-1}" = 1 ] && [ "$DBMODE_G" = "mariadb" ]; then
    if ! bash "$ROOT/scripts/tms-service-core.sh" mariadb status >/dev/null 2>&1; then
      event warn mariadb unhealthy 'MariaDB không phản hồi mysqladmin ping.' 0
      if [ "$AUTO_REPAIR" = 1 ]; then
        bash "$ROOT/scripts/tms-service-core.sh" mariadb start >>"$LOGDIR/guardian.log" 2>&1 || true
        event info mariadb repair 'Đã yêu cầu Unified Core khởi động MariaDB.' 1
      fi
    fi
  fi
  local disk
  disk=$(df -P "$HOME" 2>/dev/null | awk 'NR==2{gsub(/%/,"",$5);print $5}')
  [ -n "$disk" ] && [ "$disk" -ge 90 ] && event warn storage threshold "Dung lượng đã dùng ${disk}%." 0 || true
  if [ "${CHECK_TUNNEL:-1}" = 1 ] && [ -f "$STATE/cloudflare-hosting/config.json" ] && ! cloudflared_running; then
    [ "$AUTO_REPAIR" = 1 ] && repair_tunnel || event warn tunnel down 'Cloudflare Tunnel không chạy (auto repair tắt).' 0
  fi
  if [ "${CHECK_CRON:-1}" = 1 ] && [ -s "$STATE/cron-jobs.json" ] && ! bash "$ROOT/scripts/tms-cron-engine.sh" status >/dev/null 2>&1; then
    [ "$AUTO_REPAIR" = 1 ] && repair_crond || event warn cron down 'crond không chạy (auto repair tắt).' 0
  fi
  printf '{"updated_at":"%s","panel":"%s","website":"%s","nginx":%s,"php":%s,"mariadb":%s}\n' \
    "$(date -Iseconds)" "$panel" "$website" \
    "$(bash "$ROOT/scripts/tms-service-core.sh" nginx status >/dev/null 2>&1 && echo true || echo false)" \
    "$(bash "$ROOT/scripts/tms-service-core.sh" php status >/dev/null 2>&1 && echo true || echo false)" \
    "$(if [ "$DBMODE_G" = "sqlite" ]; then echo true; else bash "$ROOT/scripts/tms-service-core.sh" mariadb status >/dev/null 2>&1 && echo true || echo false; fi)" > "$STATE/guardian-status.json"
}
daemon(){
  if command -v flock >/dev/null 2>&1; then exec 9>"$LOCKFILE"; flock -n 9 || exit 0; fi
  echo $$ > "$PIDFILE"; trap 'rm -f "$PIDFILE"' EXIT INT TERM
  event info guardian start "TMS Guardian bắt đầu, chu kỳ ${INTERVAL}s." 1
  while :; do check_once; sleep "$INTERVAL"; done
}
case "${1:-status}" in
  daemon) daemon;;
  once) check_once;;
  start)
    [ "${ENABLED:-1}" = 1 ] || exit 0
    if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE" 2>/dev/null)" 2>/dev/null; then exit 0; fi
    nohup "$0" daemon >>"$LOGDIR/guardian.log" 2>&1 & echo $! > "$PIDFILE";;
  stop)
    [ -f "$PIDFILE" ] && kill "$(cat "$PIDFILE" 2>/dev/null)" 2>/dev/null || true
    pkill -f '/scripts/tms-guardian.sh daemon' 2>/dev/null || true; rm -f "$PIDFILE";;
  restart) "$0" stop; sleep 0.5; "$0" start;;
  status) [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE" 2>/dev/null)" 2>/dev/null;;
  *) exit 2;;
esac
