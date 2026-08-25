#!/data/data/com.termux/files/usr/bin/bash
# ============================================================
# TMS OS — Bộ cài 1 dòng lệnh (V16.1.6)
# Cách dùng: curl -fsSL URL/install.sh | bash
# Chỉ cần Termux + quyền truy cập bộ nhớ (termux-setup-storage).
# Tương thích Android 7.0+ (API 24+).
# V16.1.6: Website Control Center nhận đúng website đang dừng (.conf.disabled)
#          để có thể xóa website default sau khi tạo snapshot an toàn.
# V16.1.5: đổi mật khẩu panel giữ nguyên username quản trị hiện có;
#          ghi tệp secret atomically để không tạo xác thực dở dang.
# V16.1.4: repair/startup tự khôi phục Cloudflare Tunnel đã cấu hình;
#          connector chạy từ Tunnel token lưu cục bộ, không cần gọi API khi khởi động.
# V16.1.3: worker báo cáo tự nạp UnifiedSystemCoreService khi crond chạy độc lập;
#          wrapper không gửi stack trace của worker báo cáo qua Telegram.
# V16.1.2: sửa nhận diện cấu hình real-IP do repair tạo để báo cáo truy cập
#          không chèn trùng Nginx map/log_format và vẫn chỉ tin Cloudflare loopback.
# V16.1.1: repair tự tạo storage runtime nếu gói cũ thiếu thư mục rỗng;
#          tài khoản database + admin phải tự nhập (không tự tạo);
#          khi chạy qua pipe vẫn tự chuyển sang chế độ tương tác.
# ============================================================
set -uo pipefail

# Universal Compatibility Installer. Hai cờ này chỉ đọc/kiểm tra, không cài,
# không xin quyền lưu trữ và không sửa dữ liệu người dùng.
CLI_MODE="${1:-}"
export HOME="${HOME:-/data/data/com.termux/files/home}"
COMPAT_STATE_DIR="${TMS_COMPAT_STATE_DIR:-$HOME/.tms-os-installer-state}"
COMPAT_LOCAL="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/scripts/lib" 2>/dev/null && pwd)/installer-compatibility.sh"
if [ -r "$COMPAT_LOCAL" ]; then
  # shellcheck disable=SC1090
  . "$COMPAT_LOCAL"
elif command -v curl >/dev/null 2>&1; then
  COMPAT_BOOTSTRAP="$HOME/.tms-os-compatibility-bootstrap-$$.sh"
  curl -fsSL --retry 2 "https://raw.githubusercontent.com/${TMS_REPO:-geogich961-lab/tms-os}/main/scripts/lib/installer-compatibility.sh" -o "$COMPAT_BOOTSTRAP" 2>/dev/null || true
  if [ -r "$COMPAT_BOOTSTRAP" ]; then
    # shellcheck disable=SC1090
    . "$COMPAT_BOOTSTRAP"
  fi
fi

if [ "$CLI_MODE" = "--diagnose" ] || [ "$CLI_MODE" = "--plan" ]; then
  export TMS_COMPAT_STATE_DIR="$COMPAT_STATE_DIR"
  export TMS_COMPAT_REPORT="$COMPAT_STATE_DIR/compatibility.env"
  export TMS_COMPAT_HUMAN_REPORT="$COMPAT_STATE_DIR/compatibility-report.txt"
  if ! command -v compat_detect_base >/dev/null 2>&1; then
    echo '[LỖI 20] Không tải được compatibility checker. Kiểm tra mạng và chạy lại.' >&2
    exit 20
  fi
  compat_detect_base
  if [ "$CLI_MODE" = "--plan" ]; then
    compat_check_base
    BASE_RC=$?
    compat_dependency_plan "${TMS_DB_MODE:-sqlite}"
    echo "Báo cáo: $TMS_COMPAT_HUMAN_REPORT"
    if [ "$BASE_RC" -ne 0 ]; then
      API_LEVEL="$(compat_getprop ro.build.version.sdk)"
      [ -n "$API_LEVEL" ] && [ "$API_LEVEL" -lt 24 ] 2>/dev/null && exit 10 || exit 20
    fi
    exit 0
  fi
  compat_full_preflight "${TMS_DB_MODE:-sqlite}"
  DIAG_RC=$?
  echo "Báo cáo chẩn đoán: $TMS_COMPAT_HUMAN_REPORT"
  echo "Mã lỗi: $DIAG_RC (10=Android/ABI, 20=Termux/package, 30=PHP engine, 40=Nginx/network, 50=dữ liệu/backup)"
  exit "$DIAG_RC"
fi

# ---------- Kiểm tra môi trường Termux ----------
if [ -z "${PREFIX:-}" ] || [ ! -d "${PREFIX:-/data/data/com.termux/files/usr}" ]; then
  echo '[LỖI] Bộ cài này chỉ chạy được trong Termux trên Android.'
  echo '        Hãy tải Termux từ F-Droid: https://f-droid.org/packages/com.termux'
  exit 1
fi
# HOME đã được thiết lập ở bootstrap compatibility phía trên.

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

# Một số Wi-Fi/ISP và bản cURL trên Android cũ ngắt kết nối khi GitHub chuyển
# từ github.com sang CDN release-assets (curl 56). Tải theo IPv4 + HTTP/1.1
# trước, sau đó mới quay về chế độ mặc định; mỗi cách có retry riêng. Dữ liệu
# chỉ được đổi tên sang đích khi tải xong, nên file dở dang không thể đi vào
# bước kiểm tra SHA-256 hoặc bước cài đặt.
tms_download_release_asset() {
  local url="$1" destination="$2" label="$3"
  local temporary="${destination}.part" rc=1 profile
  for profile in ipv4_http1 default; do
    rm -f "$temporary"
    if [ "$profile" = "ipv4_http1" ]; then
      echo "[TẢI] ${label}: thử IPv4/HTTP 1.1 (phù hợp mạng Android cũ)..."
      if curl -f -sS -L -4 --http1.1 --connect-timeout 20 --max-time 240 --retry 2 --retry-delay 2 -o "$temporary" "$url"; then
        rc=0
      else
        rc=$?
      fi
    else
      echo "[TẢI] ${label}: thử lại với cấu hình mạng mặc định..."
      if curl -f -sS -L --connect-timeout 20 --max-time 240 --retry 2 --retry-delay 2 -o "$temporary" "$url"; then
        rc=0
      else
        rc=$?
      fi
    fi
    if [ "$rc" -eq 0 ] && [ -s "$temporary" ]; then
      mv -f "$temporary" "$destination"
      return 0
    fi
    rm -f "$temporary"
    echo "[THỬ LẠI] ${label} chưa tải xong (curl $rc); chuyển phương án mạng sau 3 giây..." >&2
    sleep 3
  done
  return "$rc"
}

# ---------- Bước 3: cập nhật kho gói và cài thành phần ----------
echo 'Bước 3/7: Cập nhật kho gói Termux và cài đặt các thành phần (PHP, Nginx, MariaDB, OpenSSH)...'
export DEBIAN_FRONTEND=noninteractive
pkg update -y -q
pkg install -y php nginx mariadb curl zip unzip openssh procps coreutils findutils grep sed gawk which openssl diffutils termux-api psmisc >/dev/null
for c in php nginx curl mariadb mariadb-dump zip unzip sshd; do
  command -v "$c" >/dev/null || { echo "[LỖI] Thiếu lệnh sau cài: $c"; exit 1; }
done
# PHP trên một số Termux dùng PREFIX/var/tmp cho lock nội bộ; tạo sớm
# để tránh lỗi mơ hồ Permission denied ở bước khởi động engine.
if ! mkdir -p "$PREFIX/var/tmp" "$PREFIX/var/run"; then
  echo '[LỖI] Không thể tạo thư mục runtime của Termux ($PREFIX/var/tmp).' >&2
  exit 1
fi
chmod 700 "$PREFIX/var/tmp" "$PREFIX/var/run" 2>/dev/null || true
if ! test -w "$PREFIX/var/tmp" || ! mktemp "$PREFIX/var/tmp/tms-installer.XXXXXX" >/dev/null 2>&1; then
  echo '[LỖI] $PREFIX/var/tmp không ghi được; không tiếp tục để tránh lỗi PHP lock.' >&2
  exit 1
fi
rm -f "$PREFIX/var/tmp"/tms-installer.* 2>/dev/null || true
echo '[OK] Đã cài đủ các thành phần.'

# Kiểm tra đúng PHP server binary sau khi package đã sẵn sàng. Nếu PHP-CGI/FPM
# và PHP built-in HTTP đều fail, dừng trước khi tải source hoặc chạm dữ liệu.
if command -v compat_full_preflight >/dev/null 2>&1; then
  export TMS_COMPAT_STATE_DIR="$COMPAT_STATE_DIR"
  export TMS_COMPAT_REPORT="$COMPAT_STATE_DIR/compatibility.env"
  export TMS_COMPAT_HUMAN_REPORT="$COMPAT_STATE_DIR/compatibility-report.txt"
  compat_full_preflight "${TMS_DB_MODE:-sqlite}"
  COMPAT_RC=$?
  if [ "$COMPAT_RC" -ne 0 ]; then
    echo "[DỪNG] Thiết bị chưa có PHP engine hoạt động (mã $COMPAT_RC)."
    echo "       Báo cáo: $TMS_COMPAT_HUMAN_REPORT"
    exit "$COMPAT_RC"
  fi
  ENGINE_VALUE="$(sed -n 's/^ENGINE=//p' "$TMS_COMPAT_REPORT" | tail -n 1 | tr -d '\"')"
  echo "[OK] Universal Compatibility profile đã chọn engine: ${ENGINE_VALUE:-unknown}"
else
  echo '[LỖI 20] Không có compatibility checker; dừng trước khi tải source.' >&2
  exit 20
fi

# ---------- Bước 4: tải và kiểm tra bộ nguồn TMS OS mới nhất ----------
echo 'Bước 4/7: Tải bộ nguồn TMS OS mới nhất...'
ZIP="$WORK/TMS_OS.zip"
# Chữ ký luôn lấy từ RELEASE.json nằm CÙNG GitHub Release với ZIP. Không dùng
# checksum nhúng: checksum nhúng sẽ cũ ở release sau và tự chặn gói hợp lệ.
# Nếu GitHub đang đồng bộ hai asset, bộ cài tải lại tối đa 4 lần rồi dừng an toàn.
RELEASE_MANIFEST_URL="https://github.com/${REPO}/releases/latest/download/RELEASE.json"
RELEASE_MANIFEST="$WORK/RELEASE.json"
VERIFY_OK=0
VERIFY_SOURCE=""
for VERIFY_ATTEMPT in 1 2 3 4; do
  if ! tms_download_release_asset "$RELEASE_URL" "$ZIP" 'Gói nguồn TMS OS'; then
    echo "[LỖI] Không tải được bộ nguồn từ $RELEASE_URL"
    echo '        Hãy đổi mạng Wi-Fi/4G rồi chạy lại. Không có dữ liệu TMS OS nào bị xóa.'
    exit 1
  fi
  ACTUAL="$(sha256sum "$ZIP" | awk '{print $1}')"
  EXPECTED=""
  if tms_download_release_asset "${RELEASE_MANIFEST_URL}?nocache=$RANDOM" "$RELEASE_MANIFEST" 'Manifest chữ ký'; then
    EXPECTED="$(sed -n 's/.*"checksum_sha256"[[:space:]]*:[[:space:]]*"\([a-f0-9]\{64\}\)".*/\1/p' "$RELEASE_MANIFEST" | head -n 1)"
  fi
  if [ "$ACTUAL" = "$EXPECTED" ] && [ -n "$EXPECTED" ]; then
    VERIFY_OK=1; VERIFY_SOURCE="GitHub Release"
    break
  fi
  # Thêm nocache vào ZIP để bẻ cache nếu GitHub đang đồng bộ asset mới.
  RELEASE_URL="${RELEASE_URL}?nocache=$RANDOM"
  echo "[THỬ LẠI] ZIP hoặc RELEASE.json (try $VERIFY_ATTEMPT) chưa đồng bộ trên GitHub. Tải lại sau 5 giây..."
  sleep 5
done
if [ "$VERIFY_OK" -ne 1 ]; then
  echo "[LỖI] File tải về không khớp chữ ký SHA-256 sau 4 lần thử. Dừng cài đặt vì lý do an toàn."
  echo "        EXPECTED (GitHub Release) : ${EXPECTED:-'(không đọc được)'}"
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

# Không xóa dữ liệu tại root installer. Sub-installer phải preflight và backup
# trước, sau đó mới xử lý clean/repair trong transaction có rollback.
SAFETY_LIB="$SRC/scripts/lib/installer-safety.sh"
if [ ! -r "$SAFETY_LIB" ]; then
  echo '[LỖI] Gói cài thiếu installer safety library; dừng trước khi thay đổi dữ liệu.' >&2
  exit 50
fi
# shellcheck disable=SC1090
. "$SAFETY_LIB"
TMS_COMPAT_ENGINE="$(sed -n 's/^ENGINE=//p' "$COMPAT_STATE_DIR/compatibility.env" 2>/dev/null | tail -n 1 | tr -d '\"')"
export TMS_COMPAT_ENGINE
export TMS_COMPAT_VALID=1
TMS_PREFLIGHT_REQUIRE_NGINX=0 TMS_INSTALL_MODE="$INSTALL_MODE" tms_preflight
RC=$?
if [ "$RC" -ne 0 ]; then
  echo "[DỪNG] Thiết bị chưa đạt preflight (mã $RC); không xóa dữ liệu cũ."
  echo "       Báo cáo: $TMS_REPORT"
  exit "$RC"
fi

echo "[OK] Root preflight đạt. Báo cáo: $TMS_REPORT"
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
