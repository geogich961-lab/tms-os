#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${BUILD_ROOT:-$ROOT/.build/v17.1.14}"
PAYLOAD="$BUILD_ROOT/payload"; OUT="$BUILD_ROOT/release"; VERSION="17.1.14"; BUILD_DATE="2026-11-14"; PHP_BIN="${PHP_BIN:-php}"
rm -rf -- "$BUILD_ROOT"; mkdir -p -- "$PAYLOAD" "$OUT"
git -C "$ROOT" archive HEAD -- app config public routes scripts | tar -x -C "$PAYLOAD"
rm -f -- "$PAYLOAD/scripts/verify-uci-payload.sh"
find "$PAYLOAD" -name '.*' -not -name '.' -type f -delete
chmod 700 "$PAYLOAD/scripts/"*.sh 2>/dev/null || true
"$PHP_BIN" "$ROOT/tests/make_payload_zip.php" "$PAYLOAD" "$OUT/TMS_OS_LATEST.zip"
SHA256="$(sha256sum "$OUT/TMS_OS_LATEST.zip" | awk '{print $1}')"
cat > "$OUT/RELEASE.json" <<JSON
{
  "product":"TMS OS","display_name":"TMS OS","internal_version":"17.1.14","build":"20261114-v17.1.14","channel":"stable","version_visible_to_user":true,
  "released_at":"$BUILD_DATE","name":"TMS OS V17.1.14","version":"$VERSION",
  "notes":"V17.1.14: Cảnh báo vận hành qua Telegram nay theo dõi thêm PIN CẠN KHI KHÔNG CÓ ĐIỆN VÀO — đúng nguyên nhân làm server tắt nguồn hai đêm 12/11 và 14/11 mà không ai hay biết. Trước đây mục Cảnh báo vận hành (trang Thông báo) chỉ lo pin sạc đầy 100% quá lâu, bộ nhớ, RAM, nhiệt độ và Tunnel rớt. Nay khi pin xuống dưới ngưỡng báo sớm (mặc định 30%) mà máy không có điện vào, Telegram sẽ cảnh báo ngay để kịp bật ổ cắm từ xa hoặc nhờ người cắm sạc; xuống dưới ngưỡng nguy kịch (mặc định 20%) sẽ có cảnh báo khẩn và nhắc lại dày hơn (mặc định mỗi 15 phút). Mọi bất thường (kể cả các loại có từ trước) khi trở lại bình thường đều có thêm một tin Telegram báo đã hồi phục. Các ngưỡng đều chỉnh được trong trang Thông báo, mục Cảnh báo vận hành. Lưu ý: muốn cảnh báo chạy thì mục này phải đang BẬT (kiểm tra mỗi 15 phút) và máy đã cài termux-api để đo pin.",
  "features":["Cảnh báo vận hành: thêm cảnh báo pin thấp khi không có điện vào — báo sớm ở 30%, báo khẩn ở 20% kèm nhắc lại mỗi 15 phút (các ngưỡng chỉnh được ở trang Thông báo).","Cảnh báo vận hành: bất thường nào trở lại bình thường (pin có điện vào lại, RAM hạ, Tunnel sống lại...) đều có tin Telegram báo đã hồi phục.","Giữ nguyên các cảnh báo cũ: bộ nhớ sắp đầy, RAM quá tải, pin đầy quá lâu, quá nóng, Tunnel rớt — cùng cơ chế cooldown chống spam."],
  "checksum_sha256":"$SHA256","build_date":"$BUILD_DATE","version_display":"TMS OS V17.1.14",
  "download_url":"https://github.com/geogich961-lab/tms-os/releases/latest/download/TMS_OS_LATEST.zip","release_date":"$BUILD_DATE"
}
JSON
