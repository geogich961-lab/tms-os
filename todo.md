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

## Khắc phục giao diện Cron bị vỡ

- [x] Cô lập nguyên nhân: build `V16.1.0-TEST` không được nhận diện, khiến trình duyệt giữ CSS cũ và SVG phóng đại.
- [x] Buộc làm mới CSS/JavaScript sau mỗi lần cập nhật kênh nội bộ và nhận diện đúng build TEST.
- [ ] Xác thực hiển thị trên desktop và mobile trước khi đẩy lại nhánh thử nghiệm.

## Khôi phục shell chung cho Cron

- [x] Xác minh biến hoặc luồng render khiến Cron bỏ qua layout `os-shell`.
- [x] Khôi phục sidebar, header di động, footer và toast dùng chung cho Cron Jobs.
- [ ] Kiểm tra không phát sinh lỗi menu, toast hoặc dialog trên desktop và mobile trước khi đồng bộ.

## Lệnh trạng thái qua Telegram

- [x] Chọn webhook HTTPS bảo mật để phản hồi lệnh gần như tức thì.
- [x] Thiết kế lệnh `/status`, giới hạn Chat ID và nội dung phản hồi không chứa bí mật.
- [x] Thu thập thông tin thiết bị, tài nguyên và dịch vụ TMS OS có sẵn trên Termux.
- [x] Thêm webhook HTTPS có secret, chống gửi trùng, giới hạn kích thước payload và endpoint quản trị có xác thực/CSRF.
- [x] Thêm kiểm thử service cho secret thiếu/sai, sai Chat ID, lệnh không hỗ trợ, chống trùng và báo cáo an toàn.
- [ ] Kiểm thử bot trả lời trên điện thoại và đồng bộ lên `develop-v16.1.0`.

## Báo cáo truy cập Telegram theo giờ

- [x] Kiểm tra định dạng và vị trí access log Nginx của panel cùng các website do TMS OS quản lý.
- [x] Chọn chế độ chi tiết quản trị: IP đầy đủ, số lượt theo panel/website; loại trừ URL truy vấn, cookie, token và log thô.
- [x] Xác định chính sách riêng tư: tổng hợp IP/đếm truy cập, loại trừ URL truy vấn và không đưa log thô vào Telegram.
- [x] Thiết kế tác vụ theo giờ chạy bằng Cron runtime hiện có, không phụ thuộc tác vụ bên ngoài.
- [x] Tạo dịch vụ tổng hợp, lưu mốc xử lý chống báo trùng và gửi Telegram có xác nhận API.
- [x] Bổ sung điều khiển bật/tắt, gửi thử và trạng thái an toàn vào giao diện Cron Jobs.
- [x] Kiểm thử với access log mô phỏng, cấu hình trusted-proxy, endpoint bị chặn khi chưa đăng nhập và không rò token/URL nhạy cảm.
- [ ] Đồng bộ nhánh thử nghiệm và kiểm thử báo cáo thực tế trên điện thoại.
- [ ] Kiểm thử báo cáo thực tế trên điện thoại trước khi xem xét phát hành ổn định.

## Khôi phục IP khách thật qua Cloudflare Tunnel

- [x] Xác minh header IP mà cloudflared thực tế chuyển vào Nginx và nguyên nhân access log ghi `127.0.0.1`.
- [x] Chỉ chọn header đáng tin cậy từ kết nối loopback của cloudflared, không cho truy cập trực tiếp giả IP.
- [x] Bổ sung tương thích Tunnel trong Nginx, bộ tổng hợp báo cáo và website mới tạo.
- [x] Kiểm thử Nginx thực với `CF-Connecting-IP` và fallback `X-Forwarded-For`.
- [ ] Đồng bộ nhánh thử nghiệm và kiểm thử IP công khai thật, IP LAN trực tiếp trên điện thoại.

## Phát hành ổn định V16.1.0

- [x] Rà soát commits, thay đổi tệp và kết quả kiểm thử trên `develop-v16.1.0`.
- [x] Chuẩn hóa metadata phiên bản từ build TEST sang V16.1.0 ổn định.
- [x] Chạy bộ kiểm thử phát hành, kiểm tra trình cài đặt và luồng cập nhật từ `main`.
- [x] Hợp nhất bản đã xác nhận vào `main`, tạo tag và GitHub Release V16.1.0.
- [x] Xác minh release, asset và hướng dẫn cập nhật một chạm sau phát hành.

## Hotfix bộ cài sau phát hành V16.1.0

- [ ] Tái hiện gói source từ `main` và xác định vì sao thư mục `storage` không tồn tại sau giải nén.
- [ ] Sửa bộ cài repair để tạo các thư mục runtime bắt buộc mà không đụng dữ liệu hiện có.
- [ ] Kiểm thử repair với dữ liệu SQLite, website và cấu hình đã tồn tại.
- [ ] Đóng gói, xác minh checksum và phát hành hotfix có hướng dẫn cập nhật an toàn.
