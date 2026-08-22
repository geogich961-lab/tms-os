# 🚀 TMS OS — Biến điện thoại Android cũ thành VPS Mini chuyên nghiệp

**TMS OS** (Termux Mobile Server OS) là một nền tảng mã nguồn mở mạnh mẽ giúp tận dụng các thiết bị Android cũ làm máy chủ web (VPS mini). Chỉ với một dòng lệnh duy nhất, bạn sẽ có ngay một môi trường máy chủ hoàn chỉnh với giao diện quản trị Web (Panel) trực quan, hỗ trợ đầy đủ PHP, Database và kết nối Internet toàn cầu.

---

## 🌟 Tính năng nổi bật

- **Cài đặt dễ dàng**: Tự động hóa hoàn toàn quy trình thiết lập môi trường (Nginx, PHP, MariaDB/SQLite).
- **File Explorer**: Trình quản lý tệp chuyên nghiệp hỗ trợ Multi-select, thao tác hàng loạt và tối ưu cho di động.
- **Cloudflare**: Đưa website ra Internet bằng tên miền riêng chính chủ với HTTPS miễn phí (Full Strict).
- **Resource Monitor**: Theo dõi thời gian thực RAM, CPU, Nhiệt độ, Pin và lưu lượng mạng (RX/TX).
- **Database Manager**: Quản lý SQLite/MariaDB trực quan theo phong cách Navicat ngay trên trình duyệt.
- **Guardian System**: Cơ chế tự động phát hiện và sửa lỗi dịch vụ, đảm bảo hệ thống hoạt động 24/7.
- **PWA Support**: Cài đặt Panel quản trị như một ứng dụng gốc trên màn hình chính điện thoại.
- **Auto-Boot & API Support**: Hỗ trợ Termux:Boot để tự khởi động khi mở máy và Termux:API để báo cáo chi tiết thông số thiết bị.

---

## 🛠️ Hướng dẫn cài đặt nhanh

### Yêu cầu chuẩn bị
1. Một điện thoại chạy **Android 7.0** trở lên.
2. Cài đặt ứng dụng **Termux** từ [F-Droid](https://f-droid.org/packages/com.termux).
3. (Khuyên dùng) Cài đặt thêm **Termux:API** và **Termux:Boot** từ F-Droid để sử dụng đầy đủ tính năng.

### Lệnh cài đặt duy nhất
Mở Termux và dán dòng lệnh sau:

```bash
curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh | bash
```

> **Lưu ý**: Trong quá trình cài đặt, nếu Android hiện hộp thoại hỏi quyền truy cập bộ nhớ, hãy nhấn **CHO PHÉP** để tiếp tục.

---

## 📱 Truy cập Panel quản trị

Sau khi cài đặt hoàn tất, bạn có thể truy cập vào giao diện quản lý qua các địa chỉ:
- **Tại chỗ**: `http://127.0.0.1:8888`
- **Mạng LAN**: `http://<IP_ĐIỆN_THOẠI>:8888`

---

## 📊 Thông số tương thích

- **Android tối thiểu**: 7.0 (API 24)
- **Kiến trúc**: aarch64, armhf
- **Dịch vụ đi kèm**: Nginx, PHP 8.x, MariaDB, SQLite, Cloudflared.

---

## 🤝 Đóng góp và Hỗ trợ

Dự án được phát triển liên tục bởi THCGaming. Nếu bạn gặp lỗi hoặc có ý tưởng nâng cấp, hãy tạo **Issue** hoặc **Pull Request** trên GitHub.

- **GitHub**: [geogich961-lab/tms-os](https://github.com/geogich961-lab/tms-os)
- **Phiên bản hiện tại**: V16.0.22 (Latest Stable)

---
*Phát triển bởi THCGaming. Tận dụng đồ cũ - Bảo vệ môi trường - Sáng tạo công nghệ.*
