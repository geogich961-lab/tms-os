# Changelog
## [17.0.23] — 2026-09-05

V17.0.23: harden installer để tự sửa Nginx server_names_hash trước mọi kiểm tra, repair và reload.
- Tự thêm server_names_hash_bucket_size 128 và server_names_hash_max_size 4096 khi thiếu.
- Tự nâng cấu hình cũ 32/512 lên giá trị an toàn trước nginx -t.
- Repair idempotent, không chèn trùng directive khi cài/sửa chữa nhiều lần.
- Giữ nguyên cơ chế backup/rollback, website, database và cấu hình Cloudflare.
- Làm mới cache PWA bằng Service Worker V17.0.23.

## [17.0.22] — 2026-09-04

V17.0.22: sửa upload chunk của File Manager và làm Update Center ưu tiên không downtime khi quản trị qua Cloudflare Tunnel.
- Khôi phục endpoint /files/upload-chunk và /files/upload-complete để upload hoạt động khi JavaScript bật.
- Giữ đầy đủ thao tác File Manager, không còn cần tắt JavaScript để tải ZIP.
- Update Center health-check panel trước; chỉ restart riêng PHP khi thật sự cần.
- Chủ động giữ/khôi phục Cloudflare Tunnel và không tự full-stack restart trong worker cập nhật.
- Làm mới cache PWA bằng Service Worker V17.0.22.

## [17.0.21] — 2026-09-04

V17.0.21: sửa lỗi Website Control Center trên Nginx khi server_names_hash bucket mặc định quá nhỏ; tự repair nginx.conf khi nâng cấp và khi mở panel; sửa HTML pattern tên website cho Chrome mới.
- Tự bổ sung server_names_hash_bucket_size 128 và server_names_hash_max_size 4096 vào nginx.conf cũ theo cách idempotent.
- Update Center tự repair, chạy nginx -t và reload Nginx an toàn trước khi xác nhận cập nhật hoàn tất.
- Panel tự repair cấu hình Nginx còn thiếu để các máy nâng cấp từ V17.0.20 không cần cài lại TMS OS.
- Sửa pattern tên website/clone website để tương thích biểu thức chính quy Unicode v trên Chrome mới.
- Làm mới cache PWA bằng Service Worker V17.0.21.

## [17.0.20] — 2026-08-30

V17.0.20: cảnh báo vận hành qua Telegram theo ngưỡng (bộ nhớ, RAM, pin 100% quá lâu, nhiệt độ, Tunnel rớt) kiểm tra mỗi 15 phút; Guardian tự heal Cloudflare Tunnel và crond; cấu hình trong trang Thông báo.
- Cảnh báo Telegram theo ngưỡng: bộ nhớ trống thấp, RAM cạn, pin sạc 100% quá lâu (nguy cơ phồng pin), nhiệt độ pin cao và Cloudflare Tunnel rớt.
- Kiểm tra mỗi 15 phút qua cron job tms-alerts-check; mỗi loại cảnh báo chỉ nhắc lại sau cooldown cấu hình được.
- Cấu hình ngưỡng và chạy thử ngay trong trang Thông báo; đo pin/nhiệt độ cần termux-api.
- Guardian tự khởi động lại cloudflared khi tunnel rớt và crond khi có cron job bật (tuỳ chọn trong cấu hình Guardian).
- Kèm bảo mật V17.0.18 và backup tự động + offsite rclone V17.0.19.

## [17.0.19] — 2026-08-30

V17.0.19: Backup tự động hằng ngày qua cron + đẩy offsite lên cloud bằng rclone + khôi phục một chạm trong Backup Center; tự dọn bản cũ theo retention và thông báo Telegram.
- Backup tự động hằng ngày theo giờ cấu hình qua cron engine sẵn có, bản backup xuất hiện ngay trong Backup Center.
- Đẩy offsite lên Google Drive/S3/Any S3 bằng rclone (pkg install rclone; remote cấu hình bằng rclone config).
- Tự dọn theo retention (1–90 bản), chỉ xoá đúng các bản tự động, không đụng snapshot đã khoá.
- Khôi phục một chạm: dùng đúng luồng Backup Center hiện hữu, tự tạo snapshot an toàn trước khi restore.
- Tuỳ chọn thông báo kết quả (thành công/lỗi) qua Telegram; kèm bản sửa bảo mật V17.0.18.

## [17.0.18] — 2026-08-30

V17.0.18: cứng hoá bảo mật panel — đăng nhập bị khoá tạm sau 5 lần sai liên tiếp; mọi route mặc định yêu cầu đăng nhập trừ danh sách public trắng; thêm CommandRunner làm điểm gọi lệnh hệ thống chuẩn; routes/web.php tổ chức lại dễ đọc.
- Rate limit đăng nhập: sai 5 lần trong 15 phút bị khoá 15 phút, hiển thị thời gian chờ còn lại.
- Default-deny auth middleware ở Router: quên guard() trong controller không còn làm lộ endpoint; /login, /, /status, /telegram/webhook, /api/public-status, /api/updates/run là public có chủ đích.
- API chưa đăng nhập trả JSON 401 AUTH_REQUIRED thống nhất; trang thường redirect /login?next= giữ nguyên điểm đến sau đăng nhập.
- CommandRunner: điểm gọi lệnh hệ thống tập trung với escapeshellarg bắt buộc cho mọi dữ liệu từ request.
- Giữ nguyên các bản sửa V17.0.17: Update Center chịu lỗi kết nối GitHub, giới hạn upload 100M/110M.

## [17.0.17] — 2026-08-30

V17.0.17: Update Center chịu lỗi kết nối GitHub — báo đúng nguyên nhân từng endpoint, ép IPv4 dự phòng, thêm Chẩn đoán kết nối; PHP engine nâng giới hạn upload 100M/110M để upload ZIP thủ công không bị chặn.
- Update Center báo rõ nguyên nhân lỗi từng endpoint GitHub (DNS, TLS, HTTP 403) thay vì thông báo chung chung.
- Ép IPv4 và bỏ endpoint hỏng — chịu lỗi mạng IPv6/DNS phổ biến trên Android.
- Nút Chẩn đoán kết nối GitHub và API /api/updates/diagnose để xác định bước kết nối bị kẹt.
- PHP-CGI, PHP HTTP và PHP-FPM nâng upload_max_filesize/post_max_size lên 100M/110M — gói TMS_OS_LATEST.zip tải thủ công không còn bị từ chối.
- Fallback metadata RELEASE.json và kiểm tra checksum SHA-256 giữ nguyên, bảo toàn storage/Cloudflare khi nâng cấp.

## V16.1.1 (2026-08-22)

- **Hotfix repair installer**: Không còn dừng khi package source thiếu thư mục runtime `storage`.
- **Giữ dữ liệu an toàn**: Repair tự tạo `storage/logs`, `storage/sessions`, `storage/cache` và tiếp tục giữ website, database, tài khoản, cấu hình hiện có.
- **Đóng gói release**: Bổ sung kiểm tra bắt buộc thư mục runtime trước khi tạo ZIP cập nhật.

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
