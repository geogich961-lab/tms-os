<?php $title='Thông báo · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">PWA ALERTS</p><h1>Thông báo</h1><p>Thông báo cục bộ khi trang PWA đang mở hoặc chạy nền trong phạm vi trình duyệt cho phép.</p></div></div>
<section class="panel-card"><h2>Quyền thông báo</h2><p id="notification-permission-text">Đang kiểm tra…</p><button class="btn btn-primary" data-notification-enable>Bật thông báo</button><button class="btn btn-secondary" data-notification-test>Gửi thử</button></section>
<section class="panel-card"><h2>Theo dõi dịch vụ</h2><label class="check-line"><input type="checkbox" data-service-alert checked> Báo khi Nginx, PHP Engine, MariaDB hoặc SSH chuyển từ chạy sang dừng.</label><p class="muted">Trình duyệt Android có thể ngừng kiểm tra khi hệ thống đóng hoàn toàn PWA. TMS OS không tuyên bố thay thế dịch vụ giám sát nền của Android.</p></section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
