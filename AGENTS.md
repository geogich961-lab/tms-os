# AGENTS.md — Quy ước làm việc TMS OS

Ngôn ngữ làm việc: **tiếng Việt** (code comment, tài liệu, commit body, release notes).

## Mặc định bắt buộc: đóng gói + phát hành release sau mọi thay đổi

Sau mỗi thay đổi được merge vào `main`, **KHÔNG cần hỏi lại** — luôn đóng gói và
phát hành release mới lên GitHub theo đủ quy trình dưới đây.

### 1. Xác định version kế tiếp

- Release mới nhất là ground truth: `https://api.github.com/repos/geogich961-lab/tms-os/releases/latest`.
- `config/app.php` và `public/service-worker.js` trong repo phải khớp bản release đó.
- Cẩn thận: `RELEASE.json` ở gốc repo có thể stale — chỉ tin GitHub API + config/app.php.
- Version kế = tăng PATCH (17.0.N → 17.0.N+1).

### 2. Bump version trong source

- `config/app.php`: `'build' => 'Platform V<MAJOR>.<MINOR>.<PATCH>'`
- `public/service-worker.js`: `const VERSION='tms-os-v<MAJOR>.<MINOR>.<PATCH>';` (bắt buộc
  để ép PWA làm mới cache, tránh lỗi 520/cache cũ)

### 3. Đóng gói payload

- Tạo `tests/build_v<M>_<m>_<p>_payload.sh` theo mẫu `tests/build_v17_0_17_payload.sh`
  (build trực tiếp từ source đã review trên main, không cần backup BASE).
- Nén bằng `tests/make_payload_zip.php` (máy không có lệnh `zip`).
- **Quy tắc ZIP bắt buộc:** chỉ chứa `app/ config/ public/ routes/ scripts/`.
  - Loại `scripts/verify-uci-payload.sh` (chỉ dùng cho CI).
  - Không chứa `RELEASE.json`, `storage/`, `tests/`, `docs/`, file ẩn (.gitkeep, .DS_Store…).
  - ZIP phải có `config/app.php`, `public/index.php`, `scripts/install.sh` (validateZip yêu cầu).
- Xuất ra `.build/v<M>.<m>.<p>/release/TMS_OS_LATEST.zip` + `RELEASE.json`
  (checksum SHA-256 bắt buộc trong RELEASE.json; `.build/` đã gitignore, không commit).

### 4. Kiểm thử trước khi phát hành

- Viết + chạy `tests/v<M>_<m>_<p>_update_payload_test.php` theo mẫu
  `tests/v17_0_17_update_payload_test.php`: mô phỏng nâng cấp từ bản trước
  (tải ZIP của release trước về `.build/v<M>.<m>.<p-1>/release/TMS_OS_LATEST.zip`),
  xác minh: layout ZIP, checksum, `currentVersion()` đổi đúng, **bảo toàn
  `storage/` và `~/.tms-os/cloudflare-hosting/`**, Service Worker refresh,
  guard PHP-before-Nginx (`ensure_php_up_for_nginx`).
- Chạy các test hồi quy liên quan đến phần vừa sửa.
- Máy dev Windows: PHP portable tại `../.tools/php/php.exe` (đã có php.ini riêng);
  đưa lên đầu PATH khi chạy test để `exec('php -l')` bên trong `apply()` hoạt động.

### 5. Commit, tag, push

- Conventional commits (`fix:`, `feat:`, `docs:`, `test:`), commit vào branch riêng
  rồi merge fast-forward vào `main` nếu không có xung đột.
- Commit **source + test + todo.md + RELEASE.json gốc**, KHÔNG commit artifact `.build/`.
- Ghi nhật ký công việc vào `todo.md` theo đúng format mục có sẵn
  (`- [x] Đóng gói, kiểm thử và phát hành V…`; mục xác minh thực đặt `- [ ]` chờ Xperia).
- Tạo tag `v<M>.<m>.<p>` trên commit release, push `main` + tag.

### 6. Tạo GitHub Release

- Dùng credential đã lưu của git (không bao giờ in token ra output/log):
  `git credential fill` → token → gọi API `POST /repos/geogich961-lab/tms-os/releases`
  rồi upload 2 assets (`TMS_OS_LATEST.zip`, `RELEASE.json`) qua `uploads.github.com`.
- Release notes: tóm tắt các bản sửa, đúng ngôn ngữ tiếng Việt như RELEASE.json notes.
- Xác minh sau phát hành: `releases/latest` trả về tag mới và asset tải được (HTTP 200).

### 7. Quy tắc khác

- Không bao giờ tự chạy `install.sh` hay script thiết lập của repo (an toàn).
- Thay đổi phải có test hồi quy kèm theo nếu chạm UpdateService, PHP engine, installer.
- CI workflow `verify-uci-payload` chạy trên push/PR vào `main` (lọc theo
  install.sh/scripts/tests/docs) — giữ verifier contract với `scripts/verify-uci-payload.sh`.
