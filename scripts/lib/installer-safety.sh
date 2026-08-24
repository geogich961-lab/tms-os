#!/data/data/com.termux/files/usr/bin/bash
# TMS OS installer safety library. Không sửa profile Termux toàn cục.
set -u

TMS_STATE_DIR="${TMS_STATE_DIR:-${HOME:-/data/data/com.termux/files/home}/.tms-os/install-state}"
TMS_REPORT="${TMS_REPORT:-${HOME:-/data/data/com.termux/files/home}/tms-os-preflight-$(date +%Y%m%d-%H%M%S).txt}"
TMS_TXN_FILE="${TMS_TXN_FILE:-$TMS_STATE_DIR/active.env}"
TMS_SAFETY_LOG="${TMS_SAFETY_LOG:-$TMS_STATE_DIR/installer.log}"

tms_safety_init() {
  mkdir -p -- "$TMS_STATE_DIR" "$(dirname -- "$TMS_REPORT")" || return 1
  chmod 700 "$TMS_STATE_DIR" 2>/dev/null || true
  : > "$TMS_REPORT" || return 1
  : > "$TMS_SAFETY_LOG" || return 1
  tms_report "started_at=$(date -Iseconds 2>/dev/null || date) pid=$$"
}

tms_report() {
  local line="[$(date '+%Y-%m-%dT%H:%M:%S%z')] $*"
  printf '%s\n' "$line" >> "$TMS_REPORT" 2>/dev/null || true
  printf '%s\n' "$line" >> "$TMS_SAFETY_LOG" 2>/dev/null || true
}

tms_check() {
  local label="$1" rc="$2" detail="${3:-}"
  tms_report "check=$label rc=$rc detail=${detail:-none}"
  if [ "$rc" -eq 0 ]; then printf '[OK] %s%s\n' "$label" "${detail:+ — $detail}"; else printf '[LỖI] %s%s\n' "$label" "${detail:+ — $detail}" >&2; fi
  return "$rc"
}

tms_writable_dir() {
  local dir="$1" probe
  mkdir -p -- "$dir" 2>/dev/null || return 1
  [ -w "$dir" ] || return 1
  probe="$(mktemp "$dir/.tms-write.XXXXXX" 2>/dev/null)" || return 1
  rm -f -- "$probe"
}

tms_probe_php_cli() {
  local tmp="$1" err="$TMS_STATE_DIR/php-cli.stderr"
  : > "$err"
  env -u LD_PRELOAD TMPDIR="$tmp" TMP="$tmp" TEMP="$tmp" PHP_INI_SCAN_DIR=/dev/null \
    php -n -d "sys_temp_dir=$tmp" -r 'exit(0);' >/dev/null 2>"$err"
}

tms_probe_php_fpm() {
  local tmp="$1" port="${2:-19001}" cfg="$TMS_STATE_DIR/php-fpm-preflight.conf" err="$TMS_STATE_DIR/php-fpm.stderr" pid
  cat > "$cfg" <<CFG
[global]
daemonize = no
error_log = $err
[www]
listen = 127.0.0.1:$port
pm = static
pm.max_children = 1
clear_env = no
CFG
  : > "$err"
  env -u LD_PRELOAD TMPDIR="$tmp" TMP="$tmp" TEMP="$tmp" PHP_INI_SCAN_DIR=/dev/null \
    php-fpm -n -F -y "$cfg" >/dev/null 2>"$err" &
  pid=$!
  sleep 1
  if kill -0 "$pid" 2>/dev/null; then
    kill "$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
    return 0
  fi
  cat "$err" >> "$TMS_REPORT" 2>/dev/null || true
  wait "$pid" 2>/dev/null || true
  return 1
}

tms_probe_php_cgi() {
  local tmp="$1" port="${2:-19000}" err="$TMS_STATE_DIR/php-cgi.stderr" pid
  : > "$err"
  env -u LD_PRELOAD TMPDIR="$tmp" TMP="$tmp" TEMP="$tmp" PHP_INI_SCAN_DIR=/dev/null \
    php-cgi -n -d "sys_temp_dir=$tmp" -b "127.0.0.1:$port" >/dev/null 2>"$err" &
  pid=$!
  sleep 1
  if kill -0 "$pid" 2>/dev/null; then
    kill "$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
    return 0
  fi
  cat "$err" >> "$TMS_REPORT" 2>/dev/null || true
  wait "$pid" 2>/dev/null || true
  return 1
}

tms_preflight() {
  local prefix="${PREFIX:-}" home="${HOME:-}" tmp="$home/.tms-os/preflight-tmp" api free failed=0 detail
  local require_nginx="${TMS_PREFLIGHT_REQUIRE_NGINX:-1}"
  tms_safety_init || return 50
  tms_report "prefix=$prefix home=$home mode=${TMS_INSTALL_MODE:-diagnose}"
  api="$(getprop ro.build.version.sdk 2>/dev/null || true)"
  tms_report "android_api=${api:-unknown} abi=$(getprop ro.product.cpu.abi 2>/dev/null || uname -m)"
  if [ -n "$api" ] && [ "$api" -lt 24 ] 2>/dev/null; then tms_check 'Android API' 10 "API $api < 24" || failed=1; else tms_check 'Android API' 0 "${api:-unknown}"; fi
  if command -v pkg >/dev/null 2>&1; then tms_check 'Termux pkg' 0; else tms_check 'Termux pkg' 20 'không tìm thấy pkg' || true; failed=1; fi
  if [ -n "$prefix" ] && [ -d "$prefix" ]; then tms_check 'PREFIX' 0 "$prefix"; else tms_check 'PREFIX' 20 'PREFIX không hợp lệ' || true; failed=1; fi
  for c in mkdir mktemp awk sed grep realpath; do command -v "$c" >/dev/null 2>&1 || { tms_check "command:$c" 20 'thiếu binary' || true; failed=1; }; done
  free="$(df -Pk "$home" 2>/dev/null | awk 'NR==2{print $4}')"
  if [ -n "$free" ] && [ "$free" -lt 1572864 ] 2>/dev/null; then tms_check 'Dung lượng trống' 20 "${free}KB" || true; failed=1; else tms_check 'Dung lượng trống' 0 "${free:-unknown}KB"; fi
  tms_writable_dir "$tmp" && tms_check 'TMS temp' 0 "$tmp" || { tms_check 'TMS temp' 20 'không ghi được' || true; failed=1; }
  tms_writable_dir "$prefix/var/tmp" && tms_check 'Termux var/tmp' 0 "$prefix/var/tmp" || { tms_check 'Termux var/tmp' 20 'không ghi được' || true; failed=1; }
  if command -v php >/dev/null 2>&1 && tms_probe_php_cli "$tmp"; then tms_check 'PHP CLI' 0; else detail="$(tr '\n' ' ' < "$TMS_STATE_DIR/php-cli.stderr" 2>/dev/null)"; tms_check 'PHP CLI' 30 "$detail" || true; failed=1; fi
  if [ "${TMS_COMPAT_ENGINE:-}" = 'php-http' ]; then
    tms_report 'check=PHP built-in HTTP rc=0 detail=validated by compatibility probe'
    printf '[OK] PHP built-in HTTP — đã được compatibility probe xác nhận\n'
  elif command -v php-fpm >/dev/null 2>&1 && tms_probe_php_fpm "$tmp" 19001; then
    tms_check 'PHP-FPM' 0
  elif command -v php-cgi >/dev/null 2>&1 && tms_probe_php_cgi "$tmp" 19000; then
    tms_check 'PHP-CGI FastCGI' 0
  else
    detail="$(tr '\n' ' ' < "$TMS_STATE_DIR/php-cgi.stderr" 2>/dev/null; tr '\n' ' ' < "$TMS_STATE_DIR/php-fpm.stderr" 2>/dev/null)"
    tms_check 'PHP FastCGI' 30 "$detail" || true
    failed=1
  fi
  if [ "$require_nginx" = 0 ]; then
    tms_report 'check=Nginx config rc=skipped detail=pre-install phase'
  elif command -v nginx >/dev/null 2>&1 && nginx -t >/dev/null 2>"$TMS_STATE_DIR/nginx.stderr"; then
    tms_check 'Nginx config' 0
  else
    detail="$(tr '\n' ' ' < "$TMS_STATE_DIR/nginx.stderr" 2>/dev/null)"
    tms_check 'Nginx config' 40 "$detail" || true
    failed=1
  fi
  tms_report "finished_at=$(date -Iseconds 2>/dev/null || date) failed=$failed report=$TMS_REPORT"
  [ "$failed" -eq 0 ]
}

tms_write_txn() {
  local key
  umask 077
  : > "$TMS_TXN_FILE" || return 1
  for key in TMS_TXN_ID TMS_TXN_MODE TMS_TXN_BACKUP TMS_TXN_STAGING TMS_TXN_TARGET TMS_TXN_NGINX TMS_TXN_RUNTIME TMS_TXN_PHASE TMS_TXN_COMMITTED; do
    printf '%s=%q\n' "$key" "${!key-}" >> "$TMS_TXN_FILE" || return 1
  done
  sync 2>/dev/null || true
}

tms_set_phase() {
  TMS_TXN_PHASE="$1"
  tms_write_txn
  tms_report "phase=$TMS_TXN_PHASE"
}

tms_init_txn() {
  TMS_TXN_ID="${1:-$(date +%s)-$$}"
  TMS_TXN_MODE="${2:-repair}"
  TMS_TXN_BACKUP="$3"
  TMS_TXN_STAGING="$4"
  TMS_TXN_TARGET="$5"
  TMS_TXN_NGINX="${6:-${PREFIX:-}/etc/nginx/nginx.conf}"
  TMS_TXN_RUNTIME="${7:-${HOME:-}/.tms-os}"
  TMS_TXN_PHASE='initialized'
  TMS_TXN_COMMITTED=0
  tms_write_txn
}

tms_clear_txn() { rm -f -- "$TMS_TXN_FILE"; }

tms_backup_item() {
  local src="$1" dst="$2"
  [ -e "$src" ] || return 0
  mkdir -p -- "$dst" || return 1
  cp -a -- "$src" "$dst/"
}

tms_create_backup() {
  local backup="$1" target="$2" nginx_conf="$3" runtime="$4"
  mkdir -p -- "$backup" || return 1
  chmod 700 "$backup"
  tms_backup_item "$target" "$backup" || return 1
  tms_backup_item "$nginx_conf" "$backup" || return 1
  tms_backup_item "$runtime" "$backup" || return 1
  (cd "$backup" && find . -type f ! -name MANIFEST.sha256 -print0 | sort -z | xargs -0 sha256sum > MANIFEST.sha256) || return 1
  sync 2>/dev/null || true
  tms_report "backup=$backup manifest=$backup/MANIFEST.sha256"
}

tms_restore_backup() {
  local backup="$1" target="$2" nginx_conf="$3" runtime="$4"
  [ -f "$backup/MANIFEST.sha256" ] || return 1
  (cd "$backup" && sha256sum -c MANIFEST.sha256 >/dev/null 2>&1) || {
    tms_report "restore_rejected=manifest-mismatch backup=$backup"
    return 1
  }
  case "$target" in "$HOME"/*) ;; *) return 1 ;; esac
  case "$runtime" in "$HOME"/.tms-os) ;; *) return 1 ;; esac
  if [ -d "$backup/tms-os" ]; then rm -rf -- "$target" && cp -a -- "$backup/tms-os" "$target"; fi
  if [ -f "$backup/nginx.conf" ]; then mkdir -p -- "$(dirname -- "$nginx_conf")" && cp -a -- "$backup/nginx.conf" "$nginx_conf"; fi
  if [ -d "$backup/.tms-os" ]; then rm -rf -- "$runtime" && cp -a -- "$backup/.tms-os" "$runtime"; fi
  tms_report "restored_backup=$backup"
}

tms_rollback_active() {
  [ -f "$TMS_TXN_FILE" ] || { echo '[INFO] Không có giao dịch installer cần rollback.'; return 0; }
  # File active.env do chính installer tạo bằng printf %q.
  # shellcheck disable=SC1090
  . "$TMS_TXN_FILE"
  [ "${TMS_TXN_PHASE:-}" = committed ] && { tms_clear_txn; echo '[OK] Giao dịch đã commit, không cần rollback.'; return 0; }
  [ -z "${TMS_TXN_STAGING:-}" ] || rm -rf -- "$TMS_TXN_STAGING"
  if [ -n "${TMS_TXN_BACKUP:-}" ]; then
    tms_restore_backup "$TMS_TXN_BACKUP" "$TMS_TXN_TARGET" "$TMS_TXN_NGINX" "$TMS_TXN_RUNTIME" || return 50
  fi
  tms_clear_txn
}
