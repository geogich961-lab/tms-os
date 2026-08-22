## V16.0.17 (2026-08-22)
- **Đồng bộ nhận diện thương hiệu chính thức**:
  - Cập nhật toàn bộ icon PWA (`icon-192.png`, `icon-512.png`) theo logo `tms-os.png` mới nhất.
  - Làm mới `favicon.png` và `logo-landing.png` để đồng bộ trên toàn hệ thống.
  - Chuẩn hóa `manifest.webmanifest`: Cập nhật màu nền (#a70e13) và đổi tên lối tắt "TMS Explorer" thành "File Manager".
- **Service Worker V16.0.17**: Ép trình duyệt làm mới bộ nhớ đệm icon và tài nguyên hệ thống.

## V16.0.16 (2026-08-22)
- **Fix lỗi UI PWA tại Landing Page**:
  - Cải thiện logic phát hiện trạng thái cài đặt: Tự động ẩn nút cài đặt nếu ứng dụng đã được cài đặt trên màn hình chính (standalone mode).
  - Cập nhật thông báo lỗi thân thiện hơn khi trình duyệt chưa sẵn sàng hoặc không hỗ trợ cài đặt tự động.
  - Tối ưu hóa việc hiển thị nút cài đặt: Chỉ hiện khi trình duyệt phát tín hiệu sẵn sàng (`beforeinstallprompt`).
- **Fix lỗi Dashboard Install UI**:
  - Chuyển đổi bố cục nút cài đặt Android/iOS sang dạng xếp chồng dọc (Vertical Stack) trên màn hình di động để tránh tràn chữ.
  - Tối ưu hóa font-size (0.85rem), padding và cho phép chữ tự động xuống dòng (white-space: normal) để hiển thị đầy đủ trên mọi kích cỡ màn hình.
- **Service Worker & Cache Sync**:
  - Cập nhật phiên bản Service Worker lên V16.0.16 để làm mới bộ nhớ đệm.
  - Đồng bộ asset version cho CSS/JS để đảm bảo người dùng nhận được giao diện mới ngay lập tức.

## V16.0.15 (2026-08-22)
- **Nuclear Update Engine (Cơ chế hạt nhân)**: 
  - Thay đổi chiến lược cập nhật từ "Ghi đè" sang "Xóa sạch - Chép mới" (rm -rf rồi cp -af). Điều này bẻ gãy hoàn toàn sự chiếm giữ file của PHP-CGI cũ, đảm bảo file cấu hình được thay đổi 100%.
  - Cưỡng bức xóa file `config/app.php` cũ trước khi chép file mới để vượt qua cơ chế cache của hệ điều hành.
- **Force Kill Engine**: Nâng cấp script khởi động lại với lệnh `pkill -9` cưỡng bức cả `php-cgi` và `nginx`, kết hợp `fuser -k` để giải phóng RAM và cổng kết nối triệt để.
- **Asynchronous Smart Restart**: 
  - Tối ưu hóa phản hồi HTTP: Gửi tín hiệu thành công về trình duyệt trước khi thực hiện kill tiến trình.
  - Smart Delay 2s: Tạo khoảng trễ an toàn để Cloudflare và Nginx đóng kết nối trước khi khởi động lại, loại bỏ hoàn toàn lỗi "Error 520" và "Bad Gateway".
- **OPcache & Stat Cache Purge**: Tự động gọi `clearstatcache()` và `opcache_reset()` cưỡng bức để PHP nhận diện mã nguồn mới ngay lập tức.
