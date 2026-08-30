<?php $title='Thông báo · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">PWA ALERTS</p><h1>Thông báo</h1><p>Thông báo cục bộ khi trang PWA đang mở hoặc chạy nền trong phạm vi trình duyệt cho phép.</p></div></div>
<section class="panel-card"><h2>Quyền thông báo</h2><p id="notification-permission-text">Đang kiểm tra…</p><button class="btn btn-primary" data-notification-enable>Bật thông báo</button><button class="btn btn-secondary" data-notification-test>Gửi thử</button></section>
<section class="panel-card"><h2>Theo dõi dịch vụ</h2><label class="check-line"><input type="checkbox" data-service-alert checked> Báo khi Nginx, PHP Engine, MariaDB hoặc SSH chuyển từ chạy sang dừng.</label><p class="muted">Trình duyệt Android có thể ngừng kiểm tra khi hệ thống đóng hoàn toàn PWA. TMS OS không tuyên bố thay thế dịch vụ giám sát nền của Android.</p></section>
<?php $alertConfig=$alerts['config']??[];$alertState=$alerts['state']??[];?>
<section class="panel-card" id="ops-alerts-card"><h2>Cảnh báo vận hành qua Telegram</h2>
<p class="muted">Kiểm tra mỗi 15 phút qua cron và gửi Telegram khi vượt ngưỡng — bảo vệ điện thoại cắm điện 24/7: bộ nhớ đầy, RAM cạn, pin sạc 100% quá lâu (nguy cơ phồng pin), quá nóng và Tunnel rớt. Mỗi loại chỉ nhắc lại sau khoảng cooldown.</p>
<form method="post" action="/notifications/alerts/config" class="form-stack">
	<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
	<label class="check-line"><input type="checkbox" name="enabled" value="1" <?=!empty($alertConfig['enabled'])?'checked':''?>><span>Bật cảnh báo vận hành</span></label>
	<div class="auto-backup-row">
		<label>Nhắc lại sau (phút)<input type="number" name="cooldown_minutes" min="5" max="720" value="<?=tms_h((string)($alertConfig['cooldown_minutes']??'60'))?>"></label>
		<label>Bộ nhớ trống dưới (%)<input type="number" name="storage_min_free_percent" min="1" max="50" value="<?=tms_h((string)($alertConfig['storage_min_free_percent']??'10'))?>"></label>
		<label>RAM dùng trên (%)<input type="number" name="ram_max_percent" min="50" max="99" value="<?=tms_h((string)($alertConfig['ram_max_percent']??'85'))?>"></label>
	</div>
	<div class="auto-backup-row">
		<label>Pin đầy quá (phút)<input type="number" name="battery_full_max_minutes" min="30" max="2880" value="<?=tms_h((string)($alertConfig['battery_full_max_minutes']??'240'))?>"></label>
		<label>Nhiệt độ trên (°C)<input type="number" name="temp_max_c" min="35" max="80" value="<?=tms_h((string)($alertConfig['temp_max_c']??'48'))?>"></label>
	</div>
	<label class="check-line"><input type="checkbox" name="check_battery" value="1" <?=!empty($alertConfig['check_battery'])?'checked':''?>><span>Theo dõi pin (cần termux-api: pkg install termux-api)</span></label>
	<label class="check-line"><input type="checkbox" name="check_tunnel" value="1" <?=!empty($alertConfig['check_tunnel'])?'checked':''?>><span>Cảnh báo Cloudflare Tunnel rớt</span></label>
	<button class="btn btn-primary">Lưu cấu hình cảnh báo</button>
</form>
<p class="muted">
	Trạng thái: <?=empty($alertConfig['enabled'])?'<strong>Đang tắt</strong>':'<strong>Bật</strong> — kiểm tra mỗi 15 phút.'?>
	<?=empty($alerts['termux_api_available'])?'<br>⚠️ termux-api chưa cài: không đo được pin/nhiệt độ (pkg install termux-api).':''?>
	<?php if(!empty($alertState['last_run'])):?><br>Lần kiểm tra gần nhất: <?=tms_h((string)$alertState['last_run'])?><?=!empty($alertState['last_alerts'])?' — '.tms_h(implode(', ',(array)$alertState['last_alerts'])):' — không có cảnh báo.'?><?php endif;?>
</p>
<form method="post" action="/notifications/alerts/run"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button class="btn btn-secondary">Kiểm tra ngay</button></form>
</section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
