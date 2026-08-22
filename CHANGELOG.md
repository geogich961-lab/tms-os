## V16.0.19 (2026-08-22)
- **Cập nhật Icon ứng dụng chuyên biệt**:
  - Sử dụng chính xác hình ảnh người dùng cung cấp (điện thoại vàng trên nền đỏ) để tạo bộ icon PWA (`icon-192.png`, `icon-512.png`).
  - Cập nhật `favicon.png` theo icon ứng dụng mới để đồng bộ trên tab trình duyệt.
  - Đảm bảo tính nhất quán giữa biểu tượng trên màn hình chính và màn hình chào (Splash Screen).
- **Service Worker V16.0.19**: Làm mới bộ nhớ đệm để kích hoạt icon ứng dụng mới ngay lập tức.

## V16.0.18 (2026-08-22)
- **Fix lỗi PWA tại Landing Page**:
  - Tích hợp thẻ `<link rel="manifest">` để trình duyệt nhận diện Landing Page là một phần của ứng dụng PWA.
  - Đăng ký Service Worker ngay tại Landing Page, giúp kích hoạt sự kiện `beforeinstallprompt` nhanh hơn và ổn định hơn.
  - Đồng bộ Favicon và màu nền Splash Screen cho PWA.
- **Service Worker V16.0.18**: Làm mới bộ nhớ đệm để kích hoạt các thay đổi về Manifest ngay lập tức.

## V16.0.17 (2026-08-22)
- **Đồng bộ nhận diện thương hiệu chính thức**:
  - Cập nhật toàn bộ icon PWA (`icon-192.png`, `icon-512.png`) theo logo `tms-os.png` mới nhất.
  - Làm mới `favicon.png` và `logo-landing.png` để đồng bộ trên toàn hệ thống.
  - Chuẩn hóa `manifest.webmanifest`: Cập nhật màu nền (#a70e13) và đổi tên lối tắt "TMS Explorer" thành "File Manager".
  - Service Worker V16.0.17: Ép trình duyệt làm mới bộ nhớ đệm icon và tài nguyên hệ thống.
