## V16.0.23 (2026-08-22)
- **Hotfix Service Worker (Syntax Error Fix)**:
  - Sửa lỗi cú pháp nghiêm trọng (lặp dòng) trong file `service-worker.js` khiến trình duyệt không thể đăng ký Service Worker và treo tính năng PWA.
  - Cập nhật phiên bản Service Worker lên V16.0.23 để làm mới toàn bộ cache trình duyệt.
  - Tối ưu hóa việc nạp manifest trong logic fetch của Service Worker.

## V16.0.22 (2026-08-22)
- **Tài liệu hóa tối ưu hệ thống (Documentation Update)**:
  - Cập nhật `README.md` và `HUONG_DAN_CAI_DAT.md` với hướng dẫn chi tiết cách cài đặt `termux-api` để theo dõi Pin/Nhiệt độ và `Termux:Boot` để tự khởi động máy chủ khi bật nguồn điện thoại.
  - Hướng dẫn người dùng cách tải các ứng dụng bổ trợ từ F-Droid để đảm bảo tính tương thích cao nhất.
- **Service Worker V16.0.22**: Cập nhật phiên bản để làm mới cache cho các tài liệu hướng dẫn vừa cập nhật.

## V16.0.21 (2026-08-22)
- **Cưỡng bức làm mới Manifest (Ultimate Cache-Busting)**:
  - Đổi tên file cấu hình PWA từ `manifest.webmanifest` sang `tms-pwa-v21.json`. Đây là biện pháp cuối cùng để ép trình duyệt Chrome trên Android phải xóa bỏ hoàn toàn thông tin icon cũ và nạp lại icon điện thoại vàng mới.
  - Cập nhật toàn bộ liên kết manifest trong Landing Page và Dashboard để trỏ vào file cấu hình mới.
- **Service Worker V16.0.21**: Cập nhật để nạp manifest mới và đảm bảo bộ icon `tms-app-icon-*.png` được ưu tiên nạp vào bộ nhớ đệm.

## V16.0.20 (2026-08-22)
- **Bẻ gãy Cache Icon ứng dụng (Nuclear Cache-Busting)**:
  - Đổi tên toàn bộ file icon PWA thành `tms-app-icon-192.png` và `tms-app-icon-512.png`.
  - Cập nhật manifest để trỏ vào các file icon tên mới, ép trình duyệt phải tải lại ảnh mới 100%.
