#!/data/data/com.termux/files/usr/bin/bash
# ============================================================
# TMS OS — Bộ cài 1 dòng lệnh
# Cách dùng: curl -fsSL URL/install.sh | bash
# Chỉ cần Termux + quyền truy cập bộ nhớ (termux-setup-storage).
# ============================================================
set -uo pipefail

# ---------- Kiểm tra môi trường Termux ----------
if [ -z "${PREFIX:-}" ] || [ ! -d "${PREFIX:-/data/data/com.termux/files/usr}" ]; then
  echo '[LỖI] Bộ cài này chỉ chạy được trong Termux trên Android.'
  echo '        Hãy tải Termux từ F-Droid: https://f-droid.org/packages/com.termux'
  exit 1
fi
export HOME="${HOME:-/data/data/com.termux/files/home}"

# ---------- Quyền truy cập bộ nhớ ----------
if [ ! -d "$HOME/storage" ]; then
  echo 'Bước 1/6: Cấp quyền truy cập bộ nhớ cho Termux...'
  termux-setup-storage
  echo 'Vừa hiện hộp thoại "CHO PHÉP" trên màn hình. Sau khi nhấn CHO PHÉP, hãy quay lại Termux.'
  for _ in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
    [ -d "$HOME/storage" ] && break
    sleep 2
  done
  if [ ! -d "$HOME/storage" ]; then
    echo 'Chưa thấy quyền bộ nhớ. Thử: Cài đặt Android → Ứng dụng → Termux → Quyền → Tệp và phương tiện → CHO PHÉP, rồi chạy lại bộ cài.'
    exit 1
  fi
  echo '[OK] Đã có quyền truy cập bộ nhớ.'
else
  echo 'Bước 1/6: Quyền truy cập bộ nhớ đã sẵn sàng. [OK]'
fi

# ---------- Cấu hình bộ cài ----------
REPO="${TMS_REPO:-geogich961-lab/tms-os}"
RELEASE_URL="https://github.com/${REPO}/releases/latest/download/TMS_OS_V14.0.1.zip"
WORK="$HOME/.tms-os-installer-$$"
mkdir -p "$WORK"
trap 'rm -rf "$WORK"' EXIT

# ---------- Bước 2: cập nhật kho gói và cài thành phần ----------
echo 'Bước 2/6: Cập nhật kho gói Termux và cài đặt các thành phần (PHP, Nginx, MariaDB, OpenSSH)...'
if ! command -v pkg >/dev/null 2>&1; then
  echo '[LỖI] Không tìm thấy lệnh pkg. Hãy cài lại Termux và thử lại.'; exit 1
fi
export DEBIAN_FRONTEND=noninteractive
pkg update -y -q
pkg install -y php nginx mariadb curl zip unzip openssh procps coreutils findutils grep sed gawk which openssl diffutils termux-api >/dev/null
for c in php php-cgi nginx curl mariadb mariadb-dump zip unzip sshd; do
  command -v "$c" >/dev/null || { echo "[LỖI] Thiếu lệnh sau cài: $c"; exit 1; }
done
echo '[OK] Đã cài đủ các thành phần.'

# ---------- Bước 3: tải và kiểm tra bộ nguồn TMS OS ----------
echo 'Bước 3/6: Tải bộ nguồn TMS OS mới nhất...'
ZIP="$WORK/TMS_OS.zip"
if ! curl -fsSL --retry 3 --retry-delay 2 -m 120 -o "$ZIP" "$RELEASE_URL"; then
  echo "[LỖI] Không tải được bộ nguồn từ $RELEASE_URL"
  echo '        Kiểm tra kết nối mạng, hoặc đặt biến môi trường TMS_REPO=owner/repo rồi chạy lại.'
  exit 1
fi
EXPECTED="$(curl -fsSL -m 30 "https://raw.githubusercontent.com/${REPO}/main/RELEASE.json" 2>/dev/null | sed -n 's/.*"checksum_sha256"[[:space:]]*:[[:space:]]*"\([a-f0-9]*\)".*/\1/p')"
if [ -n "$EXPECTED" ]; then
  ACTUAL="$(sha256sum "$ZIP" | awk '{print $1}')"
  if [ "$ACTUAL" != "$EXPECTED" ]; then
    echo "[LỖI] File tải về không khớp chữ ký SHA-256. Dừng cài đặt vì lý do an toàn."
    exit 1
  fi
  echo "[OK] Đã xác minh chữ ký SHA-256."
fi
rm -rf "$WORK/extract"
mkdir -p "$WORK/extract"
unzip -qo "$ZIP" -d "$WORK/extract"
# Tìm thư mục chứa app/config/public (tải về có thể giải nén thẳng hoặc có thư mục gốc)
SRC="$WORK/extract"
[ -d "$SRC/app" ] || SRC="$(find "$WORK/extract" -maxdepth 2 -name app -type d | head -1 | xargs dirname)"
[ -d "$SRC/app" ] && [ -d "$SRC/scripts" ] || { echo '[LỖI] Bộ nguồn giải nén bị hỏng cấu trúc.'; exit 1; }
echo '[OK] Bộ nguồn đã tải về và hợp lệ.'

# ---------- Bước 4: chạy bộ cài chính của TMS OS ----------
chmod -R 700 "$SRC/scripts"
echo 'Bước 4/6–5/6: Thiết lập TMS OS (tài khoản quản trị + MariaDB)...'
bash "$SRC/scripts/install.sh"
RC=$?
if [ $RC -ne 0 ]; then
  echo "[LỖI] Bộ cài gặp lỗi (exit $RC). Hãy chạy lại cùng lệnh trên — bộ cài có sao lưu tự động."
  exit $RC
fi

# ---------- Bước 6: báo kết quả và đường dẫn LAN ----------
echo 'Bước 6/6: Hoàn tất — kiểm tra đường dẫn LAN...'
if command -v php >/dev/null 2>&1; then
  LAN_IP="$(php -r '
    $s = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    @socket_connect($s, "8.8.8.8", 53);
    socket_getsockname($s, $a); @socket_close($s);
    if ($a && $a !== "0.0.0.0") { echo $a; exit; }
    $r = @shell_exec("ip route 2>/dev/null");
    if (preg_match("/src ([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/", $r, $m)) { echo $m[1]; exit; }
    $g = @shell_exec("getprop dhcp.wlan0.ipaddress 2>/dev/null");
    if (trim($g) && preg_match("/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/", trim($g))) { echo trim($g); exit; }
    echo "127.0.0.1";')";
else
  LAN_IP="127.0.0.1"
fi
echo ''
echo '============================================'
echo ' [OK] TMS OS đã cài đặt thành công!'
echo " Panel (trên máy): http://127.0.0.1:8888"
echo " Panel (mạng LAN): http://${LAN_IP}:8888"
echo " Website mặc định: http://${LAN_IP}:8080"
echo '--------------------------------------------'
echo ' Sau này muốn khởi động lại, chỉ cần chạy:'
echo '   bash ~/tms-os/scripts/start-tms.sh'
echo '============================================'
echo ''
echo 'Mở http://127.0.0.1:8888 trên trình duyệt để bắt đầu sử dụng.'
