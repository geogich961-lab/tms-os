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
