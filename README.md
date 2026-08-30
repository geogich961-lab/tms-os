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
- **Cron Jobs & Telegram**: Lập lịch tác vụ, thông báo kết quả, webhook `/status` và báo cáo truy cập theo giờ qua Telegram.

---

## Hướng dẫn cài đặt ổn định một lần

### Yêu cầu chuẩn bị

Thiết bị nên chạy **Android 7.0 trở lên**, có đủ dung lượng trống và dùng kiến trúc được Termux hỗ trợ. Với Xperia XZ Premium Android 7, hãy cài Termux mới nhất tương thích từ [F-Droid](https://f-droid.org/packages/com.termux). Không trộn Termux từ Google Play với Termux:API hoặc Termux:Boot từ nguồn khác vì các ứng dụng bổ trợ có thể không cùng chữ ký.

Cài **Termux:API** nếu muốn đọc pin, nhiệt độ và thông tin thiết bị. Cài **Termux:Boot** nếu muốn tự khởi động dịch vụ sau khi bật máy. Hai ứng dụng này không bắt buộc để cài panel.

### Preflight: cập nhật repository và cài đủ công cụ nền

Mở một phiên Termux mới, cấp quyền bộ nhớ rồi chạy nguyên khối lệnh sau:

```bash
termux-setup-storage
pkg update -y
pkg upgrade -y
pkg install -y curl wget git unzip zip tar openssl coreutils findutils grep sed gawk procps psmisc util-linux
```

Các gói trên cung cấp công cụ tải xuống, giải nén, kiểm tra SHA-256, thao tác tệp, kiểm tra tiến trình và khóa an toàn. **Không cần tự cài PHP, PHP-CGI, Nginx, MariaDB, Redis hoặc Cloudflared trước**; installer sẽ tự kiểm tra và cài phiên bản phù hợp, tránh xung đột cổng và cấu hình trên Android cũ.

Nếu đã cài ứng dụng Termux:API từ cùng nguồn với Termux, cài thêm gói kết nối:

```bash
pkg install -y termux-api
```

### Kiểm tra preflight

Chạy lệnh sau. Nếu hiện `PREFLIGHT_OK`, có thể chạy installer:

```bash
set -e
for cmd in bash curl wget git unzip zip tar openssl find grep sed awk ps pgrep; do
  command -v "$cmd" >/dev/null || { echo "THIEU_LENH: $cmd"; exit 1; }
done
printf 'ARCH=%s\n' "$(uname -m)"
printf 'PREFLIGHT_OK\n'
```

### Chạy installer một dòng

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh)
```

Không đóng Termux trong quá trình cài đặt. Khi Android hỏi quyền truy cập bộ nhớ, chọn **Cho phép**. Installer sẽ tự kiểm tra quyền ghi, thư mục tạm, PHP/PHP-CGI, Nginx, database, cổng dịch vụ và khả năng tương thích trước khi thay đổi hệ thống.

Nếu gặp lỗi mirror hoặc HTTP 403 khi chạy `pkg`, đổi mirror trước rồi chạy lại preflight:

```bash
termux-change-repo
pkg update -y
pkg upgrade -y
pkg install -y curl wget git unzip zip tar openssl coreutils findutils grep sed gawk procps psmisc util-linux
```

Không tải gói `.deb` không rõ nguồn và không bỏ qua kiểm tra checksum của payload TMS OS.

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
- **Phiên bản hiện tại**: V17.0.20 (Latest Stable)

---
*Phát triển bởi THCGaming. Tận dụng đồ cũ - Bảo vệ môi trường - Sáng tạo công nghệ.*
