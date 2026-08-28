#!/data/data/com.termux/files/usr/bin/bash
set -u
HOME="${HOME:-/data/data/com.termux/files/home}"
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
ROOT="$HOME/tms-os"
LOG_DIR="$HOME/logs/services"
mkdir -p "$LOG_DIR" "$HOME/.tms-os/service-core"

service="${1:-}"
action="${2:-status}"

have(){ command -v "$1" >/dev/null 2>&1; }
first_pid(){ pgrep "$@" 2>/dev/null | head -n1; }
wait_until(){
  local expected="$1" timeout="$2" i=0
  while [ "$i" -lt "$timeout" ]; do
    if status_service "$service"; then [ "$expected" = up ] && return 0; else [ "$expected" = down ] && return 0; fi
    sleep 1; i=$((i+1))
  done
  if status_service "$service"; then [ "$expected" = up ]; else [ "$expected" = down ]; fi
}

# PHP-CGI của UCI chạy với -n/-d trước -b; không so khớp dạng "php-cgi -b"
# vì Service Manager sẽ báo dừng sai dù engine đang lắng nghe. Cổng 9000 là
# điểm giao tiếp duy nhất của PHP engine TMS và bao phủ cả FPM/CGI/php-http.
php_pid(){
  local pid
  if have fuser; then
    pid="$(fuser 9000/tcp 2>/dev/null | awk '{for (i=NF; i>=1; i--) if ($i ~ /^[0-9]+$/) { print $i; exit }}')"
    [ -n "$pid" ] && { printf '%s\n' "$pid"; return 0; }
  fi
  first_pid -f 'php-fpm: master process|php-cgi .* -b 127\.0\.0\.1:9000|php .* -S 127\.0\.0\.1:9000'
}
nginx_pid(){ first_pid -f 'nginx: master process'; }
mariadb_pid(){ first_pid -f '(^|/)(mariadbd|mysqld)( |$)'; }
ssh_pid(){ first_pid -x sshd; }
redis_pid(){ first_pid -f 'redis-server'; }

mariadb_ping(){
  if have mariadb-admin; then mariadb-admin ping --silent >/dev/null 2>&1 && return 0; fi
  if have mysqladmin; then mysqladmin ping --silent >/dev/null 2>&1 && return 0; fi
  return 1
}

status_service(){
  case "$1" in
    nginx) [ -n "$(nginx_pid)" ] ;;
    php) [ -n "$(php_pid)" ] ;;
    mariadb) mariadb_ping || [ -n "$(mariadb_pid)" ] ;;
    ssh) [ -n "$(ssh_pid)" ] ;;
    redis) if have redis-cli; then redis-cli ping 2>/dev/null | grep -qx PONG || [ -n "$(redis_pid)" ]; else [ -n "$(redis_pid)" ]; fi ;;
    *) return 2 ;;
  esac
}

pid_service(){
  case "$1" in
    nginx) nginx_pid ;;
    php) php_pid ;;
    mariadb) mariadb_pid ;;
    ssh) ssh_pid ;;
    redis) redis_pid ;;
    *) return 2 ;;
  esac
}

# Nginx chỉ là reverse proxy/FastCGI frontend. Khởi động riêng Nginx khi PHP
# chưa lắng nghe cổng loopback 9000 sẽ tạo 502 và dễ khiến người dùng nghĩ
# panel bị hỏng. Bảo đảm dependency trước khi chạm vào Nginx.
ensure_php_up_for_nginx(){
  local i=0
  status_service php && return 0
  printf '%s\n' '[INFO] PHP runtime chưa chạy; khởi động trước Nginx.' >>"$LOG_DIR/nginx.log"
  bash "$ROOT/scripts/tms-php-engine.sh" start >>"$LOG_DIR/php-engine.log" 2>&1 || {
    printf '%s\n' '[ERROR] PHP runtime không khởi động được; không khởi động Nginx để tránh 502.' >>"$LOG_DIR/nginx.log"
    return 1
  }
  while [ "$i" -lt 12 ]; do
    status_service php && return 0
    sleep 1; i=$((i+1))
  done
  printf '%s\n' '[ERROR] PHP runtime chưa lắng nghe cổng 9000; không khởi động Nginx để tránh 502.' >>"$LOG_DIR/nginx.log"
  return 1
}

start_service(){
  status_service "$service" && return 0
  case "$service" in
    nginx)
      ensure_php_up_for_nginx || return 1
      nginx -t >>"$LOG_DIR/nginx.log" 2>&1 || return 1
      nginx >>"$LOG_DIR/nginx.log" 2>&1 || return 1
      wait_until up 8
      ;;
    php)
      bash "$ROOT/scripts/tms-php-engine.sh" start >>"$LOG_DIR/php-engine.log" 2>&1 || return 1
      wait_until up 12
      ;;
    mariadb)
      rm -f "$PREFIX/var/lib/mysql"/*.pid "$PREFIX/var/run/mysqld"/*.pid 2>/dev/null || true
      nohup mariadbd-safe --datadir="$PREFIX/var/lib/mysql" >>"$LOG_DIR/mariadb.log" 2>&1 < /dev/null &
      wait_until up 25
      ;;
    ssh)
      sshd >>"$LOG_DIR/sshd.log" 2>&1 || return 1
      wait_until up 8
      ;;
    redis)
      redis-server --daemonize yes >>"$LOG_DIR/redis.log" 2>&1 || return 1
      wait_until up 8
      ;;
    *) return 2 ;;
  esac
}

stop_service(){
  status_service "$service" || return 0
  case "$service" in
    nginx)
      nginx -s quit >>"$LOG_DIR/nginx.log" 2>&1 || nginx -s stop >>"$LOG_DIR/nginx.log" 2>&1 || true
      wait_until down 10
      ;;
    php)
      bash "$ROOT/scripts/tms-php-engine.sh" stop >>"$LOG_DIR/php-engine.log" 2>&1 || true
      wait_until down 12
      ;;
    mariadb)
      if have mariadb-admin; then mariadb-admin shutdown >>"$LOG_DIR/mariadb.log" 2>&1 || true
      elif have mysqladmin; then mysqladmin shutdown >>"$LOG_DIR/mariadb.log" 2>&1 || true
      fi
      sleep 1
      pgrep -f '(^|/)(mariadbd|mysqld)( |$)' >/dev/null 2>&1 && pkill -TERM -f '(^|/)(mariadbd|mysqld)( |$)' 2>/dev/null || true
      wait_until down 15
      ;;
    ssh)
      pkill -TERM -x sshd 2>/dev/null || true
      wait_until down 8
      ;;
    redis)
      redis-cli shutdown >>"$LOG_DIR/redis.log" 2>&1 || pkill -TERM -f redis-server 2>/dev/null || true
      wait_until down 8
      ;;
    *) return 2 ;;
  esac
}

restart_service(){
  case "$service" in
    nginx)
      ensure_php_up_for_nginx || return 1
      nginx -t >>"$LOG_DIR/nginx.log" 2>&1 || return 1
      if status_service nginx; then nginx -s reload >>"$LOG_DIR/nginx.log" 2>&1 || return 1; else start_service; fi
      wait_until up 8
      ;;
    *) stop_service && sleep 1 && start_service ;;
  esac
}

case "$action" in
  installed)
    case "$service" in
      nginx) have nginx;; php) have php;; mariadb) have mariadbd;; ssh) have sshd;; redis) have redis-server;; *) exit 2;; esac
    ;;
  status) status_service "$service" ;;
  pid) pid_service "$service" ;;
  start) start_service ;;
  stop) stop_service ;;
  restart) restart_service ;;
  *) echo "Usage: $0 {nginx|php|mariadb|ssh|redis} {installed|status|pid|start|stop|restart}" >&2; exit 2 ;;
esac
