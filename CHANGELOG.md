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
