/* TMS OS — Cloudflare Hosting page controller.
 * This file is intentionally page-scoped: the module used to be absent from
 * the shared bundle, leaving a functional backend with an entirely empty UI.
 */
(() => {
  const statusUrl = window.TMS_CF_DOMAIN_STATUS_URL;
  if (!statusUrl || !document.getElementById('cfd-token-form')) return;

  const $ = (selector) => document.querySelector(selector);
  const csrf = () => $('#cfd-token-form input[name="csrf"]')?.value || $('#cfd-attach-form input[name="csrf"]')?.value || '';
  const alertBox = $('#cfd-alert');
  let latestStatus = null;
  const text = (selector, value) => { const node = $(selector); if (node) node.textContent = value || '—'; };
  const hidden = (selector, value) => { const node = $(selector); if (node) node.hidden = value; };
  const request = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false) throw new Error(data.error || 'Yêu cầu Cloudflare Hosting không thành công.');
    return data;
  };
  const post = (url, form = new FormData()) => {
    if (!form.has('csrf')) form.set('csrf', csrf());
    return request(url, { method: 'POST', body: form });
  };
  const show = (message, type = 'success') => {
    if (window.TMS?.toast) { window.TMS.toast(message, type); return; }
    if (!alertBox) return;
    alertBox.hidden = false;
    alertBox.className = `alert ${type === 'error' ? 'alert-error' : 'alert-success'}`;
    alertBox.textContent = message;
    window.setTimeout(() => { alertBox.hidden = true; }, 7000);
  };
  const setZoneWarning = (message) => {
    if (!alertBox) return;
    if (!message) {
      if (alertBox.dataset.cfdZoneWarning === '1') {
        alertBox.hidden = true;
        delete alertBox.dataset.cfdZoneWarning;
      }
      return;
    }
    alertBox.dataset.cfdZoneWarning = '1';
    alertBox.hidden = false;
    alertBox.className = 'alert alert-error';
    alertBox.textContent = `Danh sách domain chưa thể làm mới. ${message} Tunnel và các hostname hiện có vẫn được giữ nguyên.`;
  };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[c]);
  const confirmAction = (message) => window.confirm(message);

  function populateZones(zones) {
    const select = $('#cfd-zone');
    if (!select) return;
    const current = select.value;
    select.innerHTML = '<option value="">— chọn domain —</option>';
    (zones || []).forEach((zone) => {
      const option = document.createElement('option');
      option.value = zone.id || '';
      option.textContent = zone.name || zone.id || 'Domain không tên';
      select.appendChild(option);
    });
    if (current) select.value = current;
  }

  function populateSites(sites) {
    const select = $('#cfd-target');
    if (!select) return;
    const current = select.value;
    select.innerHTML = '<option value="">— chọn website nội bộ —</option>';
    (sites || []).forEach((site) => {
      const option = document.createElement('option');
      option.value = site.service_url || `http://127.0.0.1:${site.port}`;
      option.textContent = `${site.name} · cổng ${site.port}`;
      select.appendChild(option);
    });
    if (current) select.value = current;
  }

  function renderHostnames(hostnames) {
    const box = $('#cfd-hostnames-list');
    if (!box) return;
    if (!Array.isArray(hostnames) || hostnames.length === 0) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = hostnames.map((item) => {
      const hostname = escapeHtml(item.hostname);
      const url = escapeHtml(item.url || `https://${item.hostname}`);
      const route = item.route_status === 'ok' ? 'Đã đồng bộ' : item.route_status === 'pending' ? 'Đang đồng bộ Cloudflare' : item.route_status === 'missing' ? 'Cần đồng bộ route' : 'Chưa kiểm tra';
      return `<article class="cfh-hostname-item"><div><strong>${hostname}</strong><br><small>${escapeHtml(item.service || '')} · ${route}</small></div><div class="cfh-hostname-actions"><a class="btn btn-ghost btn-small" href="${url}" target="_blank" rel="noopener">Mở</a><button class="btn btn-danger-soft btn-small" type="button" data-cfd-detach-host="${hostname}">Tách</button></div></article>`;
    }).join('');
  }

  function renderOverview(status) {
    const health = status.health || {};
    const cloudflareConnected = ['healthy', 'active'].includes(String(health.status || '').toLowerCase());
    const tunnel = $('#cfd-overview-tunnel');
    const connections = $('#cfd-overview-conns');
    const hostCount = $('#cfd-overview-host-count');
    const hostBox = $('#cfd-overview-hosts');
    if (tunnel) tunnel.textContent = !status.configured ? 'Chưa cấu hình' : cloudflareConnected ? 'Hoạt động' : status.running ? 'Đang chờ kết nối' : 'Ngoại tuyến';
    if (connections) connections.textContent = cloudflareConnected ? `${health.connections ?? health.conns ?? 0} kết nối Cloudflare` : 'Kiểm tra kết nối từ Cloudflare';
    const byHost = new Map();
    (status.hostnames || []).forEach((item) => {
      if (item?.hostname) byHost.set(item.hostname, item);
    });
    if (status.panel_hostname && !byHost.has(status.panel_hostname)) {
      byHost.set(status.panel_hostname, { hostname: status.panel_hostname, url: status.panel_url || `https://${status.panel_hostname}`, route_status: status.panel_configured ? 'ok' : 'missing' });
    }
    const hosts = [...byHost.values()];
    if (hostCount) hostCount.textContent = String(hosts.length);
    if (!hostBox) return;
    if (hosts.length === 0) { hostBox.innerHTML = '<span class="muted">Chưa có hostname công khai để hiển thị.</span>'; return; }
    hostBox.innerHTML = hosts.map((item) => {
      const state = item.route_status === 'ok' ? 'running' : item.route_status === 'pending' ? 'warning' : 'stopped';
      const stateText = item.route_status === 'ok' ? 'Hoạt động' : item.route_status === 'pending' ? 'Đang đồng bộ' : 'Cần kiểm tra';
      const hostname = escapeHtml(item.hostname); const url = escapeHtml(item.url || `https://${item.hostname}`);
      return `<a class="cfd-overview-host" href="${url}" target="_blank" rel="noopener"><span>${hostname}</span><em class="status-pill ${state}">${stateText}</em></a>`;
    }).join('');
  }

  function applyStatus(status) {
    latestStatus = status || {};
    const configured = Boolean(status.configured);
    const running = Boolean(status.running);
    const health = status.health || {};
    const pill = $('#cfd-status-pill');
    if (pill) {
      const cloudflareConnected = ['healthy', 'active'].includes(String(health.status || '').toLowerCase());
      pill.textContent = configured ? (cloudflareConnected ? 'Đang kết nối' : running ? 'Đang chờ kết nối' : 'Đã cấu hình') : 'Chưa cấu hình';
      pill.className = `status-pill ${cloudflareConnected ? 'running' : configured ? 'warning' : 'stopped'}`;
    }
    text('#cfd-tunnel-name', status.tunnel_name || (configured ? 'Chưa tạo' : 'Chưa cấu hình'));
    text('#cfd-tunnel-status', health.status || (configured ? 'Chưa kiểm tra' : '—'));
    text('#cfd-tunnel-conns', health.connections ?? health.conns ?? '—');
    text('#cfd-tunnel-running', running ? 'Có' : 'Không');
    text('#cfd-log', status.log || 'Chưa có nhật ký tunnel.');

    const runningDot = $('#cfd-running-dot');
    if (runningDot) runningDot.classList.toggle('online', running);
    const remoteDot = $('#cfd-remote-dot');
    if (remoteDot) remoteDot.classList.toggle('online', Boolean(status.panel_configured));

    const accountBox = $('#cfd-account-box');
    if (accountBox) accountBox.hidden = !configured;
    text('#cfd-account-id', status.account_id || 'Đã lưu, đang tải thông tin');
    text('#cfd-zones-count', Array.isArray(status.zones) ? String(status.zones.length) : '—');
    populateZones(status.zones || []);
    setZoneWarning(status.zone_warn || '');
    renderHostnames(status.hostnames || []);
    renderOverview(status);

    const urlCard = $('#cfd-url-card');
    const url = status.url || '';
    if (urlCard) urlCard.hidden = !url;
    const publicUrl = $('#cfd-public-url');
    const openUrl = $('#cfd-open-url');
    if (publicUrl) { publicUrl.textContent = url; publicUrl.href = url || '#'; }
    if (openUrl) openUrl.href = url || '#';

    const panelUrl = status.panel_url || '';
    const remoteCard = $('#cfd-remote-url-card');
    if (remoteCard) remoteCard.hidden = !panelUrl;
    const remoteUrl = $('#cfd-remote-url');
    const remoteOpen = $('#cfd-remote-open');
    if (remoteUrl) { remoteUrl.textContent = panelUrl; remoteUrl.href = panelUrl || '#'; }
    if (remoteOpen) remoteOpen.href = panelUrl || '#';
    const hostnameInput = $('#cfd-panel-hostname');
    if (hostnameInput && status.panel_hostname && !hostnameInput.value) hostnameInput.value = status.panel_hostname;

    const createButton = $('#cfd-tunnel-form button[type="submit"]');
    if (createButton) {
      const tunnelExists = Boolean(status.tunnel_id);
      createButton.disabled = tunnelExists;
      createButton.title = tunnelExists ? 'Tunnel hiện hữu đang được giữ an toàn; không tạo tunnel mới.' : '';
      createButton.textContent = tunnelExists ? 'Tunnel hiện hữu đang được giữ' : 'Tạo Cloudflare Tunnel mới';
    }
    const stopButton = $('#cfd-stop');
    const panelHost = (() => { try { return new URL(status.panel_url || '').hostname; } catch (_) { return ''; } })();
    const openedViaPublicPanel = Boolean(panelHost && panelHost === window.location.hostname);
    if (stopButton && openedViaPublicPanel) {
      stopButton.disabled = true;
      stopButton.title = 'Không thể dừng tunnel từ panel đang chạy qua chính tunnel.';
      stopButton.textContent = 'Dừng Tunnel (chỉ từ localhost/LAN)';
    }
  }

  async function loadAccount({ interactive = false } = {}) {
    try {
      const data = await request('/api/cloudflare-domain/account-info');
      text('#cfd-account-id', data.account_id || '—');
      const apiZones = Array.isArray(data.zones) ? data.zones : [];
      const statusZones = Array.isArray(latestStatus?.zones) ? latestStatus.zones : [];
      const zones = data.zone_warn && apiZones.length === 0 ? statusZones : apiZones;
      text('#cfd-zones-count', String(zones.length));
      populateZones(zones);
      setZoneWarning(data.zone_warn || '');
      if (interactive && data.zone_warn) {
        throw new Error(`API Token đã lưu nhưng chưa thể đọc danh sách domain: ${data.zone_warn}`);
      }
      return data;
    } catch (error) {
      const statusZones = Array.isArray(latestStatus?.zones) ? latestStatus.zones : [];
      if (statusZones.length > 0) {
        text('#cfd-zones-count', String(statusZones.length));
        populateZones(statusZones);
      }
      const message = error.message || 'Không thể đọc danh sách domain.';
      setZoneWarning(message);
      if (interactive) throw error;
      return { success: false, zones: statusZones, zone_warn: message };
    }
  }

  async function loadSites() {
    const data = await request('/api/cloudflare-domain/internal-sites');
    populateSites(data.sites || []);
  }

  async function refresh({ silent = false } = {}) {
    try {
      const status = await request(statusUrl);
      applyStatus(status);
      if (status.configured) {
        const statusHasZones = Array.isArray(status.zones) && status.zones.length > 0;
        const accountTask = statusHasZones && status.account_id
          ? Promise.resolve({ success: true, zones: status.zones })
          : loadAccount();
        const results = await Promise.allSettled([accountTask, loadSites()]);
        const sitesResult = results[1];
        if (sitesResult.status === 'rejected') {
          show(sitesResult.reason?.message || 'Không thể làm mới danh sách website nội bộ.', 'error');
        }
      }
      if (!silent && alertBox?.dataset.cfdZoneWarning !== '1') {
        show('Đã làm mới trạng thái Cloudflare Hosting.');
      }
    } catch (error) {
      show(error.message || 'Không thể đọc trạng thái Cloudflare Hosting.', 'error');
    }
  }

  async function refreshPerf({ silent = true } = {}) {
    try {
      const data = await post('/api/cloudflare-domain/perf-status');
      const enabled = Boolean(data.enabled);
      text('#cfd-perf-text', enabled ? 'Đã bật gzip, cache và OPcache' : 'Chưa bật tối ưu');
      $('#cfd-perf-dot')?.classList.toggle('online', enabled);
      if (!silent) show(enabled ? 'Tối ưu hiệu năng đang hoạt động.' : 'Tối ưu hiệu năng chưa được bật.');
    } catch (error) {
      if (!silent) show(error.message || 'Không thể kiểm tra hiệu năng.', 'error');
    }
  }

  function bindForm(selector, endpoint, before, after) {
    $(selector)?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      if (before && !before(form)) return;
      const button = form.querySelector('button[type="submit"]');
      const label = button?.textContent;
      if (button) { button.disabled = true; button.textContent = 'Đang xử lý…'; }
      try {
        const data = await post(endpoint, new FormData(form));
        show(data.message || 'Đã lưu thành công.');
        if (after) await after(data);
        await refresh({ silent: true });
      } catch (error) {
        show(error.message || 'Không thể thực hiện yêu cầu.', 'error');
      } finally {
        if (button) { button.disabled = false; button.textContent = label; }
      }
    });
  }

  bindForm('#cfd-token-form', '/api/cloudflare-domain/token', (form) => {
    if ((form.querySelector('[name="api_token"]')?.value || '').trim().length < 20) {
      show('Hãy dán API Token Cloudflare hợp lệ.', 'error');
      return false;
    }
    return true;
  }, async () => { await Promise.all([loadAccount({ interactive: true }), loadSites()]); });
  bindForm('#cfd-tunnel-form', '/api/cloudflare-domain/create-tunnel', () => confirmAction('Tạo Cloudflare Tunnel mới? Chỉ thực hiện khi chưa có tunnel cần dùng.'));
  bindForm('#cfd-attach-form', '/api/cloudflare-domain/attach');
  bindForm('#cfd-remote-form', '/api/cloudflare-domain/attach-panel');

  const action = (selector, endpoint, confirmation, after) => $(selector)?.addEventListener('click', async () => {
    if (confirmation && !confirmAction(confirmation)) return;
    const button = $(selector);
    const label = button?.textContent;
    if (button) { button.disabled = true; button.textContent = 'Đang xử lý…'; }
    try {
      const data = await post(endpoint);
      show(data.message || 'Đã thực hiện thành công.');
      if (after) await after(data);
      await refresh({ silent: true });
    } catch (error) {
      show(error.message || 'Không thể thực hiện yêu cầu.', 'error');
    } finally {
      if (button) { button.disabled = false; button.textContent = label; }
    }
  });

  action('#cfd-start', '/api/cloudflare-domain/start');
  action('#cfd-stop', '/api/cloudflare-domain/stop', 'Dừng tunnel? Website công khai và panel từ xa sẽ tạm thời không truy cập được.');
  action('#cfd-sync-routes', '/api/cloudflare-domain/sync-routes', 'Kiểm tra và thêm lại các route đang thiếu? Các hostname, DNS và route hiện có sẽ được giữ nguyên.');
  action('#cfd-detach', '/api/cloudflare-domain/detach', 'Tách tên miền chính khỏi tunnel? Tunnel và các tên miền khác vẫn được giữ.');
  action('#cfd-delete-tunnel', '/api/cloudflare-domain/delete-tunnel', 'Xóa tunnel khỏi Cloudflare? Tất cả website gắn với tunnel này sẽ ngừng hoạt động.');
  action('#cfd-uninstall', '/api/cloudflare-domain/uninstall', 'Xóa toàn bộ cấu hình Cloudflare Hosting? Chỉ tiếp tục khi bạn muốn gỡ cấu hình hiện tại.');
  action('#cfd-remote-detach', '/api/cloudflare-domain/detach-panel', 'Tắt truy cập panel từ xa? Website công khai không bị ảnh hưởng.');
  action('#cfd-perf-apply', '/api/cloudflare-domain/perf-optimize', 'Bật gzip, cache và OPcache? Nginx cùng PHP Engine sẽ được khởi động lại.', refreshPerf);
  $('#cfd-refresh')?.addEventListener('click', () => refresh());
  $('#cfd-perf-check')?.addEventListener('click', () => refreshPerf({ silent: false }));

  $('#cfd-copy-url')?.addEventListener('click', async () => {
    const value = $('#cfd-public-url')?.href || '';
    if (!value) return;
    await navigator.clipboard?.writeText(value);
    show('Đã sao chép liên kết website.');
  });
  $('#cfd-remote-copy')?.addEventListener('click', async () => {
    const value = $('#cfd-remote-url')?.href || '';
    if (!value) return;
    await navigator.clipboard?.writeText(value);
    show('Đã sao chép liên kết panel.');
  });
  $('#cfd-subdomain-chips')?.addEventListener('click', (event) => {
    const chip = event.target.closest('[data-chip]');
    if (!chip) return;
    const zone = $('#cfd-zone option:checked')?.textContent || '';
    if (!zone || zone.startsWith('—')) { show('Hãy chọn domain trước.', 'error'); return; }
    const input = $('#cfd-hostname');
    if (input) input.value = `${chip.dataset.chip}.${zone}`;
  });
  $('#cfd-hostnames-list')?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-cfd-detach-host]');
    if (!button || !confirmAction(`Tách ${button.dataset.cfdDetachHost} khỏi tunnel?`)) return;
    try {
      const form = new FormData();
      form.set('hostname', button.dataset.cfdDetachHost || '');
      const data = await post('/api/cloudflare-domain/detach', form);
      show(data.message || 'Đã tách tên miền.');
      await refresh({ silent: true });
    } catch (error) { show(error.message || 'Không thể tách tên miền.', 'error'); }
  });

  refresh({ silent: true });
  refreshPerf({ silent: true });
})();
