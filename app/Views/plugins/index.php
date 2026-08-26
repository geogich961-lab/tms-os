<?php $title='Package Manager · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">Package Manager</p><h1>Runtime Packages</h1><p>Cài, kiểm tra phiên bản và gỡ các gói Termux đã được kiểm duyệt.</p></div>
<form method="post" action="/packages/update"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button class="btn btn-secondary">Cập nhật kho gói</button></form></div>
<?php if(!empty($flash)):?><div class="alert <?=($flash['type']??'')==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden><pre class="flash-pre"><?=tms_h($flash['message']??'')?></pre></div><?php endif;?>
<div class="plugin-grid" data-package-grid>
<?php foreach($plugins as $plugin):?>
<article class="panel-card plugin-card" data-package-card="<?=tms_h($plugin['id'])?>" data-package-busy="<?=$plugin['busy']?'1':'0'?>">
<div class="plugin-card-top"><div class="plugin-icon"><?=tms_h(strtoupper(substr($plugin['name'],0,2)))?></div><span class="status-pill <?=$plugin['installed']?'running':''?>" data-package-status><?=$plugin['busy']?'Đang cài nền…':($plugin['installed']?'Đã cài':'Chưa cài')?></span></div>
<h2><?=tms_h($plugin['name'])?></h2><p><?=tms_h($plugin['description'])?></p><small><?=tms_h($plugin['group'])?> · pkg <?=tms_h($plugin['package'])?></small>
<?php if($plugin['version']):?><div class="plugin-result" data-package-version><?=tms_h($plugin['version'])?></div><?php endif;?>
<div class="plugin-result" data-package-result <?=$plugin['last_result']?'':'hidden'?>><?=tms_h($plugin['last_result'])?></div>
<div class="service-actions">
<?php if(!$plugin['installed']):?><form method="post" action="/packages/install" data-package-install-form data-package-id="<?=tms_h($plugin['id'])?>" data-package-name="<?=tms_h($plugin['name'])?>"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($plugin['id'])?>"><button class="btn btn-primary" data-package-install-button <?=$plugin['busy']?'disabled':''?>><?=$plugin['busy']?'Đang cài nền…':'Cài đặt'?></button></form>
<?php elseif(empty($plugin['protected'])):?><form method="post" action="/packages/remove" data-confirm="Gỡ gói này?"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($plugin['id'])?>"><button class="btn btn-danger-soft">Gỡ bỏ</button></form>
<?php else:?><button class="btn btn-secondary" disabled>Gói lõi</button><?php endif;?>
</div>
</article>
<?php endforeach;?></div>
<script>
(() => {
  const endpoint = '/api/packages/status';
  const csrf = <?=json_encode($csrf, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
  const maxPolls = 155;
  let polls = 0;
  let timer = null;

  const notify = (message, type = 'info') => {
    if (typeof window.showToast === 'function') { window.showToast(message, type); return; }
    const area = document.querySelector('[data-package-feedback]') || (() => {
      const node = document.createElement('div');
      node.dataset.packageFeedback = '1'; node.setAttribute('role', 'status');
      document.querySelector('[data-package-grid]').before(node); return node;
    })();
    area.className = type === 'error' ? 'alert alert-error' : 'alert alert-success';
    area.textContent = message;
  };

  const readJson = async (response) => {
    const text = await response.text();
    try { return JSON.parse(text); }
    catch (_) { return { ok: false, retryable: response.status >= 500, error: response.status === 401 ? 'Phiên đăng nhập đã hết. Vui lòng đăng nhập lại.' : 'Máy chủ chưa phản hồi trạng thái hợp lệ. Hãy thử lại sau.' }; }
  };

  const updateCard = (plugin) => {
    const card = document.querySelector('[data-package-card="' + plugin.id + '"]');
    if (!card) return;
    const busy = !!plugin.busy;
    card.dataset.packageBusy = busy ? '1' : '0';
    const status = card.querySelector('[data-package-status]');
    const button = card.querySelector('[data-package-install-button]');
    const result = card.querySelector('[data-package-result]');
    if (status) { status.textContent = busy ? 'Đang cài nền…' : (plugin.installed ? 'Đã cài' : 'Chưa cài'); }
    if (button) { button.disabled = busy; button.textContent = busy ? 'Đang cài nền…' : 'Cài đặt'; }
    if (result && plugin.last_result) { result.hidden = false; result.textContent = plugin.last_result; }
  };

  const active = () => Array.from(document.querySelectorAll('[data-package-card][data-package-busy="1"]')).length > 0;
  const stopPolling = () => { if (timer) { window.clearTimeout(timer); timer = null; } };
  const poll = async () => {
    stopPolling();
    if (!active()) return;
    if (++polls > maxPolls) { notify('Theo dõi nền đã dừng sau thời gian chờ. Panel vẫn an toàn; hãy tải lại trang để xem trạng thái mới nhất.', 'error'); return; }
    try {
      const payload = await readJson(await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } }));
      if (!payload.ok) { if (payload.code === 'AUTH_REQUIRED') { notify(payload.error, 'error'); return; } throw new Error(payload.error || 'Không thể đọc trạng thái.'); }
      (payload.packages || []).forEach(updateCard);
      if (active()) { timer = window.setTimeout(poll, 2000); }
    } catch (error) {
      if (polls < maxPolls) { timer = window.setTimeout(poll, 3000); }
      else { notify(error.message || 'Không thể theo dõi trạng thái Runtime Package.', 'error'); }
    }
  };

  document.querySelectorAll('[data-package-install-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[data-package-install-button]');
      if (button && button.disabled) return;
      if (button) { button.disabled = true; button.textContent = 'Đang xếp hàng…'; }
      try {
        const data = new FormData(form);
        const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf, Accept: 'application/json' }, body: data });
        const payload = await readJson(response);
        if (!payload.ok) throw new Error(payload.error || 'Không thể xếp hàng cài đặt.');
        notify(payload.message || 'Đã xếp hàng cài đặt trong nền.', 'success');
        const card = form.closest('[data-package-card]');
        if (payload.queued && card) { card.dataset.packageBusy = '1'; updateCard({ id: form.dataset.packageId, busy: true, installed: false, last_result: '' }); polls = 0; poll(); }
        else if (button) { button.disabled = false; button.textContent = 'Cài đặt'; }
      } catch (error) {
        if (button) { button.disabled = false; button.textContent = 'Cài đặt'; }
        notify(error.message || 'Không thể xếp hàng cài đặt.', 'error');
      }
    });
  });
  if (active()) poll();
})();
</script>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
