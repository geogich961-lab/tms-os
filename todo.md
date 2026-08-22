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

## Khắc phục bộ thực thi Cron

- [ ] Kiểm tra cách `CronJobService` tạo crontab và yêu cầu gói `cronie` trên Termux.
- [ ] Kiểm tra script khởi động TMS có kích hoạt dịch vụ cron sau cập nhật hay không.
- [ ] Bổ sung cơ chế khởi tạo `crond` không cần root, có PID file và tránh chạy trùng.
- [ ] Xác nhận wrapper ghi lại trạng thái chạy/thành công/thất bại đúng vào danh sách job.
- [ ] Xác thực cron mẫu mỗi phút trong môi trường Termux tương thích và đồng bộ nhánh test.
- [ ] Cung cấp lệnh kiểm tra dịch vụ cron trên điện thoại sau khi cập nhật.

## Khắc phục Job ID Cron rỗng

- [x] Chặn trường ID rỗng từ form tạo hoặc sửa Cron Job.
- [x] Chuẩn hóa tự động các cron job cũ không có ID hợp lệ.
- [x] Xác nhận dòng crontab luôn truyền Job ID hợp lệ tới `cron-wrapper.php`.
- [ ] Đồng bộ bản vá lên `develop-v16.1.0` và hướng dẫn người dùng kiểm tra lại.

## Xác thực thông báo Telegram của Cron

- [x] Kiểm tra phản hồi Telegram API thay vì chỉ trạng thái lệnh Cron.
- [x] Lưu kết quả giao thông báo an toàn, không lưu hoặc hiển thị Bot Token.
- [x] Hiển thị kết quả gửi Telegram gần nhất trên trang Cron Jobs.
- [ ] Kiểm thử và đồng bộ bản vá lên `develop-v16.1.0`.

## Khắc phục lỗi giao Telegram

- [x] Lấy thông báo lỗi Telegram đã được làm sạch từ tác vụ Cron trên điện thoại.
- [x] Xác định nguyên nhân cấu hình, xác thực hoặc kết nối dựa trên mã lỗi thực tế: Chat ID đang là ID bot.
- [ ] Thay Chat ID bằng ID người dùng hoặc nhóm hợp lệ, không thay Bot Token.
- [ ] Kiểm tra lại trạng thái `Đã gửi` bằng Cron Job trên thiết bị thật.

## Hoàn thiện giao diện Cron Job Manager

- [x] Rà soát layout, màu sắc và thành phần dùng chung của giao diện TMS OS.
- [x] Thiết kế lại phần tổng quan Cron, danh sách tác vụ và trạng thái Telegram dễ quét.
- [x] Tối ưu biểu mẫu tạo/sửa tác vụ và cấu hình Telegram cho màn hình điện thoại.
- [ ] Kiểm tra responsive, cú pháp PHP/JavaScript và đồng bộ lên `develop-v16.1.0`.
