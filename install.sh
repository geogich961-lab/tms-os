#!/data/data/com.termux/files/usr/bin/bash
# ============================================================
# TMS OS — Bộ cài 1 dòng lệnh (V14.1.0)
# Cách dùng: curl -fsSL URL/install.sh | bash
# Chỉ cần Termux + quyền truy cập bộ nhớ (termux-setup-storage).
# Tương thích Android 7.0+ (API 24+).
# V14.1.0: phát hiện cài đặt cũ → hỏi cài mới / sửa chữa;
#          tài khoản database + admin phải tự nhập (không tự tạo);
#          khi chạy qua pipe vẫn tự chuyển sang chế độ tương tác.
# ============================================================
set -uo pipefail

# ---------- Kiểm tra môi trường Termux ----------
if [ -z "${PREFIX:-}" ] || [ ! -d "${PREFIX:-/data/data/com.termux/files/usr}" ]; then
  echo '[LỖI] Bộ cài này chỉ chạy được trong Termux trên Android.'
  echo '        Hãy tải Termux từ F-Droid: https://f-droid.org/packages/com.termux'
  exit 1
fi
export HOME="${HOME:-/data/data/com.termux/files/home}"

# ---------- Tương thích Android 7.0+ ----------
# Termux chỉ hỗ trợ Android 7.0 (API 24) trở lên. Nếu pkg hoặc các lệnh
# cốt lõi không khả dụng, báo lỗi rõ ràng thay vì fail khó hiểu.
if ! command -v pkg >/dev/null 2>&1; then
  echo '[LỖI] Không tìm thấy lệnh pkg — thiết bị hoặc Termux không tương thích.'
  echo '        TMS OS yêu cầu Android 7.0 trở lên và Termux từ F-Droid.'
  echo '        Hãy tải Termux: https://f-droid.org/packages/com.termux'
  exit 1
fi
# Binary mới của Termux yêu cầu Android >= 8; nếu các gói cài fail với
# "not found" khi chạy, kiểm tra sớm ở đây với fallback thân thiện.
if command -v getprop >/dev/null 2>&1; then
  API_LEVEL="$(getprop ro.build.version.sdk 2>/dev/null || true)"
  if [ -n "$API_LEVEL" ] && [ "$API_LEVEL" -lt 24 ]; then
    echo "[LỖI] Android $API_LEVEL không được hỗ trợ. TMS OS yêu cầu Android 7.0 (API 24) trở lên."
    exit 1
  fi
fi

# ---------- Quyền truy cập bộ nhớ ----------
if [ ! -d "$HOME/storage" ]; then
  echo 'Bước 1/7: Cấp quyền truy cập bộ nhớ cho Termux...'
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
  echo 'Bước 1/7: Quyền truy cập bộ nhớ đã sẵn sàng. [OK]'
fi

# ---------- Phát hiện cài đặt TMS OS cũ ----------
HAS_OLD_INSTALL=0
if [ -d "$HOME/tms-os" ] || [ -f "$HOME/.tms-os/db-mode" ] || [ -f "$HOME/.tms-os/config/panel-secret.php" ] || [ -d "$HOME/.redmi-mini-vps" ]; then
  HAS_OLD_INSTALL=1
fi
if [ "$HAS_OLD_INSTALL" -eq 1 ]; then
  echo ''
  echo '============================================'
  echo ' [PHÁT HIỆN] Máy này đã có TMS OS (hoặc bản cũ .redmi-mini-vps).'
  echo ' Bạn muốn:'
  echo '   [1] Sửa chữa — giữ nguyên toàn bộ dữ liệu (website, database, tài khoản), cài đè bản mới'
  echo '   [2] Cài mới — XÓA SẠCH mọi dữ liệu cũ, làm lại từ đầu'
  echo '============================================'
  if [ ! -t 0 ]; then
    # Không tương tác (pipe hoặc redirect). Với máy đã có TMS OS, dừng và hướng dẫn
    # chạy tương tác thay vì treo chờ read (pipe không nhận được bàn phím).
    echo '[INFO] Bộ cài phát hiện đã có TMS OS cũ. Vui lòng chọn trực tiếp trong Termux:'
    echo '  Hãy chạy lệnh sau trong Termux và trả lời câu hỏi:'
    echo "  bash <(curl -fsSL https://raw.githubusercontent.com/${TMS_REPO:-geogich961-lab/tms-os}/main/install.sh)"
    echo ''
    echo '[DỪNG] Bộ cài không thể hỏi lựa chọn khi chạy qua pipe. Nếu cài mới hoàn toàn trên máy CHƯA có TMS OS, lệnh curl sẽ chạy tự động không cần hỏi.'
    exit 2
  fi
  while true; do
    printf 'Chọn [1/2]: '
    read -r REPAIR_CHOICE
    case "$REPAIR_CHOICE" in
      1) INSTALL_MODE="repair"; break ;;
      2) INSTALL_MODE="clean"; break ;;
      *) echo 'Vui lòng gõ 1 hoặc 2.' ;;
    esac
  done
else
  INSTALL_MODE="clean"
  echo 'Bước 2/7: Đây là lần cài đặt đầu tiên — TMS OS chưa tồn tại trên máy. [OK]'
fi

# ---------- Cấu hình bộ cài ----------
REPO="${TMS_REPO:-geogich961-lab/tms-os}"
RELEASE_URL="https://github.com/${REPO}/releases/latest/download/TMS_OS_LATEST.zip"
WORK="$HOME/.tms-os-installer-$$"
mkdir -p "$WORK"
trap 'rm -rf "$WORK"' EXIT

# ---------- Bước 3: cập nhật kho gói và cài thành phần ----------
echo 'Bước 3/7: Cập nhật kho gói Termux và cài đặt các thành phần (PHP, Nginx, MariaDB, OpenSSH)...'
export DEBIAN_FRONTEND=noninteractive
pkg update -y -q
pkg install -y php nginx mariadb curl zip unzip openssh procps coreutils findutils grep sed gawk which openssl diffutils termux-api psmisc >/dev/null
for c in php php-cgi nginx curl mariadb mariadb-dump zip unzip sshd; do
  command -v "$c" >/dev/null || { echo "[LỖI] Thiếu lệnh sau cài: $c"; exit 1; }
done
echo '[OK] Đã cài đủ các thành phần.'

# ---------- Bước 4: tải và kiểm tra bộ nguồn TMS OS ----------
echo 'Bước 4/7: Tải bộ nguồn TMS OS mới nhất...'
ZIP="$WORK/TMS_OS.zip"
# Cơ chế xác minh checksum 3 lớp, chống race condition GitHub CDN:
#   Lớp 1: checksum ONLINE từ RELEASE.json (phiên bản phát hành)
#   Lớp 2: checksum EMBED sẵn trong chính installer này (fallback khi online cache cũ)
#   Lớp 3: tự tải lại tối đa 4 lần khi hai lớp lệch nhau do GitHub đang cập nhật
# Mỗi release mới: checksum embed được cập nhật tự động cùng lúc đóng gói ZIP.
EMBED_SHA256="da5113c1f1736e339553c568d3196919fc23da610e274be3538132655c8c9ac5"
VERIFY_OK=0
VERIFY_SOURCE=""
for VERIFY_ATTEMPT in 1 2 3 4; do
  if ! curl -fsSL --retry 3 --retry-delay 2 -m 120 -o "$ZIP" "$RELEASE_URL"; then
    echo "[LỖI] Không tải được bộ nguồn từ $RELEASE_URL"
    echo '        Kiểm tra kết nối mạng, hoặc đặt biến môi trường TMS_REPO=owner/repo rồi chạy lại.'
    exit 1
  fi
  ACTUAL="$(sha256sum "$ZIP" | awk '{print $1}')"
  # Lớp 1: checksum online
  EXPECTED="$(curl -fsSL -m 30 "https://raw.githubusercontent.com/${REPO}/main/RELEASE.json?nocache=$RANDOM" 2>/dev/null | sed -n 's/.*"checksum_sha256"[[:space:]]*:[[:space:]]*"\([a-f0-9]*\)".*/\1/p')"
  if [ "$ACTUAL" = "$EXPECTED" ] && [ -n "$EXPECTED" ]; then
    VERIFY_OK=1; VERIFY_SOURCE="online"
    break
  fi
  # Lớp 2: checksum embed trong installer (ghithub CDN cache RELEASE.json cũ)
  if [ "$ACTUAL" = "$EMBED_SHA256" ] && [ "$EMBED_SHA256" != "__EMBED_SHA256_PLACEHOLDER__" ]; then
    VERIFY_OK=1; VERIFY_SOURCE="embedded"
    echo '[THÔNG BÁO] Chữ ký online chưa cập nhật trên GitHub — dùng chữ ký nhúng trong bộ cài (đã xác minh).'
    break
  fi
  # V16.0.15: Thêm nocache vào URL tải ZIP để bẻ gãy cache của GitHub CDN
  RELEASE_URL="${RELEASE_URL}?nocache=$RANDOM"
  echo "[THỬ LẠI] File tải về (try $VERIFY_ATTEMPT) chưa khớp chữ ký SHA-256 — GitHub đang cập nhật release. Tải lại sau 5 giây..."
  sleep 5
done
if [ "$VERIFY_OK" -ne 1 ]; then
  echo "[LỖI] File tải về không khớp chữ ký SHA-256 sau 4 lần thử. Dừng cài đặt vì lý do an toàn."
  echo "        EXPECTED (online) : ${EXPECTED:-'(không đọc được)'}"
  echo "        EXPECTED (embed)  : ${EMBED_SHA256:-'(không có)'}"
  echo "        ACTUAL            : $ACTUAL"
  echo '        Nếu lỗi tiếp diễn, hãy chạy lại cùng lệnh sau 5 phút hoặc báo lỗi tại GitHub issues.'
  exit 1
fi
echo "[OK] Đã xác minh chữ ký SHA-256 (nguồn: $VERIFY_SOURCE)."
rm -rf "$WORK/extract"
mkdir -p "$WORK/extract"
unzip -qo "$ZIP" -d "$WORK/extract"
# Tìm thư mục chứa app/config/public (tải về có thể giải nén thẳng hoặc có thư mục gốc)
SRC="$WORK/extract"
[ -d "$SRC/app" ] || SRC="$(find "$WORK/extract" -maxdepth 2 -name app -type d | head -1 | xargs dirname)"
[ -d "$SRC/app" ] && [ -d "$SRC/scripts" ] || { echo '[LỖI] Bộ nguồn giải nén bị hỏng cấu trúc.'; exit 1; }
echo '[OK] Bộ nguồn đã tải về và hợp lệ.'

# ---------- Bước 5: chạy bộ cài chính của TMS OS ----------
chmod -R 700 "$SRC/scripts"
echo 'Bước 5/7: Thiết lập TMS OS (chọn engine database, tài khoản quản trị)...'

# V16.0.15: Nếu kẹt V16.0.6, cưỡng bức xóa sạch thư mục target trước khi chạy sub-installer
if [ "$INSTALL_MODE" = "clean" ]; then
  echo '[CẢNH BÁO] Đang thực hiện cài đặt sạch — Xóa toàn bộ dữ liệu cũ...'
  pkill -9 -f php-cgi 2>/dev/null || true
  pkill -9 -f nginx 2>/dev/null || true
  fuser -k 8888/tcp 9000/tcp 2>/dev/null || true
  rm -rf "$HOME/tms-os" "$HOME/tms-os.previous"
fi

RC=0
if [ "$INSTALL_MODE" = "repair" ]; then
  export TMS_INSTALL_MODE="repair"
else
  export TMS_INSTALL_MODE="clean"
fi
bash "$SRC/scripts/install.sh" || RC=$?
if [ "$RC" -ne 0 ]; then
  if [ "$RC" -eq 2 ]; then
    echo "[INFO] Bộ cài dừng để chờ bạn chọn chế độ (cài mới / sửa chữa). Chạy lại cùng lệnh trên và trả lời câu hỏi."
  else
    echo "[LỖI] Bộ cài gặp lỗi (exit $RC). Hãy chạy lại cùng lệnh trên — bộ cài có sao lưu tự động."
  fi
  exit $RC
fi

# ---------- Bước 6: bật auto-start khi khởi động máy (tùy chọn) ----------
if [ ! -t 0 ]; then
  BOOT_CHOICE="y"
else
  printf 'Bước 6/7: Tự khởi động TMS OS khi bật máy? (cần app Termux:Boot) [Y/n]: '
  read -r BOOT_CHOICE
fi
case "${BOOT_CHOICE:-y}" in
  n|N|no|NO) echo "Bỏ qua. Khi cần, chạy: bash ~/tms-os/scripts/tms-boot.sh on" ;;
  *)
    bash "$SRC/scripts/tms-boot.sh" on
    echo '[OK] Auto-start đã được thiết lập.'
    ;;
esac

# ---------- Bước 7: báo kết quả và đường dẫn LAN ----------
echo 'Bước 7/7: Hoàn tất — kiểm tra đường dẫn LAN...'
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
echo ' Khởi động lại thủ công: bash ~/tms-os/scripts/start-tms.sh'
echo ' Quản lý auto-start: bash ~/tms-os/scripts/tms-boot.sh [on|off|status]'
echo '============================================'
echo ''
echo 'Mở http://127.0.0.1:8888 trên trình duyệt để bắt đầu sử dụng.'
