#!/data/data/com.termux/files/usr/bin/bash
# =============================================================================
# TMS OS — Kênh thử nghiệm nội bộ V16.1.0
# Cập nhật mã nguồn từ một nhánh GitHub riêng; không dùng GitHub Release.
# Luôn sao lưu mã nguồn panel trước khi thay đổi và giữ nguyên dữ liệu ~/.tms-os,
# websites/ và database của thiết bị.
# =============================================================================
set -Eeuo pipefail

REPO="${TMS_REPO:-geogich961-lab/tms-os}"
BRANCH="${TMS_TEST_BRANCH:-develop-v16.1.0}"
HOME="${HOME:-/data/data/com.termux/files/home}"
ROOT="$HOME/tms-os"
STATE_DIR="$HOME/.tms-os"
BACKUP_ROOT="$STATE_DIR/dev-channel-backups"
BASELINE="$STATE_DIR/dev-channel-stable-baseline"
WORK="$STATE_DIR/.dev-channel-work-$$"
ARCHIVE_URL="https://github.com/${REPO}/archive/refs/heads/${BRANCH}.zip"

cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT

need_command() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "[LỖI] Thiếu lệnh '$1'. Hãy mở TMS OS bản ổn định và chạy lại bộ cài nếu Termux chưa đủ gói."
    exit 1
  }
}

confirm() {
  local prompt="$1"
  if [ ! -t 0 ]; then
    echo '[LỖI] Kênh thử nghiệm cần chạy trực tiếp trong Termux để xác nhận thao tác.'
    exit 2
  fi
  printf '%s' "$prompt"
  read -r answer
  [ "$answer" = "TEST" ] || { echo '[DỪNG] Không có thay đổi nào được thực hiện.'; exit 0; }
}

restart_tms() {
  if [ ! -f "$ROOT/scripts/start-tms.sh" ]; then
    echo '[LỖI] Không tìm thấy script khởi động TMS OS sau khi cập nhật.'
    return 1
  fi
  # Dọn cả master/worker Nginx còn sót và các port do TMS OS quản lý trước khi
  # gọi start-tms. Điều này tránh trạng thái "Address already in use" khi swap code.
  pkill -9 -f '[n]ginx' 2>/dev/null || true
  pkill -9 -f '[p]hp-cgi' 2>/dev/null || true
  for port in 80 8081 8082 8888 9000; do
    fuser -k "${port}/tcp" 2>/dev/null || true
  done
  sleep 1
  bash "$ROOT/scripts/start-tms.sh"
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    if curl -fsS --max-time 3 http://127.0.0.1:8888/login >/dev/null; then
      return 0
    fi
    sleep 1
  done
  return 1
}

rollback() {
  if [ ! -d "$BASELINE/tms-os" ]; then
    echo '[LỖI] Chưa có điểm khôi phục trước thử nghiệm trên thiết bị này.'
    exit 1
  fi
  echo "Điểm khôi phục ổn định: $BASELINE"
  confirm 'Gõ TEST để khôi phục mã nguồn trước khi vào kênh thử nghiệm: '
  rm -rf "$ROOT.failed-rollback"
  [ -d "$ROOT" ] && mv "$ROOT" "$ROOT.failed-rollback"
  cp -a "$BASELINE/tms-os" "$ROOT"
  chmod -R 700 "$ROOT/scripts" "$ROOT/storage" 2>/dev/null || true
  if restart_tms; then
    rm -rf "$ROOT.failed-rollback"
    echo '[OK] Đã khôi phục bản sao lưu và khởi động lại TMS OS.'
  else
    echo '[CẢNH BÁO] Mã nguồn đã được khôi phục nhưng dịch vụ chưa phản hồi. Chạy: bash ~/tms-os/scripts/start-tms.sh'
    exit 1
  fi
}

update() {
  [ -d "$ROOT" ] || { echo '[LỖI] Không tìm thấy TMS OS hiện có tại ~/tms-os. Kênh này chỉ dùng để cập nhật bản đã cài.'; exit 1; }
  for cmd in curl unzip php sha256sum; do need_command "$cmd"; done
  if ! command -v crond >/dev/null 2>&1 || ! command -v crontab >/dev/null 2>&1; then
    echo '[CHUẨN BỊ] Đang cài Cron runtime (cronie) cho Cron Jobs...'
    command -v pkg >/dev/null 2>&1 || { echo '[LỖI] Không có pkg để cài cronie.'; exit 1; }
    pkg install -y cronie
  fi
  echo '============================================================'
  echo ' TMS OS — KÊNH THỬ NGHIỆM NỘI BỘ'
  echo " Nhánh nguồn: ${BRANCH}"
  echo ' Dữ liệu website, database và tài khoản hiện tại sẽ được giữ nguyên.'
  echo ' Mã nguồn panel hiện tại sẽ được sao lưu để có thể rollback.'
  echo '============================================================'
  confirm 'Gõ TEST để tải và cài bản V16.1.0 thử nghiệm: '

  mkdir -p "$WORK" "$BACKUP_ROOT" "$STATE_DIR"
  local archive="$WORK/source.zip"
  echo '[1/6] Đang tải mã nguồn từ nhánh thử nghiệm...'
  curl -fL --retry 3 --retry-delay 2 --connect-timeout 20 --max-time 300 -o "$archive" "$ARCHIVE_URL"
  echo "[INFO] SHA-256 gói thử nghiệm: $(sha256sum "$archive" | awk '{print $1}')"
  unzip -q "$archive" -d "$WORK/extract"
  local source
  source="$(find "$WORK/extract" -mindepth 1 -maxdepth 1 -type d -name 'tms-os-*' | head -n 1 || true)"
  [ -n "$source" ] && [ -d "$source/app" ] && [ -d "$source/config" ] && [ -d "$source/public" ] && [ -d "$source/routes" ] && [ -d "$source/scripts" ] && [ -d "$source/storage" ] || {
    echo '[LỖI] Gói từ nhánh thử nghiệm không có cấu trúc TMS OS hợp lệ.'
    exit 1
  }
  echo '[2/6] Đang kiểm tra cú pháp PHP của mã nguồn...'
  while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$source" -type f -name '*.php' -print0)

  local stamp backup
  stamp="$(date +%Y%m%d_%H%M%S)"
  backup="$BACKUP_ROOT/$stamp"
  echo '[3/6] Đang sao lưu mã nguồn hiện tại...'
  mkdir -p "$backup"
  cp -a "$ROOT" "$backup/tms-os"
  printf '%s\n' "branch=$BRANCH" "updated_at=$(date -Iseconds)" > "$backup/metadata.txt"
  if [ ! -d "$BASELINE/tms-os" ]; then
    mkdir -p "$BASELINE"
    cp -a "$ROOT" "$BASELINE/tms-os"
    printf '%s\n' "saved_at=$(date -Iseconds)" "reason=before-first-internal-test" > "$BASELINE/metadata.txt"
  fi

  echo '[4/6] Đang chuyển sang mã nguồn thử nghiệm...'
  mv "$ROOT" "$ROOT.previous-dev"
  if ! mv "$source" "$ROOT"; then
    mv "$ROOT.previous-dev" "$ROOT"
    echo '[LỖI] Không thể chuyển mã nguồn thử nghiệm; đã giữ nguyên bản cũ.'
    exit 1
  fi
  chmod -R 700 "$ROOT/scripts" "$ROOT/storage" 2>/dev/null || true
  chmod 700 "$ROOT/scripts/tms-cron-engine.sh" 2>/dev/null || true
  mkdir -p "$ROOT/storage/logs" "$ROOT/storage/sessions" "$ROOT/storage/cache"

  echo '[5/6] Đang sửa dữ liệu và lịch Cron hiện có...'
  if ! php "$ROOT/scripts/repair-cron-runtime.php"; then
    echo '[LỖI] Không thể chuẩn hóa Cron runtime; sẽ khôi phục mã nguồn trước thử nghiệm.'
    rm -rf "$ROOT.failed-dev"
    mv "$ROOT" "$ROOT.failed-dev"
    cp -a "$backup/tms-os" "$ROOT"
    restart_tms || true
    exit 1
  fi

  echo '[6/6] Đang khởi động lại TMS OS...'
  if restart_tms; then
    rm -rf "$ROOT.previous-dev"
    printf '{\n  "channel": "internal-test",\n  "branch": "%s",\n  "updated_at": "%s"\n}\n' "$BRANCH" "$(date -Iseconds)" > "$STATE_DIR/dev-channel.json"
    chmod 600 "$STATE_DIR/dev-channel.json"
    echo '[OK] Đã cập nhật V16.1.0-TEST từ kênh nội bộ.'
    echo '     Mở lại panel tại http://127.0.0.1:8888 và kiểm tra App Marketplace, Cron Jobs.'
  else
    echo '[KHẮC PHỤC] Dịch vụ không phản hồi. Đang khôi phục mã nguồn trước khi thử nghiệm...'
    rm -rf "$ROOT.failed-dev"
    mv "$ROOT" "$ROOT.failed-dev"
    cp -a "$backup/tms-os" "$ROOT"
    restart_tms || true
    echo '[LỖI] Đã quay về mã nguồn trước thử nghiệm. Hãy gửi lại log lỗi để kiểm tra.'
    exit 1
  fi
}

case "${1:-update}" in
  update) update ;;
  rollback) rollback ;;
  *)
    echo 'Cách dùng: bash dev-channel.sh [update|rollback]'
    exit 2
    ;;
esac
