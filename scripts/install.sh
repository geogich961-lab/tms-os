#!/data/data/com.termux/files/usr/bin/bash
# TMS OS V14.1.0 — Bộ cài chính (chạy trực tiếp, không qua pipe khi cần hỏi)
# Luồng:
#   1. Phát hiện cài cũ → hỏi Sửa chữa (giữ dữ liệu) / Cài mới (xóa sạch)
#   2. Chọn engine database (SQLite/MariaDB)
#   3. BẮT BUỘC tự nhập tài khoản + mật khẩu database (SQLite)
#   4. BẮT BUỘC tự nhập tài khoản + mật khẩu quản trị panel
#   5. Cấu hình PHP/Nginx, khởi tạo DB, khởi động dịch vụ
set -Eeuo pipefail
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"; HOME="${HOME:-/data/data/com.termux/files/home}"
SOURCE_DIR="$(cd "$(dirname "$0")/.." && pwd)"; TARGET="$HOME/tms-os"; NGINX="$PREFIX/etc/nginx/nginx.conf"; SITES="$PREFIX/etc/nginx/sites-enabled"; PHP_CONF_DIR="$PREFIX/etc/php/conf.d"
STAMP="$(date +%Y%m%d_%H%M%S)"; BACKUP="$HOME/.tms-os/backups/$STAMP"; STAGING="$HOME/.tms-os-staging-$STAMP"; QUARANTINE="$HOME/.tms-os/quarantine/$STAMP"
trap 'echo "[LỖI] Dòng $LINENO. Xem sao lưu: $BACKUP"' ERR
printf '\n============================================\n Welcome to TMS OS by THCGaming\n============================================\n'

if [ -p /dev/stdin ]; then
  echo '[LỖI] Bộ cài này cần tương tác bàn phím — không chạy được qua pipe (curl | bash).'
  echo '        Hãy tải và chạy trực tiếp:'
  echo "          bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh)"
  echo '        hoặc tải ZIP từ GitHub Releases rồi chạy:'
  echo '          bash ~/tms-os/scripts/install.sh'
  exit 2
fi

if command -v pkg >/dev/null 2>&1; then
  # ========== V14.1.3: Pre-check thiết bị thật — RAM + dung lượng + quyền bộ nhớ ==========
  # Điện thoại đời cũ (RAM < 2GB hoặc disk < 1.5GB) chạy MariaDB rất dễ treo → đề xuất SQLite.
  if [ "${INSTALL_MODE:-}" != "repair" ] 2>/dev/null || [ "${TMS_DB_MODE_AUTO:-}" != "1" ]; then
    TMS_RAM_KB="$(free 2>/dev/null | awk '/Mem:/{print $2}')"
    TMS_DISK_KB="$(df "$HOME" 2>/dev/null | awk 'NR==2{print $4}')"
    if [ -n "$TMS_RAM_KB" ] && [ "$TMS_RAM_KB" -lt 1500000 ] 2>/dev/null; then
      echo '[Gợi ý] RAM máy khá thấp (<1.5 GB) — khuyến nghị dùng SQLite thay cho MariaDB.'
      echo '         SQLite nhẹ hơn, không cần daemon, phù hợp với VPS mini.'
    fi
    if [ -n "$TMS_DISK_KB" ] && [ "$TMS_DISK_KB" -lt 1572864 ] 2>/dev/null; then
      echo '[Gợi ý] Dung lượng trống thấp (<1.5 GB) — khuyến nghị dùng SQLite.'
      echo '         MariaDB cần khoảng 500 MB–1 GB cho datadir và gói cài.'
    fi
  fi
  # Quyền bộ nhớ Termux: nếu chưa có ~/storage (termux-setup-storage chưa chạy) vẫn cài được
  # nhưng nhắc người dùng nếu họ định dùng thư mục ngoài (Downloads/shared) sau này.
  if [ ! -d "$HOME/storage/shared" ]; then
    echo '[Thông báo] Quyền bộ nhớ Termux chưa được cấp — lệnh termux-setup-storage sẽ chạy nếu cần sau này.'
    termux-setup-storage >/dev/null 2>&1 || true
  fi
  echo '[1/7] Cập nhật kho và tự động cài mọi thành phần...'
  # V14.1.1: mirror Termux thỉnh thoảng lỗi 404 (index chưa cập nhật).
  # Tự retry: apt-get update → apt-get install --fix-missing → đổi mirror nếu vẫn lỗi.
  TMS_PKGS="php nginx mariadb sqlite curl zip unzip openssh procps coreutils findutils grep sed gawk which openssl"
  tms_pkg_ok=0
  for TMS_ATTEMPT in 1 2 3; do
    if pkg install -y $TMS_PKGS >/dev/null 2>&1; then tms_pkg_ok=1; break; fi
    echo "[Khắc phục] Tải gói bị lỗi — cập nhật lại kho gói và thử với --fix-missing (lần $TMS_ATTEMPT/3)..."
    apt-get update >/dev/null 2>&1 || pkg update -y >/dev/null 2>&1 || true
    sleep 2
    if apt-get install -y --fix-missing $TMS_PKGS >/dev/null 2>&1; then tms_pkg_ok=1; break; fi
    # Lần cuối thử đổi mirror mặc định (khỏi 3san.dev sang mirrors termux chính thức)
    if [ "$TMS_ATTEMPT" -eq 2 ]; then
      echo "[Khắc phục] Vẫn lỗi — chuyển sang mirror dự phòng..."
      termux-change-repo --choice-all --repository "Mirrors by Grimler" >/dev/null 2>&1 || termux-change-repo --choice-all >/dev/null 2>&1 || true
      apt-get update >/dev/null 2>&1 || pkg update -y >/dev/null 2>&1 || true
      sleep 2
    fi
  done
  if [ "$tms_pkg_ok" -ne 1 ]; then
    echo '[LỖI] Không cài được một số gói sau 3 lần thử (có thể do mạng hoặc mirror).'
    echo '        Thử lại sau vài phút, hoặc chạy thủ công:'
    echo '          pkg install php nginx mariadb sqlite curl zip unzip openssh procps coreutils findutils grep sed gawk which openssl'
    exit 1
  fi
  # V14.1.3: kiểm tra từng binary thiết yếu — điện thoại thật đôi khi pkg báo OK nhưng thiếu file
  TMS_MISSING=""
  for c in php nginx curl mariadb mariadb-dump zip unzip sshd; do command -v "$c" >/dev/null || TMS_MISSING="$TMS_MISSING $c"; done
  if [ -n "$TMS_MISSING" ]; then
    echo "[Khắc phục] Thiếu: $TMS_MISSING — thử cài lại riêng từng gói..."
    for c in $TMS_MISSING; do
      pkg install -y "$c" >/dev/null 2>&1 || apt-get install -y --fix-missing "$c" >/dev/null 2>&1 || true
    done
    for c in $TMS_MISSING; do command -v "$c" >/dev/null || { echo "[LỖI] Vẫn thiếu: $c — thoát."; exit 1; }; done
  fi
  # PHP ext zip: ZIP handler yêu cầu ext zip — bắt buộc cài php-zip nếu thiếu
  if ! php -m 2>/dev/null | grep -q '^zip$'; then
    pkg install -y php-zip >/dev/null 2>&1 || apt-get install -y --fix-missing php-zip >/dev/null 2>&1 || true
  fi

  # ========== Phát hiện cài đặt cũ — hỏi cài mới hay sửa chữa ==========
  # TMS_INSTALL_MODE được bộ cài root (install.sh) truyền; nếu không có
  # (chạy trực tiếp scripts/install.sh), tự phát hiện.
  HAS_OLD=0
  [ -d "$TARGET" ] && HAS_OLD=1
  [ -f "$HOME/.tms-os/db-mode" ] && HAS_OLD=1
  [ -d "$HOME/.redmi-mini-vps" ] && HAS_OLD=1
  if [ "${TMS_INSTALL_MODE:-}" = "repair" ]; then
    INSTALL_MODE="repair"
  elif [ "${TMS_INSTALL_MODE:-}" = "clean" ]; then
    INSTALL_MODE="clean"
  elif [ "$HAS_OLD" -eq 1 ]; then
    echo ''
    echo '============================================'
    echo ' [PHÁT HIỆN] Máy này đã có TMS OS (hoặc bản cũ .redmi-mini-vps).'
    echo '   [1] Sửa chữa — giữ nguyên dữ liệu (website, database, tài khoản), cài đè bản mới'
    echo '   [2] Cài mới — XÓA SẠCH mọi dữ liệu cũ, làm lại từ đầu'
    echo '============================================'
    while true; do
      printf 'Chọn [1/2]: '
      if ! read -r REPAIR_CHOICE; then
        # Stdin hết (pipe/EOF): không thể tương tác — dừng và hướng dẫn.
        echo ''
        echo '[DỪNG] Bộ cài cần bàn phím. Hãy chạy tương tác trong Termux:'
        echo '  bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh)'
        exit 2
      fi
      case "$REPAIR_CHOICE" in
        1) INSTALL_MODE="repair"; break ;;
        2) INSTALL_MODE="clean"; break ;;
        *) echo 'Vui lòng gõ 1 hoặc 2.' ;;
      esac
    done
  else
    INSTALL_MODE="clean"
    echo 'Chưa có TMS OS trên máy — cài mới từ đầu. [OK]'
  fi

  # ========== Chế độ cài mới: xóa sạch dữ liệu cũ ==========
  if [ "$INSTALL_MODE" = "clean" ]; then
    echo ''
    echo '[CẢNH BÁO] Chế độ CÀI MỚI: sẽ XÓA SẠCH toàn bộ dữ liệu TMS OS cũ'
    echo '  (website, database, tài khoản panel, cấu hình nginx/PHP).'
    printf '  Gõ YES (in hoa) để xác nhận xóa sạch: '
    read -r CONFIRM_CLEAN
    if [ "$CONFIRM_CLEAN" != "YES" ]; then
      echo 'Đã hủy — không xóa gì cả.'
      exit 0
    fi
    # Dừng dịch vụ đang chạy trước khi xóa
    bash "$TARGET/scripts/stop-tms.sh" 2>/dev/null || true
    pkill -9 -f mariadbd 2>/dev/null || true
    pkill -9 -f "tms-php-engine" 2>/dev/null || true
    sleep 2
    echo '[OK] Đã dừng các dịch vụ cũ. Xóa dữ liệu...'
    rm -rf "$TARGET" "$TARGET.previous"
    rm -rf "$HOME/.tms-os/data" "$HOME/.tms-os/service-core" "$HOME/.tms-os/service-manager.json"
    rm -rf "$HOME/.tms-os/backups" "$HOME/backups/database"
    rm -rf "$HOME/websites"
    # MariaDB: xóa datadir cũ + cấu hình cũ (đảm bảo khởi tạo từ zero)
    rm -rf "$PREFIX/var/lib/mysql" "$PREFIX/var/run/mysqld" "$PREFIX/tmp/mysql.sock" "/tmp/mysql.sock"
    rm -f "$HOME/.tms-os/mariadb-client.cnf" "$HOME/logs/services/mariadb.log"
    # Cấu hình nginx/PHP cũ
    rm -f "$NGINX" "$SITES/default.conf"
    rm -f "$PHP_CONF_DIR/99-tms-os.ini"
    echo '[OK] Đã xóa sạch dữ liệu cũ.'
  fi

  # ========== Chế độ sửa chữa: sao lưu dữ liệu hiện tại ==========
  if [ "$INSTALL_MODE" = "repair" ]; then
    echo 'Sửa chữa — sao lưu dữ liệu hiện tại trước khi cài đè...'
    printf '[2/7] Chuẩn bị dữ liệu và sao lưu...\n'
    mkdir -p "$HOME/.tms-os" "$HOME/.tms-os/backups" "$HOME/logs/services" "$BACKUP"
    if [ -d "$TARGET" ]; then
      cp -a "$TARGET" "$BACKUP/tms-os" || true
    fi
    [ -f "$HOME/.tms-os/data/db/tms.db" ] && cp "$HOME/.tms-os/data/db/tms.db" "$BACKUP/tms.db.bak" || true
    [ -f "$NGINX" ] && cp "$NGINX" "$BACKUP/nginx.conf" || true
    echo "[OK] Đã sao lưu dữ liệu hiện tại vào $BACKUP"
  fi

  # ========== Chọn engine database ==========
  # V14.0.3: SQLite (khuyến nghị: nhẹ, không daemon, không thể cài hỏng)
  # hoặc MariaDB (đầy đủ tính năng, cần daemon chạy nền).
  if [ "$INSTALL_MODE" = "repair" ] && [ -f "$HOME/.tms-os/db-mode" ]; then
    DB_MODE="$(cat "$HOME/.tms-os/db-mode")"
    echo "Giữ nguyên engine database hiện tại: $DB_MODE (chế độ sửa chữa)."
  else
    DB_MODE="mariadb"
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
date +%s > "$HOME/.tms-os/runtime_started_at"
[ -d "$TARGET" ] && cp -a "$TARGET" "$BACKUP/tms-os" || true
[ -f "$NGINX" ] && cp "$NGINX" "$BACKUP/nginx.conf" || true
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
  # V14.1.0: SQLite với tài khoản database BẮT BUỘC tự nhập.
  mkdir -p "$HOME/.tms-os/data/db" "$HOME/backups/database"
  chmod 700 "$HOME/.tms-os/data" "$HOME/.tms-os/data/db"
  if ! command -v sqlite3 >/dev/null 2>&1 || ! sqlite3 "$HOME/.tms-os/data/test.db" 'SELECT 1' >/dev/null 2>&1; then
    echo 'Không thể xác thực SQLite.' >&2
    exit 1
  fi
  rm -f "$HOME/.tms-os/data/test.db"
  echo '[OK] SQLite đã sẵn sàng.'
else
  # === MariaDB: chỉ khởi tạo lại khi cài mới; sửa chữa giữ datadir ===
  if [ "$INSTALL_MODE" = "clean" ]; then
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
  fi

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
# ========== V14.1.3: Chế độ sửa chữa — giữ nguyên tài khoản hiện tại ==========
if [ "$INSTALL_MODE" = "repair" ] && [ -f "$SECRET" ]; then
  echo '[OK] Chế độ sửa chữa — giữ nguyên tài khoản quản trị hiện tại.'
  ADMIN_USER="$(php -r '$d=require $argv[1];echo $d["username"]??"";' "$SECRET" 2>/dev/null)" || ADMIN_USER=""
  printf '[6/7] Cài source và khởi động dịch vụ...\n'
else
# ========== V14.1.0: BẮT BUỘC tự nhập tài khoản + mật khẩu ==========
# Hàm kiểm tra tên tài khoản hợp lệ (3-32 ký tự chữ/số/._-)
_valid_user() { printf '%s' "$1" | grep -Eq '^[A-Za-z0-9._-]{3,32}$'; }

# --- Tài khoản quản trị panel: tự nhập ---
_ATTEMPTS=0
while :; do
  printf 'Nhập tên đăng nhập quản trị (3-32 ký tự, chữ/số/._-): '
  read -r ADMIN_USER || ADMIN_USER=""
  ADMIN_USER="${ADMIN_USER%$'\r'}"
  if _valid_user "$ADMIN_USER"; then break; fi
  echo 'Tên đăng nhập không hợp lệ. Ví dụ: admin, tms_admin, thc.gaming'
  _ATTEMPTS=$((_ATTEMPTS+1))
  if [ "$_ATTEMPTS" -ge 5 ]; then echo '[LỖI] Đã thử nhiều lần — thoát.'; exit 1; fi
done

while :; do
  printf 'Nhập mật khẩu quản trị (tối thiểu 8 ký tự): '
  IFS= read -r -s ADMIN_PASS || ADMIN_PASS=""
  ADMIN_PASS="${ADMIN_PASS%$'\r'}"
  echo
  if [ "${#ADMIN_PASS}" -lt 8 ]; then echo 'Mật khẩu quá ngắn (cần ít nhất 8 ký tự).'; continue; fi
  printf 'Nhập lại mật khẩu: '
  IFS= read -r -s ADMIN_PASS_CONFIRM || ADMIN_PASS_CONFIRM=""
  ADMIN_PASS_CONFIRM="${ADMIN_PASS_CONFIRM%$'\r'}"
  echo
  if [ "$ADMIN_PASS" != "$ADMIN_PASS_CONFIRM" ]; then echo 'Hai mật khẩu không khớp. Vui lòng nhập lại.'; continue; fi
  break
done
_PW_TMP="$(mktemp)"
printf '%s' "$ADMIN_PASS" > "$_PW_TMP"; chmod 600 "$_PW_TMP"
HASH="$(php -r 'echo password_hash((string)file_get_contents($argv[1]), PASSWORD_DEFAULT);' "$_PW_TMP")" || HASH=""
rm -f "$_PW_TMP"
if [ -z "$HASH" ] || [ "${#HASH}" -lt 20 ]; then
  echo '[LỖI] Không thể tạo hash mật khẩu. Hãy chạy lại bộ cài.' >&2
  exit 1
fi
php -r '
  $file=(string)$argv[1];
  $user=(string)$argv[2];
  $hash=(string)$argv[3];
  $data="<?php\nreturn [\"username\"=>".var_export($user,true).",\"password_hash\"=>".var_export($hash,true)."];\n";
  if (file_put_contents($file,$data)===false) { fwrite(STDERR,"Không thể ghi tệp tài khoản.\n"); exit(1); }
  chmod($file,0600);
' "$SECRET" "$ADMIN_USER" "$HASH"
unset ADMIN_PASS HASH
echo '[OK] Đã lưu tài khoản quản trị panel.'
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
echo '============================================'
echo '[OK] TMS OS đã cài đặt thành công.'
echo "Panel: http://127.0.0.1:8888 (đăng nhập: $ADMIN_USER)"
echo 'Website: http://127.0.0.1:8080'
echo "PHP Engine: $MODE"
echo 'Khởi động lần sau: bash ~/tms-os/scripts/start-tms.sh'
echo 'Đổi tên tài khoản/mật khẩu bất kỳ lúc nào:'
echo '  bash ~/tms-os/scripts/tms-setup-admin.sh'
echo '============================================'
