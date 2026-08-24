#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
STATE="$HOME/.tms-os"
LOG="$HOME/logs/services/php-engine.log"
PID="$STATE/php-engine.pid"
LEGACY_PID="$STATE/php-cgi.pid"
POLICY_FILE="$STATE/php-engine-policy"
ENGINE_TMPDIR="${TMPDIR:-$STATE/tmp}"
TERMUX_VAR_TMP="$PREFIX/var/tmp"
WEB_ROOT="${TMS_WEB_ROOT:-$HOME/tms-os/public}"

if ! mkdir -p "$STATE" "$(dirname "$LOG")" "$ENGINE_TMPDIR" "$TERMUX_VAR_TMP" "$PREFIX/var/run"; then
  echo '[ERROR] Không thể tạo thư mục runtime/temp cho PHP.' >&2
  exit 1
fi
chmod 700 "$STATE" "$ENGINE_TMPDIR" "$TERMUX_VAR_TMP" "$PREFIX/var/run" 2>/dev/null || true
if ! test -w "$ENGINE_TMPDIR" || ! test -w "$TERMUX_VAR_TMP"; then
  echo '[ERROR] PHP temp directory không ghi được.' >&2
  exit 1
fi
export TMPDIR="$ENGINE_TMPDIR" TMP="$ENGINE_TMPDIR" TEMP="$ENGINE_TMPDIR"

policy(){
  local value
  value="${TMS_COMPAT_ENGINE:-$(cat "$POLICY_FILE" 2>/dev/null || true)}"
  case "$value" in fastcgi|php-http) printf '%s' "$value" ;; *) printf 'fastcgi' ;; esac
}
mode(){ policy; }
master_pid(){ pgrep -f 'php-fpm: master process' 2>/dev/null | head -n1; }
wait_dead(){
  local pattern="$1" i=0
  while pgrep -f "$pattern" >/dev/null 2>&1 && [ "$i" -lt 25 ]; do sleep 0.2; i=$((i+1)); done
}

write_http_router(){
  # Nginx truyền X-TMS-Root khác nhau cho panel và từng website; chỉ nhận
  # giá trị từ Nginx local, sau đó canonicalize để chặn ../ path traversal.
  cat > "$STATE/php-http-router.php" <<'PHP_ROUTER'
<?php
$root = realpath($_SERVER['HTTP_X_TMS_ROOT'] ?? '');
$home = realpath(getenv('HOME') ?: '');
$allowed = array_filter([
    $home !== false ? realpath($home . '/tms-os/public') : false,
    $home !== false ? realpath($home . '/websites') : false,
]);
$rootAllowed = $root !== false && is_dir($root);
foreach ($allowed as $base) {
    if ($root === $base || strncmp($root, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) === 0) { $rootAllowed = true; break; }
}
if (!$rootAllowed) { http_response_code(403); echo 'TMS root not allowed'; exit; }
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$relative = ltrim(rawurldecode($uri), '/');
$file = realpath($root . DIRECTORY_SEPARATOR . $relative);
$inside = $file !== false && ($file === $root || strncmp($file, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) === 0);
if ($relative !== '' && $inside && is_file($file)) { return false; }
$index = $root . DIRECTORY_SEPARATOR . 'index.php';
if (!is_file($index)) { http_response_code(404); echo 'TMS index unavailable'; exit; }
require $index;
PHP_ROUTER
}

start_cgi(){
  pkill -9 -f 'php-cgi (-n )?-b 127.0.0.1:9000' 2>/dev/null || true
  fuser -k 9000/tcp 2>/dev/null || true
  nohup env TMPDIR="$ENGINE_TMPDIR" TMP="$ENGINE_TMPDIR" TEMP="$ENGINE_TMPDIR" PHP_INI_SCAN_DIR=/dev/null php-cgi -n -d "sys_temp_dir=$ENGINE_TMPDIR" -b 127.0.0.1:9000 >>"$LOG" 2>&1 &
  echo $! > "$PID"; cp "$PID" "$LEGACY_PID"; sleep 0.7; kill -0 "$(cat "$PID")" 2>/dev/null
}
start_http(){
  [ -d "$WEB_ROOT" ] || { echo "[ERROR] Web root không tồn tại: $WEB_ROOT" >&2; return 1; }
  write_http_router
  pkill -9 -f 'php .* -S 127.0.0.1:9000' 2>/dev/null || true
  fuser -k 9000/tcp 2>/dev/null || true
  nohup env TMPDIR="$ENGINE_TMPDIR" TMP="$ENGINE_TMPDIR" TEMP="$ENGINE_TMPDIR" PHP_INI_SCAN_DIR=/dev/null php -n -d "sys_temp_dir=$ENGINE_TMPDIR" -S 127.0.0.1:9000 -t "$WEB_ROOT" "$STATE/php-http-router.php" >>"$LOG" 2>&1 &
  echo $! > "$PID"; sleep 0.8; kill -0 "$(cat "$PID")" 2>/dev/null
}
start_fpm(){
  local cfg="$STATE/php-fpm-runtime.conf"
  cat > "$cfg" <<CFG
[global]
daemonize = no
error_log = $LOG
[www]
listen = 127.0.0.1:9000
pm = static
pm.max_children = 2
clear_env = no
CFG
  rm -f "$PREFIX/var/run/php-fpm.pid" "$PREFIX/var/run/php-fpm.sock"
  TMPDIR="$ENGINE_TMPDIR" TMP="$ENGINE_TMPDIR" TEMP="$ENGINE_TMPDIR" PHP_INI_SCAN_DIR=/dev/null \
    php-fpm -n -F -y "$cfg" >>"$LOG" 2>&1 &
  echo $! > "$PID"
  sleep 0.8
  kill -0 "$(cat "$PID")" 2>/dev/null
}
start_inner(){
  if [ "$(policy)" = 'php-http' ]; then start_http; return; fi
  if command -v php-fpm >/dev/null 2>&1 && start_fpm; then return 0; fi
  printf '%s\n' '[WARN] PHP-FPM không khởi động được; thử PHP-CGI.' >>"$LOG"
  start_cgi
}
stop_inner(){
  local p
  p="$(master_pid)"; [ -n "$p" ] && kill -TERM "$p" 2>/dev/null || true
  [ -f "$PID" ] && kill -TERM "$(cat "$PID" 2>/dev/null)" 2>/dev/null || true
  wait_dead 'php-fpm: master process'
  pgrep -f 'php-fpm: master process' >/dev/null 2>&1 && pkill -KILL -f 'php-fpm: master process' 2>/dev/null || true
  [ -f "$PID" ] && kill -9 "$(cat "$PID" 2>/dev/null)" 2>/dev/null || true
  pkill -9 -f 'php-cgi (-n )?-b 127.0.0.1:9000' 2>/dev/null || true
  pkill -9 -f 'php .* -S 127.0.0.1:9000' 2>/dev/null || true
  fuser -k 9000/tcp 2>/dev/null || true
  rm -f "$PID" "$LEGACY_PID" "$PREFIX/var/run/php-fpm.pid" "$PREFIX/var/run/php-fpm.sock"
}
restart_inner(){ stop_inner; sleep 0.5; start_inner; }
case "${1:-status}" in
  start) start_inner ;;
  stop) stop_inner ;;
  restart) restart_inner ;;
  status) mode ;;
  *) echo 'Cách dùng: tms-php-engine.sh {start|stop|restart|status}' >&2; exit 2 ;;
esac
