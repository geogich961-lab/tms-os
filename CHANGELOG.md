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
