#!/data/data/com.termux/files/usr/bin/bash
set -Eeuo pipefail
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"; HOME="${HOME:-/data/data/com.termux/files/home}"
SOURCE_DIR="$(cd "$(dirname "$0")/.." && pwd)"; TARGET="$HOME/tms-os"; NGINX="$PREFIX/etc/nginx/nginx.conf"; SITES="$PREFIX/etc/nginx/sites-enabled"; PHP_CONF_DIR="$PREFIX/etc/php/conf.d"
STAMP="$(date +%Y%m%d_%H%M%S)"; BACKUP="$HOME/.tms-os/backups/$STAMP"; STAGING="$HOME/.tms-os-staging-$STAMP"; QUARANTINE="$HOME/.tms-os/quarantine/$STAMP"
trap 'echo "[LỖI] Dòng $LINENO. Xem sao lưu: $BACKUP"' ERR
printf '\n============================================\n Welcome to TMS OS by THCGaming\n============================================\n'
if command -v pkg >/dev/null 2>&1; then
  echo '[1/7] Cập nhật kho và tự động cài mọi thành phần...'
  pkg update -y
  pkg install -y php nginx mariadb sqlite curl zip unzip openssh procps coreutils findutils grep sed gawk which openssl

  # V14.0.3: chọn engine database — SQLite (khuyến nghị: nhẹ, không daemon, không thể cài hỏng)
  # hoặc MariaDB (đầy đủ tính năng, cần daemon chạy nền).
  DB_MODE="mariadb"
  if [ -z "${TMS_FORCE_DB_MODE:-}" ]; then
    printf 'Chọn engine database: [S]QLite (khuyến nghị cho điện thoại cũ, nhẹ và ổn định) hay [M]ariaDB (đầy đủ tính năng)? [S/m]: '
    read -r DB_CHOICE
    case "${DB_CHOICE:-S}" in
      m|M|MariaDB|MARIADB|maria*) DB_MODE="mariadb" ;;
      *) DB_MODE="sqlite" ;;
    esac
  fi
  mkdir -p "$HOME/.tms-os" "$HOME/logs/services"
  printf '%s' "$DB_MODE" > "$HOME/.tms-os/db-mode"
  chmod 600 "$HOME/.tms-os/db-mode"
  if [ "$DB_MODE" = "sqlite" ]; then
    echo '[OK] Đã chọn SQLite — không cần khởi động database daemon.'
  fi
else echo 'Bộ cài này yêu cầu Termux có lệnh pkg.'; exit 1; fi
for c in php php-cgi nginx curl mariadb mariadb-dump zip unzip sshd; do command -v "$c" >/dev/null || { echo "Thiếu lệnh sau cài: $c"; exit 1; }; done
for part in app config public routes storage scripts; do [ -d "$SOURCE_DIR/$part" ] || { echo "Thiếu thư mục: $part"; exit 1; }; done
printf '[2/7] Chuẩn bị dữ liệu và sao lưu...\n'
mkdir -p "$HOME/.tms-os" "$BACKUP" "$STAGING/storage/logs" "$STAGING/storage/sessions" "$STAGING/storage/cache" "$SITES" "$PHP_CONF_DIR" "$HOME/logs/nginx" "$HOME/logs/services" "$HOME/backups" "$QUARANTINE" "$HOME/websites/default/public"
date +%s > "$HOME/.tms-os/runtime_started_at"; [ -d "$TARGET" ] && cp -a "$TARGET" "$BACKUP/tms-os" || true; [ -f "$NGINX" ] && cp "$NGINX" "$BACKUP/nginx.conf" || true
cp -a "$SOURCE_DIR"/{app,config,public,routes,scripts} "$STAGING/"; cp -a "$SOURCE_DIR/storage/." "$STAGING/storage/"; chmod -R 700 "$STAGING/storage"
find "$STAGING" -type f -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done
printf '[3/7] Cấu hình PHP Engine tương thích tự động...\n'
cat > "$PHP_CONF_DIR/99-tms-os.ini" <<INI
memory_limit=256M
upload_max_filesize=512M
post_max_size=520M
max_execution_time=300
max_input_time=300
display_errors=Off
log_errors=On
session.save_path="$TARGET/storage/sessions"
INI
cat > "$NGINX" <<NGINX
worker_processes auto;
events { worker_connections 1024; }
http { include mime.types; default_type application/octet-stream; sendfile on; keepalive_timeout 65; client_max_body_size 500M; server_tokens off; include $SITES/*.conf;
server { listen 127.0.0.1:8888; server_name localhost; root $TARGET/public; index index.php;
access_log $HOME/logs/nginx/tms-access.log; error_log $HOME/logs/nginx/tms-error.log;
location / { try_files \$uri \$uri/ /index.php?\$query_string; }
location ~ \.php$ { try_files \$uri =404; include fastcgi_params; fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name; fastcgi_pass 127.0.0.1:9000; }
location ~ /\. { deny all; } } }
NGINX
cat > "$SITES/default.conf" <<NGINX
server { listen 0.0.0.0:8080; server_name _; root $HOME/websites/default/public; index index.php index.html;
access_log $HOME/logs/nginx/default-access.log; error_log $HOME/logs/nginx/default-error.log;
location / { try_files \$uri \$uri/ /index.php?\$query_string; }
location ~ \.php$ { try_files \$uri =404; include fastcgi_params; fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name; fastcgi_pass 127.0.0.1:9000; }
location ~ /\. { deny all; } }
NGINX
[ -f "$HOME/websites/default/public/index.php" ] || printf '<?php echo "<h1>TMS OS hoạt động thành công</h1><p>PHP ".PHP_VERSION."</p>";\n' > "$HOME/websites/default/public/index.php"
printf '[4/7] Khởi tạo database (%s)...\n' "$DB_MODE"
mkdir -p "$HOME/logs/services"

if [ "$DB_MODE" = "sqlite" ]; then
  # V14.0.3: SQLite — không daemon, không socket, không thể cài hỏng.
  mkdir -p "$HOME/.tms-os/data/db" "$HOME/backups/database"
  chmod 700 "$HOME/.tms-os/data" "$HOME/.tms-os/data/db"
  if ! command -v sqlite3 >/dev/null 2>&1 || ! sqlite3 "$HOME/.tms-os/data/test.db" 'SELECT 1' >/dev/null 2>&1; then
    echo 'Không thể xác thực SQLite.' >&2
    exit 1
  fi
  rm -f "$HOME/.tms-os/data/test.db"
  echo '[OK] SQLite đã sẵn sàng.'
else
  # === MariaDB: clear sạch toàn bộ trạng thái cũ trước khi cài ===
  # V14.0.3: luôn dọn sạch tiến trình + datadir cũ — đảm bảo khởi tạo từ zero.
  echo '[Thông báo] Sẽ xóa sạch kho dữ liệu MariaDB cũ (nếu có) để cài mới hoàn toàn...'
  pkill -9 -f mariadbd 2>/dev/null || true
  sleep 2
  rm -rf "$PREFIX/var/lib/mysql" "$PREFIX/var/run/mysqld" "$PREFIX/tmp/mysql.sock" "/tmp/mysql.sock"
  rm -f "$HOME/.tms-os/mariadb-client.cnf" "$HOME/logs/services/mariadb.log" "$HOME/logs/services/mariadb-init.log"
  mkdir -p "$PREFIX/var/lib/mysql" "$PREFIX/var/run/mysqld" "$PREFIX/tmp"
  rm -f "$HOME/logs/services/mariadb-init.log"
  if ! mariadb-install-db --datadir="$PREFIX/var/lib/mysql" >"$HOME/logs/services/mariadb-init.log" 2>&1; then
    echo 'Không thể khởi tạo kho dữ liệu MariaDB. Xem nhật ký:' "$HOME/logs/services/mariadb-init.log" >&2
    exit 1
  fi
  echo '[OK] Đã khởi tạo kho dữ liệu MariaDB mới hoàn toàn.'

  # Khởi động — lần 1: user hiện tại (--user=) để tránh lỗi quyền pid file.
  nohup mariadbd-safe --datadir="$PREFIX/var/lib/mysql" --user= >"$HOME/logs/services/mariadb.log" 2>&1 &
  disown
  for _ in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22 23 24 25 26 27 28 29 30; do
    if mariadb-admin ping --silent >/dev/null 2>&1; then break; fi
    sleep 1
  done
  if ! mariadb-admin ping --silent >/dev/null 2>&1; then
    echo 'MariaDB vẫn không khởi động được. Xem nhật ký:' "$HOME/logs/services/mariadb.log" >&2
    tail -5 "$HOME/logs/services/mariadb.log" >&2
    exit 1
  fi

  # Tự phát hiện socket đang dùng.
  SOCKET="$(mariadb -e 'SELECT @@socket' -N 2>/dev/null | head -1 || true)"
  if [ -z "$SOCKET" ]; then
    for CAND in "$PREFIX/tmp/mysql.sock" "$PREFIX/var/run/mysqld/mysqld.sock" "/tmp/mysql.sock" "$PREFIX/var/lib/mysql/mysql.sock"; do
      [ -S "$CAND" ] && SOCKET="$CAND" && break
    done
  fi
  if [ -z "$SOCKET" ]; then
    echo 'MariaDB ping được nhưng không tìm thấy socket. Xem nhật ký:' "$HOME/logs/services/mariadb.log" >&2
    exit 1
  fi

  DB_CLIENT="$HOME/.tms-os/mariadb-client.cnf"
  cat > "$DB_CLIENT" <<CNF
[client]
user=root
host=localhost
protocol=socket
socket=$SOCKET
CNF
  chmod 600 "$DB_CLIENT"

  # Kết nối không mật khẩu trước (data mới luôn không có mật khẩu root).
  if ! mariadb --defaults-extra-file="$DB_CLIENT" -e 'SELECT 1' >/dev/null 2>&1; then
    # Fallback: kết nối qua TCP 127.0.0.1 nếu socket thất bại.
    cat > "$DB_CLIENT" <<CNF
[client]
user=root
host=127.0.0.1
protocol=tcp
CNF
    if ! mariadb --defaults-extra-file="$DB_CLIENT" -e 'SELECT 1' >/dev/null 2>&1; then
      echo 'Không thể xác thực tài khoản quản trị MariaDB.' >&2
      echo 'Nhật ký:' "$HOME/logs/services/mariadb.log" >&2
      tail -5 "$HOME/logs/services/mariadb.log" >&2
      exit 1
    fi
  fi
fi
printf '[5/7] Thiết lập tài khoản quản trị...\n'
# Migrate: nếu bản cài cũ dùng đường dẫn .redmi-mini-vps, chuyển sang .tms-os/config.
mkdir -p "$HOME/.tms-os/config"; chmod 700 "$HOME/.tms-os/config"
if [ -f "$HOME/.redmi-mini-vps/config/panel-secret.php" ] && [ ! -f "$HOME/.tms-os/config/panel-secret.php" ]; then
  cp "$HOME/.redmi-mini-vps/config/panel-secret.php" "$HOME/.tms-os/config/panel-secret.php"
  chmod 600 "$HOME/.tms-os/config/panel-secret.php"
  echo '[OK] Đã chuyển tài khoản quản trị sang thư mục cấu hình mới (.tms-os).'
fi
SECRET="$HOME/.tms-os/config/panel-secret.php"
CREATE_ADMIN=1
if [ -f "$SECRET" ]; then
  if [ -z "${TMS_ADMIN_USER:-}" ]; then
    printf 'Đã phát hiện tài khoản quản trị hiện có. Giữ nguyên tài khoản này? [Y/n]: '
    read -r KEEP_ADMIN
    case "${KEEP_ADMIN:-Y}" in
      n|N|no|NO) CREATE_ADMIN=1 ;;
      *) CREATE_ADMIN=0 ;;
    esac
  fi
fi

if [ "$CREATE_ADMIN" -eq 1 ]; then
  if [ -z "${TMS_ADMIN_USER:-}" ]; then
  _ATTEMPTS=0
  while :; do
    printf 'Nhập tên tài khoản quản trị (3-32 ký tự, chữ/số/._-): '
    read -r ADMIN_USER || ADMIN_USER=""
    ADMIN_USER="${ADMIN_USER%$'\r'}"
    if printf '%s' "$ADMIN_USER" | grep -Eq '^[A-Za-z0-9._-]{3,32}$'; then break; fi
    echo 'Tên tài khoản không hợp lệ. Ví dụ: admin, tms_admin, thc.gaming'
    _ATTEMPTS=$((_ATTEMPTS+1))
    if [ "$_ATTEMPTS" -ge 100 ]; then echo '[LỖI] Nhập tên quá nhiều lần. Hãy thử lại với tên đơn giản (admin).' >&2; exit 1; fi
  done
  else
    ADMIN_USER="$TMS_ADMIN_USER"
  fi

  if [ -z "${TMS_ADMIN_PASS:-}" ]; then
  while :; do
    printf 'Nhập mật khẩu quản trị (tối thiểu 8 ký tự): '
    IFS= read -r -s ADMIN_PASS; printf '\n'
    ADMIN_PASS="${ADMIN_PASS%$'\r'}"
    if [ "${#ADMIN_PASS}" -lt 8 ]; then echo 'Mật khẩu quá ngắn.'; continue; fi
    printf 'Nhập lại mật khẩu: '
    IFS= read -r -s ADMIN_PASS_CONFIRM; printf '\n'
    ADMIN_PASS_CONFIRM="${ADMIN_PASS_CONFIRM%$'\r'}"
    if [ "$ADMIN_PASS" != "$ADMIN_PASS_CONFIRM" ]; then echo 'Hai mật khẩu không khớp.'; continue; fi
    break
  done
  else
    ADMIN_PASS="$TMS_ADMIN_PASS"
  fi

  # Hash mật khẩu an toàn: truyền qua file tạm để mật khẩu chứa ký tự đặc biệt ($ ! " ') không bị hỏng.
  _PW_TMP="$(mktemp)"
  printf '%s' "$ADMIN_PASS" > "$_PW_TMP"; chmod 600 "$_PW_TMP"
  HASH="$(php -r 'echo password_hash((string)file_get_contents($argv[1]), PASSWORD_DEFAULT);' "$_PW_TMP")" || HASH=""
  rm -f "$_PW_TMP"
  if [ -z "$HASH" ] || [ "${#HASH}" -lt 20 ]; then
    echo '[LỖI] Không thể tạo hash mật khẩu (cần PHP >= 7.0 với sodium/bcrypt). Hãy chạy lại bộ cài.' >&2
    exit 1
  fi
  TMS_SECRET_FILE="$SECRET" php -r '
    $file=(string)getenv("TMS_SECRET_FILE");
    $user=(string)$argv[1];
    $hash=(string)$argv[2];
    $data="<?php\nreturn [\"username\"=>".var_export($user,true).",\"password_hash\"=>".var_export($hash,true)."];\n";
    if (file_put_contents($file,$data)===false) { fwrite(STDERR,"Không thể ghi tệp tài khoản.\n"); exit(1); }
    chmod($file,0600);
  ' "$ADMIN_USER" "$HASH"
  unset ADMIN_PASS ADMIN_PASS_CONFIRM HASH
  rm -f "$HOME/.tms-os/first-login.txt"
  echo "Đã tạo tài khoản quản trị: $ADMIN_USER"
else
  echo 'Giữ nguyên tài khoản quản trị hiện tại.'
fi
printf '[6/7] Cài source và khởi động dịch vụ...\n'
# V13.0.1: dọn toàn bộ khóa, hàng đợi và trạng thái pending cũ trước khi thay core.
rm -f "$HOME/.tms-os/php-engine.lock" "$HOME/.tms-os/service-worker.lock" 2>/dev/null || true
rm -rf "$HOME/.tms-os/service-core/worker.lock" "$HOME/.tms-os/service-core/queue" "$HOME/.tms-os/service-core/results" 2>/dev/null || true
mkdir -p "$HOME/.tms-os/service-core/queue" "$HOME/.tms-os/service-core/results"
if [ -f "$HOME/.tms-os/service-manager.json" ]; then
  php -r '$f=getenv("HOME")."/.tms-os/service-manager.json";$d=json_decode((string)@file_get_contents($f),true);if(!is_array($d))$d=[];$d["pending"]=[];file_put_contents($f,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));' || true
fi
nginx -t; rm -rf "$TARGET.previous"; [ -d "$TARGET" ] && mv "$TARGET" "$TARGET.previous" || true; mv "$STAGING" "$TARGET"
PHP_ENGINE_OK=0
for ATTEMPT in 1 2 3; do
  echo "Khởi động PHP Engine (lần $ATTEMPT/3)..."
  if bash "$TARGET/scripts/tms-php-engine.sh" restart; then
    PHP_ENGINE_OK=1
    break
  fi
  echo 'PHP Engine chưa sẵn sàng, chờ 2 giây rồi thử lại...'
  sleep 2
done
if [ "$PHP_ENGINE_OK" -ne 1 ]; then
  echo 'Không thể khởi động PHP Engine sau 3 lần thử.' >&2
  echo "Nhật ký: $HOME/logs/services/php-engine.log" >&2
  exit 1
fi
nginx -s reload 2>/dev/null || nginx
DBMODE="$(cat "$HOME/.tms-os/db-mode" 2>/dev/null || echo mariadb)"
if [ "$DBMODE" = "mariadb" ]; then
pgrep -f mariadbd >/dev/null 2>&1 || mariadbd-safe --datadir="$PREFIX/var/lib/mysql" >"$HOME/logs/services/mariadb.log" 2>&1 &
fi
pgrep -x sshd >/dev/null 2>&1 || sshd
sleep 3; curl -fsS http://127.0.0.1:8888/login >/dev/null; rm -rf "$TARGET.previous"
printf '[7/7] Hoàn tất.\n'
MODE="$(bash "$TARGET/scripts/tms-php-engine.sh" status)"
echo '============================================'; echo '[OK] TMS OS đã cài đặt thành công.'; echo 'Panel: http://127.0.0.1:8888'; echo 'Website: http://127.0.0.1:8080'; echo "PHP Engine: $MODE"; echo 'Đăng nhập bằng tài khoản quản trị bạn vừa thiết lập.'; echo 'Khởi động lần sau: bash ~/tms-os/scripts/start-tms.sh'; echo '============================================'
