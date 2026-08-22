# Ghi chú xác minh cơ chế nhận lệnh Telegram

Nguồn chính thức: <https://core.telegram.org/bots/api> (truy cập ngày 22-08-2026).

- Telegram Bot API hoạt động qua HTTPS; phản hồi API có trường `ok`, và khi lỗi có thể có `description` cùng `error_code`.
- Telegram hỗ trợ webhook và có tham số `secret_token`; khi được cấu hình, Telegram gửi token này qua header để máy chủ xác thực request đến.
- Thiết kế sẽ chỉ xử lý tin nhắn từ Chat ID đã cấu hình, lưu trạng thái nhận cập nhật cục bộ và không đưa Bot Token, Chat ID hay nội dung lệnh thô vào log phản hồi.
- Cần lựa chọn giữa endpoint webhook HTTPS để phản hồi gần như tức thời và cơ chế kiểm tra định kỳ nội bộ để không phơi thêm endpoint nhận lệnh.

## Triển khai thử nghiệm V16.1.0

TMS OS đăng ký endpoint HTTPS cố định `/telegram/webhook` bằng `setWebhook`. Khi bật lệnh từ panel, hệ thống chỉ đọc `panel_hostname` đã lưu cục bộ bởi Cloudflare Hosting, tạo secret ngẫu nhiên bằng `random_bytes`, lưu state trong `~/.tms-os/telegram-webhook.json` với quyền `0600`, rồi gửi secret đó tới Telegram. Secret, Bot Token, Chat ID và URL webhook không được trả về giao diện hay trạng thái API panel.

Endpoint luôn trả HTTP `200` với dữ liệu không hợp lệ để không tiết lộ trạng thái xác thực. Trước khi trả lời, nó yêu cầu POST JSON dưới 64 KiB, đối chiếu header `X-Telegram-Bot-Api-Secret-Token` bằng `hash_equals`, kiểm tra đúng Chat ID đã cấu hình, bỏ qua update trùng và chỉ nhận chính xác `/status`, `/status@botname`, `/help` hoặc `/help@botname`. Phản hồi luôn gửi trở lại Chat ID cấu hình thay vì dùng ID từ request.

Lệnh `/status` dùng `MonitoringService` và danh sách Cron đã chuẩn hóa để báo model thiết bị, Android, pin/nhiệt độ nếu có, RAM, lưu trữ, uptime, lưu lượng mạng, số tiến trình, trạng thái dịch vụ và tóm tắt Cron. Báo cáo không bao gồm địa chỉ IP, hostname public, đường dẫn tệp, lệnh hệ thống, token, khoá hay mật khẩu.

Panel cung cấp trạng thái làm sạch và hai thao tác bật/tắt. Các thao tác quản trị, bao gồm lưu Cron hiện có, bắt buộc phiên đăng nhập hợp lệ và CSRF header; bản thân endpoint webhook không sử dụng phiên panel.
