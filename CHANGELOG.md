## V16.0.15 (2026-08-22)
- **Nuclear Update Engine (Cơ chế hạt nhân)**: 
  - Thay đổi chiến lược cập nhật từ "Ghi đè" sang "Xóa sạch - Chép mới" (rm -rf rồi cp -af). Điều này bẻ gãy hoàn toàn sự chiếm giữ file của PHP-CGI cũ, đảm bảo file cấu hình được thay đổi 100%.
  - Cưỡng bức xóa file `config/app.php` cũ trước khi chép file mới để vượt qua cơ chế cache của hệ điều hành.
- **Force Kill Engine**: Nâng cấp script khởi động lại với lệnh `pkill -9` cưỡng bức cả `php-cgi` và `nginx`, kết hợp `fuser -k` để giải phóng RAM và cổng kết nối triệt để.
- **Asynchronous Smart Restart**: 
  - Tối ưu hóa phản hồi HTTP: Gửi tín hiệu thành công về trình duyệt trước khi thực hiện kill tiến trình.
  - Smart Delay 2s: Tạo khoảng trễ an toàn để Cloudflare và Nginx đóng kết nối trước khi khởi động lại, loại bỏ hoàn toàn lỗi "Error 520" và "Bad Gateway".
- **OPcache & Stat Cache Purge**: Tự động gọi `clearstatcache()` và `opcache_reset()` cưỡng bức để PHP nhận diện mã nguồn mới ngay lập tức.

## V16.0.14 (2026-08-22)
- **Fix Bad Gateway 502**: Chuyển sang cơ chế khởi động lại bất đồng bộ (Asynchronous Restart). Hệ thống sẽ gửi phản hồi thành công trước khi restart dịch vụ, tránh việc Cloudflare ngắt kết nối đột ngột.
- **Smart Restart UI**: Giao diện Update Center tự động hiển thị trạng thái "Đang khởi động lại..." và chờ 8 giây để các dịch vụ Nginx/PHP-CGI trên Termux lên lại hoàn toàn trước khi reload trang.
- **AJAX Update**: Sử dụng fetch API để áp dụng cập nhật, giúp trải nghiệm mượt mà và không bị gián đoạn trang.

## V16.0.13 (2026-08-22)
- **Atomic Config Overwrite**: Nâng cấp cơ chế ghi đè file cấu hình để vượt qua mọi sự khóa file của hệ điều hành, đảm bảo số phiên bản được cập nhật tuyệt đối.
- **Force Port Liberation**: Tích hợp `psmisc` (lệnh `fuser`) vào bộ cài để cưỡng bức giải phóng cổng 8888/9000 bị treo.
- **OPcache Purge**: Ép PHP nạp lại toàn bộ mã nguồn mới từ đĩa ngay lập tức sau khi cập nhật.
- **System Sync Fix**: Sửa lỗi hiển thị sai phiên bản (V16.0.6) dù giao diện đã đổi mới.

## V16.0.12 (2026-08-21)
- **Process Killer (Thiết quân luật)**: Nâng cấp `start-tms.sh` với lệnh `fuser -k` và `pkill -9` để giải phóng cổng 8888/9000 cưỡng bức, tránh lỗi "Address already in use".
- **Auto-Restart Engine**: Bộ cập nhật tự động gọi script khởi động lại ngay sau khi swap code để ép PHP-CGI cũ phải thoát và nạp code mới từ RAM.
- **Port Conflict Fix**: Tự động phát hiện và xử lý kẹt cổng Nginx khi khởi động.

## V16.0.11 (2026-08-21)
- **Force Overwrite Mechanism**: Sử dụng `cp -af` để ghi đè cưỡng bức các file cấu hình đang bị PHP khóa trên Termux.
- **OPcache Reset**: Tự động xóa bộ nhớ đệm PHP ngay sau khi cập nhật để đảm bảo số phiên bản mới được nhận diện lập tức.
- **Strict Sync**: Đảm bảo đồng bộ tuyệt đối giữa code giao diện và file cấu hình hệ thống.

## V16.0.10 (2026-08-21)
- **Robust Update Engine**: 
  - Thay thế `rename` bằng `cp -rf` để đảm bảo ghi đè file thành công trên môi trường Termux.
  - Tự động xóa thư mục cũ trước khi chép mới để tránh file rác và xung đột.
- **UI Optimization**: Xóa nút "Cập nhật ngay" dư thừa trong phần kiểm tra phiên bản để giao diện gọn gàng hơn.

## V16.0.9 (2026-08-21)
- **Fix Redirect Loop**: Sửa lỗi sau khi đăng nhập bị chuyển về Landing Page thay vì Dashboard.
- **Update Center Reliability**: 
  - Tối ưu hóa quy trình ghi đè file để tránh lỗi Error 520 của Cloudflare.
  - Tự động làm mới `asset_version` sau khi cập nhật để ép trình duyệt tải lại CSS/JS mới nhất.
  - Tăng cường kiểm tra sức khỏe hệ thống trước khi hoàn tất cập nhật.

## V16.0.8 (2026-08-21)
- **Landing Page Perfecting**: Tinh chỉnh giao diện Landing Page khớp 100% thiết kế mẫu.
  - Sử dụng logo `tms-os.png` mới (loại bỏ viền trắng và khung).
  - Màu chữ vàng sáng (Bright Yellow) cho phần giới thiệu trên nền đỏ.
  - Màu chữ đỏ đậm (Red) cho nội dung bên trong các khung vàng và nút bấm.
  - Tối ưu hóa bố cục, khoảng cách và font chữ theo hình mẫu `install-success.webp`.

## V16.0.7 (2026-08-21)
- **Bug Fixes**: 
  - Fix lỗi **Error 520 (Unknown Error)** khi upload logo bằng cách tối ưu hóa bộ nhớ PHP GD (giải phóng tài nguyên ngay sau khi xử lý).
  - Sửa lỗi hiển thị preview logo trong **Brand Center** (loại bỏ hoàn toàn viền đỏ và nền trắng).
- **UI Polish**: Đồng bộ kích thước các nút trong **Update Center** (Kiểm tra/Khôi phục) để giao diện cân đối hơn.

## V16.0.6 (2026-08-21)
- **Landing Page**: Thêm trang giới thiệu công khai với thiết kế Gradient Red/Yellow chuẩn.
- **UI/UX Polish**: 
  - Xóa preview "TMS OS" trong Cài đặt, bỏ viền đỏ logo.
  - Sửa lỗi placeholder "Nhập tên tài khoản" tại trang đăng nhập.
  - Rút gọn mục cài đặt ứng dụng (Android/iOS).
  - Đồng bộ kích thước các nút trong Update Center.
- **Bug Fixes**: Fix lỗi thời gian hiển thị Toast notification (đồng bộ với cài đặt người dùng).
- **Router**: Dashboard chuyển sang `/dashboard`, trang chủ `/` là Landing Page.

## V16.0.5 (2026-08-21)
- **UI/UX Refinement**:
  - Đổi tên **TMS Explorer** thành **File Manager** trên toàn hệ thống (Sidebar, Website view).
  - Đổi tên **TMS Guardian** thành **Guardian** và tối ưu lại khoảng cách giữa các khung giám sát.
  - Loại bỏ phần giải thích "Unified Core" dư thừa trong Service Manager để giao diện tinh gọn hơn.
  - Fix lỗi hiển thị chữ bị lọt dòng trong trang **Cài đặt** trên màn hình nhỏ.
  - Tăng khoảng cách giữa các nút trong **Cloudflare Hosting** (Điều khiển Tunnel) để tránh bấm nhầm trên điện thoại.
- **Update Center Upgrade**:
  - Hỗ trợ **Xóa hàng loạt (Batch Delete)** các gói cập nhật đã tải về, giúp dọn dẹp bộ nhớ nhanh chóng.
  - Đồng bộ hóa kích thước các nút thao tác để giao diện cân đối và chuyên nghiệp hơn.
- **Backend Enhancements**:
  - Nâng cấp `UpdateService` và `UpdateController` để xử lý mảng dữ liệu xóa hàng loạt.

## V16.0.4 (2026-08-21)
- **UI Cleanup**:
  - Loại bỏ khung thông báo màu xanh (alert-info) trong trang Cài đặt để giao diện sạch sẽ và chuyên nghiệp hơn.

## V16.0.3 (2026-08-21)
- **Login UI Polish**:
  - Bo tròn 4 góc cho logo trang đăng nhập (20px) để giao diện mềm mại hơn.
  - Chuẩn hóa viền đỏ phía trên khung đăng nhập thành một đường gradient duy nhất (4px).
  - Cập nhật dòng giới thiệu thành "Mini Android VPS by THCGaming", chuyển sang màu đỏ (#ed1d24) và căn giữa.
  - Tăng kích thước logo trang đăng nhập lên 100x100px để cân đối hơn.

## V16.0.2 (2026-08-21)
- **Official Branding Update**:
  - Triển khai Logo chính thức mới theo thiết kế của người dùng.
  - Áp dụng tông màu **Red Gradient** (A70E13, ED1D24, A70E13) đồng bộ toàn bộ giao diện (nút, thanh tiến trình, tiêu đề, màn hình đăng nhập).
  - Loại bỏ tính năng tùy chỉnh màu sắc trong Cài đặt để giữ vững nhận diện thương hiệu nhất quán.
  - Cập nhật bộ icon PWA, Favicon và Splash screen theo logo mới.

## V16.0.1 (2026-08-21)
- **Cập nhật nhận diện thương hiệu**:
  - Logo mới hiện đại theo phong cách Tech-Minimalism.
  - Bộ icon PWA mới (192x192, 512x512) và favicon.
  - Tối ưu hiển thị logo trên toàn bộ hệ thống.

## V16.0.0 (2026-08-21)
- **TMS Explorer Đại tu**:
  - Hỗ trợ chọn nhiều mục (Multi-select) bằng checkbox hoặc nhấn giữ (long press) trên điện thoại.
  - Thanh công cụ ngữ cảnh (Context Toolbar) thông minh xuất hiện khi chọn mục để thực hiện thao tác hàng loạt.
  - Thao tác hàng loạt (Batch Operations): Xóa, Sao chép, Di chuyển, Nén ZIP, Phân quyền cho nhiều mục cùng lúc.
  - Tối ưu hóa giao diện di động: Icon lớn hơn, khoảng cách chạm rộng hơn, mượt mà và chuyên nghiệp như ứng dụng quản lý file gốc.
- **Backend Batch Support**: Nâng cấp `FileManagerController` và `FileManagerService` để xử lý các mảng dữ liệu file hàng loạt.
- **Frontend Sync**: Cập nhật Service Worker và Asset Version lên V16.0.0.
