# Cài TMS OS UCI trên Sony Xperia XZ Premium chạy Android 7

## Phạm vi và điều kiện trước khi bắt đầu

> Hướng dẫn này dành cho **Sony Xperia XZ Premium đang chạy Android 7.0 hoặc 7.1.x**, không cần root. TMS OS yêu cầu Android 7.0/API 24 trở lên; Universal Compatibility Installer sẽ tự kiểm tra API, ABI, quyền thư mục runtime, package Termux, PHP server engine và Nginx trước khi cho phép cài đặt.

Xperia XZ Premium sử dụng Snapdragon 835, có RAM 4 GB và bộ nhớ trong 64 GB theo thông số công khai [1]. Cấu hình này phù hợp để thử TMS OS với **SQLite** trước. Android 7 là mức hỗ trợ tối thiểu; không nên cài MariaDB ở ca thử đầu tiên vì SQLite nhẹ hơn, ít tiến trình hơn và đơn giản hơn khi cần khôi phục.

Không dùng Termux cũ từ Google Play. Hãy cài Termux từ [F-Droid][2] hoặc GitHub phát hành chính thức của Termux, và nếu sau này cài thêm Termux:API hoặc Termux:Boot thì phải dùng **cùng nguồn phát hành** với ứng dụng Termux chính.

| Chuẩn bị | Mức tối thiểu khuyến nghị |
|---|---|
| Pin | Trên 50% hoặc cắm sạc trong lúc cài |
| Mạng | Wi-Fi ổn định, không chuyển mạng giữa chừng |
| Dung lượng trống | Từ 3 GB trở lên |
| Quyền root | Không cần |
| Cơ sở dữ liệu ca đầu | SQLite |
| Dữ liệu cũ | Không có; nếu đã có TMS OS, chọn **Sửa chữa**, không chọn Cài mới |

## Phần A — Cài đúng Termux và cấp quyền Android

### Bước 1. Cài Termux

1. Mở trình duyệt trên điện thoại, truy cập [trang Termux trên F-Droid][2].
2. Tải và cài ứng dụng **Termux**. Nếu Android chặn cài APK, chỉ cho phép cài từ nguồn trình duyệt/F-Droid tại màn hình hệ thống yêu cầu; không bật quyền này cho các ứng dụng không tin cậy khác.
3. Mở Termux lần đầu và chờ Termux hoàn tất khởi tạo thư mục `$PREFIX`.

### Bước 2. Cấp quyền Storage cho Termux

Trong Termux, chạy lệnh đầu tiên sau:

```bash
termux-setup-storage
```

Android sẽ hiện hộp thoại xin quyền. Chọn **CHO PHÉP / ALLOW**. Nếu không thấy hộp thoại, mở phần cài đặt bằng đường dẫn sau trên Xperia:

```text
Cài đặt → Ứng dụng → Termux → Quyền → Bộ nhớ/Storage → Cho phép
```

Quay lại Termux, xác minh quyền đã hoạt động:

```bash
test -d "$HOME/storage" && echo "[OK] Đã cấp quyền Storage" || echo "[LỖI] Chưa cấp quyền Storage"
```

Chỉ tiếp tục khi dòng `[OK]` xuất hiện.

### Bước 3. Ngăn Android dừng Termux trong khi cài

Trên Android 7, mở:

```text
Cài đặt → Ứng dụng → Termux → Pin/Battery → Không tối ưu hóa hoặc Tắt tối ưu pin
```

Nếu máy Sony có **STAMINA mode**, không bật STAMINA trong lúc cài. Giữ Termux ở foreground và cắm sạc. Trong Termux, có thể giữ CPU thức tạm thời:

```bash
termux-wake-lock
```

Sau khi cài xong có thể giải phóng wakelock:

```bash
termux-wake-unlock
```

## Phần B — Chuẩn bị package Termux

### Bước 4. Kiểm tra Android API và ABI

Chạy các lệnh sau nguyên văn:

```bash
echo "Android API: $(getprop ro.build.version.sdk)"
echo "Android: $(getprop ro.build.version.release)"
echo "ABI: $(getprop ro.product.cpu.abi)"
echo "PREFIX: $PREFIX"
df -h "$HOME"
```

Kết quả bình thường cho XZ Premium là Android API **24 hoặc 25** và ABI thường là `arm64-v8a`. Nếu API nhỏ hơn `24`, dừng tại đây vì TMS OS không hỗ trợ Android 6 trở xuống.

### Bước 5. Chọn mirror, cập nhật Termux và cài công cụ bootstrap

Trước tiên mở trình chọn mirror:

```bash
termux-change-repo
```

Tại màn hình chọn repository, giữ mặc định **Main repository**, chọn một mirror gần bạn và xác nhận. Sau đó chạy lần lượt từng lệnh:

```bash
pkg update -y
pkg upgrade -y
pkg install -y curl coreutils
```

Xác minh lệnh cơ bản:

```bash
curl --version | head -n 1
sha256sum --version | head -n 1
```

Nếu `pkg update` báo lỗi mirror, chạy lại `termux-change-repo`, chọn mirror khác, sau đó lặp lại ba lệnh trên. Không dùng `apt install` từ một hướng dẫn Debian/Ubuntu; Termux dùng `pkg`.

## Phần C — Kiểm tra UCI trước khi cài

### Bước 6. Xem kế hoạch dependency (không cài gì)

Lệnh này chỉ đọc profile thiết bị và in kế hoạch, không cài package, không xin thêm quyền và không sửa dữ liệu:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh) --plan
```

Đọc phần cuối kết quả. Android API phải từ 24 trở lên. Nếu lệnh báo không tải được compatibility checker, kiểm tra Internet rồi chạy lại lệnh `pkg install -y curl coreutils`.

### Bước 7. Chạy chẩn đoán PHP đầy đủ (tùy chọn nhưng khuyến nghị)

`--diagnose` kiểm tra các PHP server mode thật, vì vậy PHP và Nginx phải tồn tại trước khi chạy. Nếu muốn phát hiện lỗi PHP lock trước khi cài toàn bộ TMS OS, chạy hai lệnh sau:

```bash
pkg install -y php nginx
bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh) --diagnose
```

Nếu muốn cài đặt theo luồng đơn giản nhất, có thể bỏ qua Bước 7 sau khi `--plan` đạt; bộ cài chính ở Bước 8 vẫn sẽ tự cài PHP/Nginx rồi chạy preflight tương tự trước khi tải và thay đổi dữ liệu.

Lệnh tạo báo cáo tại:

```text
~/.tms-os-installer-state/compatibility-report.txt
```

Đọc báo cáo bằng:

```bash
cat ~/.tms-os-installer-state/compatibility-report.txt
```

| Mã trả về | Ý nghĩa | Việc cần làm |
|---:|---|---|
| `0` | Preflight đạt; có engine `fastcgi` hoặc `php-http` | Có thể cài tiếp |
| `10` | Android/ABI không đạt | Dừng, không có cách sửa bằng `chmod` |
| `20` | Termux, package, HOME/PREFIX hoặc temp directory lỗi | Sửa Termux/mirror/quyền và chạy lại diagnose |
| `30` | Mọi PHP server mode đều lỗi | Dừng; không ép cài tiếp vì sẽ gây 502 |
| `40` | Nginx/network không đạt | Dừng và gửi báo cáo |
| `50` | Không thể tạo/đọc state hoặc backup an toàn | Kiểm tra dung lượng và quyền HOME |

Nếu mã là `30`, **không tiếp tục phần cài đặt**. Sao chép nội dung `compatibility-report.txt` sau khi che thông tin riêng tư, hoặc dùng Pilot Kit để tạo archive đã lọc dữ liệu.

## Phần D — Cài TMS OS

### Bước 8. Chạy bộ cài chính thức

Chạy bước này khi `--plan` đạt và, nếu đã thực hiện Bước 7, `--diagnose` trả mã `0`.

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh)
```

Ở lần cài trên máy mới, bộ cài tự nhận diện là **Cài mới**. Bộ cài tự cài các package cần thiết sau khi preflight đạt, bao gồm:

```text
php nginx mariadb curl zip unzip openssh procps coreutils findutils
grep sed gawk which openssl diffutils termux-api psmisc
```

Bạn **không cần tự chạy** lệnh cài toàn bộ danh sách này. Cài bằng bộ cài chính thức giúp kiểm tra dependency, PHP engine, integrity ZIP SHA-256 và rollback theo transaction đúng thứ tự.

### Bước 9. Trả lời các câu hỏi của installer

Khi installer hỏi database, chọn:

```text
SQLite
```

Khi installer hỏi thông tin quản trị, tự nhập:

| Trường | Khuyến nghị |
|---|---|
| Tên tài khoản quản trị | Không dùng tên quá dễ đoán như `admin` nếu có thể |
| Mật khẩu | Từ 14 ký tự, có chữ hoa, chữ thường, số và ký tự đặc biệt |
| Tên website/panel | Tự chọn; không chứa mật khẩu hoặc token |
| Port panel | Giữ mặc định nếu chưa có dịch vụ khác dùng port đó |

Không gửi mật khẩu, token Cloudflare, nội dung `~/.tms-os` hoặc database cho bất kỳ ai. Installer không có mật khẩu mặc định; mật khẩu do bạn tạo trong lúc cài.

Khi được hỏi về auto-start bằng Termux:Boot, bạn có thể chọn `n` ở ca thử đầu tiên. Chỉ bật sau khi panel hoạt động ổn định. Termux:Boot là ứng dụng riêng; nếu cài sau này, phải lấy từ cùng nguồn với Termux [2].

## Phần E — Kiểm tra ngay sau cài đặt

### Bước 10. Kiểm tra engine và panel local

Sau khi installer báo hoàn tất, chạy:

```bash
cd ~/tms-os
bash scripts/tms-php-engine.sh status
curl -i --max-time 5 http://127.0.0.1:8888/login
```

Kết quả tốt là engine ở trạng thái đang chạy và lệnh `curl` trả header HTTP, thường là `200`, `301` hoặc `302`. Nếu thấy `502 Bad Gateway`, không cài lại ngay; chạy:

```bash
cat ~/.tms-os-installer-state/compatibility-report.txt
bash ~/tms-os/scripts/tms-php-engine.sh status
```

### Bước 11. Truy cập panel

Trên chính Xperia, mở trình duyệt và truy cập:

```text
http://127.0.0.1:8888
```

Đăng nhập bằng tài khoản bạn vừa tạo. Chỉ cấu hình Cloudflare Tunnel, domain, MariaDB hoặc Termux:Boot sau khi panel local hoạt động ổn định.

## Phần F — Trường hợp cài lại hoặc lỗi thường gặp

Nếu installer phát hiện TMS OS cũ, nó sẽ hỏi:

```text
[1] Sửa chữa — giữ website, database và tài khoản
[2] Cài mới — xóa sạch dữ liệu cũ
```

Chọn **1** nếu bạn không chủ động muốn xóa dữ liệu. Chỉ chọn **2** sau khi đã kiểm tra backup và đồng ý mất toàn bộ dữ liệu cũ.

| Hiện tượng | Cách xử lý an toàn |
|---|---|
| `Permission denied (13)` / mã 30 | Dừng cài; chạy `--diagnose`, kiểm tra `$PREFIX/var/tmp`, gửi báo cáo đã lọc |
| `pkg` lỗi hoặc không có package | Đổi mirror bằng `termux-change-repo`, chạy `pkg update -y` lại |
| Termux không có `~/storage` | Chạy lại `termux-setup-storage`; kiểm tra quyền Storage trong Android Settings |
| `curl: (56) Recv failure: Connection aborted` tại Bước 4/7 | Đây là kết nối GitHub/CDN bị ngắt, không phải lỗi PHP hay dữ liệu. Chạy lại bộ cài sau ít phút; bản UCI hiện tại tự thử IPv4/HTTP 1.1, sau đó tự thử mạng mặc định. Không xóa `~/tms-os`, không chọn Cài mới và không bỏ qua checksum. Nếu vẫn lặp lại, đổi Wi‑Fi/4G rồi chạy lại cùng lệnh. |
| Installer báo checksum ZIP không khớp | Không bỏ qua; chờ vài phút rồi chạy lại do GitHub có thể đang đồng bộ asset/manifest |
| Panel local không mở | Kiểm tra engine status và `curl` local trước khi kiểm tra Wi-Fi, Cloudflare hoặc domain |

## Tham khảo

[1]: https://www.o2.co.uk/help/phones-sims-and-devices/sony/xperia-xz-premium-android-7-1/specifications "Thông số Sony Xperia XZ Premium"
[2]: https://f-droid.org/packages/com.termux/ "Termux trên F-Droid"
