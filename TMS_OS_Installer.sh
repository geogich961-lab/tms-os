#!/data/data/com.termux/files/usr/bin/bash
set -u
GREEN='\033[1;32m'; RED='\033[1;31m'; YELLOW='\033[1;33m'; CYAN='\033[1;36m'; RESET='\033[0m'
ok(){ printf "${GREEN}✓ %s${RESET}\n" "$1"; }
err(){ printf "${RED}✗ %s${RESET}\n" "$1"; }
info(){ printf "${CYAN}→ %s${RESET}\n" "$1"; }
warn(){ printf "${YELLOW}! %s${RESET}\n" "$1"; }
clear
printf "${CYAN}========================================\n Welcome to TMS OS by THCGaming\n========================================${RESET}\n\n"
if [ -z "${PREFIX:-}" ] || [ ! -d "$PREFIX" ]; then err 'Vui lòng chạy file này bằng Termux.'; exit 1; fi
if [ ! -e "$HOME/storage/downloads" ]; then
  warn 'Termux chưa có quyền truy cập bộ nhớ.'
  termux-setup-storage
  printf 'Sau khi cấp quyền, nhấn ENTER để tiếp tục...'; read -r _
fi
DOWNLOAD_DIR=''
for dir in "$HOME/storage/downloads" /storage/emulated/0/Download /sdcard/Download; do
  if [ -d "$dir" ]; then DOWNLOAD_DIR="$dir"; break; fi
done
if [ -z "$DOWNLOAD_DIR" ]; then err 'Không truy cập được thư mục Download.'; exit 1; fi
ok 'Đã truy cập thư mục Download.'
info "Đường dẫn: $DOWNLOAD_DIR"
info 'Kiểm tra công cụ cài đặt...'
pkg update -y || { err 'Không thể cập nhật kho Termux.'; exit 1; }
pkg install -y unzip coreutils findutils grep sed || { err 'Không thể cài công cụ.'; exit 1; }
ok 'Công cụ cài đặt đã sẵn sàng.'
ZIP_LIST=()
while IFS= read -r -d '' file; do ZIP_LIST+=("$file"); done < <(find -L "$DOWNLOAD_DIR" -maxdepth 1 -type f -iname '*.zip' -print0 2>/dev/null)
ZIP_COUNT=${#ZIP_LIST[@]}
if [ "$ZIP_COUNT" -eq 0 ]; then
  err 'Không tìm thấy file ZIP nào trong Download.'
  ls -lah "$DOWNLOAD_DIR" 2>/dev/null || true
  exit 1
fi
printf "\n${CYAN}Các file ZIP trong Download:${RESET}\n\n"
idx=1
for file in "${ZIP_LIST[@]}"; do
  name=$(basename "$file"); size=$(du -h "$file" 2>/dev/null | awk '{print $1}')
  printf "  ${GREEN}%d)${RESET} %s [%s]\n" "$idx" "$name" "${size:-?}"
  idx=$((idx+1))
done
printf "  ${YELLOW}0) Hủy${RESET}\n\n"
while true; do
  printf 'Chọn file cần cài [0-%d]: ' "$ZIP_COUNT"; read -r choice
  case "$choice" in
    ''|*[!0-9]*) warn 'Vui lòng nhập số hợp lệ.';;
    0) info 'Đã hủy.'; exit 0;;
    *) if [ "$choice" -ge 1 ] && [ "$choice" -le "$ZIP_COUNT" ]; then SELECTED_ZIP="${ZIP_LIST[$((choice-1))]}"; break; else warn 'Lựa chọn không hợp lệ.'; fi;;
  esac
done
info "Đã chọn: $(basename "$SELECTED_ZIP")"
if ! unzip -tq "$SELECTED_ZIP" >/dev/null 2>&1; then err 'ZIP bị lỗi hoặc chưa tải xong.'; exit 1; fi
WORK_DIR="$HOME/.tms-installer"; EXTRACT_DIR="$WORK_DIR/extracted"
rm -rf "$WORK_DIR"; mkdir -p "$EXTRACT_DIR"
info 'Đang giải nén...'
unzip -oq "$SELECTED_ZIP" -d "$EXTRACT_DIR" || { err 'Không thể giải nén.'; exit 1; }
INSTALL_SCRIPT=$(find "$EXTRACT_DIR" -maxdepth 6 -type f -path '*/scripts/install.sh' -print -quit 2>/dev/null)
if [ -z "$INSTALL_SCRIPT" ]; then INSTALL_SCRIPT=$(find "$EXTRACT_DIR" -maxdepth 4 -type f -name 'install.sh' -print -quit 2>/dev/null); fi
if [ -z "$INSTALL_SCRIPT" ] || [ ! -f "$INSTALL_SCRIPT" ]; then
  err 'File đã chọn không chứa trình cài đặt TMS OS.'
  find "$EXTRACT_DIR" -maxdepth 3 -type f 2>/dev/null | head -n 25
  exit 1
fi
ok 'Đã nhận diện bộ cài TMS OS.'
INSTALL_ROOT=$(cd "$(dirname "$INSTALL_SCRIPT")/.." && pwd)
chmod +x "$INSTALL_SCRIPT" 2>/dev/null || true
find "$INSTALL_ROOT/scripts" -maxdepth 1 -type f -name '*.sh' -exec chmod +x {} \; 2>/dev/null || true
cd "$INSTALL_ROOT" || exit 1
bash "$INSTALL_SCRIPT"
