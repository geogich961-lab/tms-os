# Changelog

## V16.1.0 (2026-08-22)

- **Cron Jobs ổn định**: Chạy tác vụ thực tế trên Termux, tự chuẩn hóa ID job, ghi nhận lần chạy/kết quả và giữ nguyên shell giao diện TMS OS trên di động.
- **Telegram an toàn**: Thông báo Cron có xác nhận API; webhook HTTPS `/status` dùng secret, lọc Chat ID, chống update trùng và không trả về bí mật.
- **Báo cáo truy cập theo giờ**: Tổng hợp panel và từng website, request, IP duy nhất, lỗi HTTP và IP khách thật qua Cloudflare Tunnel; không gửi raw log, query, cookie hay token.
- **Cloudflare Tunnel**: Khôi phục IP khách từ `CF-Connecting-IP`, fallback an toàn `X-Forwarded-For` chỉ khi nguồn là loopback cloudflared.
- **Bảo mật**: Thao tác quản trị Cron/Telegram được bảo vệ bởi đăng nhập và CSRF; không bổ sung quyền Root.

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
- **Hotfix Service Worker (Syntax Error Fix)**: Sửa lỗi Service Worker và cập nhật cache PWA.

## V16.0.22 (2026-08-22)
- **Tài liệu hóa tối ưu hệ thống**: Bổ sung hướng dẫn Termux:API và Termux:Boot.

## V16.0.21 (2026-08-22)
- **Làm mới Manifest**: Đổi tên manifest PWA để làm mới icon ứng dụng.
