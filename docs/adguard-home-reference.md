# Nguồn tham chiếu AdGuard Home

- Hướng dẫn bắt đầu chính thức: <https://github.com/AdguardTeam/AdGuardHome/wiki/Getting-Started>
  - Lần khởi chạy đầu, AdGuard Home cung cấp trình thiết lập web trên TCP 3000 theo mặc định.
  - Dịch vụ DNS mặc định dùng UDP/TCP 53; khi không có đặc quyền, cần đặt cổng lớn hơn 1024.
  - Khuyến nghị tải và giải nén gói binary đúng nền tảng, rồi khởi chạy binary trong thư mục làm việc.
- Mã nguồn chính thức: <https://github.com/AdguardTeam/AdGuardHome>
  - AdGuard Home là DNS sinkhole dùng toàn mạng; giao diện quản trị không nên công bố trực tiếp qua Internet nếu không có lớp kiểm soát truy cập phù hợp.

Ghi chú truy vấn ngày 23-08-2026: API GitHub của release ổn định gần nhất liệt kê asset `AdGuardHome_linux_arm64.tar.gz`; trình cài TMS OS phải xác minh binary thực sự khởi chạy và cổng giao diện phản hồi trước khi đánh dấu cài đặt thành công.
