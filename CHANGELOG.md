# V15.2.1 — Database Manager tự động phát hiện SQLite trong website (Typecho, ứng dụng người dùng)

- Trang Database (chế độ SQLite) trước đây chỉ liệt kê database do TMS OS tạo (`~/.tms-os/data/db/`) — các ứng dụng tự cài như Typecho tạo file SQLite trong thư mục website (`usr/<uniqid>.db`) nên không hiển thị. Giờ tự động quét file `*.sqlite3`, `*.sqlite`, `*.db` trong `~/websites/<site>/public` (đệ quy 3 cấp, bỏ qua `node_modules`/`vendor`/`.git`) và chỉ nhận file SQLite thật (kiểm tra header "SQLite format 3").
- Giao diện chia 2 nhóm: **Database TMS OS** (đầy đủ Tạo/Xuất/Nhập/Xóa) và **Database trong website** (badge tên website + đường dẫn + kích thước; nút **Xuất SQL** và **Mang về quản lý**).
- "Mang về quản lý" copy file về `~/.tms-os/data/db/` để quản lý như database thông thường — file gốc trong website vẫn giữ nguyên (an toàn với dữ liệu đang chạy). Kèm kiểm tra path traversal — chỉ chấp nhận file trong HOME người dùng.

# V15.3.0 — SQL Editor: đọc và chỉnh sửa database trực tiếp trong panel (kiểu Navicat)

- Trang **SQL Editor** mới (menu bên trái, cạnh Database): trình duyệt/chỉnh sửa dữ liệu ngay trong trình duyệt, hỗ trợ cả SQLite và MariaDB.
- **Tab Dữ liệu**: chọn database → chọn bảng → xem dữ liệu dạng bảng (LIMIT 500); **bấm vào ô bất kỳ để sửa trực tiếp** (Enter lưu, Esc hủy); thêm dòng mới qua form tự sinh theo cấu trúc bảng; xóa dòng an toàn theo khóa chính (bảng không có khóa chính không cho xóa).
- **Tab Chạy SQL**: editor monospace chạy câu lệnh tùy ý (Ctrl+Enter), kết quả hiển thị dạng bảng kèm thời gian chạy; có chế độ "Chỉ đọc" bảo vệ không ghi dữ liệu.
- **Tab Cấu trúc**: xem cột, kiểu dữ liệu, giá trị mặc định, khóa chính của từng bảng.
- Bảo mật: mọi câu lệnh chạy qua tiến trình có **timeout 15 giây** (chống treo panel); chặn ATTACH/DETACH (SQLite) và CREATE/DROP/ALTER DATABASE (MariaDB); đọc ghi đều yêu cầu đăng nhập + CSRF.

# V15.2.0 — Remote Access: truy cập panel quản trị từ xa qua Cloudflare Tunnel

- Tính năng mới "Truy cập panel từ xa" trong Cloudflare Hosting: bật bằng một cú bấm, tự chọn subdomain (mặc định `panel.<domain>` của bạn) và tự tạo ingress rule + record DNS CNAME trên cùng tunnel đang có — không tạo tunnel mới, không phá kết nối website hiện tại.
- Panel quản trị có thể truy cập từ bất kỳ máy nào qua Internet (ví dụ `https://panel.thc.io.vn`), không phụ thuộc WiFi/LAN — di chuyển, đổi mạng vẫn hoạt động vì cloudflared tự reconnect.
- Trang panel vốn đã yêu cầu đăng nhập nên an toàn khi bật truy cập từ xa. Có nút tắt riêng (tách subdomain panel, không ảnh hưởng tunnel và website).

# V15.1.2 — Tự khởi động lại tunnel khi hệ thống tắt ngầm cloudflared (khắc phục Error 1033)

- Khi cloudflared bị Android tắt ngầm (cơ chế tiết kiệm pin), website trả Cloudflare Error 1033 "unable to resolve". Trang Cloudflare Hosting giờ tự phát hiện (tunnel đã cấu hình đầy đủ mà tiến trình tắt) và tự khởi động lại tunnel đúng 1 lần mỗi 60 giây, kèm thông báo cho người dùng.

# V15.1.1 — Sửa dropdown Domain trống trong Cloudflare Hosting
- Trang Cloudflare Hosting giờ tự nạp danh sách domain từ Cloudflare ngay khi mở trang, không cần bấm "Kiểm tra & lưu token" trước.
- Endpoint /api/cloudflare-domain/status trả thêm danh sách zones để giao diện hiển thị đầy đủ.

# V15.1.0 — Loại bỏ hoàn toàn Smart Fallback Engine, chỉ còn Cloudflare Hosting

- Xóa hoàn toàn Smart Fallback Engine (tạo URL công khai tạm thời qua localhost.run/Ngrok/Pinggy/TMS Relay) — Cloudflare Hosting với tên miền riêng là cách duy nhất đưa website online.
- Loại bỏ `CloudflareService`/`CloudflareController` cũ cùng toàn bộ route `/internet-access` và `/cloudflare` (tự chuyển hướng sang `/cf-hosting`).
- Trang Cloudflare Hosting bỏ tab "Smart Fallback (cũ)" — giao diện một tab gọn gàng, bỏ logic poll trạng thái cũ.
- Menu điều hướng đổi tên "Internet Access" thành "Cloudflare Hosting".

# V15.0.6 — Tối ưu hiệu năng website qua Cloudflare Tunnel (gzip + cache tĩnh + OPcache + ingress keepalive)
- Cài mới (install.sh): `nginx.conf` toàn cục bật nén gzip (comp_level 4, gzip_types đầy đủ), `tcp_nopush`/`tcp_nodelay`, `open_file_cache`, `keepalive_timeout 65`, `client_max_body_size 500M`, `server_tokens off`; site mặc định và site mới tạo có cache trình duyệt 1 năm cho file tĩnh (ảnh/CSS/JS/font).
- PHP Engine: bật OPcache (`memory_consumption=64`, `revalidate_freq=60`) — tăng tốc biên dịch PHP.
- Cloudflare Hosting: ingress rule mới thêm `originRequest` (`connectTimeout 10s`, `tcpKeepAlive 60s`, `noHappyEyeballs`, `httpHostHeader`) — giảm độ trễ kết nối tunnel.
- Máy đã cài: tab Cloudflare Hosting có mục **HIỆU NĂNG** — nút "⚡ Bật tối ưu hóa hiệu năng" chạy `scripts/optimize-nginx.sh` (backup cấu hình cũ, ghi tối ưu, `nginx -t` trước khi reload, restart PHP, an toàn chạy lại nhiều lần).

# V15.0.5 — Sửa dropdown "Website nội bộ" trống + hostname mặc định = domain gốc (Cloudflare Hosting)
# V15.0.4 — Thêm fallback lấy account_id từ /zones, hướng dẫn token Cloudflare đầy đủ (Tunnel:Edit + Zone DNS:Edit + Zone Zone:Read)
# V15.0.3 — Token Cloudflare không còn bắt buộc quyền "Account Settings: Read"

- Sửa lỗi xác thực API Token báo "Không thể đọc thông tin tài khoản. Hãy kiểm tra quyền Account Settings: Read" dù token đã đúng quyền Cloudflare Tunnel (Edit) + Zone DNS (Edit). `accountInfo()` giờ thử theo thứ tự: (1) `/accounts` — đọc account ID trực tiếp, (2) fallback `/user/memberships` — lấy tài khoản từ danh sách membership, (3) fallback giữ `account_id` đã lưu trong cấu hình trước đó. Chỉ báo lỗi khi cả ba cách đều không có dữ liệu.

# V15.0.2 — Sửa race condition chữ ký SHA-256 khi cài đặt

- Sửa lỗi "File tải về không khớp chữ ký SHA-256" khi chạy lại installer ngay sau khi phát hành bản mới: GitHub CDN có thể trả file ZIP của release mới nhưng `RELEASE.json` cũ (checksum chưa cập nhật trên branch main), khiến xác minh thất bại. Installer giờ tự tải lại tối đa 4 lần (cách nhau 5 giây) trước khi dừng an toàn, kèm in rõ EXPECTED/ACTUAL để dễ chẩn đoán.


# V15.0.1 — Sửa lỗi cài đặt 500 (thiếu require class CloudflareDomain)

- Sửa lỗi installer dừng ở bước 6/7 với `curl (22) error 500` khi khởi động PHP Engine: `public/index.php` thiếu khai báo `require` hai class mới `CloudflareDomainService` và `CloudflareDomainController` (V15.0.0), gây PHP Fatal "Class not found" trên Termux. Đã thêm vào danh sách require trực tiếp như các service khác.


# V15.0.0 — Cloudflare Hosting: website qua tên miền riêng chính chủ

- Thay thế chế độ tunnel tạm thời bằng **Cloudflare Hosting** chính chủ: người dùng cung cấp API Token Cloudflare, hệ thống lấy tài khoản và danh sách domain tự động qua API, tạo **Cloudflare Tunnel** trực tiếp trên tài khoản, gán hostname (ví dụ `shop.example.com`), tự tạo record DNS **CNAME** trỏ `cfargotunnel.com` (có bật Proxy — website đi qua hạ tầng Cloudflare, HTTPS miễn phí) và chạy `cloudflared` bằng tunnel token.
- Luồng 3 bước rõ ràng: **Bước 1** kiểm tra & lưu API Token → **Bước 2** tạo Tunnel → **Bước 3** gắn tên miền & kích hoạt. Điều khiển đầy đủ: khởi động/dừng tunnel, tách tên miền (giữ tunnel), xóa tunnel khỏi Cloudflare, xóa toàn bộ cấu hình.
- Trạng thái real-time: trạng thái tunnel từ API Cloudflare (healthy/degraded/inactive), số kết nối, tiến trình local, nhật ký và URL công khai kèm nút sao chép/mở.
- Tính năng tunnel cũ (Smart Fallback: Cloudflare → Pinggy → localhost.run → Ngrok → TMS Relay) vẫn giữ nguyên trong tab riêng để ai cần dùng nhanh không cần tên miền.
- Dọn nhãn phiên bản cũ trong giao diện (V10.3.1, V11.1, V13, V13.0.1) — tất cả giờ thống nhất "TMS OS V14 · <TÊN CHỨC NĂNG>".

# V14.2.3 — Thanh menu trên điện thoại luôn cố định khi cuộn trang
- Sửa thanh header chứa menu trên điện thoại vẫn bị cuộn theo trang: thanh menu giờ dùng `position:fixed` (thay `sticky` bị phá vỡ), luôn bám trên cùng màn hình khi cuộn dù đang mở trang nào.
- Header giờ hiển thị đè nhẹ lên nội dung theo kiểu hiện đại, an toàn với safe-area trên iPhone (có notch/khung đen), nội dung chính tự đẩy xuống tránh bị che.
- Khi menu ngang đang mở, nút ☰ vẫn luôn bấm được để đóng menu mà không cần cuộn lại lên đầu trang.

# V14.2.2 — Sửa 3 lỗi giao diện V14.2.0
- Sửa SyntaxError trong app.js: khai báo `setRelative` trùng (2 lần) làm toàn bộ menu toggle, nút "Cài TMS OS lên màn hình chính" và theme toggle không hoạt động.
- Sửa logo header/trang đăng nhập bị mất: helper `tms_brand_icon('logo')` trả đường dẫn vào thư mục `icons/` không chứa file logo — giờ trả đúng `/assets/logo-tms-os.png`.
- Sửa icon PWA bị thu nhỏ có viền trắng: manifest và tính năng đổi logo giờ dùng icon solid chiếm đầy nền (`icon-192-solid.png`/`icon-512-solid.png`/`icon-maskable-solid-512.png`) — biểu tượng lấp đầy màn hình chính Android/iOS, không còn viền trắng.

# V14.2.1 — Tính năng Xóa cache trong Cài đặt
- Cài đặt → Xóa cache: xóa các session cũ (giữ phiên đăng nhập hiện tại), tệp tạm trong storage/cache, flash file cũ và bộ nhớ đệm PHP (nếu có opcache). Sau khi xóa, tự động tăng phiên bản cache để trình duyệt và PWA tải lại toàn bộ giao diện mới (CSS, JS, biểu tượng, manifest) — hữu ích sau khi cập nhật phiên bản mới để đảm bảo thấy đúng giao diện mới nhất.

# V14.2.0 — Nâng cấp giao diện: menu cố định dạng lưới, Cài đặt ứng dụng (PWA), đổi logo tùy chỉnh

- Menu trái trên mobile: cố định không cuộn theo trang — thanh header chứa nút menu luôn hiển thị ở đầu màn hình khi cuộn trang; menu mở rộng chuyển sang dạng lưới 2 cột (icon + tên) giúp thao tác nhanh hơn.
- Nút "Cài TMS OS lên màn hình chính" xuất hiện ở cuối menu và trên Dashboard (card Cài đặt ứng dụng) — bấm 1 lần để cài PWA lên màn hình chính Android; iPhone/iPad có nút "Chỉ dẫn cho iPhone/iPad" kèm 3 bước thêm vào màn hình chính.
- Trang đăng nhập gọn hơn: bỏ dòng chữ "TMS OS by THCGaming" (chỉ giữ logo) và bỏ giá trị mặc định trong ô tài khoản — giờ dùng placeholder chuẩn, nhập ngay không cần xóa.
- Cài đặt → Logo & Thương hiệu: upload logo mới (PNG/JPG/WebP, tối thiểu 128x128, tối đa 2048x2048, dưới 2 MB, khuyến nghị 512x512 vuông). Logo được tự động crop vuông, tạo đủ icon 192/512/maskable 512 và áp dụng ngay cho menu, trang đăng nhập, biểu tượng PWA trên Android và iPhone/iPad. Có nút khôi phục logo mặc định.

# V14.1.7 — Internet Access thân thiện với mạng công cộng (WiFi quán/doanh nghiệp)

- Internet Access trả "Không có nhà cung cấp tunnel nào hoạt động (HTTP 0)" trên nhiều mạng WiFi công cộng: các mạng này thường chặn hoặc làm chậm kết nối tunnel lần đầu. Sửa: probe xác minh URL công khai retry 3 lần (nghỉ 3s giữa các lần) thay vì bỏ cuộc ngay khi HTTP 0; thời gian chờ đăng ký tunnel trước khi chuyển nhà cung cấp 35s → 60s; đổi thứ tự tự động chọn provider: localhost.run lên trước Pinggy (Pinggy dùng SSH port 443 dễ bị chặn hơn trên mạng doanh nghiệp).

# V14.1.6 — Update Check bền vững với mạng di động

- Bấm "Kiểm tra cập nhật" báo "Không thể kết nối GitHub": nhiều mạng di động chặn/làm chậm `api.github.com`. Sửa: retry 3 lần với backoff, thử thêm endpoint dự phòng `www.github.com/api/v3`, tăng timeout (15s → 20s cho API, 60s → 90s cho tải ZIP), thông báo lỗi tiếng Việt rõ ràng kèm hướng dẫn.
- Tải gói cập nhật (cập nhật 1 chạm + API tự cập nhật) cũng dùng cơ chế retry 3 lần mới.

# V14.1.5 — Sửa lỗi SQLite mode (System Check 500 + Internet Access)

- System Check (Kiểm tra hệ thống) trả HTTP 500 khi dùng SQLite: `DiagnosticsService` duyệt danh sách dịch vụ cứng gồm MariaDB dù `definitions()` chỉ thêm MariaDB trong chế độ mariadb → throw "Dịch vụ không hợp lệ". Sửa: duyệt theo `definitions()` động.
- Dashboard/Runtime Packages/Guardian: thống nhất chỉ hiển thị và điều khiển MariaDB khi chế độ database là mariadb — người dùng SQLite không còn thấy mục MariaDB "dừng" gây nhầm lẫn.
- Internet Access: thông báo lỗi tiếng Việt rõ ràng khi website nội bộ chưa phản hồi (hướng dẫn khởi động lại dịch vụ từ Trang chủ) thay vì lỗi tiếng Anh gây khó hiểu.

# V14.1.4 — Sửa lỗi bộ cài (Repair Mode)

- Sửa lỗi `INSTALL_MODE: unbound variable` tại dòng 28 khi chọn "Sửa chữa" — biến được dùng trước khi gán giá trị với `set -u` bật (`$INSTALL_MODE` → `${INSTALL_MODE:-}`).
- Sửa lỗi sao lưu trong chế độ sửa chữa: tạo thư mục `$BACKUP` trước khi copy dữ liệu (trước đây `cp` báo lỗi "No such file or directory", luồng vẫn an toàn nhờ `|| true` nhưng không sao lưu được).

# V14.1.3 — Update Center hoàn chỉnh + Tối ưu bộ cài
- Update Center: Cập nhật 1 chạm trong panel — tải bản mới nhất từ GitHub, sao lưu, swap, kiểm tra sức khỏe, rollback tự động khi lỗi.
- API tự cập nhật 1 lệnh: POST /api/updates/run với token riêng (~/.tms-os/update-token) — dùng được cho script/tự động hóa.
- Rollback an toàn: chỉ dùng backup có đủ các phần thiết yếu (app/public/config), bỏ qua backup rỗng/không đủ.
- Flash messages bền vững qua swap (lưu ~/.tms-os/flash.json) — thông báo không mất khi target bị thay thế.
- Bộ cài tối ưu cho điện thoại thật: pre-check RAM/disk trước khi cài MariaDB (đề xuất SQLite cho máy yếu), kiểm tra php-zip trước khi cài, repair mode bỏ qua bước tạo tài khoản, retry pkg mạnh hơn.
# V14.1.2 — TMS Explorer nâng cấp
- File Manager: thêm Sao chép tệp/thư mục (tự đổi tên khi trùng, có cho phép ghi đè).
- File Manager: thêm Di chuyển tệp/thư mục sang thư mục khác (nút "Dùng thư mục này" để chọn đích nhanh).
- File Manager: phân quyền (chmod) qua giao diện — preset 600/644/700/755, hỗ trợ recursive cho thư mục.


# V14.1.1 — Mirror 404 recovery (pkg install auto-retry with --fix-missing + mirror switch)

- Tự động retry khi tải gói Termux lỗi 404 (mirror chưa cập nhật): cập nhật lại kho → apt-get install --fix-missing → đổi sang mirror dự phòng nếu vẫn lỗi.

# V14.1.0 — Smart Installer (Clean/Repair) + Mandatory Accounts + Android 7.0+

- Installer detects an existing TMS OS installation and asks the user to choose: [1] Repair (keeps all websites, databases, accounts; reinstalls the core) or [2] Clean install (wipes all old data after typing YES to confirm).
- Repair mode preserves the existing database engine, datadir and nginx/php config; clean mode stops services and removes everything before provisioning.
- Panel admin credentials are now ALWAYS entered by the user at install time (username + password with retype confirmation, minimum 8 characters) — no more auto-generated temporary passwords.
- Installer no longer runs non-interactively via pipe when a previous install is detected; users are directed to run it interactively.
- Added Android 7.0 (API 24) compatibility checks with clear error messages for unsupported devices.

# V14.0.9 — Auto-Start on Boot + Installer Fixes

- Added `tms-boot.sh`: auto-start TMS OS when the device boots, via the Termux boot mechanism (`~/.termux/boot/`). Supports `on`/`off`/`status`.
- Installer asks whether to enable auto-start at install time (defaults to yes in pipe mode); auto-opens F-Droid page if Termux:Boot app is missing.
- Fixed `RC: unbound variable` error at the end of the root installer (`set -u` safety).
- Bumped version string to Platform V14.0.9.

# V13.0.1 — LAN Address Stability Update

- Fixed LAN IP discovery on Android/Termux.
- Removed invalid 127.0.0.1.sslip.io publication.
- Added short direct per-site LAN URLs.
- Added safe fallback when Wi-Fi IPv4 is unavailable.

## V12.1.3 — Restart All Reliability Hotfix

- Deferred PHP-FPM restart outside the active HTTP request.
- Added service-specific health checks and verification windows.
- Process PHP last in Restart All.
- Fixed false failure reports for Nginx and early failure reports for MariaDB.

## V12.1.2 — Installer Compatibility Hotfix

- Removed low-level flock from PHP Engine control script.
- Added cleanup for stale experimental lock files during installation.
- Preserved web-layer concurrency protection in Service Manager Pro.

# TMS OS Platform V12.0.1 — Resource Monitor Stability Hotfix

- Fixed a blocking Termux:API battery probe that could exhaust PHP-FPM workers and cause 504 responses on ports 8888 and 8080.
- Added strict command timeouts and short-lived monitoring cache.
- Improved Guardian PHP-FPM detection and startup reliability.

# TMS OS Platform V12 — Self-Healing Mini VPS

- Added TMS Guardian background watchdog and health dashboard.
- Added verified HTTP health checks and automatic PHP-FPM recovery.
- Added restart-rate protection, structured event logs, MariaDB/Nginx checks, and storage warnings.
- Hardened PHP Engine control with locking, graceful shutdown, stale socket/PID cleanup, and duplicate prevention.

# TMS OS Platform Stable V9

- Viết lại Cloudflare Quick Tunnel thành V2.
- Tự kiểm tra website nội bộ trước khi mở tunnel.
- Theo dõi tiến trình và trạng thái theo thời gian thực.
- Tự phát hiện URL trycloudflare.com và hiển thị nút Mở/Sao chép.
- Phát hiện timeout, DNS, QUIC, SSL và lỗi kết nối phổ biến.
- Cho phép chọn Auto, HTTP/2 hoặc QUIC.
- Không còn báo 'Đang chạy' khi tiến trình đã chết.

# TMS OS Platform Stable V8

- Tập trung toàn bộ kết nối MariaDB qua `DatabaseService`.
- Tạo tệp client riêng với quyền 0600, tránh tài khoản rỗng khi gọi CLI.
- Sửa App Installer WordPress: tạo database, user, phân quyền và rollback đồng bộ.
- Sửa import/export/list/create/drop database.
- Ẩn số phiên bản khỏi header, sidebar và trang cài đặt.
- Giữ thông tin phiên bản nội bộ trong `RELEASE.json`.
- Bổ sung khả năng repair tự tạo lại cấu hình MariaDB.

# TMS OS – Zero Command Installation

- Thêm bootstrap installer `TMS_OS_Installer.sh` dành cho người dùng mới.
- Tự cấp quyền bộ nhớ theo hướng dẫn, cập nhật kho và cài công cụ giải nén.
- Tự tìm gói ZIP trong Download, kiểm tra tính toàn vẹn và giải nén vào vùng tạm.
- Phát hiện bản cài hiện có và cho phép giữ dữ liệu hoặc Clean Setup.
- Chạy wizard với tiêu đề `Welcome to TMS OS by THCGaming`.
- Không hiển thị số phiên bản trong trải nghiệm người dùng.
- Tự mở Panel sau khi cài nếu thiết bị hỗ trợ `termux-open-url`.
- Ghi nhật ký cài đặt riêng để chẩn đoán khi có lỗi.

## Platform Stable V8 UI/PWA Fix
- Rebuilt opaque any/maskable PWA icons to remove launcher corner artifacts.
- Removed the in-page splash from module navigation.
- Removed CSS rounding and shadows from admin branding logo.
- Bumped service-worker cache for immediate asset refresh.

## V10.1 Mobile Settings Access Fix
- Sửa menu mobile không cuộn được.
- Hiển thị đầy đủ System Check và Cài đặt.
- Ghim nhóm thao tác cuối menu, hỗ trợ safe-area Android.


## Platform Stable V10.1 — Local Domain & LAN Access Center
- Alias `.localhost` cho từng website.
- Reverse proxy theo hostname trên cổng 80 khi thiết bị hỗ trợ; tự chuyển sang 8088 khi cần.
- Smart LAN URL bằng `sslip.io` theo IP Wi-Fi hiện tại.
- Xuất file hosts cho tên miền `.lan`.
- WordPress tự nhận local, LAN và Cloudflare hostname.


## V10.2.1 — PWA Icon & Navigation Polish
- Chỉ sử dụng splash hệ thống khi mở PWA; loại bỏ hoàn toàn splash HTML giữa các trang.
- Chuyển module trực tiếp, không hiện logo/loading khi bấm menu.
- Icon PWA mới có vùng trong suốt và logo thu nhỏ, tránh viền đỏ lớn quanh biểu tượng.
- Splash hệ thống dùng nền tối và icon có khoảng thở, không còn logo vuông quá lớn.
- Tăng phiên bản cache để Android/PWA nhận tài nguyên mới.

## V10.2.2 — Cloudflare Tunnel Verified Health
- Chỉ báo “Đã kết nối” sau khi URL công khai được kiểm tra thực tế.
- Phát hiện riêng lỗi Cloudflare 1033 thay vì báo thành công giả.
- Chờ log đăng ký edge `Registered tunnel connection` trước khi xác minh.
- Tự tạo lại Quick Tunnel một lần bằng HTTP/2 khi lỗi 1033 lặp lại.
- Cô lập `config.yml` cũ bằng `--config /dev/null` để tránh xung đột Quick Tunnel.
- Theo dõi PID chính xác, trạng thái tiến trình, đăng ký edge và mã HTTP.
- Không dừng các named tunnel Cloudflare khác trên thiết bị.


## V10.3 — Internet Access Center & TMS Relay
- Thay Cloudflare-only bằng Smart Fallback Engine.
- Hỗ trợ Cloudflare, Pinggy, localhost.run, Ngrok và TMS Relay.
- Tự chuyển nhà cung cấp khi mạng công cộng chặn tunnel hiện tại.
- Kiểm tra URL công khai trước khi báo kết nối thành công.
- Thêm cấu hình reverse SSH tới VPS riêng.
- Giữ tương thích route Cloudflare cũ.


## V10.3.1 — Verified Tunnel URL Engine
- Corrected Pinggy SSH endpoint to `free.pinggy.io`.
- Rejects `dashboard.pinggy.io` and provider landing pages.
- Provider-specific hostname allowlists and redirect validation.
- Public response must be HTTP 2xx/3xx and match the local website content.
- Prevents false-positive connections caused by generic HTTP 200 pages.


## V11.5 — Mini VPS Foundation Suite
- V11.1 Package Manager with guarded install/remove and version detection.
- V11.2 Service Manager with status, PID, logs and autostart.
- V11.3 File Explorer Pro search and safe permission updates.
- V11.4 richer resource monitoring for Android/Termux.
- V11.5 expanded App Marketplace with local starter templates.


## V11.5 — Mini VPS Foundation Suite
- V11.1 Package Manager with guarded install/remove and version detection.
- V11.2 Service Manager with status, PID, logs and autostart.
- V11.3 File Explorer Pro search and safe permission updates.
- V11.4 richer resource monitoring for Android/Termux.
- V11.5 expanded App Marketplace with local starter templates.

## V12.1.1 — PHP Engine Lock Hotfix
- Fixed a self-deadlock in `tms-php-engine.sh restart` caused by acquiring the same flock twice.
- Restart now performs stop and start under one lock acquisition.
- Installer retries PHP Engine startup up to three times and reports the engine log path on failure.

## V12.2.0 — Service Core Rewrite
- Replaced synchronous PHP service execution with atomic queue jobs.
- Added independent `tms-service-worker.sh` and `tms-service-core.sh`.
- Added Termux-specific adapters and readiness checks for all managed services.
- Fixed PHP self-restart architecture and MariaDB premature failure detection.
- Fixed PID reporting by using service-specific master-process detection.

## V13.0.0 — Unified Core
- Added `UnifiedSystemCoreService` as the single source of truth for installed/running state, PID, version, RAM, threads and uptime.
- Service Manager, Dashboard/SystemService, Diagnostics, Terminal and Guardian now use the same Termux adapters.
- Guardian repairs now call `tms-service-core.sh` instead of a separate PHP process detector.
- Installer clears stale V12 queue jobs, pending state and worker locks during upgrade.
- PWA and asset caches bumped to V13.0.

# V14.2.4 — Sửa header menu biến mất trên điện thoại
- V14.2.3 bị lỗi: rule CSS `.mobile-header{display:none}` toàn cục (không có media query) đứng sau rule hiển thị header → trên mọi kích thước màn hình điện thoại, thanh menu bị ẩn hoàn toàn. Đã xóa rule toàn cục này; header giờ luôn hiện với vị trí cố định (position:fixed) khi truy cập bằng điện thoại.

# V14.2.5 — Dọn nhãn phiên bản cũ trong giao diện
- Thay các nhãn phiên bản nội bộ cũ hiển thị ở đầu các trang (V10.3.1, V11.1, V11 CORE, V13, V13.0.1) bằng nhãn đồng bộ hiện đại `TMS OS V14 · [TÊN PHÂN HỆ]` — giao diện chuyên nghiệp, nhất quán trên mọi trang.
