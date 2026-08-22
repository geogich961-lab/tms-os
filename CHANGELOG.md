## V16.0.20 (2026-08-22)
- **Bẻ gãy Cache Icon ứng dụng (Nuclear Cache-Busting)**:
  - Đổi tên toàn bộ file icon PWA thành `tms-app-icon-192.png` và `tms-app-icon-512.png`. Việc đổi tên file là cách duy nhất để ép Chrome trên Android xóa bỏ icon cũ và tải lại ảnh mới.
  - Sử dụng chính xác hình ảnh người dùng cung cấp (điện thoại vàng trên nền đỏ) làm nguồn duy nhất cho bộ icon này.
  - Cập nhật `manifest.webmanifest` và `manifest.php` để đồng bộ với tên file mới.
- **Service Worker V16.0.20**: Cập nhật phiên bản để làm mới bộ nhớ đệm và nạp bộ icon tên mới ngay lập tức.

## V16.0.19 (2026-08-22)
- **Cập nhật Icon ứng dụng chuyên biệt**:
  - Sử dụng chính xác hình ảnh người dùng cung cấp (điện thoại vàng trên nền đỏ) để tạo bộ icon PWA (`icon-192.png`, `icon-512.png`).
  - Cập nhật `favicon.png` theo icon ứng dụng mới này.

## V16.0.18 (2026-08-22)
- **Fix lỗi PWA tại Landing Page**:
  - Tích hợp manifest và Service Worker vào Landing Page để trình duyệt nhận diện ứng dụng có thể cài đặt.
