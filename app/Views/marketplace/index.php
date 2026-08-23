<?php
$title = 'App Marketplace · TMS OS';
$showShell = true;
require __DIR__ . '/../layouts/header.php';
$installedIds = array_flip(array_filter(array_map(static fn($item) => $item['app'] ?? null, $installed)));
$installedCount = count($installedIds);
?>
<div class="page-head marketplace-intro">
  <div>
    <p class="eyebrow">App Marketplace</p>
    <h1>Ứng dụng cho mini VPS</h1>
    <p class="muted">Chọn ứng dụng phù hợp, hệ thống sẽ đưa bạn qua các bước cài đặt cần thiết. Không thay đổi dữ liệu hoặc dịch vụ đang chạy khi bạn chỉ xem danh mục.</p>
    <div class="marketplace-summary"><span class="status-pill running"><?= count($catalog) ?> ứng dụng sẵn sàng</span><span class="status-pill <?= $installedCount ? 'warning' : 'stopped' ?>"><?= $installedCount ? $installedCount . ' ứng dụng đã cài' : 'Chưa có ứng dụng cài thêm' ?></span></div>
  </div>
</div>

<section class="panel-card marketplace-installed" aria-labelledby="installedAppsTitle">
  <div class="marketplace-installed-head"><div><p class="eyebrow">Đã triển khai</p><h2 id="installedAppsTitle">Ứng dụng đang cài</h2></div><span class="status-pill <?= $installedCount ? 'running' : 'stopped' ?>"><?= $installedCount ?: 0 ?> ứng dụng</span></div>
  <?php if (!$installed): ?>
    <p class="muted">Chưa có ứng dụng nào được cài từ Marketplace.</p>
  <?php else: ?>
    <div class="marketplace-installed-list">
      <?php foreach ($installed as $item): $isService = ($item['type'] ?? '') === 'service'; $isRunning = ($item['health'] ?? '') === 'running'; ?>
        <article class="marketplace-installed-item">
          <div><h3><?= tms_h((string)($item['name'] ?? 'Ứng dụng')) ?></h3><p><?= $isService ? 'Dịch vụ nội bộ · cổng quản trị ' . (int)($item['port'] ?? 0) : 'Website · cổng ' . (int)($item['port'] ?? 0) ?></p></div>
          <div class="marketplace-installed-actions"><span class="status-pill <?= $isRunning || !$isService ? 'running' : 'stopped' ?>"><?= $isRunning || !$isService ? 'Đang hoạt động' : 'Chưa phản hồi' ?></span><?php if ($isService && $isRunning && !empty($item['access_url'])): ?><a class="btn btn-secondary btn-small" href="<?= tms_h((string)$item['access_url']) ?>" target="_blank" rel="noopener noreferrer">Mở trên máy chủ</a><?php endif; ?></div>
          <?php if ($isService): ?><p class="marketplace-service-note">Thiết lập ban đầu chỉ mở an toàn trên điện thoại chạy TMS OS qua <code>127.0.0.1:<?= (int)($item['port'] ?? 0) ?></code>. Không tự công khai trang quản trị qua Cloudflare.</p><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="marketplace-grid" aria-label="Danh mục ứng dụng">
<?php foreach ($catalog as $app):
  $isInstalled = isset($installedIds[$app['id']]);
  $initial = strtoupper(substr((string)$app['name'], 0, 1));
?>
  <article class="panel-card marketplace-card">
    <div class="marketplace-card-head"><div class="marketplace-icon" aria-hidden="true"><?= tms_h($initial) ?></div><span class="status-pill <?= $isInstalled ? 'running' : 'stopped' ?>"><?= $isInstalled ? 'Đã cài' : 'Có sẵn' ?></span></div>
    <h2><?= tms_h($app['name']) ?></h2>
    <p><?= tms_h($app['description']) ?></p>
    <div class="marketplace-requirement"><span class="badge badge-info-soft">Yêu cầu</span><span><?= tms_h($app['requirements']) ?></span></div>
    <button class="btn <?= $isInstalled ? 'btn-secondary' : 'btn-primary' ?>" type="button" data-marketplace-install data-app-id="<?= tms_h($app['id']) ?>" data-app-name="<?= tms_h($app['name']) ?>" data-app-database="<?= $app['database'] ? '1' : '0' ?>"><?= $isInstalled ? 'Cài thêm phiên bản mới' : 'Cài đặt ứng dụng' ?></button>
  </article>
<?php endforeach; ?>
</section>

<div id="installModal" class="marketplace-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="marketplace-modal-dialog">
    <div class="marketplace-modal-header"><h2 id="modalTitle">Cài đặt ứng dụng</h2><button class="marketplace-close" type="button" data-marketplace-close aria-label="Đóng">×</button></div>
    <form id="installForm">
      <input type="hidden" id="appId" name="app">
      <div class="marketplace-modal-body">
        <div class="marketplace-form-group"><label for="appName">Tên định danh (không dấu)</label><input type="text" name="name" id="appName" placeholder="ví dụ: my-site" autocomplete="off" required></div>
        <div class="marketplace-form-group"><label for="appPort">Cổng truy cập (1024–65535)</label><input type="number" name="port" id="appPort" min="1024" max="65535" placeholder="8080" required></div>
        <div id="dbFields" class="marketplace-db-fields" hidden>
          <h3>Cấu hình Database</h3>
          <div class="marketplace-form-group"><label for="dbName">Tên Database</label><input type="text" name="db_name" id="dbName" placeholder="wp_db"></div>
          <div class="marketplace-form-group"><label for="dbUser">Tên User</label><input type="text" name="db_user" id="dbUser" placeholder="wp_user"></div>
          <div class="marketplace-form-group"><label for="dbPass">Mật khẩu User</label><input type="password" name="db_pass" id="dbPass" placeholder="Tối thiểu 8 ký tự" autocomplete="new-password"></div>
        </div>
      </div>
      <div class="marketplace-modal-footer"><button type="button" class="btn btn-ghost" data-marketplace-close>Hủy</button><button type="submit" class="btn btn-primary" id="installBtn">Bắt đầu cài đặt</button></div>
    </form>
  </div>
</div>

<link rel="stylesheet" href="/assets/marketplace.css?v=16.1.19">
<script>
(() => {
  const modal = document.getElementById('installModal'); const form = document.getElementById('installForm'); const csrf = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const close = () => { modal.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; };
  document.querySelectorAll('[data-marketplace-close]').forEach((btn) => btn.addEventListener('click', close));
  modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') close(); });
  document.querySelectorAll('[data-marketplace-install]').forEach((button) => button.addEventListener('click', () => {
    document.getElementById('appId').value = button.dataset.appId; document.getElementById('modalTitle').textContent = `Cài đặt ${button.dataset.appName}`;
    document.getElementById('dbFields').hidden = button.dataset.appDatabase !== '1'; document.getElementById('appPort').value = 8000 + Math.floor(Math.random() * 1000);
    modal.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; document.getElementById('appName').focus();
  }));
  form.addEventListener('submit', async (event) => {
    event.preventDefault(); const button = document.getElementById('installBtn'); const original = button.textContent; button.disabled = true; button.textContent = 'Đang cài đặt…';
    try { const response = await fetch('/marketplace/install', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(Object.fromEntries(new FormData(form).entries())) }); const result = await response.json().catch(() => ({}));
      if (result.ok) { (window.TMS?.toast || window.tmsToast)(result.message, 'success'); close(); setTimeout(() => location.reload(), 1200); } else { (window.TMS?.toast || window.tmsToast)(result.message || 'Không thể cài đặt ứng dụng.', 'error'); }
    } catch (_) { (window.TMS?.toast || window.tmsToast)('Lỗi kết nối máy chủ', 'error'); } finally { button.disabled = false; button.textContent = original; }
  });
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
