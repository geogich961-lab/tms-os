(() => {
  const label = { operational: 'Hoạt động', configured: 'Đã cấu hình', syncing: 'Đang đồng bộ', attention: 'Cần chú ý', offline: 'Ngoại tuyến', unknown: 'Chưa rõ' };
  const state = document.querySelector('#tunnel-state'); const meta = document.querySelector('#tunnel-meta'); const badge = document.querySelector('#tunnel-badge'); const hosts = document.querySelector('#hosts'); const updated = document.querySelector('#updated');
  const esc = value => String(value).replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  function setBadge(el, value){ el.className = `badge ${value}`; el.textContent = label[value] || label.unknown; }
  async function load(){
    try {
      const res = await fetch('/api/public-status', { cache: 'no-store', headers: { Accept: 'application/json' } }); const data = await res.json(); if (!res.ok || !data.success) throw new Error('Không thể đọc trạng thái');
      const tunnel = data.tunnel || {}; const value = tunnel.state || 'unknown'; state.textContent = label[value] || label.unknown; meta.textContent = value === 'operational' ? `${Number(tunnel.connections || 0)} kết nối Cloudflare đang hoạt động` : 'Dữ liệu giám sát không xác nhận được kết nối đầy đủ'; setBadge(badge, value);
      const rows = Array.isArray(data.hostnames) ? data.hostnames : [];
      hosts.innerHTML = rows.length ? rows.map(host => `<div class="service-row"><div><a class="public-host-link" href="${esc(host.url)}" rel="noopener noreferrer">${esc(host.hostname)}</a><span class="public-host-caption">${esc(host.url)}</span></div><span class="status-pill ${esc(host.state || 'unknown')}">${esc(label[host.state] || label.unknown)}</span></div>`).join('') : '<div class="empty-state">Chưa có hostname công khai để hiển thị.</div>';
      updated.textContent = `Cập nhật lần cuối: ${new Date(data.updated_at).toLocaleString('vi-VN')}. Làm mới tự động mỗi 60 giây.`;
    } catch (_) { state.textContent = 'Chưa rõ'; meta.textContent = 'Trang trạng thái tạm thời chưa đọc được dữ liệu.'; setBadge(badge, 'unknown'); hosts.innerHTML = '<div class="empty-state">Vui lòng thử lại sau.</div>'; }
  }
  document.querySelector('#refresh').addEventListener('click', load); load(); setInterval(load, 60000);
})();
