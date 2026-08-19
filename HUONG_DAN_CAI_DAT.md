# TMS OS V14.0.0 — Hướng dẫn cài đặt 1 dòng lệnh

TMS OS là nền tảng biến điện thoại Android cũ thành VPS mini: chạy website, MariaDB, PHP, SSH với panel quản trị PWA tiếng Việt, quản lý dịch vụ qua giao diện web, Guardian tự sửa lỗi, sao lưu/khôi phục, và Cloudflare Tunnel để đưa server ra Internet.

## Cài đặt (3 bước, người dùng chỉ thao tác 2 lần)

**Bước 1.** Tải và cài **Termux** (từ [F-Droid](https://f-droid.org/packages/com.termux) — không tải từ Google Play vì bản cũ).

**Bước 2.** Dán **1 dòng lệnh** sau vào Termux:

```bash
curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh | bash
```

**Bước 3.** Khi hộp thoại Android hỏi quyền truy cập bộ nhớ, nhấn **CHO PHÉP**. Bộ cài sẽ tự động: cập nhật kho gói, cài PHP/Nginx/MariaDB/OpenSSH, tải bộ nguồn TMS OS, kiểm tra chữ ký SHA-256, hỏi tên tài khoản + mật khẩu quản trị, cấu hình và khởi động toàn bộ dịch vụ.

> Khi cài xong, mở trình duyệt: `http://127.0.0.1:8888` (hoặc địa chỉ LAN hiển thị cuối quá trình cài để truy cập từ máy khác trong mạng Wi-Fi).

## Khởi động lại sau này

Mỗi khi khởi động lại điện thoại hoặc muốn bật server:

```bash
bash ~/tms-os/scripts/start-tms.sh
```

### Tự khởi động khi bật máy (V14.0.9)

Bộ cài sẽ hỏi **"Tự khởi động TMS OS khi bật máy?"** — nếu đồng ý (mặc định khi cài qua pipe), server sẽ tự chạy mỗi khi bật nguồn.

Tính năng này dựa trên cơ chế boot của Termux và cần app **Termux:Boot** (miễn phí trên F-Droid). Bộ cài tự mở trang cài nếu app chưa có — sau khi cài, hãy **mở app Termux:Boot 1 lần** để kích hoạt.

Quản lý bất kỳ lúc nào:

```bash
bash ~/tms-os/scripts/tms-boot.sh on      # Bật auto-start
bash ~/tms-os/scripts/tms-boot.sh off     # Tắt auto-start
bash ~/tms-os/scripts/tms-boot.sh status  # Xem trạng thái
```

## Tính năng mới V14.0.9

| Tính năng | Mô tả |
|---|---|
| Auto-start khi bật máy | Tự chạy server mỗi khi khởi động điện thoại, qua cơ chế boot của Termux (`~/.termux/boot/`) |
| Script `tms-boot.sh` | Quản lý bật/tắt/trạng thái auto-start từ dòng lệnh |

## Tính năng mới V14.0.0

| Tính năng | Mô tả |
|---|---|
| Cài đặt 1 dòng lệnh | `curl ... install.sh \| bash` — không cần chép file ZIP thủ công, tự kiểm tra SHA-256 |
| Migration cấu hình | Tài khoản quản trị tự chuyển từ đường dẫn cũ `.redmi-mini-vps` sang `.tms-os`, tương thích ngược với bản cài cũ |
| Version string | `config/app.php` và `RELEASE.json` đồng bộ V14.0.0 |
| Unified Core V13 | Kiến trúc dịch vụ + queue worker + Guardian đã ổn định được giữ nguyên |

## Lưu ý bảo mật

- Mật khẩu quản trị tối thiểu 8 ký tự; panel truyền HTTP trong mạng LAN. Với truy cập từ Internet, hãy dùng tính năng **Cloudflare Tunnel** trong panel.
- Không nên chỉnh sửa file ngoài `~/websites/` và `~/tms-os/scripts/` khi chưa hiểu rõ.

## Gỡ lỗi nhanh

```bash
bash ~/tms-os/scripts/diagnose.sh   # kiểm tra toàn hệ thống
bash ~/tms-os/scripts/repair.sh     # tự sửa lỗi
```
