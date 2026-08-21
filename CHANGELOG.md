## V15.5.2 (2026-08-21)
- **Termux:API Enhanced**: Tăng thời gian chờ (timeout) cho các lệnh API lên 3 giây, giúp hệ thống ổn định hơn trên các dòng máy phản hồi chậm.
- **Diagnostic Messages**: Hiển thị thông báo chi tiết khi API không phản hồi (thường do chưa cấp quyền Android hoặc chưa cài ứng dụng APK) thay vì chỉ báo "Chưa cài".
- **Frontend Sync**: Cập nhật Service Worker và Asset Version lên V15.5.2.

## V15.5.1 (2026-08-21)
- **Fix Cập nhật 2 lần**: Tích hợp `tms_clear_cache()` vào cuối quy trình cập nhật, giúp hệ thống tự động làm mới Asset Version ngay sau khi swap code thành công.
- **Toast Force Top**: Sử dụng JavaScript Inline Style để ép thông báo hiển thị ở phía trên (Top), bỏ qua hoàn toàn các quy tắc CSS bị cache trong trình duyệt.
- **Frontend Sync**: Cập nhật Service Worker và Asset Version lên V15.5.1.

## V15.5.0 (2026-08-21)
- **Toast Position Fix**: Ép vị trí thông báo toast lên phía trên cùng (Top) bằng quy tắc `!important` và `bottom: auto`, đảm bảo thông báo không bao giờ bị dính ở chân trang trên bất kỳ dòng Android nào.
- **Z-Index Fix**: Nâng z-index của thông báo lên mức cao nhất để không bị các thành phần khác che khuất.
- **Frontend Sync**: Cập nhật Service Worker và Asset Version lên V15.5.0.

## V15.4.9 (2026-08-21)
- **Toast Position**: Chuyển vị trí thông báo toast lên phía trên cùng (Top) để người dùng dễ quan sát, không bị che khuất bởi nội dung cuối trang.
- **Toast Settings**: Thêm tùy chọn "Thời gian hiển thị thông báo" trong Cài đặt (cho phép chỉnh từ 1 đến 60 giây).
- **Frontend Sync**: Cập nhật Service Worker và Asset Version lên V15.4.9.

## V15.4.8 (2026-08-21)
- **Hotfix Toast Notification**: Khôi phục hệ thống thông báo toast bị mất do lỗi cú pháp JavaScript khi gộp code V15.4.7.
- **Frontend Sync**: Đồng bộ Service Worker và Asset version mới nhất để đảm bảo trình duyệt tải lại code JS/CSS đã sửa.

## V15.4.7 (2026-08-21)
- **Hoàn thiện Resource Monitor**: Fix lỗi Network RX/TX hiển thị 0/0 bằng cách tự động quét và tính toán lưu lượng trên mọi card mạng đang hoạt động (wlan0, rmnet, eth0, tun0...).
- **CPU Temperature**: Hỗ trợ quét đa cảm biến nhiệt độ (Thermal Zones), tự động fallback sang nhiệt độ pin nếu không đọc được cảm biến trực tiếp.
- **Tối ưu SystemService**: Thêm timeout fallback cho các truy vấn hệ thống qua `/proc` (uptime, loadavg, meminfo) tránh treo PHP-CGI trên một số dòng Android đời mới.
- **Device Info**: Cải thiện nhận diện model máy (Xiaomi, Redmi, Samsung...) qua `getprop` và `cat` fallback.
- **Start-up Clean-up**: Nâng cấp `start-tms.sh` tự động dọn dẹp các tiến trình treo cũ trước khi khởi động, đảm bảo không bị lỗi "Address already in use".

## V15.4.6 (2026-08-21)
- Fix lỗi Resource Monitor hiển thị 0/0: đồng bộ dữ liệu giữa `SystemService` và `MonitoringService`.
- Thêm `data-monitor-value` attributes vào view giúp `app.js` cập nhật dữ liệu live chính xác.
- Tối ưu `memoryInfo` với fallback `shell_exec cat`.

## V15.4.5 (2026-08-21)
- Fix `start-tms.sh` tự dọn dẹp tiến trình treo.
- Cải thiện độ ổn định của dịch vụ ngầm.

## V15.4.4 (2026-08-21)
- Fix lỗi 502/treo PHP-CGI trong `MonitoringService` bằng cách loại bỏ `proc_open` thay bằng `shell_exec` có timeout.

## V15.4.3 (2026-08-21)
- Nâng cấp Resource Monitor lần 1: giao diện mới, thêm biểu đồ lịch sử.

## V15.4.2 (2026-08-21)
- Chuẩn hóa UI toàn panel, fix lỗi Service Worker cache file cũ.

## V15.4.1 (2026-08-21)
- **Tự khởi động máy chủ khi mở Termux**: thêm `tms-session-autostart.sh` + hook trong `~/.bashrc`. Mỗi lần mở Termux, nếu Nginx/PHP Engine/MariaDB chưa chạy sẽ tự khởi động ngầm (1 lần/ngày/phiên, không làm chậm mở app).
- Khắc phục lỗi PWA "Không thể kết nối máy chủ" sau khi Android tắt ngầm Termux (tiết kiệm pin / khởi động lại máy).
- Hook tự động được gắn vào `~/.bashrc` khi cài mới hoặc sửa chữa qua bộ cài.

# V15.4.0 — Fix lỗi Rate limited Cloudflare API

- **Cache API 60 giây** cho các lệnh GET đọc trạng thái (danh sách zone, cấu hình tunnel/ingress, DNS records) — giảm đáng kể số lần gọi Cloudflare API, tránh chạm rate limit
- **Retry tự động** 1 lần sau 5 giây khi API trả lỗi rate limit/throttling
- **Thông báo lỗi rõ ràng** qua toast thay vì im lặng: zone không còn hiển thị "—" khi không lấy được danh sách
- Nhận diện lỗi rate limit chính xác (message + HTTP code 10121)

# V15.3.9 — Tự động kiểm tra & đồng bộ route Cloudflare
- API mới `POST /api/cloudflare-domain/sync-routes`: đọc cấu hình tunnel THẬT trên Cloudflare, so sánh với danh sách tên miền đã gắn; tự động THÊM route còn thiếu vào tunnel (giữ nguyên các route cũ — an toàn cho multi-site, kèm `originRequest.httpHostHeader` đúng hostname).
- Cảnh báo tên miền có record DNS không trỏ về tunnel (thiếu/sai CNAME) kèm tên miền cụ thể.
- Trang Cloudflare Hosting: khi phát hiện tên miền "Chưa có route", hiển thị banner cam "Có N tên miền chưa có route... " kèm nút **Kiểm tra & đồng bộ route** — bấm 1 lần để tự sửa, kết quả hiện dạng toast (bao nhiêu route đã thêm, bao nhiêu DNS cần kiểm tra).
- Kết quả đồng bộ: toast xanh khi mọi route đã đồng bộ đầy đủ; toast cảnh báo khi có tên miền cần kiểm tra DNS; hiển thị lỗi cụ thể nếu không thêm được route.
