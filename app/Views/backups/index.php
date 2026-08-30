<?php $title='Backup & Snapshot · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">Backup & Snapshot Center</p><h1>Sao lưu và khôi phục</h1><p class="page-subtitle">Backup toàn hệ thống, cấu hình hoặc riêng từng website.</p></div><button class="btn btn-primary" data-dialog-open="create-backup">+ Tạo backup</button></div>
<?php if($flash):?><div class="alert <?=$flash['type']==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=$flash['type']==='success'?'success':'error'?>" hidden><?=nl2br(tms_h((string)$flash['message']))?></div><?php endif;?>
<section class="panel-card" id="auto-backup-card"><h2>Backup tự động &amp; Offsite</h2>
<?php $autoConfig=$auto['config']??[];$autoLast=$auto['last']??null;?>
<p class="muted">TMS OS tự tạo backup theo lịch hằng ngày qua cron, tự dọn bản cũ và (tuỳ chọn) đẩy lên cloud bằng rclone. Bản tự động xuất hiện ngay trong danh sách bên dưới với nút Khôi phục một chạm.</p>
<form method="post" action="/backups/auto/config" class="form-stack">
	<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
	<label class="check-line"><input type="checkbox" name="enabled" value="1" <?=!empty($autoConfig['enabled'])?'checked':''?>><span>Bật backup tự động hằng ngày</span></label>
	<div class="auto-backup-row">
		<label>Giờ chạy<input type="time" name="time" value="<?=tms_h((string)($autoConfig['time']??'03:30'))?>"></label>
		<label>Phạm vi<select name="scope"><option value="system" <?=($autoConfig['scope']??'system')==='system'?'selected':''?>>Toàn hệ thống</option><option value="config" <?=($autoConfig['scope']??'')==='config'?'selected':''?>>Chỉ cấu hình</option></select></label>
		<label>Giữ lại (bản)<input type="number" name="retention" min="1" max="90" value="<?=tms_h((string)($autoConfig['retention']??'7'))?>"></label>
	</div>
	<label class="check-line"><input type="checkbox" name="offsite_enabled" value="1" <?=!empty($autoConfig['offsite_enabled'])?'checked':''?>><span>Đẩy bản backup lên cloud qua rclone (offsite)</span></label>
	<div class="auto-backup-row">
		<label>rclone remote<input name="offsite_remote" placeholder="gdrive" value="<?=tms_h((string)($autoConfig['offsite_remote']??''))?>"></label>
		<label>Thư mục đích<input name="offsite_path" placeholder="tms-os-backups" value="<?=tms_h((string)($autoConfig['offsite_path']??'tms-os-backups'))?>"></label>
	</div>
	<label class="check-line"><input type="checkbox" name="notify_telegram" value="1" <?=!empty($autoConfig['notify_telegram'])?'checked':''?>><span>Thông báo kết quả qua Telegram</span></label>
	<button class="btn btn-primary">Lưu cấu hình tự động</button>
</form>
<p class="muted" id="auto-backup-status">
	Trạng thái: <?=empty($autoConfig['enabled'])?'<strong>Đang tắt</strong>':'<strong>Bật</strong> — chạy lúc '.tms_h((string)($autoConfig['time']??'')).' hằng ngày, giữ '.tms_h((string)($autoConfig['retention']??'7')).' bản.'?>
	<?=empty($auto['rclone_available'])?'<br>⚠️ rclone chưa cài trên thiết bị (pkg install rclone) — tính năng offsite sẽ bị chặn khi lưu.':'<br>rclone sẵn sàng.'?>
	<?php if(!empty($auto['cron_registered'])&&!empty($autoConfig['enabled'])):?><br>Cron job <code>tms-auto-backup</code> đã đăng ký.<?php endif;?>
	<?php if($autoLast):?><br>Lần chạy gần nhất: <?=tms_h((string)($autoLast['at']??''))?> — <?=!empty($autoLast['ok'])?'✅ '.tms_h((string)($autoLast['message']??'')):'❌ '.tms_h((string)($autoLast['message']??''))?><?php endif;?>
</p>
<form method="post" action="/backups/auto/run" data-confirm="Chạy một vòng backup tự động ngay bây giờ?"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button class="btn btn-secondary">Chạy backup tự động ngay</button></form>
</section>
<section class="backup-grid"><?php foreach($backups as $b):?><article class="panel-card backup-card"><div class="backup-card-head"><div><span class="status-pill running"><?=tms_h(($b['scope']??'system')==='website'?'Website':(($b['scope']??'')==='config'?'Cấu hình':'Toàn hệ thống'))?></span><h3><?=tms_h(($b['website']??'')?:($b['id']??''))?></h3></div><span class="lock-mark"><?=!empty($b['locked'])?'🔒':'🔓'?></span></div><p><?=tms_h(($b['note']??'')?:'Không có ghi chú')?></p><dl class="backup-meta"><div><dt>Dung lượng</dt><dd><?=tms_format_bytes((int)$b['size'])?></dd></div><div><dt>Ngày tạo</dt><dd><?=date('d/m/Y H:i',(int)$b['created_ts'])?></dd></div></dl><div class="backup-actions"><a class="btn btn-secondary" href="<?=tms_url('/backups/download',['id'=>$b['id']])?>">Tải xuống</a><form method="post" action="/backups/lock"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($b['id'])?>"><button class="btn btn-secondary"><?=!empty($b['locked'])?'Mở khóa':'Khóa'?></button></form><form method="post" action="/backups/restore" data-confirm="Hệ thống sẽ tạo snapshot an toàn trước khi khôi phục. Tiếp tục?"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($b['id'])?>"><button class="btn btn-primary">Khôi phục</button></form><form method="post" action="/backups/delete" data-confirm="Xóa vĩnh viễn backup này?"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($b['id'])?>"><button class="btn btn-danger-soft">Xóa</button></form></div></article><?php endforeach;?><?php if(!$backups):?><div class="panel-card empty-state">Chưa có bản backup hoặc snapshot.</div><?php endif;?></section>
<dialog id="create-backup" class="tms-dialog"><form method="post" action="/backups/create" class="form-stack"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><div class="dialog-head"><h2>Tạo backup</h2><button type="button" data-dialog-close>×</button></div><label>Phạm vi<select name="scope" data-backup-scope><option value="system">Toàn hệ thống</option><option value="config">Chỉ cấu hình</option><option value="website">Một website</option></select></label><label data-backup-website hidden>Website<select name="website"><?php foreach($sites as $site):?><option value="<?=tms_h($site['name'])?>"><?=tms_h($site['name'])?></option><?php endforeach;?></select></label><label>Ghi chú<input name="note" maxlength="160" placeholder="Ví dụ: Trước khi cập nhật plugin"></label><label class="check-line"><input type="checkbox" name="locked" value="1"><span>Khóa snapshot sau khi tạo</span></label><button class="btn btn-primary">Tạo backup ngay</button></form></dialog>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
