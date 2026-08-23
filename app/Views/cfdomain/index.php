<?php
$title='Cloudflare Hosting · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';
$csrf=tms_csrf_token();
?>
<div class="page-head"><div><p class="eyebrow">Cloudflare Hosting</p><h1>Cloudflare Hosting</h1><p class="muted">Kết nối TMS OS với Cloudflare để đưa website lên Internet bằng <strong>tên miền riêng</strong>, như một hosting thực thụ. Không còn tunnel tạm thời — website của bạn luôn online tại tên miền bạn chọn.</p></div><span id="cfd-status-pill" class="status-pill stopped" data-cfd-status-pill>Chưa cấu hình</span></div>
<div id="cfd-alert" class="alert alert-error" hidden></div>

<!-- ========== BƯỚC 1 · TÀI KHOẢN ========== -->
<section class="panel-card cfd-section" data-panel="hosting">
<div class="section-title-row"><div><p class="eyebrow">BƯỚC 1 · TÀI KHOẢN</p><h2>Cấu hình tài khoản Cloudflare</h2></div></div>
<p class="muted">Tạo API Token tại <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener">dash.cloudflare.com/profile/api-tokens</a> với quyền: <strong>Cloudflare Tunnel (Edit)</strong>, <strong>Zone → DNS (Edit)</strong>, <strong>Zone → Zone (Read)</strong>. Phạm vi: Account <strong>All accounts</strong> · Zone <strong>All zones</strong>.</p>
<form class="form-stack cfd-form" id="cfd-token-form">
<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
<label for="cfd-api-token">API Token<input type="password" id="cfd-api-token" name="api_token" placeholder="Dán API Token của bạn vào đây" autocomplete="off"></label>
<button type="submit" class="btn btn-primary">Kiểm tra &amp; lưu token</button>
</form>
<div id="cfd-account-box" class="cfh-info-box" hidden><div><strong>Tài khoản:</strong> <span id="cfd-account-id">—</span></div><div><strong>Zones (domains):</strong> <span id="cfd-zones-count">—</span></div></div>
</section>

<!-- ========== BƯỚC 2 · TUNNEL ========== -->
<section class="panel-card cfd-section" data-panel="hosting">
<div class="section-title-row"><div><p class="eyebrow">BƯỚC 2 · TUNNEL</p><h2>Cloudflare Tunnel</h2></div></div>
<p class="muted">Tạo một tunnel chính chủ trên tài khoản Cloudflare. Tunnel này giữ trạng thái cố định — tên miền luôn trỏ về điện thoại của bạn khi tunnel hoạt động. Một tunnel có thể phục vụ <strong>nhiều website hoặc subdomain cùng lúc</strong>.</p>
<div class="form-grid two">
<div class="cfh-stat"><span>Tunnel</span><strong id="cfd-tunnel-name">Chưa tạo</strong></div>
<div class="cfh-stat"><span>Trạng thái</span><strong id="cfd-tunnel-status">—</strong></div>
<div class="cfh-stat"><span>Kết nối</span><strong id="cfd-tunnel-conns">—</strong></div>
<div class="cfh-stat"><span>Đang chạy</span><strong id="cfd-tunnel-running">—</strong></div>
</div>
<form class="form-stack cfd-form" id="cfd-tunnel-form"><button type="submit" class="btn btn-secondary">Tạo Cloudflare Tunnel mới</button></form>
</section>

<!-- ========== BƯỚC 3 · GẮN TÊN MIỀN (HỖ TRỢ SUBDOMAIN / NHIỀU WEBSITE) ========== -->
<section class="panel-card cfd-section" data-panel="hosting">
<div class="section-title-row"><div><p class="eyebrow">BƯỚC 3 · TÊN MIỀN</p><h2>Gắn tên miền / subdomain</h2></div></div>
<p class="muted">Mỗi lần gắn sẽ tạo một đường vào mới cho website nội bộ của bạn — có thể là <strong>domain gốc</strong> (ví dụ <strong>thc.io.vn</strong>) hoặc <strong>subdomain riêng</strong> (ví dụ <strong>shop.thc.io.vn</strong>, <strong>game.thc.io.vn</strong>). Các tên miền đã gắn không bị ảnh hưởng, hoạt động song song trên cùng một tunnel. Hệ thống tự tạo record DNS CNAME trỏ về tunnel.</p>
<form class="form-stack cfd-form" id="cfd-attach-form">
<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
<label for="cfd-zone">Domain<select id="cfd-zone" name="zone_id" required><option value="">— chọn domain —</option></select></label>
<label for="cfd-hostname">Tên host công khai<input type="text" id="cfd-hostname" name="hostname" placeholder="Ví dụ: thc.io.vn hoặc shop.thc.io.vn" autocomplete="off" required></label>
<div class="cfd-subdomain-chips" id="cfd-subdomain-chips">
<button type="button" class="btn btn-ghost btn-small cfd-chip" data-chip="shop">shop</button>
<button type="button" class="btn btn-ghost btn-small cfd-chip" data-chip="blog">blog</button>
<button type="button" class="btn btn-ghost btn-small cfd-chip" data-chip="game">game</button>
<button type="button" class="btn btn-ghost btn-small cfd-chip" data-chip="app">app</button>
<button type="button" class="btn btn-ghost btn-small cfd-chip" data-chip="api">api</button>
<span class="muted cfd-chip-hint">chạm để tạo subdomain nhanh theo domain đã chọn</span>
</div>
<label for="cfd-target">Website nội bộ<select id="cfd-target" name="target" required></select></label>
<button type="submit" class="btn btn-primary">Gắn tên miền &amp; tạo record DNS</button>
</form>

<!-- Danh sách tên miền đang hoạt động (multi-site) -->
<div id="cfd-hostnames-list" class="cfd-hostnames" hidden></div>

<div id="cfd-url-card" class="cf-url-card" hidden><span>Website công khai của bạn</span><a id="cfd-public-url" href="#" target="_blank" rel="noopener"></a><div class="cf-url-actions"><a id="cfd-open-url" class="btn btn-primary btn-small" href="#" target="_blank" rel="noopener">Mở liên kết</a><button type="button" id="cfd-copy-url" class="btn btn-secondary btn-small">Sao chép</button></div></div>
<div class="form-stack cfd-form"><button type="button" class="btn btn-danger-soft btn-block" id="cfd-detach">Tách tên miền chính (giữ tunnel)</button></div>
</section>

<!-- ========== ĐIỀU KHIỂN TUNNEL ========== -->
<section class="panel-card cfd-section" data-panel="hosting">
<div class="section-title-row"><h2>Điều khiển Tunnel</h2><span class="online-dot" id="cfd-running-dot"></span></div>
<div class="btn-grid cfd-btn-grid two" style="gap: 12px; margin-bottom: 12px;">
<button type="button" class="btn btn-success" id="cfd-start">▶ Khởi động Tunnel</button>
<button type="button" class="btn btn-danger-soft" id="cfd-stop">■ Dừng Tunnel</button>
</div>
<p class="muted">Sau khi khởi động, các website tại tên miền đã gắn sẽ online ngay lập tức qua hạ tầng Cloudflare.</p>
<div class="form-stack cfd-form"><button type="button" class="btn btn-ghost btn-block" id="cfd-refresh">Làm mới trạng thái</button></div>
<div class="form-stack cfd-form"><button type="button" class="btn btn-danger-soft btn-block" id="cfd-delete-tunnel">Xóa Tunnel khỏi Cloudflare</button></div>
<div class="form-stack cfd-form"><button type="button" class="btn btn-danger-soft btn-block" id="cfd-uninstall">Xóa toàn bộ cấu hình Cloudflare</button></div>
</section>

<!-- ========== REMOTE ACCESS ========== -->
<section class="panel-card cfd-section" data-panel="hosting">
<div class="section-title-row"><div><p class="eyebrow">REMOTE ACCESS</p><h2>Truy cập panel từ xa</h2></div><span class="cf-live-dot" id="cfd-remote-dot"></span></div>
<p class="muted">Bật truy cập <strong>panel quản trị</strong> từ bất kỳ máy nào qua Internet, trên <strong>subdomain riêng</strong> của chính bạn (ví dụ <strong>panel.thc.io.vn</strong>) — không phụ thuộc WiFi/LAN, tự hoạt động khi đổi mạng. Panel vẫn yêu cầu đăng nhập như bình thường nên an toàn khi bật.</p>
<form class="form-stack cfd-form" id="cfd-remote-form">
<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
<label for="cfd-panel-hostname">Hostname panel<input type="text" id="cfd-panel-hostname" name="panel_hostname" placeholder="panel.thc.io.vn (mặc định: panel. + tên domain của bạn)" autocomplete="off"></label>
<button type="submit" class="btn btn-success">Bật truy cập từ xa</button>
</form>
<div id="cfd-remote-url-card" class="cf-url-card" hidden><span>Truy cập panel từ xa</span><a id="cfd-remote-url" href="#" target="_blank" rel="noopener"></a><div class="cf-url-actions"><a id="cfd-remote-open" class="btn btn-primary btn-small" href="#" target="_blank" rel="noopener">Mở liên kết</a><button type="button" id="cfd-remote-copy" class="btn btn-secondary btn-small">Sao chép</button></div></div>
<div class="form-stack cfd-form"><button type="button" class="btn btn-danger-soft btn-block" id="cfd-remote-detach">Tắt truy cập từ xa</button></div>
</section>

<!-- ========== HIỆU NĂNG ========== -->
<section class="panel-card cfd-section" data-panel="hosting">
<div class="section-title-row"><div><p class="eyebrow">HIỆU NĂNG</p><h2>Tối ưu tốc độ website</h2></div><span class="cf-live-dot" id="cfd-perf-dot"></span></div>
<p class="muted">Bật nén <strong>gzip</strong> (giảm ~60-70% dung lượng truyền qua tunnel), cache trình duyệt cho ảnh/CSS/JS và bật <strong>OPcache</strong> cho PHP. Website sẽ tải nhanh hơn đáng kể trên đường truyền chậm.</p>
<div id="cfd-perf-status" class="muted"><strong>Trạng thái: <span id="cfd-perf-text">Chưa kiểm tra</span></strong></div>
<div class="btn-grid cfd-btn-grid two"><button type="button" class="btn btn-success" id="cfd-perf-apply">⚡ Bật tối ưu hóa hiệu năng</button><button type="button" class="btn btn-secondary" id="cfd-perf-check">Kiểm tra trạng thái</button></div>
<p class="muted">Sau khi bật, hệ thống tự khởi động lại Nginx &amp; PHP Engine (không làm mất dữ liệu). Nếu đang dùng OPcache, xóa cache panel sau khi cập nhật code.</p>
</section>

<section class="panel-card cfd-section" data-panel="hosting"><div class="section-title-row"><h2>Nhật ký Tunnel</h2></div><pre id="cfd-log" class="terminal-output"><?=tms_h('')?></pre></section>

<script>window.TMS_CF_DOMAIN_STATUS_URL='/api/cloudflare-domain/status';</script>
<script src="/assets/cfdomain.js?v=16.1.9"></script>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
