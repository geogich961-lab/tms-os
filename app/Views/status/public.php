<?php
$ui = tms_ui_settings();
$theme = $_COOKIE['tms_theme'] ?? $ui['default_theme'];
?>
<!doctype html>
<html lang="vi" data-theme="<?= tms_h($theme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="<?= tms_h($ui['accent']) ?>">
  <title>Trạng thái hệ thống · TMS OS</title>
  <link rel="icon" href="/assets/favicon.png?v=<?= tms_asset_version() ?>">
  <link rel="stylesheet" href="/assets/app.css?v=<?= tms_asset_version() ?>">
  <link rel="stylesheet" href="/assets/public-status.css?v=<?= tms_asset_version() ?>">
</head>
<body class="public-status-page">
  <div class="public-status-shell">
    <aside class="public-status-sidebar" aria-label="Điều hướng công khai">
      <a class="os-brand public-status-brand" href="/" aria-label="TMS OS">
        <img class="brand-logo small" src="<?= tms_h(tms_brand_icon('logo')) ?>" alt="TMS OS">
        <div><strong>TMS OS</strong><span>Trạng thái công khai</span></div>
      </a>
      <nav class="public-status-nav">
        <a class="nav-item active" href="/status" aria-current="page"><span aria-hidden="true">●</span>Trạng thái hệ thống</a>
        <a class="nav-item" href="/"><span aria-hidden="true">⌂</span>Trang chủ</a>
        <a class="nav-item" href="/login"><span aria-hidden="true">→</span>Đăng nhập quản trị</a>
      </nav>
      <p class="public-status-sidebar-note">Theo dõi vận hành công khai. Không có quyền điều khiển hay dữ liệu cấu hình tại đây.</p>
    </aside>

    <main class="public-status-main">
      <header class="public-status-mobile-header">
        <a class="os-brand" href="/"><img class="brand-logo small" src="<?= tms_h(tms_brand_icon('logo')) ?>" alt=""><strong>TMS OS</strong></a>
        <span class="status-pill running">Công khai</span>
      </header>

      <section class="page-head public-status-head">
        <div>
          <p class="eyebrow">System Status</p>
          <h1>Trạng thái hệ thống</h1>
          <p class="page-subtitle">Theo dõi Cloudflare Tunnel và hostname đã công khai. Dữ liệu chỉ đọc, đã được lọc an toàn.</p>
        </div>
        <button class="btn btn-secondary" id="refresh" type="button">↻ Làm mới</button>
      </section>

      <section class="metric-grid public-status-metrics" aria-live="polite">
        <article class="metric-card public-tunnel-card">
          <span>Cloudflare Tunnel</span>
          <div class="public-status-value-row">
            <strong class="metric-text" id="tunnel-state">Đang kiểm tra…</strong>
            <span id="tunnel-badge" class="status-pill">ĐANG TẢI</span>
          </div>
          <small id="tunnel-meta">Vui lòng chờ trong giây lát</small>
        </article>
        <article class="metric-card public-last-updated-card">
          <span>Cập nhật trạng thái</span>
          <strong class="metric-text" id="updated">Đang tải dữ liệu…</strong>
          <small>Tự làm mới mỗi 60 giây</small>
        </article>
      </section>

      <section class="panel-card public-hosts-card">
        <div class="card-title public-hosts-heading">
          <div><p class="eyebrow">Public Endpoints</p><h2>Hostname công khai</h2></div>
          <span class="muted">Mở hostname trong tab mới</span>
        </div>
        <div id="hosts" class="service-list public-hosts-list"><div class="empty-state">Đang tải danh sách hostname…</div></div>
      </section>

      <footer class="public-status-footer">
        <span class="online-dot" aria-hidden="true"></span>
        <span>Trang theo dõi công khai của TMS OS · Không hiển thị token, DNS, port hoặc cấu hình nội bộ.</span>
      </footer>
    </main>
  </div>
  <script src="/assets/public-status.js?v=<?= tms_asset_version() ?>" defer></script>
</body>
</html>
