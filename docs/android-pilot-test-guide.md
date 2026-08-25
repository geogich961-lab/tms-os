# Gói pilot kiểm thử TMS OS UCI trên Android thật

Tài liệu này là quy trình kiểm thử có kiểm soát cho payload UCI của TMS OS. Mục tiêu là xác nhận **preflight**, cài đặt mới, sửa chữa và rollback trên thiết bị thật mà không gửi database, mã website, token Cloudflare hoặc mật khẩu ra ngoài.

## Phạm vi thiết bị

Pilot tối thiểu nên bao gồm ba nhóm thiết bị. Android 7.0/API 24 là mức tương thích của installer; Android 8/API 26 là mức nên kiểm tra; Android 10/API 29 trở lên đại diện cho cấu hình vận hành khuyến nghị. Nếu có thể, chọn một máy `armeabi-v7a` và hai máy `arm64-v8a`.

| Nhóm | Mục tiêu | Điều kiện đạt |
|---|---|---|
| A — Android 7/API 24 | Xác nhận mức tối thiểu | `--diagnose` không trả mã 10, hoặc ghi nhận rõ blocker package/PHP |
| B — Android 8/API 26 | Xác nhận cài SQLite | Installer chọn `fastcgi` hoặc `php-http`, panel local hoạt động |
| C — Android 10+ | Xác nhận vận hành tham chiếu | Cài mới, repair, verifier và panel local đạt |

## Chuẩn bị thiết bị

Cài Termux từ cùng một nguồn đáng tin cậy với mọi plugin Termux và hoàn thành quyền storage bằng `termux-setup-storage`. Không cài hay cập nhật TMS OS trong lúc pin yếu hoặc mạng chập chờn. Trước mỗi ca thử, ghi lại Android API, ABI, nguồn Termux, dung lượng trống và phiên bản PHP.

## Bước 1 — Thu thập báo cáo trước cài đặt

Sau khi tải payload UCI và giải nén vào `~/tms-os`, chạy:

```bash
cd ~/tms-os
bash scripts/collect-pilot-report.sh \
  --payload /đường/dẫn/TMS_OS_LATEST.zip \
  --release-json /đường/dẫn/RELEASE.json
```

Collector chỉ đọc thông tin, chạy probe trong thư mục tạm và tạo archive `~/tms-os-pilot-reports/tms-os-pilot-*.tar.gz`. Archive chỉ chứa bản log đã che token, password, API key, cookie và URL chứa credential. Không cần gửi database, thư mục `websites/`, `~/.tms-os` hoặc token Cloudflare.

## Bước 2 — Đọc mã preflight

| Mã | Diễn giải | Hành động pilot |
|---:|---|---|
| 0 | Preflight đạt, đã có engine `fastcgi` hoặc `php-http` | Có thể sang ca cài mới |
| 10 | Android API dưới 24 | Dừng; thiết bị không thuộc phạm vi hỗ trợ |
| 20 | Termux/PREFIX/HOME/temp/pkg không hợp lệ | Sửa cài đặt Termux và chạy lại collector |
| 30 | PHP server engine không hoạt động | Dừng trước cài đặt; gửi archive collector đã che dữ liệu |
| 40 | Nginx config không hợp lệ | Dừng; kiểm tra cấu hình và log Nginx |
| 50 | Không tạo/đọc được state an toàn | Dừng; kiểm tra dung lượng và quyền HOME |

## Bước 3 — Ca cài mới SQLite

Chỉ chạy khi preflight trả mã `0`. Chọn SQLite để loại trừ biến số MariaDB trong vòng pilot đầu. Sau khi installer báo hoàn tất, kiểm tra:

```bash
bash scripts/tms-php-engine.sh status
curl -i --max-time 5 http://127.0.0.1:8888/login
bash scripts/verify-uci-payload.sh --no-matrix
```

Ca đạt khi PHP engine đúng với `php-engine-policy`, HTTP local trả phản hồi, và verifier trả `RESULT=PASS`.

## Bước 4 — Ca repair và rollback

Để kiểm tra repair, chạy lại installer trên máy đã cài và chọn **Sửa chữa**. Xác minh username, SQLite database, website và cấu hình hiện hữu vẫn còn. Không chọn cài mới trong ca repair.

Rollback chỉ kiểm tra khi installer dừng giữa transaction hoặc khi có backup transaction chưa commit. Trước tiên liệt kê backup:

```bash
bash scripts/installer-rollback.sh --list
```

Chỉ khi xác định có transaction chưa commit mới chạy rollback và xác nhận bằng `YES`:

```bash
bash scripts/installer-rollback.sh
```

Sau rollback, kiểm tra lại panel local. Không dùng rollback để quay phiên bản release tùy ý.

## Điều kiện nghiệm thu

Một profile được coi là đạt khi preflight trả 0, installation/repair không mất dữ liệu đang có, panel local phản hồi và collector/verifier tạo được báo cáo. Với Android 7/API 24, có thể ghi nhận **tương thích có điều kiện** nếu Termux và PHP upstream còn hoạt động nhưng hiệu năng hoặc package repository hạn chế.

Khi gửi kết quả, chỉ gửi archive collector và bảng tóm tắt gồm Android API, ABI, source Termux, mã preflight, engine chọn, tình trạng cài mới/repair và tình trạng panel. Không gửi mật khẩu, token, database hoặc log Cloudflare thô.

