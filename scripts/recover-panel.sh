#!/data/data/com.termux/files/usr/bin/bash
# Khôi phục an toàn dịch vụ panel TMS OS khi Nginx/PHP bị kẹt port sau cập nhật.
set -u

HOME="${HOME:-/data/data/com.termux/files/home}"
ROOT="$HOME/tms-os"

if [ ! -f "$ROOT/scripts/start-tms.sh" ]; then
  echo '[LỖI] Không tìm thấy ~/tms-os/scripts/start-tms.sh.'
  exit 1
fi

echo '[1/3] Đang dừng tiến trình web cũ của TMS OS...'
pkill -9 -f '[n]ginx' 2>/dev/null || true
pkill -9 -f '[p]hp-cgi' 2>/dev/null || true
for port in 80 8081 8082 8888 9000; do
  fuser -k "${port}/tcp" 2>/dev/null || true
done
sleep 1

echo '[2/3] Đang khởi động lại TMS OS...'
bash "$ROOT/scripts/start-tms.sh"

echo '[3/3] Đang kiểm tra panel...'
for _ in 1 2 3 4 5 6 7 8 9 10; do
  if curl -fsS --max-time 3 http://127.0.0.1:8888/login >/dev/null; then
    echo '[OK] Panel đã phản hồi tại http://127.0.0.1:8888/login'
    exit 0
  fi
  sleep 1
done

echo '[LỖI] Panel chưa phản hồi. Xem 30 dòng log gần nhất:'
tail -n 30 "$HOME/logs/services/php-engine.log" 2>/dev/null || true
exit 1
