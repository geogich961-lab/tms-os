# TMS OS V15.4.1 — Hướng dẫn cài đặt 1 dòng lệnh

**TMS OS** là nền tảng biến điện thoại Android cũ thành VPS mini: chạy website, PHP, SQLite/MariaDB với panel quản trị PWA tiếng Việt, quản lý dịch vụ qua giao diện web, Guardian tự sửa lỗi, sao lưu/khôi phục, và **Cloudflare Hosting** để đưa website ra Internet bằng tên miền riêng chính chủ (HTTPS miễn phí).

Phiên bản ổn định hiện tại: **V15.4.1** (2026-08-21).

## Cài đặt (3 bước, người dùng chỉ thao tác 2 lần)

**Bước 1.** Tải và cài **Termux** từ [F-Droid](https://f-droid.org/packages/com.termux) (không tải từ Google Play vì bản đã cũ).

**Bước 2.** Dán **1 dòng lệnh** sau vào Termux:

```bash
curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh | bash
```

**Bước 3.** Khi hộp thoại Android hỏi quyền truy cập bộ nhớ, nhấn **CHO PHÉP**. Bộ cài sẽ tự động: cập nhật kho gói, cài PHP/Nginx/MariaDB/OpenSSH, tải bộ nguồn TMS OS, kiểm tra chữ ký SHA-256, hỏi người dùng tự nhập tên tài khoản + mật khẩu quản trị, hỏi chọn cơ sở dữ liệu (SQLite/MariaDB), rồi cấu hình và khởi động toàn bộ dịch vụ.

> Khi cài xong, mở trình duyệt: `http://127.0.0.1:8888` (hoặc địa chỉ LAN hiển thị cuối quá trình cài để truy cập từ máy khác trong mạng Wi-Fi).

## Đã cài TMS OS rồi? Bộ cài tự nhận biết

Khi máy đã từng cài TMS OS, bộ cài sẽ tự phát hiện và hỏi người dùng chọn một trong hai chế độ:

| Chế độ | Khi nào dùng |
|---|---|
| **Sửa chữa** — giữ nguyên dữ liệu (website, database, tài khoản), cài đè bản mới | Bản cài hiện tại gặp lỗi, muốn giữ toàn bộ website và dữ liệu |
| **Cài mới** — XÓA SẠCH mọi dữ liệu cũ, làm lại từ đầu | Muốn bắt đầu hoàn toàn mới, hoặc reset toàn bộ hệ thống |

Ngoài ra, bạn có thể reset toàn bộ hệ thống bất kỳ lúc nào qua `~/tms-os/scripts/factory-reset.sh` hoặc gỡ cài đặt qua `~/tms-os/scripts/uninstall.sh`.

## Khởi động máy chủ

### Khởi động thủ công

```bash
bash ~/tms-os/scripts/start-tms.sh   # bật máy chủ
bash ~/tms-os/scripts/stop-tms.sh    # tắt máy chủ
```

### Tự khởi động khi mở Termux (V15.4.1)

Mỗi lần mở Termux, hệ thống tự kiểm tra: nếu Nginx/PHP Engine/MariaDB chưa chạy sẽ tự khởi động ngầm (chỉ thực hiện 1 lần trong mỗi phiên, không làm chậm khi mở app). Hook tự động được gắn vào `~/.bashrc` khi cài mới hoặc sửa chữa. Tính năng này khắc phục hoàn toàn lỗi "Không thể kết nối máy chủ" khi Android tắt ngầm Termux để tiết kiệm pin.

## Public website bằng tên miền riêng — Cloudflare Hosting

Thay vì dùng tunnel tạm thời, TMS OS tích hợp **Cloudflare Hosting** chính chủ: tạo Cloudflare Tunnel trực tiếp trên tài khoản Cloudflare của bạn, gắn được **nhiều website và nhiều subdomain cùng lúc** (ví dụ `shop.thc.io.vn`, `game.thc.io.vn`), tự tạo record DNS CNAME trỏ `cfargotunnel.com` có bật Proxy — website chạy HTTPS miễn phí như một hosting thực thụ.

Quy trình 3 bước trong panel: **Bước 1** nhập API Token Cloudflare (quyền `Tunnel:Edit`, `Zone DNS:Edit`, `Zone:Read`) → **Bước 2** tạo Tunnel → **Bước 3** gắn tên miền/subdomain và kích hoạt. Hỗ trợ chips tạo subdomain nhanh, trạng thái route thật từ Cloudflare (badge "Route OK"/"Chưa có route"), và tự động kiểm tra & đồng bộ route còn thiếu (V15.3.9). Khi chuyển mạng Wi-Fi (nhà → quán cà phê → di động), website vẫn hoạt động liên tục vì đường truyền đi qua hạ tầng Cloudflare.

## Database Manager kiểu Navicat (V15.3.7+)

Trang **Database** trong panel hoạt động như một Navicat thu gọn: Object Explorer bên trái duyệt các database (TMS OS / website), vùng làm việc bên phải có 3 tab:

| Tab | Tính năng |
|---|---|
| **Dữ liệu** | Grid chỉnh sửa trực tiếp (bấm ô để sửa, Enter lưu), thêm/xóa dòng, tìm kiếm nhanh, lọc theo cột, sắp xếp, phân trang 50 dòng/trang |
| **Cấu trúc** | Xem tên cột, kiểu dữ liệu, khóa chính, sao chép CREATE TABLE |
| **SQL** | Trình soạn thảo SQL, chạy Ctrl+Enter, kết quả dạng grid |

Tự động phát hiện SQLite của website cài trên máy (Typecho, ứng dụng người dùng).

## Tính năng nổi bật theo phiên bản

| Phiên bản | Tính năng |
|---|---|
| **V15.4.1** | Tự khởi động máy chủ khi mở Termux; khắc phục lỗi PWA "Không thể kết nối máy chủ" |
| **V15.4.0** | Fix lỗi Rate limited Cloudflare API (cache 60s, retry, thông báo lỗi rõ) |
| **V15.3.9** | Tự động kiểm tra & đồng bộ route Cloudflare còn thiếu |
| **V15.3.8** | Xác nhận route thật trên tunnel sau khi gắn tên miền (không còn cảnh hoạt động nhưng 404) |
| **V15.3.7** | Database Manager kiểu Navicat: duyệt, sửa, cấu trúc, SQL |
| **V15.3.6** | Toast notification cho mọi thao tác trong panel |
| **V15.3.5** | Cloudflare Hosting multi-site: nhiều website/subdomain trên cùng tunnel |
| **V15.3.3** | Giao diện thiết kế lại: sidebar một cột, font Inter, icon SVG, theme sáng/tối |
| **V15.2.0** | Remote Access: truy cập panel từ xa qua Cloudflare Tunnel |
| **V15.1.0** | Loại bỏ Smart Fallback Engine cũ, chỉ còn Cloudflare Hosting chính chủ |
| **V15.0.0** | Cloudflare Hosting chính chủ: tên miền riêng, HTTPS miễn phí, tạo tunnel/token/CNAME tự động |

## Cập nhật phiên bản

Trong panel: **Update Center** kiểm tra và cập nhật 1 chạm. Từ dòng lệnh (hoặc tự động hàng ngày):

```bash
bash ~/tms-os/scripts/tms-auto-update.sh   # kiểm tra + cập nhật lên bản mới nhất
```

## Lưu ý bảo mật

- Mật khẩu quản trị tối thiểu 8 ký tự; người dùng tự nhập tài khoản/mật khẩu khi cài (hệ thống không tự tạo).
- Panel truyền HTTP trong mạng LAN. Với truy cập từ Internet, dùng **Cloudflare Hosting** trong panel (HTTPS qua hạ tầng Cloudflare).
- Không nên chỉnh sửa file ngoài `~/websites/` và `~/tms-os/scripts/` khi chưa hiểu rõ.
- Sao lưu định kỳ bằng `bash ~/tms-os/scripts/quick-backup.sh`.

## Gỡ lỗi nhanh

```bash
bash ~/tms-os/scripts/diagnose.sh   # kiểm tra toàn hệ thống
bash ~/tms-os/scripts/repair.sh     # tự sửa lỗi
```

Nguồn code: <https://github.com/geogich961-lab/tms-os>
