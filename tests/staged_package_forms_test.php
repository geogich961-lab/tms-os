<?php
declare(strict_types=1);

// V17.1.8 — Test hồi quy sự cố 08/11/2026: form xóa hàng loạt trong trang
// Update Center BỌC cả bảng "Gói đã lưu trữ", mà mỗi dòng lại có form
// "Áp dụng"/"Xóa" riêng. HTML cấm form lồng nhau: trình duyệt bỏ qua thẻ
// form con, nên nút Áp dụng trở thành nút submit của form XÓA — user bấm
// Áp dụng thì gói bị xóa. Test kiểm tra cấu trúc view: form batch phải
// đóng ngay (không chứa bảng, không chứa form con), checkbox liên kết
// với nó qua thuộc tính form=, và form Áp dụng phải nằm ngoài form batch.

function failS1718(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function okS1718(bool $value, string $message): void { if (!$value) failS1718($message); }

$view = (string)file_get_contents(dirname(__DIR__) . '/app/Views/updates/index.php');
okS1718($view !== '', 'Không đọc được view updates.');

$batchStart = strpos($view, '<form id="batch-delete-form"');
okS1718($batchStart !== false, 'Thiếu form batch-delete-form.');
$batchEnd = strpos($view, '</form>', $batchStart);
okS1718($batchEnd !== false, 'Form batch-delete-form không đóng.');
$batchSpan = substr($view, $batchStart, $batchEnd - $batchStart);
okS1718(!str_contains($batchSpan, '<table'), 'Form batch vẫn bọc bảng danh sách gói (form lồng nhau).');
okS1718(!str_contains($batchSpan, 'staged/apply'), 'Form Áp dụng vẫn nằm trong form batch (bấm Áp dụng sẽ thành Xóa).');
okS1718(!str_contains(substr($batchSpan, 5), '<form'), 'Trong form batch còn form con lồng nhau.');

okS1718(str_contains($view, 'class="package-checkbox" form="batch-delete-form"'), 'Checkbox gói chưa liên kết form="batch-delete-form".');
okS1718(str_contains($view, 'action="/updates/staged/apply"'), 'Thiếu form Áp dụng gói đã lưu.');

$applyPos = strpos($view, 'action="/updates/staged/apply"');
okS1718($applyPos > $batchEnd, 'Form Áp dụng phải nằm SAU khi form batch đã đóng.');

echo "PASS: form Gói đã lưu trữ không còn lồng nhau — Áp dụng là Áp dụng, Xóa là Xóa.\n";
