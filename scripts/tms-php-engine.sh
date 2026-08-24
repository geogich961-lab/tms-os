#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
STATE="$HOME/.tms-os"
LOG="$HOME/logs/services/php-engine.log"
PID="$STATE/php-cgi.pid"
mkdir -p "$STATE" "$(dirname "$LOG")" "$PREFIX/var/run"

mode(){ command -v php-fpm >/dev/null 2>&1 && echo fpm || echo cgi; }
master_pid(){ pgrep -f 'php-fpm: master process' 2>/dev/null | head -n1; }
wait_dead(){
  local pattern="$1" i=0
  while pgrep -f "$pattern" >/dev/null 2>&1 && [ "$i" -lt 25 ]; do
    sleep 0.2
    i=$((i+1))
  done
}

start_cgi(){
  if [ -f "$PID" ] && kill -0 "$(cat "$PID" 2>/dev/null)" 2>/dev/null; then return 0; fi
  # PHP-CGI là fallback tương thích cho Termux/Android cũ khi PHP-FPM không chạy được.
  pkill -9 -f 'php-cgi -b 127.0.0.1:9000' 2>/dev/null || true
  fuser -k 9000/tcp 2>/dev/null || true
  nohup php-cgi -b 127.0.0.1:9000 >>"$LOG" 2>&1 &
  echo $! > "$PID"
  sleep 0.7
  kill -0 "$(cat "$PID")" 2>/dev/null
}

start_inner(){
  if [ "$(mode)" = fpm ]; then
    if [ -n "$(master_pid)" ]; then return 0; fi
    rm -f "$PREFIX/var/run/php-fpm.pid" "$PREFIX/var/run/php-fpm.sock"
    if php-fpm >>"$LOG" 2>&1; then
      sleep 0.5
      [ -n "$(master_pid)" ] && return 0
    fi
    printf '%s\n' '[WARN] PHP-FPM không khởi động được; chuyển sang PHP-CGI trên cổng 9000.' >>"$LOG"
  fi
  start_cgi
}

stop_inner(){
  if [ "$(mode)" = fpm ]; then
    local p
    p="$(master_pid)"
    [ -n "$p" ] && kill -TERM "$p" 2>/dev/null || true
    wait_dead 'php-fpm: master process'
    pgrep -f 'php-fpm: master process' >/dev/null 2>&1 && pkill -KILL -f 'php-fpm: master process' 2>/dev/null || true
    rm -f "$PREFIX/var/run/php-fpm.pid" "$PREFIX/var/run/php-fpm.sock"
  fi
  # Dọn cả CGI fallback, kể cả khi máy vẫn còn cài binary php-fpm.
  [ -f "$PID" ] && kill -9 "$(cat "$PID" 2>/dev/null)" 2>/dev/null || true
  pkill -9 -f 'php-cgi -b 127.0.0.1:9000' 2>/dev/null || true
  fuser -k 9000/tcp 2>/dev/null || true
  rm -f "$PID"
}

restart_inner(){
  stop_inner
  sleep 0.5
  start_inner
}

case "${1:-status}" in
  start) start_inner ;;
  stop) stop_inner ;;
  restart) restart_inner ;;
  status) mode ;;
  *) echo 'Cách dùng: tms-php-engine.sh {start|stop|restart|status}' >&2; exit 2 ;;
esac
