#!/bin/bash
# TMS OS Factory Reset Script

HOME_DIR="/data/data/com.termux/files/home"
TMS_DIR="$HOME_DIR/tms-os"
CONFIG_DIR="$HOME_DIR/.tms-os"

echo "Đang bắt đầu quá trình khôi phục cài đặt gốc..."

# 1. Dừng tất cả dịch vụ
echo "Dừng các dịch vụ..."
pkill -9 -f php-cgi
pkill -9 -f nginx
pkill -9 -f cloudflared
pkill -9 -f mariadbd

# 2. Xóa dữ liệu cấu hình và database
echo "Xóa dữ liệu người dùng..."
rm -rf "$CONFIG_DIR/service-manager.json"
rm -rf "$CONFIG_DIR/update-token"
rm -rf "$CONFIG_DIR/runtime_started_at"
rm -rf "$CONFIG_DIR/cloudflare-token"
rm -rf "$CONFIG_DIR/db-mode"

# Xóa database SQLite (nếu có)
find "$TMS_DIR/storage" -name "*.sqlite" -delete
find "$TMS_DIR/storage" -name "*.db" -delete

# 3. Dọn dẹp logs
echo "Dọn dẹp nhật ký..."
rm -rf "$HOME_DIR/logs/services/*"
rm -rf "$HOME_DIR/logs/nginx/*"

# 4. Reset config app về mặc định (nếu cần)
# Ở đây ta giữ nguyên code nhưng xóa trạng thái cài đặt
# Giả sử file app.php có chứa trạng thái, nhưng thường TMS OS dùng DB để check

echo "Khôi phục hoàn tất. Hệ thống sẽ khởi động lại ở chế độ thiết lập."
exit 0
