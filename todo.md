# Kênh thử nghiệm V16.1.0

- [x] Kiểm tra thay đổi V16.1.0 hiện có, mã nguồn chưa commit và trạng thái nhánh `main`.
- [x] Xác nhận các tệp ứng dụng nhúng, đặc biệt Typecho Việt Hóa, không bị loại khỏi nhánh thử nghiệm.
- [x] Tạo nhánh GitHub riêng `develop-v16.1.0` từ mã nguồn V16.1.0 hiện tại, không tạo GitHub Release.
- [x] Thêm script cập nhật nội bộ có sao lưu an toàn, chỉ tải từ nhánh `develop-v16.1.0`.
- [x] Kiểm tra cú pháp script và xác thực URL raw GitHub của kênh thử nghiệm.
- [x] Chuẩn bị đúng một lệnh Termux để điện thoại cài đặt/cập nhật bản thử nghiệm.
- [x] Tích hợp lệnh quay về mã nguồn trước thử nghiệm khi cần.

## Khắc phục lỗi cập nhật nội bộ

- [x] Kiểm tra logic `start-tms.sh` và điều kiện tiến trình Nginx/PHP trước khi restart.
- [x] Sửa script test để dừng tiến trình đang chiếm cổng trước khi khởi động lại dịch vụ.
- [x] Sửa điều kiện kiểm tra script khởi động sau rollback, không phụ thuộc nhầm vào quyền thực thi.
- [x] Kiểm tra đường khôi phục khi health check trả về HTTP 500.
- [x] Đồng bộ bản sửa restart lên `develop-v16.1.0` mà không tác động `main` hoặc GitHub Release.
- [x] Cung cấp lệnh khôi phục panel an toàn cho điện thoại trước lần test tiếp theo.

## Sửa Cron Jobs và Telegram

- [x] Xác định hàm toast chuẩn được nạp trong layout panel thay cho `tms_toast` không tồn tại.
- [x] Sửa JavaScript lưu Telegram và tất cả hành động Cron để không dừng vì lỗi thông báo.
- [x] Kiểm tra endpoint lưu Telegram không trả về dữ liệu nhạy cảm.
- [ ] Kiểm tra cú pháp view/controller và đồng bộ bản vá lên nhánh `develop-v16.1.0`.
- [ ] Hướng dẫn cập nhật lại kênh test và dùng Bot Token mới an toàn.
