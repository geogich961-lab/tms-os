# CI/CD kiểm tra Universal Compatibility Installer

Workflow `.github/workflows/verify-uci-payload.yml` tự động kiểm tra UCI trong hai trường hợp. Khi source installer thay đổi trên `main` hoặc trong pull request, workflow chạy Bash syntax check và các regression test. Khi một GitHub Release được publish hoặc workflow được chạy thủ công, workflow tải `TMS_OS_LATEST.zip` cùng `RELEASE.json`, sau đó chạy `scripts/verify-uci-payload.sh`.

## Các trigger

| Trigger | Phạm vi kiểm tra |
|---|---|
| Push vào `main` | Source syntax và regression tests |
| Pull request | Source syntax và regression tests |
| Release published | Payload live, checksum, ZIP, file UCI, matrix Android/ABI |
| Workflow dispatch | Chọn tag release bất kỳ, mặc định `v16.1.21` |

Workflow dùng `permissions: contents: read`, không cần API token cá nhân và không có bước tự động xóa, thay thế hoặc phát hành asset. Nếu verifier trả mã khác `0`, job thất bại và GitHub lưu log làm artifact trong 30 ngày.

## Chạy thủ công

Vào **Actions → Verify UCI payload → Run workflow**, chọn branch và nhập tag, ví dụ `v16.1.21`. Với release event, workflow tự lấy đúng tag của release vừa publish.

## Artifact sau mỗi lần chạy

Artifact có tên dạng `uci-verification-<run_id>` và chứa `uci-integrity.log` cùng context tag. Log bao gồm SHA-256, kết quả ZIP integrity, file bắt buộc, Bash syntax, secret scan, matrix API/ABI và policy engine.

## Nguyên tắc phát hành

Workflow này là cổng xác minh, không phải bộ phát hành tự động. Payload chỉ nên được upload hoặc publish sau khi job `Validate published UCI payload` đạt. Nếu phát hành asset mới, cần cập nhật `RELEASE.json` với SHA-256 đúng của ZIP; verifier sẽ từ chối checksum lệch.
