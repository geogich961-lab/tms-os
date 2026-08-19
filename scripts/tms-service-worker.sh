#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
ROOT="$HOME/tms-os"
STATE="$HOME/.tms-os/service-core"
QUEUE="$STATE/queue"
RESULTS="$STATE/results"
LOCK="$STATE/worker.lock"
LOG="$HOME/logs/services/service-core.log"
mkdir -p "$QUEUE" "$RESULTS" "$(dirname "$LOG")"

if ! mkdir "$LOCK" 2>/dev/null; then exit 0; fi
trap 'rmdir "$LOCK" 2>/dev/null || true' EXIT INT TERM

json_escape(){ sed ':a;N;$!ba;s/\\/\\\\/g;s/"/\\"/g;s/\r//g;s/\n/\\n/g'; }
write_result(){
  local job="$1" service="$2" action="$3" ok="$4" message="$5" pid running
  pid="$(bash "$ROOT/scripts/tms-service-core.sh" "$service" pid 2>/dev/null || true)"
  if bash "$ROOT/scripts/tms-service-core.sh" "$service" status >/dev/null 2>&1; then running=true; else running=false; fi
  printf '{"job":"%s","service":"%s","action":"%s","ok":%s,"running":%s,"pid":"%s","message":"%s","finished_at":"%s"}\n' \
    "$job" "$service" "$action" "$ok" "$running" "$pid" "$(printf '%s' "$message" | json_escape)" "$(date -Iseconds)" > "$RESULTS/$job.json.tmp"
  mv "$RESULTS/$job.json.tmp" "$RESULTS/$job.json"
}

while :; do
  jobfile="$(find "$QUEUE" -maxdepth 1 -type f -name '*.job' 2>/dev/null | sort | head -n1)"
  [ -n "$jobfile" ] || break
  job="$(basename "$jobfile" .job)"
  IFS=$'\t' read -r service action < "$jobfile" || true
  rm -f "$jobfile"
  case "$service:$action" in
    nginx:start|nginx:stop|nginx:restart|php:start|php:stop|php:restart|mariadb:start|mariadb:stop|mariadb:restart|ssh:start|ssh:stop|ssh:restart|redis:start|redis:stop|redis:restart) ;;
    *) write_result "$job" "${service:-unknown}" "${action:-unknown}" false "Công việc không hợp lệ."; continue ;;
  esac
  printf '[%s] %s %s (%s)\n' "$(date '+%F %T')" "$action" "$service" "$job" >> "$LOG"
  output="$(bash "$ROOT/scripts/tms-service-core.sh" "$service" "$action" 2>&1)"; code=$?
  if [ "$code" -eq 0 ]; then
    write_result "$job" "$service" "$action" true "Hoàn tất và đã xác minh trạng thái thực tế."
  else
    [ -n "$output" ] || output="Thao tác thất bại hoặc dịch vụ không đạt trạng thái mong đợi. Xem Live Log để biết chi tiết."
    write_result "$job" "$service" "$action" false "$output"
  fi
done
