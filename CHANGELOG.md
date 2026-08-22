## V16.0.21 (2026-08-22)
- **Cưỡng bức làm mới Manifest (Ultimate Cache-Busting)**:
  - Đổi tên file cấu hình PWA từ `manifest.webmanifest` sang `tms-pwa-v21.json`. Đây là biện pháp cuối cùng để ép trình duyệt Chrome trên Android phải xóa bỏ hoàn toàn thông tin icon cũ và nạp lại icon điện thoại vàng mới.
  - Cập nhật toàn bộ liên kết manifest trong Landing Page và Dashboard để trỏ vào file cấu hình mới.
- **Service Worker V16.0.21**: Cập nhật để nạp manifest mới và đảm bảo bộ icon `tms-app-icon-*.png` được ưu tiên nạp vào bộ nhớ đệm.

## V16.0.20 (2026-08-22)
- **Bẻ gãy Cache Icon ứng dụng (Nuclear Cache-Busting)**:
  - Đổi tên toàn bộ file icon PWA thành `tms-app-icon-192.png` và `tms-app-icon-512.png`.
