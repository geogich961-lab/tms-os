<?php
$title='Cloudflare Hosting · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';
$csrf=tms_csrf_token();
?>
<div class="page-head"><div><p class="eyebrow">TMS OS V15 · CLOUDFLARE HOSTING</p><h1>Cloudflare Hosting</h1><p class="muted">Kết nối TMS OS với Cloudflare để đưa website lên Internet bằng <strong>tên miền riêng</strong>, như một hosting thực thụ. Không còn tunnel tạm thời — website của bạn luôn luôn online tại tên miền bạn chọn.</p></div><span id="cfd-status-pill" class="status-pill stopped" data-cfd-status-pill>Chưa cấu hình</span></div>
<div id="cfd-alert" class="alert alert-error" hidden></div>

<!-- ========== TAB ========== -->
<div class="cfh-tabs" role="tablist">
<button class="cfh-tab active" role="tab" data-tab="hosting" id="tab-hosting">☁ Cloudflare Hosting</button>
<button class="cfh-tab" role="tab" data-tab="fallback" id="tab-fallback">⚡ Smart Fallback (cũ)</button>
</div>

<!-- ========== TAB 1: CLOUDFLARE HOSTING ========== -->
<section class="panel-card" data-panel="hosting">
<div class="section-title-row"><div><p class="eyebrow">BƯỚC 1 · TÀI KHOẢN</p><h2>Cấu hình tài khoản Cloudflare</h2></div></div>
<p class="muted">Tạo API Token tại <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener">dash.cloudflare.com/profile/api-tokens</a> với quyền: <strong>Cloudflare Tunnel (Edit)</strong>, <strong>Zone → DNS (Edit)</strong>, <strong>Zone → Zone (Read)</strong>. Phạm vi: Account <strong>All accounts</strong> · Zone <strong>All zones</strong>.</p>
<form class="form-stack" id="cfd-token-form">
<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
<label>API Token<input type="password" id="cfd-api-token" name="api_token" placeholder="Dán API Token của bạn vào đây" autocomplete="off"></label>
<button type="submit" class="btn btn-primary">Kiểm tra &amp; lưu token</button>
</form>
<div id="cfd-account-box" class="cfh-info-box" hidden><div><strong>Tài khoản:</strong> <span id="cfd-account-id">—</span></div><div><strong>Zones (domains):</strong> <span id="cfd-zones-count">—</span></div></div>
</section>

<section class="panel-card" data-panel="hosting">
<div class="section-title-row"><div><p class="eyebrow">BƯỚC 2 · TUNNEL</p><h2>Cloudflare Tunnel</h2></div></div>
<p class="muted">Tạo một tunnel chính chủ trên tài khoản Cloudflare. Tunnel này sẽ giữ trạng thái cố định — tên miền luôn trỏ về điện thoại của bạn khi tunnel hoạt động.</p>
<div class="form-grid two">
<div class="cfh-stat"><span>Tunnel</span><strong id="cfd-tunnel-name">Chưa tạo</strong></div>
<div class="cfh-stat"><span>Trạng thái</span><strong id="cfd-tunnel-status">—</strong></div>
<div class="cfh-stat"><span>Kết nối</span><strong id="cfd-tunnel-conns">—</strong></div>
<div class="cfh-stat"><span>Đang chạy</span><strong id="cfd-tunnel-running">—</strong></div>
</div>
<form class="form-stack" id="cfd-tunnel-form"><button type="submit" class="btn btn-secondary">Tạo Cloudflare Tunnel mới</button></form>
</section>

<section class="panel-card" data-panel="hosting">
<div class="section-title-row"><div><p class="eyebrow">BƯỚC 3 · TÊN MIỀN</p><h2>Gắn tên miền &amp; kích hoạt</h2></div></div>
<p class="muted">Chọn domain của bạn, nhập tên host muốn public (ví dụ <strong>shop.example.com</strong>), chọn website nội bộ trên máy. Hệ thống tự tạo record DNS CNAME trỏ về tunnel.</p>
<form class="form-stack" id="cfd-attach-form">
<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
<label>Domain<select id="cfd-zone" name="zone_id" required><option value="">— chọn domain —</option></select></label>
<label>Tên host công khai<input type="text" id="cfd-hostname" name="hostname" placeholder="ví dụ: shop.example.com" autocomplete="off" required></label>
<label>Website nội bộ<select id="cfd-target" name="target" required></select></label>
<button type="submit" class="btn btn-primary">Gắn tên miền &amp; tạo record DNS</button>
</form>
<div id="cfd-url-card" class="cf-url-card" hidden><span>Website công khai của bạn</span><a id="cfd-public-url" href="#" target="_blank" rel="noopener"></a><div class="cf-url-actions"><a id="cfd-open-url" class="btn btn-primary btn-small" href="#" target="_blank" rel="noopener">Mở liên kết</a><button type="button" id="cfd-copy-url" class="btn btn-secondary btn-small">Sao chép</button></div></div>
<div class="form-stack"><button type="button" class="btn btn-danger-soft btn-block" id="cfd-detach">Tách tên miền (giữ tunnel)</button></div>
</section>

<section class="panel-card" data-panel="hosting">
<div class="section-title-row"><h2>Điều khiển Tunnel</h2><span class="online-dot" id="cfd-running-dot"></span></div>
<div class="btn-grid two">
<button type="button" class="btn btn-success" id="cfd-start">▶ Khởi động Tunnel</button>
<button type="button" class="btn btn-danger-soft" id="cfd-stop">■ Dừng Tunnel</button>
</div>
<p class="muted">Sau khi khởi động, website của bạn tại tên miền đã gắn sẽ online ngay lập tức qua hạ tầng Cloudflare.</p>
<div class="form-stack"><button type="button" class="btn btn-ghost btn-block" id="cfd-refresh">Làm mới trạng thái</button></div>
<div class="form-stack"><button type="button" class="btn btn-danger-soft btn-block" id="cfd-delete-tunnel">Xóa Tunnel khỏi Cloudflare</button></div>
<div class="form-stack"><button type="button" class="btn btn-danger-soft btn-block" id="cfd-uninstall">Xóa toàn bộ cấu hình Cloudflare</button></div>
</section>

<section class="panel-card" data-panel="hosting"><div class="section-title-row"><h2>Nhật ký Tunnel</h2></div><pre id="cfd-log" class="terminal-output"><?=tms_h('')?></pre></section>

<!-- ========== TAB 2: SMART FALLBACK (cũ) ========== -->
<section class="panel-card" data-panel="fallback">
<div class="section-title-row"><div><p class="eyebrow">SMART FALLBACK ENGINE</p><h2>Tạo URL công khai</h2></div><span class="cf-live-dot" id="cf-live-dot"></span></div>
<div class="tunnel-provider-grid">
<?php foreach(['cloudflare'=>'Cloudflare','pinggy'=>'Pinggy','localhostrun'=>'localhost.run','ngrok'=>'Ngrok','relay'=>'TMS Relay'] as $key=>$name):?>
<div class="provider-chip <?=!empty($caps[$key])?'ready':'missing'?>"><strong><?=tms_h($name)?></strong><span><?=!empty($caps[$key])?'Sẵn sàng':'Chưa cấu hình'?></span></div>
<?php endforeach;?>
</div>
<div class="cf-diagnostics"><span>Nhà cung cấp: <strong id="cf-provider"><?=tms_h($status['provider_label']??'Chưa chọn')?></strong></span><span>Tiến trình: <strong id="cf-process-state"><?=!empty($status['running'])?'Đang chạy':'Đã dừng'?></strong></span><span>HTTP: <strong id="cf-http-code"><?=!empty($status['http_code'])?(int)$status['http_code']:'—'?></strong></span></div>
<form method="post" action="/internet-access/start" class="form-stack" data-action-form>
<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
<label>Website nội bộ<select name="target" required><?php foreach($sites as $site):if(empty($site['enabled'])||empty($site['port']))continue;$value='http://127.0.0.1:'.(int)$site['port'];?><option value="<?=tms_h($value)?>" <?=$value===($status['target']??'')?'selected':''?>><?=tms_h($site['name'])?> · cổng <?=(int)$site['port']?></option><?php endforeach;?></select></label>
<label>Chế độ kết nối<select name="provider"><option value="auto">Tự động — Cloudflare → Pinggy → localhost.run → Ngrok → TMS Relay</option><option value="cloudflare">Chỉ Cloudflare</option><option value="pinggy">Chỉ Pinggy</option><option value="localhostrun">Chỉ localhost.run</option><option value="ngrok">Chỉ Ngrok</option><option value="relay">Chỉ TMS Relay Server</option></select></label>
<label>Giao thức Cloudflare<select name="protocol"><option value="auto">Tự động</option><option value="http2">HTTP/2</option><option value="quic">QUIC</option></select></label>
<button class="btn btn-primary">Tạo URL công khai</button>
</form>
<form method="post" action="/internet-access/stop"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button class="btn btn-danger-soft btn-block">Dừng kết nối</button></form>
<div id="cf-state-box" class="cf-state-box state-<?=tms_h($state)?>"><div class="cf-state-icon" id="cf-state-icon"><?=$state==='connected'?'✓':($state==='error'?'!':'…')?></div><div><strong id="cf-state-title"><?=tms_h($stateLabel)?></strong><p id="cf-state-message"><?=tms_h($status['message']??'')?></p></div></div>
<div id="cf-url-card" class="cf-url-card" <?=empty($status['url'])?'hidden':''?>><span>Địa chỉ công khai</span><a id="cf-public-url" href="<?=tms_h($status['url']??'#')?>" target="_blank" rel="noopener"><?=tms_h($status['url']??'')?></a><div class="cf-url-actions"><a id="cf-open-url" class="btn btn-primary btn-small" href="<?=tms_h($status['url']??'#')?>" target="_blank" rel="noopener">Mở liên kết</a><button type="button" id="cf-copy-url" class="btn btn-secondary btn-small" data-copy="<?=tms_h($status['url']??'')?>">Sao chép</button></div></div>
<?php if(!empty($status['attempts'])):?><div class="fallback-history"><h3>Lịch sử chuyển tuyến</h3><?php foreach($status['attempts'] as $a):?><div><strong><?=tms_h($a['provider']??'')?></strong><span><?=tms_h($a['message']??'')?></span></div><?php endforeach;?></div><?php endif;?>
</section>
<section class="panel-card" data-panel="fallback"><div class="section-title-row"><div><p class="eyebrow">ADVANCED</p><h2>Ngrok & TMS Relay</h2></div></div><p class="muted">Ngrok cần token. TMS Relay cần VPS có SSH key và reverse proxy trỏ tới cổng remote.</p>
<form method="post" action="/internet-access/settings" class="form-stack"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><label>Ngrok Authtoken<input type="password" name="ngrok_token" value="" placeholder="Để trống để giữ token hiện tại" autocomplete="off"></label><div class="form-grid two"><label>Relay Host<input name="relay_host" value="<?=tms_h($settings['relay_host']??'')?>" placeholder="vps.example.com"></label><label>Relay User<input name="relay_user" value="<?=tms_h($settings['relay_user']??'')?>" placeholder="tmsrelay"></label><label>SSH Port<input type="number" name="relay_ssh_port" value="<?=(int)($settings['relay_ssh_port']??22)?>"></label><label>Remote Port<input type="number" name="relay_remote_port" value="<?=(int)($settings['relay_remote_port']??10080)?>"></label></div><label>Public URL của Relay<input name="relay_public_url" value="<?=tms_h($settings['relay_public_url']??'')?>" placeholder="https://demo.example.com"></label><label>SSH Identity File<input name="relay_identity_file" value="<?=tms_h($settings['relay_identity_file']??'')?>" placeholder="~/.ssh/id_ed25519"></label><button class="btn btn-secondary">Lưu cấu hình</button></form></section>
<section class="panel-card" data-panel="fallback"><div class="section-title-row"><h2>Nhật ký kết nối</h2><button type="button" class="btn btn-secondary btn-small" id="cf-refresh">Làm mới</button></div><pre id="cf-log" class="terminal-output cf-log"><?=tms_h($status['log']??'')?></pre></section>

<script>window.TMS_CLOUDFLARE_STATUS_URL='/api/internet-access/status';window.TMS_CF_DOMAIN_STATUS_URL='/api/cloudflare-domain/status';</script>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
