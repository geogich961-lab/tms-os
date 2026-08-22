## V16.0.25 (2026-08-22)
- **Gỡ bỏ Power Center (Security Revert)**:
  - Loại bỏ hoàn toàn tính năng Power Center (Reboot, Shutdown, Factory Reset) để đảm bảo an toàn cho thiết bị và tuân thủ nguyên tắc không yêu cầu quyền Root.
  - Xóa bỏ mọi logic thực thi lệnh hệ thống cấp cao trong `SystemService` và `UnifiedSystemCoreService`.
  - Dọn dẹp các script hệ thống liên quan (`tms-reset.sh`) để tinh gọn mã nguồn.
- **Bảo mật**: Đưa hệ thống về trạng thái chạy trong môi trường bị cô lập (Non-root), đảm bảo ứng dụng không có quyền can thiệp vào tầng phần cứng của điện thoại.

## V16.0.24 (2026-08-22)
- **Power Center (System Control)**:
  - Thêm trung tâm điều khiển hệ thống tại Dashboard với các tính năng: Reboot Android, Shutdown Android (Yêu cầu Root) và Factory Reset TMS OS.
  - Tích hợp logic thực thi lệnh hệ thống bất đồng bộ trong `UnifiedSystemCoreService` để tránh treo Panel khi thiết bị tắt máy.
- **Factory Reset**: Bổ sung script `tms-reset.sh` để khôi phục cài đặt gốc, xóa sạch dữ liệu cấu hình và database người dùng một cách an toàn.

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
