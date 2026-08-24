# Tự động kiểm tra tính toàn vẹn payload UCI

Tập lệnh `scripts/verify-uci-payload.sh` kiểm tra payload theo chế độ chỉ đọc. Nó không chạy installer, không cài package, không khởi động Nginx/PHP và không thay đổi dữ liệu TMS OS.

## Kiểm tra đầy đủ payload V16.1.21

```bash
cd ~/tms-os
bash scripts/verify-uci-payload.sh \
  --payload .build/v16.1.21-uci/downloaded.zip \
  --release-json .build/v16.1.21-uci/out/RELEASE.json
```

Tập lệnh kiểm tra checksum SHA-256 với `RELEASE.json`, ZIP integrity, khả năng giải nén, file bắt buộc của UCI, Bash syntax, dấu vết secret/path sandbox và matrix mô phỏng Android API/ABI cùng engine policy.

## Chỉ kiểm tra integrity tĩnh

```bash
bash scripts/verify-uci-payload.sh \
  --payload /path/to/TMS_OS_LATEST.zip \
  --release-json /path/to/RELEASE.json \
  --no-matrix \
  --report "$HOME/.tms-os/uci-integrity-static.log"
```

## Kiểm tra thêm thiết bị Termux hiện tại

```bash
bash scripts/verify-uci-payload.sh \
  --payload /path/to/TMS_OS_LATEST.zip \
  --release-json /path/to/RELEASE.json \
  --device \
  --report "$HOME/.tms-os/uci-integrity-device.log"
```

Tùy chọn `--device` chỉ chạy compatibility preflight của payload trong thư mục tạm. Nó không thực hiện bước cài đặt. Mã `0` cho biết thiết bị có engine PHP hoạt động; mã `10` cho biết Android dưới API tối thiểu; mã `30` cho biết không có PHP server engine hoạt động.

## Mã thoát của verifier

| Mã | Ý nghĩa |
|---:|---|
| 0 | Integrity và các kiểm tra được yêu cầu đều đạt |
| 1 | Payload lỗi, checksum lệch, thiếu file, syntax lỗi hoặc matrix thất bại |
| 2 | Sai tham số CLI |
| 10 | Compatibility gate chặn Android API dưới 24 trong device preflight |
| 30 | Device preflight không tìm thấy PHP server engine hoạt động |

## Chạy định kỳ bằng Termux:JobScheduler hoặc Termux:Boot

Có thể gọi verifier từ script quản trị riêng khi cần kiểm tra sau cập nhật. Không nên chạy lệnh này với quyền root và không nên cho phép script tự động cài lại payload chỉ vì một lần kiểm tra thất bại. Quy trình an toàn là ghi log, thông báo lỗi và yêu cầu người quản trị xem report trước khi sửa chữa.

Ví dụ gọi kiểm tra payload đã tải sẵn:

```bash
#!/data/data/com.termux/files/usr/bin/bash
set -Eeuo pipefail
cd "$HOME/tms-os"
bash scripts/verify-uci-payload.sh \
  --payload "$HOME/.tms-os/cache/TMS_OS_LATEST.zip" \
  --release-json "$HOME/.tms-os/cache/RELEASE.json" \
  --report "$HOME/.tms-os/uci-integrity-latest.log"
```

Kết quả mẫu thành công kết thúc bằng:

```text
[PASS] Matrix Android API/ABI và engine policy đạt
RESULT=PASS
```

Một bản payload bị thay đổi sẽ bị từ chối trước khi giải nén hoặc chạy matrix, nhờ checksum hoặc ZIP integrity không còn khớp.
