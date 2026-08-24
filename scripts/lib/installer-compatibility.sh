#!/data/data/com.termux/files/usr/bin/bash
# TMS OS Universal Compatibility profile.
# Chỉ phát hiện/kiểm tra; không tự ý cài, xóa hoặc sửa dữ liệu.
set -u

TMS_COMPAT_STATE_DIR="${TMS_COMPAT_STATE_DIR:-${TMS_STATE_DIR:-${HOME:-/data/data/com.termux/files/home}/.tms-os-installer-state}}"
TMS_COMPAT_REPORT="${TMS_COMPAT_REPORT:-$TMS_COMPAT_STATE_DIR/compatibility.env}"
TMS_COMPAT_HUMAN_REPORT="${TMS_COMPAT_HUMAN_REPORT:-$TMS_COMPAT_STATE_DIR/compatibility-report.txt}"
TMS_COMPAT_TMP="${TMS_COMPAT_TMP:-${HOME:-/data/data/com.termux/files/home}/.tms-os/compatibility-tmp}"

compat_log() {
  mkdir -p -- "$TMS_COMPAT_STATE_DIR" 2>/dev/null || true
  printf '[%s] %s\n' "$(date '+%Y-%m-%dT%H:%M:%S%z')" "$*" >> "$TMS_COMPAT_HUMAN_REPORT" 2>/dev/null || true
}

compat_set() {
  local key="$1" value="${2:-}"
  printf '%s=%q\n' "$key" "$value" >> "$TMS_COMPAT_REPORT"
}

compat_command_exists() { command -v "$1" >/dev/null 2>&1; }

compat_getprop() {
  getprop "$1" 2>/dev/null || true
}

compat_normalize_bool() {
  case "${1:-}" in
    1|true|yes|y|on) printf '1' ;;
    *) printf '0' ;;
  esac
}

compat_detect_base() {
  local sdk abi abilist ram_kb disk_kb termux_version package_source
  sdk="$(compat_getprop ro.build.version.sdk)"
  abi="$(compat_getprop ro.product.cpu.abi)"
  abilist="$(compat_getprop ro.product.cpu.abilist)"
  ram_kb="$(free 2>/dev/null | awk '/Mem:/{print $2; exit}')"
  disk_kb="$(df -Pk "${HOME:-.}" 2>/dev/null | awk 'NR==2{print $4; exit}')"
  if compat_command_exists termux-info; then
    termux_version="$(termux-info 2>/dev/null | sed -n 's/^Termux version: //p' | head -n 1 || true)"
  else
    termux_version='unknown'
  fi
  package_source="$(grep -RhoE 'https?://[^ ]+' "${PREFIX:-}/etc/apt/sources.list" "${PREFIX:-}/etc/apt/sources.list.d" 2>/dev/null | head -n 1 || true)"

  : > "$TMS_COMPAT_REPORT"
  : > "$TMS_COMPAT_HUMAN_REPORT"
  compat_set PROFILE_VERSION 1
  compat_set ANDROID_API "${sdk:-unknown}"
  compat_set ABI "${abi:-unknown}"
  compat_set ABI_LIST "${abilist:-unknown}"
  compat_set TERMUX_VERSION "${termux_version:-unknown}"
  compat_set PREFIX "${PREFIX:-}"
  compat_set HOME "${HOME:-}"
  compat_set PACKAGE_SOURCE "${package_source:-unknown}"
  compat_set RAM_KB "${ram_kb:-unknown}"
  compat_set DISK_FREE_KB "${disk_kb:-unknown}"
  compat_set HAS_STORAGE_PERMISSION "$( [ -d "${HOME:-}/storage/shared" ] && echo 1 || echo 0 )"
  compat_set HAS_LD_PRELOAD "$( [ -n "${LD_PRELOAD:-}" ] && echo 1 || echo 0 )"

  compat_log "profile=base android_api=${sdk:-unknown} abi=${abi:-unknown} termux=${termux_version:-unknown}"
}

compat_check_base() {
  local failed=0 sdk prefix home free
  sdk="$(grep '^ANDROID_API=' "$TMS_COMPAT_REPORT" | cut -d= -f2- | sed 's/^\x27//;s/\x27$//')"
  prefix="${PREFIX:-}"
  home="${HOME:-}"
  printf '%s\n' '=== TMS COMPATIBILITY PREFLIGHT ==='
  printf 'Profile report: %s\n' "$TMS_COMPAT_HUMAN_REPORT"

  if [ -n "$sdk" ] && [ "$sdk" != unknown ] && [ "$sdk" -lt 24 ] 2>/dev/null; then
    printf '[BLOCK] Android API %s < 24\n' "$sdk"; failed=1
  else printf '[OK] Android API %s\n' "${sdk:-unknown}"; fi
  if compat_command_exists pkg; then printf '[OK] Termux package manager\n'; else printf '[BLOCK] pkg không tồn tại\n'; failed=1; fi
  if [ -n "$prefix" ] && [ -d "$prefix" ]; then printf '[OK] PREFIX %s\n' "$prefix"; else printf '[BLOCK] PREFIX không hợp lệ\n'; failed=1; fi
  if [ -n "$home" ] && [ -d "$home" ]; then printf '[OK] HOME %s\n' "$home"; else printf '[BLOCK] HOME không hợp lệ\n'; failed=1; fi
  if [ -n "$home" ] && mkdir -p -- "$TMS_COMPAT_TMP" 2>/dev/null && [ -w "$TMS_COMPAT_TMP" ]; then
    local probe
    probe="$(mktemp "$TMS_COMPAT_TMP/probe.XXXXXX" 2>/dev/null)" || probe=''
    if [ -n "$probe" ]; then rm -f -- "$probe"; printf '[OK] Temp runtime %s\n' "$TMS_COMPAT_TMP"; else printf '[BLOCK] Không tạo được file temp\n'; failed=1; fi
  else printf '[BLOCK] Không ghi được temp runtime\n'; failed=1; fi
  free="$(df -Pk "$home" 2>/dev/null | awk 'NR==2{print $4; exit}')"
  if [ -n "$free" ] && [ "$free" -lt 1572864 ] 2>/dev/null; then printf '[WARN] Dung lượng thấp: %s KB\n' "$free"; else printf '[OK] Dung lượng trống: %s KB\n' "${free:-unknown}"; fi
  return "$failed"
}

compat_pkg_installed() {
  local package="$1"
  if compat_command_exists dpkg; then dpkg -s "$package" >/dev/null 2>&1; return; fi
  case "$package" in
    php) compat_command_exists php ;;
    nginx) compat_command_exists nginx ;;
    sqlite) compat_command_exists sqlite3 ;;
    *) compat_command_exists "$package" ;;
  esac
}

compat_dependency_plan() {
  local db_mode="${1:-sqlite}" package missing=0 status
  local packages='php php-gd nginx curl zip unzip openssh procps coreutils findutils grep sed gawk which openssl cronie'
  [ "$db_mode" = mariadb ] && packages="$packages mariadb"
  printf '\n=== DEPENDENCY PLAN (%s) ===\n' "$db_mode"
  : > "$TMS_COMPAT_STATE_DIR/missing-packages"
  for package in $packages; do
    if compat_pkg_installed "$package"; then status='installed'; else status='missing'; printf '%s\n' "$package" >> "$TMS_COMPAT_STATE_DIR/missing-packages"; missing=1; fi
    printf '%-16s %s\n' "$package" "$status"
    compat_log "package=$package status=$status"
  done
  compat_set DB_MODE "$db_mode"
  compat_set MISSING_PACKAGES "$(tr '\n' ' ' < "$TMS_COMPAT_STATE_DIR/missing-packages" 2>/dev/null)"
  return 0
}

compat_probe_php_cli() {
  local tmp="${1:-$TMS_COMPAT_TMP}"
  compat_command_exists php || return 1
  env -u LD_PRELOAD TMPDIR="$tmp" TMP="$tmp" TEMP="$tmp" PHP_INI_SCAN_DIR=/dev/null \
    php -n -d "sys_temp_dir=$tmp" -r 'exit(0);' >/dev/null 2>"$TMS_COMPAT_STATE_DIR/php-cli.stderr"
}

compat_probe_php_server() {
  local tmp="${1:-$TMS_COMPAT_TMP}" port="${2:-19173}" root pid
  root="$tmp/http-root"
  mkdir -p -- "$root" || return 1
  printf '%s\n' '<?php echo "TMS-COMPAT-OK";' > "$root/index.php"
  compat_command_exists php || return 1
  env -u LD_PRELOAD TMPDIR="$tmp" TMP="$tmp" TEMP="$tmp" PHP_INI_SCAN_DIR=/dev/null \
    php -n -d "sys_temp_dir=$tmp" -S "127.0.0.1:$port" -t "$root" \
    >"$TMS_COMPAT_STATE_DIR/php-http.stdout" 2>"$TMS_COMPAT_STATE_DIR/php-http.stderr" &
  pid=$!
  sleep 1
  if ! kill -0 "$pid" 2>/dev/null; then wait "$pid" 2>/dev/null || true; return 1; fi
  if compat_command_exists curl && curl -fsS --max-time 2 "http://127.0.0.1:$port/index.php" | grep -qx 'TMS-COMPAT-OK'; then
    kill "$pid" 2>/dev/null || true; wait "$pid" 2>/dev/null || true; return 0
  fi
  kill "$pid" 2>/dev/null || true; wait "$pid" 2>/dev/null || true
  return 1
}

compat_probe_fastcgi() {
  local tmp="${1:-$TMS_COMPAT_TMP}" port="${2:-19174}" pid
  mkdir -p -- "$TMS_COMPAT_STATE_DIR" "$tmp" || return 1
  if compat_command_exists php-fpm; then
    cat > "$TMS_COMPAT_STATE_DIR/php-fpm-preflight.conf" <<CFG
[global]
daemonize = no
error_log = $TMS_COMPAT_STATE_DIR/php-fpm.stderr
[www]
listen = 127.0.0.1:$port
pm = static
pm.max_children = 1
clear_env = no
CFG
    : > "$TMS_COMPAT_STATE_DIR/php-fpm.stderr"
    env -u LD_PRELOAD TMPDIR="$tmp" TMP="$tmp" TEMP="$tmp" PHP_INI_SCAN_DIR=/dev/null \
      php-fpm -n -F -y "$TMS_COMPAT_STATE_DIR/php-fpm-preflight.conf" \
      >"$TMS_COMPAT_STATE_DIR/php-fpm.stdout" 2>>"$TMS_COMPAT_STATE_DIR/php-fpm.stderr" &
    pid=$!; sleep 1
    if kill -0 "$pid" 2>/dev/null; then kill "$pid" 2>/dev/null || true; wait "$pid" 2>/dev/null || true; return 0; fi
    wait "$pid" 2>/dev/null || true
  fi
  if compat_command_exists php-cgi; then
    env -u LD_PRELOAD TMPDIR="$tmp" TMP="$tmp" TEMP="$tmp" PHP_INI_SCAN_DIR=/dev/null \
      php-cgi -n -d "sys_temp_dir=$tmp" -b "127.0.0.1:$port" \
      >"$TMS_COMPAT_STATE_DIR/php-cgi.stdout" 2>"$TMS_COMPAT_STATE_DIR/php-cgi.stderr" &
    pid=$!; sleep 1
    if kill -0 "$pid" 2>/dev/null; then kill "$pid" 2>/dev/null || true; wait "$pid" 2>/dev/null || true; return 0; fi
    wait "$pid" 2>/dev/null || true
  fi
  return 1
}

compat_select_engine() {
  local selected='none'
  if compat_probe_fastcgi "$TMS_COMPAT_TMP"; then selected='fastcgi';
  elif compat_probe_php_server "$TMS_COMPAT_TMP"; then selected='php-http'; fi
  compat_set ENGINE "$selected"
  if [ "$selected" = none ]; then
    printf '[BLOCK] Không có PHP server engine hoạt động.\n'
    printf '        CLI có thể hoạt động nhưng PHP-CGI/FPM/built-in server đều thất bại.\n'
    compat_log 'engine=none reason=no-working-php-server-mode'
    return 30
  fi
  printf '[OK] PHP engine: %s\n' "$selected"
  compat_log "engine=$selected"
  return 0
}

compat_full_preflight() {
  local db_mode="${1:-sqlite}" failed=0 rc=0 base_rc=0 sdk
  mkdir -p -- "$TMS_COMPAT_STATE_DIR" "$TMS_COMPAT_TMP" || return 50
  compat_detect_base
  if ! compat_check_base; then
    failed=1
    sdk="$(compat_getprop ro.build.version.sdk)"
    if [ -n "$sdk" ] && [ "$sdk" -lt 24 ] 2>/dev/null; then base_rc=10; else base_rc=20; fi
  fi
  compat_dependency_plan "$db_mode"
  if ! compat_probe_php_cli "$TMS_COMPAT_TMP"; then
    printf '[BLOCK] PHP CLI không chạy\n'; failed=1; [ "$base_rc" -eq 0 ] && base_rc=30
  else printf '[OK] PHP CLI\n'; fi
  if ! compat_select_engine; then failed=1; [ "$base_rc" -eq 0 ] && base_rc=30; fi
  if [ "${TMS_PREFLIGHT_REQUIRE_NGINX:-0}" = 1 ]; then
    if compat_command_exists nginx && nginx -t >/dev/null 2>"$TMS_COMPAT_STATE_DIR/nginx.stderr"; then printf '[OK] Nginx config\n'; else printf '[BLOCK] Nginx config\n'; failed=1; [ "$base_rc" -eq 0 ] && base_rc=40; fi
  fi
  rc="$base_rc"
  [ "$failed" -eq 0 ] && rc=0
  compat_set PREFLIGHT_RESULT "$([ "$rc" -eq 0 ] && echo PASS || echo BLOCKED)"
  compat_set PREFLIGHT_RC "$rc"
  printf '\nKết quả compatibility: %s (mã %s)\n' "$([ "$rc" -eq 0 ] && echo PASS || echo BLOCKED)" "$rc"
  return "$rc"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
  compat_full_preflight "${1:-sqlite}"
fi
