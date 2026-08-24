#!/data/data/com.termux/files/usr/bin/bash
# tms-setup-admin.sh — Đặt tài khoản quản trị TMS OS (chạy SAU khi cài xong).
# Cách dùng:  bash ~/tms-os/scripts/tms-setup-admin.sh
# Script này chạy trực tiếp (không qua pipe) nên hỏi tương tác bình thường.
set -euo pipefail

HOME="$(cd ~ && pwd)"
CONF_DIR="$HOME/.tms-os/config"
SECRET="$CONF_DIR/panel-secret.php"
mkdir -p "$CONF_DIR"
chmod 700 "$HOME/.tms-os"
chmod 700 "$CONF_DIR"

echo '=============================='
echo '  Đặt tài khoản quản trị TMS OS'
echo '=============================='
echo

# --- Tên tài khoản ---
_ATTEMPTS=0
while :; do
  printf 'Nhập tên tài khoản quản trị (3-32 ký tự, chữ/số/._-): '
  read -r ADMIN_USER || ADMIN_USER=""
  ADMIN_USER="${ADMIN_USER%$'\r'}"
  if printf '%s' "$ADMIN_USER" | grep -Eq '^[A-Za-z0-9._-]{3,32}$'; then break; fi
  echo 'Tên tài khoản không hợp lệ. Ví dụ: admin, tms_admin, thc.gaming'
  _ATTEMPTS=$((_ATTEMPTS+1))
  if [ "$_ATTEMPTS" -ge 5 ]; then
    echo '[LỖI] Đã thử nhiều lần — thoát. Chạy lại: bash ~/tms-os/scripts/tms-setup-admin.sh'
    exit 1
  fi
done

# --- Mật khẩu ---
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
  if [ "$ADMIN_PASS" != "$ADMIN_PASS_CONFIRM" ]; then echo 'Hai mật khẩu không khớp.'; continue; fi
  break
done

# --- Hash an toàn qua stdin, không phụ thuộc thư mục tạm/lock của Termux ---
HASH="$(printf '%s' "$ADMIN_PASS" | php -n -r '$password=stream_get_contents(STDIN); echo password_hash($password, PASSWORD_DEFAULT);')" || HASH=""
if [ -z "$HASH" ] || [ "${#HASH}" -lt 20 ]; then
  echo '[LỖI] Không thể tạo hash mật khẩu. Hãy chạy lại.'
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

# Dọn các tệp tạm của bộ cài (đánh dấu đã đặt tài khoản chính thức)
rm -f "$HOME/.tms-os/.generated-password" "$HOME/.tms-os/first-login.txt"

echo
echo '[OK] Đã đặt tài khoản quản trị: '"$ADMIN_USER"
echo '[OK] Đăng nhập tại: http://127.0.0.1:8888 (hoặc địa chỉ LAN của máy)'
