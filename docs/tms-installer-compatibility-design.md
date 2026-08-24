# Thiết kế bộ cài TMS OS tương thích nhiều thiết bị

## Mục tiêu thực tế

Không nên đặt mục tiêu “chạy trên mọi thiết bị Android” theo nghĩa tuyệt đối. Termux phụ thuộc Android API, ABI, nguồn APK, chính sách nền của nhà sản xuất, package repository và phiên bản runtime. Mục tiêu khả thi là installer **không làm hỏng dữ liệu**, phát hiện sớm profile không tương thích, tự chọn phương án phù hợp và chỉ cài khi toàn bộ health check đạt.

Termux hiện hỗ trợ đầy đủ cho Android 7.0 trở lên trong các nguồn chính thức phổ biến, với các ABI arm64-v8a, armeabi-v7a, x86 và x86_64 [1] [2]. Termux cũng cảnh báo không trộn app/plugin từ F-Droid, GitHub và Google Play vì khác chữ ký, đồng thời Android 12 trở lên có thể tự kill tiến trình nền [1]. Vì vậy TMS phải kiểm tra các điều kiện này trước khi cài và không tự ý gỡ hoặc thay đổi APK Termux.

## Ma trận hỗ trợ đề xuất

| Nhóm | Điều kiện | Chính sách installer | Mức hỗ trợ |
|---|---|---|---|
| A — chuẩn | Android 7+, ABI được hỗ trợ, Termux từ nguồn chính thức, package repo hoạt động, PHP CLI và FastCGI health check đạt | Cài đầy đủ, bật các dịch vụ đã kiểm tra | Đầy đủ |
| B — giới hạn nền | Điều kiện nhóm A đạt nhưng Android 12+ hoặc nhà sản xuất có cơ chế diệt nền mạnh | Cài đầy đủ, kèm hướng dẫn bỏ tối ưu pin, có health monitor | Đầy đủ có điều kiện |
| C — runtime bất thường | PHP CLI chạy nhưng PHP-CGI/FPM fail, thiếu socket/bind loopback hoặc package không nhất quán | Không cài tiếp; đưa diagnostic bundle và hướng dẫn sửa package | Chờ xử lý |
| D — không hỗ trợ | Android dưới 7, ABI không phù hợp, Termux không chính thức hoặc repo không thể truy cập | Dừng trước khi thay đổi dữ liệu | Không hỗ trợ |

## Quy trình cài đặt mới

Installer phải hoạt động như một giao dịch nhiều giai đoạn. Mỗi giai đoạn ghi trạng thái vào một thư mục tạm ngoài dữ liệu website, ví dụ `$HOME/.tms-os/install-state/`. Nếu thất bại, installer đọc trạng thái cuối cùng và đưa ra lệnh tiếp tục hoặc khôi phục, thay vì chạy lại toàn bộ từ đầu.

| Giai đoạn | Nội dung | Có được sửa dữ liệu người dùng không? |
|---|---|---:|
| 1. Preflight | Kiểm tra Android API, ABI, nguồn Termux, `$PREFIX`, quyền ghi, dung lượng, mạng, port loopback và package manager | Không |
| 2. Download/verify | Tải manifest và bundle, xác thực SHA-256 trước khi giải nén | Không |
| 3. Backup | Sao lưu config, SQLite, MariaDB dump nếu có, website và metadata vào thư mục backup có timestamp | Chỉ đọc |
| 4. Stage | Giải nén vào thư mục staging mới, không ghi đè runtime đang chạy | Không |
| 5. Dependency | Cài hoặc kiểm tra package; không tự xóa package ngoài danh sách TMS | Có, nhưng chỉ package cần thiết |
| 6. Migrate | Di chuyển file bằng rename nguyên tử và chạy migration có kiểm tra | Có, sau khi backup đạt |
| 7. Health check | Kiểm tra PHP CLI, PHP-CGI/FPM, Nginx, SQLite, MariaDB tùy lựa chọn và endpoint localhost | Có thể rollback |
| 8. Commit | Ghi phiên bản active sau khi health check đạt | Có |

Trong chế độ **Sửa chữa**, installer không được reset username, mật khẩu, database, website, Cloudflare token hoặc thư mục người dùng. Trong chế độ **Cài mới**, phải yêu cầu xác nhận rõ ràng trước khi xóa và vẫn nên tạo backup tối thiểu nếu còn dữ liệu cũ.

## Preflight bắt buộc

Preflight cần tạo một báo cáo duy nhất, chẳng hạn `$HOME/tms-os-preflight-YYYYMMDD-HHMMSS.txt`, để người dùng chỉ cần gửi một file khi gặp lỗi. Báo cáo phải bao gồm phiên bản Android/API, ABI, nguồn Termux, phiên bản package, biến môi trường liên quan, quyền thư mục, kết quả tạo file tạm, khả năng bind `127.0.0.1`, trạng thái port và lỗi stderr thật.

Quan trọng nhất là phải kiểm thử **đúng binary sẽ phục vụ web**, không chỉ kiểm thử `php -n -r`. Một thiết bị có thể chạy PHP CLI nhưng PHP-CGI/FPM vẫn fail như trường hợp hiện tại. Installer phải dừng trước bước tạo cấu hình Nginx nếu:

```text
php -n -r 'exit(0);' không đạt
php-cgi -n -b 127.0.0.1:<port-test> không giữ tiến trình
Nginx không vượt qua nginx -t
endpoint test trả 502 hoặc 5xx
```

Mỗi test phải lưu exit code và stderr; tuyệt đối không dùng `|| true` trong preflight chính vì sẽ biến lỗi nghiêm trọng thành thông báo thành công giả.

## Chiến lược PHP engine

Không nên giả định có package `php-cgi` riêng. Trên profile hiện tại, `php-cgi` nằm trong package `php`; vì vậy installer phải kiểm tra `command -v php-cgi` sau khi cài `php`, thay vì chỉ kiểm tra tên package.

Engine nên có ba profile. Profile FPM dùng khi binary FPM tồn tại và master process tạo socket hoặc lắng nghe port thành công. Profile CGI dùng khi `php-cgi` chạy được ở chế độ FastCGI và giữ process. Profile diagnostic dùng khi CLI chạy nhưng cả FPM và CGI fail; profile này không cố fallback vô hạn mà dừng, lưu log và hướng dẫn package repair. Fallback từ FPM sang CGI chỉ có ý nghĩa khi CGI đã qua health check độc lập.

Không nên vô hiệu hóa `LD_PRELOAD` toàn cục. Nếu thử nghiệm chứng minh `libtermux-exec.so` gây lỗi, wrapper chỉ được dùng `env -u LD_PRELOAD` cho tiến trình PHP và phải ghi lý do vào log. Mọi thay đổi biến `TMPDIR`, `TMP`, `TEMP` và `PHP_INI_SCAN_DIR` cũng chỉ áp dụng trong service process, không ghi đè profile người dùng.

## Cơ chế rollback

Trước mọi thay đổi, installer tạo backup bất biến theo timestamp và ghi checksum của các file quan trọng. Bản release mới được giải nén vào thư mục version riêng; symlink hoặc marker `active` chỉ được đổi sau khi health check đạt. Nếu PHP/Nginx fail, marker cũ vẫn được giữ nguyên và installer khôi phục service cũ.

Rollback không được dùng `rm -rf` trên các thư mục dữ liệu không thuộc staging. Mọi thao tác xóa phải kiểm tra canonical path, chặn path traversal và yêu cầu xác nhận trong chế độ cài mới.

## Cách giảm vòng lặp lỗi cho người dùng

Thay vì yêu cầu người dùng chạy nhiều nhóm lệnh, installer nên có hai lệnh rõ ràng:

```bash
# Kiểm tra, không cài và không sửa dữ liệu
bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh) --diagnose

# Cài hoặc sửa chữa sau khi preflight đạt
bash <(curl -fsSL https://raw.githubusercontent.com/geogich961-lab/tms-os/main/install.sh)
```

Tùy chọn `--diagnose` phải trả về mã lỗi theo nhóm: `10` không tương thích Android/ABI, `20` lỗi Termux/package, `30` lỗi PHP engine, `40` lỗi Nginx/network, `50` lỗi dữ liệu hoặc backup. Người dùng chỉ gửi file báo cáo và mã lỗi; không cần chụp nhiều màn hình.

## Lộ trình triển khai

Trước hết cần làm installer v2 chỉ với preflight, staging, backup và health check, chưa thêm tính năng mới. Sau đó kiểm thử trên các profile mô phỏng: thiếu `$PREFIX/var/tmp`, PHP CLI chạy nhưng CGI fail, FPM không tồn tại, port bị chiếm, Android 12+ và dữ liệu TMS cũ. Cuối cùng thử trên ít nhất hai thiết bị thật khác ABI hoặc phiên bản Android trước khi phát hành.

V16.1.27 không nên được phát hành chỉ vì installer tạo được thư mục temp. Điều kiện phát hành phải là PHP-CGI hoặc FPM chạy thật trên thiết bị hiện đang gặp lỗi, Nginx trả HTTP 200, SQLite/MariaDB giữ nguyên dữ liệu và repair không reset tài khoản.

## Kết luận

Cách xây dựng bộ cài đáng tin cậy không phải là tìm một câu lệnh có thể bỏ qua mọi lỗi, mà là **không bao giờ cài mù**. Installer phải biết thiết bị thuộc nhóm nào, kiểm tra đúng engine thực tế, backup trước khi thay đổi, commit nguyên tử sau khi kiểm thử và dừng với báo cáo có thể chẩn đoán. Kiến trúc này không loại bỏ mọi khác biệt giữa Android nhưng sẽ biến lỗi khó hiểu thành trạng thái có kiểm soát và bảo vệ dữ liệu người dùng.

## Tài liệu tham khảo

[1]: https://github.com/termux/termux-app "Termux application repository and compatibility notes"
[2]: https://f-droid.org/en/packages/com.termux/ "Termux on F-Droid: Android requirement and architectures"
[3]: https://termux.dev/en/ "Termux official website"


## Triển khai mã nguồn: preflight và rollback

Installer hiện sử dụng `scripts/lib/installer-safety.sh` làm thư viện dùng chung. Thư viện tạo báo cáo tại `~/tms-os-preflight-YYYYMMDD-HHMMSS.txt`, kiểm tra Android API, `PREFIX`, dung lượng, thư mục tạm, PHP CLI, PHP-FPM/PHP-CGI và Nginx. PHP probe gỡ `LD_PRELOAD` trong phạm vi tiến trình, dùng `PHP_INI_SCAN_DIR=/dev/null` và `sys_temp_dir` riêng; profile Termux toàn cục không bị sửa.

Transaction state nằm ngoài runtime tại `~/.tms-os-installer-state/active.env`, còn backup nằm tại `~/.tms-os-installer-backups/<timestamp>/`. Backup có `MANIFEST.sha256`; rollback xác minh manifest trước khi khôi phục và từ chối bản sao bị thay đổi.

Quy trình là: preflight, tạo backup, ghi phase `backup`, dựng source trong staging, kiểm tra cấu hình, chuyển target cũ sang `.previous`, activate staging, khởi động PHP/Nginx, gọi health check `/login`, rồi mới ghi phase `committed` và xóa marker. Bất kỳ lỗi nào sau khi transaction được tạo đều được xử lý bởi `EXIT` trap; nếu chưa commit, target/runtime/nginx sẽ được khôi phục.

Có thể liệt kê backup bằng:

```bash
bash ~/tms-os/scripts/installer-rollback.sh --list
```

Có thể rollback transaction chưa commit bằng:

```bash
bash ~/tms-os/scripts/installer-rollback.sh
```

Lệnh sẽ yêu cầu gõ `YES`. Chỉ dùng `--yes` khi đã xác nhận đúng transaction:

```bash
bash ~/tms-os/scripts/installer-rollback.sh --yes
```

Installer không xóa dữ liệu trước preflight, không nhận đường dẫn backup tùy ý, không dùng `chmod -R 777`, và không tự động xóa backup sau rollback thất bại.
